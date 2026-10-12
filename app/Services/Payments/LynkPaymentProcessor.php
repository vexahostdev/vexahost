<?php

namespace App\Services\Payments;

use App\Exceptions\InvalidStateTransitionException;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\VpsSpec;
use App\Models\WebhookEvent;
use App\Notifications\PaymentReceivedNotification;
use App\Services\OrderStateMachine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * PHASE 4 - Pemroses pembayaran Lynk.id.
 *
 * Dipakai oleh dua jalur dengan kode yang SAMA:
 *   1. LynkWebhookController, saat webhook asli datang
 *   2. Tombol proses ulang di panel admin, untuk event yang gagal
 *
 * Pemroses hanya bekerja dari data yang sudah tersimpan di WebhookEvent
 * (payload, signature, IP). Verifikasi signature dilakukan SEBELUM event
 * disimpan, sehingga event yang sampai ke sini selalu sudah terverifikasi.
 *
 * Setiap hasil dicatat ke event: processed, failed, atau ignored, beserta
 * kode HTTP yang dibalas. Tanpa ini, pembayaran yang gagal diproses hanya
 * tercatat di file log dan tidak bisa ditelusuri dari panel.
 */
class LynkPaymentProcessor
{
    public function __construct(
        protected OrderStateMachine $orderStateMachine
    ) {
    }

    /**
     * Ambil field penting dari berbagai kemungkinan struktur payload Lynk.
     */
    public static function extract(array $payload): array
    {
        $event = strtolower(trim((string) ($payload['event'] ?? $payload['action'] ?? $payload['type'] ?? '')));
        $data = $payload['data'] ?? [];
        $messageAction = strtoupper(trim((string) ($data['message_action'] ?? $payload['message_action'] ?? '')));
        $messageData = $data['message_data'] ?? $payload['message_data'] ?? [];

        $refId = trim((string) (
            $messageData['refId']
            ?? $messageData['ref_id']
            ?? $data['refId']
            ?? $data['ref_id']
            ?? $payload['refId']
            ?? $payload['ref_id']
            ?? ''
        ));

        $grandTotal = trim((string) (
            $messageData['totals']['grandTotal']
            ?? $messageData['grandTotal']
            ?? $messageData['amount']
            ?? $data['totals']['grandTotal']
            ?? $data['grandTotal']
            ?? $data['amount']
            ?? $payload['amount']
            ?? ''
        ));

        $messageId = trim((string) (
            $messageData['message_id']
            ?? $messageData['messageId']
            ?? $data['message_id']
            ?? $data['messageId']
            ?? $payload['message_id']
            ?? $payload['messageId']
            ?? ''
        ));

        $customer = $messageData['customer'] ?? $data['customer'] ?? $payload['customer'] ?? [];

        return [
            'event' => $event,
            'data' => $data,
            'message_action' => $messageAction,
            'message_data' => $messageData,
            'ref_id' => $refId,
            'grand_total' => $grandTotal,
            'message_id' => $messageId,
            'customer' => is_array($customer) ? $customer : [],
            'customer_email' => strtolower(trim((string) ($customer['email'] ?? $payload['email'] ?? ''))),
        ];
    }

    /**
     * Proses satu event yang sudah terverifikasi.
     *
     * @return array{http:int, body:array}
     */
    public function process(WebhookEvent $event): array
    {
        $payload = $event->payload ?? [];
        $f = self::extract($payload);
        $refId = $f['ref_id'];

        // Pengaman ganda terhadap pembayaran ganda: selain status event,
        // periksa juga transaksi settled dengan refId yang sama.
        $alreadySettled = $refId !== '' && PaymentTransaction::where('provider', 'lynk')
            ->where('provider_transaction_id', $refId)
            ->where('status', 'settled')
            ->exists();

        if ($event->processing_status === 'processed' || $alreadySettled) {
            $orderId = $event->order_id
                ?? PaymentTransaction::where('provider', 'lynk')
                    ->where('provider_transaction_id', $refId)
                    ->value('order_id');

            if ($event->processing_status !== 'processed') {
                $this->mark($event, 'processed', 200, null, ['order_id' => $orderId]);
            }

            return $this->reply($event, 200, [
                'success' => true,
                'message' => 'Event already processed.',
                'order_id' => $orderId,
            ], false);
        }

        // Hanya event payment.received dengan action SUCCESS yang diproses.
        if ($f['event'] !== 'payment.received' || $f['message_action'] !== 'SUCCESS') {
            Log::info("Lynk webhook event ignored: event={$f['event']}, action={$f['message_action']}");
            $this->mark($event, 'ignored', 200, null);

            return $this->reply($event, 200, [
                'success' => true,
                'message' => 'Event ignored.',
            ], false);
        }

        $customer = $f['customer'];
        $customerEmail = $f['customer_email'];
        $customerName = trim((string) ($customer['name'] ?? $payload['name'] ?? 'Pelanggan Lynk'));
        $customerPhone = trim((string) ($customer['phone'] ?? $payload['phone'] ?? ''));

        if ($customerEmail === '') {
            Log::warning('Lynk webhook missing customer email', ['refId' => $refId]);
            $this->mark($event, 'failed', 422, 'Email pelanggan tidak ada di payload.');

            return $this->reply($event, 422, [
                'success' => false,
                'message' => 'Customer email is required in payload.',
            ], false);
        }

        $messageData = $f['message_data'];
        $data = $f['data'];
        $items = $messageData['items'] ?? $data['items'] ?? $payload['items'] ?? [];
        $firstItem = $items[0] ?? [];
        $itemTitle = (string) ($firstItem['title'] ?? $firstItem['name'] ?? 'VPS Cloud');
        $itemPrice = (float) ($firstItem['price'] ?? 0);
        $amountPaid = $f['grand_total'] !== '' ? (float) $f['grand_total'] : $itemPrice;
        $messageId = $f['message_id'];

        $spec = $this->resolveVpsSpec($itemTitle, $itemPrice, $amountPaid);

        try {
            $result = DB::transaction(function () use (
                $event,
                $payload,
                $customerEmail,
                $customerName,
                $customerPhone,
                $spec,
                $amountPaid,
                $refId,
                $messageId
            ) {
                // 1. Cari atau buat User
                $user = User::where('email', $customerEmail)->first();
                $isNewUser = false;

                if (!$user) {
                    $isNewUser = true;
                    $generatedUsername = 'vx_' . Str::lower(Str::random(8));
                    while (User::where('username', $generatedUsername)->exists()) {
                        $generatedUsername = 'vx_' . Str::lower(Str::random(8));
                    }

                    $user = User::create([
                        'username' => $generatedUsername,
                        'email' => $customerEmail,
                        'password' => Hash::make(Str::password(16, true, true, false, false)),
                        'full_name' => $customerName,
                        'channel' => 'website',
                    ]);
                }

                // Pastikan user memiliki organisasi
                $organization = $user->currentOrganization
                    ?? $user->organizations()->first()
                    ?? $user->createPersonalOrganization();

                $user->switchToOrganization($organization);

                // 2. Cek apakah ada order pending yang cocok, atau buat baru
                $order = Order::where('customer_id', $user->id)
                    ->where('vps_spec_id', $spec->id)
                    ->where('status', 'pending')
                    ->latest()
                    ->lockForUpdate()
                    ->first();

                if (!$order) {
                    $order = Order::create([
                        'customer_id' => $user->id,
                        'organization_id' => $organization->id,
                        'vps_spec_id' => $spec->id,
                        'control_panel' => $spec->default_stack ?: 'none',
                        'datacenter_location' => 'indonesia',
                        'os' => 'ubuntu2404',
                        'billing_cycle' => 'monthly',
                        'status' => 'pending',
                        'channel' => 'website',
                        'payment_method' => 'lynk',
                        'amount' => $amountPaid > 0 ? $amountPaid : $spec->sell_price,
                        'currency' => 'IDR',
                        'hostname' => 'vx-' . Str::lower(Str::random(6)) . '.vexahost.cloud',
                    ]);
                }

                // 3. Transisi status Order ke paid lewat OrderStateMachine
                $this->orderStateMachine->transition($order, 'paid', [
                    'reason' => "Pembayaran via Lynk.id berhasil (Ref: {$refId})",
                    'actor_type' => 'webhook',
                    'metadata' => [
                        'provider' => 'lynk',
                        'refId' => $refId,
                        'message_id' => $messageId,
                        'customer_name' => $customerName,
                        'customer_phone' => $customerPhone,
                    ],
                    'onLocked' => function (Order $locked) use ($amountPaid) {
                        $locked->payment_method = 'lynk';
                        $locked->paid_at = now();
                        if ($amountPaid > 0) {
                            $locked->amount = $amountPaid;
                        }
                        $locked->save();
                    },
                ]);

                $order->refresh();

                // 4. Update Invoice yang sudah ada, atau buat baru jika belum dibuat
                $invoice = Invoice::where('order_id', $order->id)->first();
                if ($invoice) {
                    $invoice->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                        'amount' => $order->amount,
                    ]);
                } else {
                    $invoice = Invoice::create([
                        'order_id' => $order->id,
                        'organization_id' => $order->organization_id,
                        'invoice_number' => 'INV-LYNK-' . strtoupper(Str::random(8)),
                        'amount' => $order->amount,
                        'status' => 'paid',
                        'issued_at' => now(),
                        'due_at' => now(),
                        'paid_at' => now(),
                    ]);
                }

                // 5. Catat PaymentTransaction
                $tx = PaymentTransaction::create([
                    'order_id' => $order->id,
                    'invoice_id' => $invoice->id,
                    'provider' => 'lynk',
                    'provider_transaction_id' => $refId,
                    'provider_order_ref' => $messageId,
                    'payment_method' => 'lynk',
                    'amount' => $order->amount,
                    'currency' => 'IDR',
                    'status' => 'settled',
                    'signature_key' => $event->signature,
                    'raw_payload' => $payload,
                    'settled_at' => now(),
                ]);

                // 6. Tandai event berhasil di dalam transaksi yang sama
                $event->update([
                    'event_type' => 'payment.received',
                    'processing_status' => 'processed',
                    'processing_error' => null,
                    'http_status' => 200,
                    'processed_at' => now(),
                    'order_id' => $order->id,
                    'payment_transaction_id' => $tx->id,
                ]);

                return [
                    'order' => $order,
                    'invoice' => $invoice,
                    'user' => $user,
                    'is_new_user' => $isNewUser,
                ];
            });

            // 7. Notifikasi pelanggan & admin (+ perpanjangan bila order renewal) dikirim
            //    otomatis oleh PaymentCompletionService setelah transaksi commit. Panggilan
            //    ini pengaman yang idempoten.
            app(\App\Services\PaymentCompletionService::class)->handle($result['order']->id);

            Log::info("Lynk webhook successfully processed for Order #{$result['order']->id} (Ref: {$refId})");

            return [
                'http' => 200,
                'body' => [
                    'success' => true,
                    'message' => 'Lynk payment processed successfully.',
                    'order_id' => $result['order']->id,
                    'is_new_user' => $result['is_new_user'],
                ],
            ];
        } catch (InvalidStateTransitionException $stateEx) {
            Log::warning('Lynk webhook state transition error: ' . $stateEx->getMessage());
            $this->mark($event, 'failed', 409, 'Transisi status ditolak: ' . $stateEx->getMessage());

            return [
                'http' => 409,
                'body' => [
                    'success' => false,
                    'message' => 'State transition error: ' . $stateEx->getMessage(),
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('Lynk webhook unexpected error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            $this->mark($event, 'failed', 500, $e->getMessage());

            return [
                'http' => 500,
                'body' => [
                    'success' => false,
                    'message' => 'Internal server error processing webhook: ' . $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Catat hasil pemrosesan ke event. Dijalankan di luar transaksi agar
     * status gagal tetap tersimpan walaupun transaksi pemrosesan dibatalkan.
     */
    protected function mark(
        WebhookEvent $event,
        string $status,
        int $http,
        ?string $error,
        array $extra = []
    ): void {
        $event->update(array_merge([
            'processing_status' => $status,
            'processing_error' => $error ? mb_substr($error, 0, 2000) : null,
            'http_status' => $http,
            'processed_at' => $status === 'processed' ? now() : $event->processed_at,
        ], $extra));
    }

    protected function reply(WebhookEvent $event, int $http, array $body, bool $markHttp = true): array
    {
        if ($markHttp) {
            $event->update(['http_status' => $http]);
        }

        return ['http' => $http, 'body' => $body];
    }

    /**
     * Petakan nama item atau harga dari Lynk ke VpsSpec VexaHost.
     */
    public function resolveVpsSpec(string $itemTitle, float $itemPrice, float $grandTotal): VpsSpec
    {
        $normalizedTitle = strtolower(trim($itemTitle));

        // 1. Pencarian berdasarkan nama paket spesifik
        foreach (VpsSpec::where('is_active', true)->get() as $spec) {
            if (!empty($spec->name) && str_contains($normalizedTitle, strtolower($spec->name))) {
                return $spec;
            }
        }

        // 2. Pemetaan keyword umum
        $keywords = [
            'terminal' => 7,
            'workstation' => 8,
            'hermes' => 9,
            'rag' => 10,
            'private ai' => 10,
            'db micro' => 11,
            'db standard' => 12,
            'db enterprise' => 13,
            'vector' => 13,
            'student' => 1,
            'mahasiswa' => 6,
            'standard' => 2,
            'premium' => 3,
            'startup' => 4,
            'business' => 5,
        ];

        foreach ($keywords as $key => $specId) {
            if (str_contains($normalizedTitle, $key)) {
                $found = VpsSpec::find($specId);
                if ($found) {
                    return $found;
                }
            }
        }

        // 3. Pencarian berdasarkan harga yang cocok
        $priceMatch = VpsSpec::where('is_active', true)
            ->where(function ($q) use ($itemPrice, $grandTotal) {
                $q->where('sell_price', $itemPrice)
                    ->orWhere('sell_price', $grandTotal);
            })
            ->first();

        if ($priceMatch) {
            return $priceMatch;
        }

        // 4. Default: Standard Spec (ID: 2) atau spec pertama
        return VpsSpec::find(2) ?? VpsSpec::first();
    }
}
