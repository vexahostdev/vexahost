<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InvalidStateTransitionException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Models\WebhookEvent;
use App\Services\OrderStateMachine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Webhook payment gateway (Midtrans-compatible).
 *
 * Kontrak keamanan (P0):
 *   1. MIDTRANS_SERVER_KEY WAJIB di-set. Tanpa itu, endpoint langsung 500.
 *      Tidak boleh ada mode "skip signature" seperti sebelumnya.
 *   2. Signature diverifikasi sebelum apapun lainnya.
 *   3. Idempotent: webhook duplikat (provider + transaction_id sama) tidak
 *      pernah memproses ulang. Sekali sukses = terkunci.
 *   4. Amount verify: gross_amount di webhook harus == order.amount.
 *      Kalau tidak match → tolak, catat sebagai anomaly.
 *   5. Semua write dalam DB::transaction + lockForUpdate untuk cegah race.
 *   6. Perubahan status order lewat OrderStateMachine (validated transitions).
 *   7. Raw payload disimpan permanen di webhook_events + payment_transactions
 *      untuk forensik.
 */
class PaymentWebhookController extends Controller
{
    public function __construct(protected OrderStateMachine $orderStateMachine)
    {
    }

    public function handle(Request $request)
    {
        // Respond to GET requests (for browser check, uptime monitoring, and ping tests)
        if ($request->isMethod('get')) {
            return response()->json([
                'status' => 'ok',
                'message' => 'Midtrans payment webhook endpoint is active and ready.',
            ], 200);
        }

        // PHASE 4 - registry gateway lebih dulu, jatuh kembali ke .env.
        $serverKey = PaymentGateway::credential('midtrans', 'server_key', config('services.midtrans.server_key'));

        // Guard 1: server key WAJIB dikonfigurasi.
        if (empty($serverKey)) {
            Log::critical('Payment webhook rejected: MIDTRANS_SERVER_KEY not configured');
            return response()->json([
                'success' => false,
                'message' => 'Payment gateway not configured on server.',
            ], 500);
        }

        // Validasi struktur minimal payload (menggunakan Validator agar tidak redirect 302 jika non-JSON Accept header).
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'order_id' => 'required|string',
            'status_code' => 'nullable|string',
            'gross_amount' => 'nullable|string',
            'signature_key' => 'required|string',
            'transaction_status' => 'nullable|string',
            'status' => 'nullable|string',
            'transaction_id' => 'nullable|string',
            'payment_type' => 'nullable|string',
            'fraud_status' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid webhook payload structure.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $rawOrderId = (string) $validated['order_id'];
        $statusCode = (string) ($validated['status_code'] ?? '');
        $grossAmount = (string) ($validated['gross_amount'] ?? '');
        $incomingSignature = (string) $validated['signature_key'];

        // Guard 2: verifikasi signature.
        $expectedSignature = hash('sha512', $rawOrderId . $statusCode . $grossAmount . $serverKey);
        if (!hash_equals($expectedSignature, $incomingSignature)) {
            Log::warning('Payment webhook signature mismatch', [
                'order_id' => $rawOrderId,
                'ip' => $request->ip(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Invalid signature key.',
            ], 403);
        }

        // Deteksi event id (untuk idempotency). Prioritas: transaction_id -> signature.
        $providerTxId = $validated['transaction_id']
            ?? $incomingSignature; // fallback: signature unik per (order,status,amount).
        $provider = 'midtrans';

        // Status mentah dari gateway.
        $statusField = $validated['transaction_status'] ?? $validated['status'] ?? null;
        if (!$statusField) {
            return response()->json([
                'success' => false,
                'message' => 'Missing transaction status.',
            ], 422);
        }
        $paymentStatus = strtolower($statusField);

        // Parse order id dari format "ORDER-123", "ORDER-123-timestamp", "INV-YYYYMM-XXXX", atau ID angka langsung.
        $order = null;
        if (is_numeric($rawOrderId)) {
            $order = Order::with('invoice')->find((int) $rawOrderId);
        }
        if (!$order && preg_match('/^(?:ORDER|VX)[-_]?(\d+)(?:[-_].*)?$/i', $rawOrderId, $matches)) {
            $order = Order::with('invoice')->find((int) $matches[1]);
        }
        if (!$order) {
            $order = Order::whereHas('invoice', fn ($q) => $q->where('invoice_number', $rawOrderId))->with('invoice')->first();
        }
        if (!$order) {
            $tx = PaymentTransaction::where('provider_order_ref', $rawOrderId)->with('order.invoice')->first();
            if ($tx && $tx->order) {
                $order = $tx->order;
            }
        }
        if (!$order) {
            $cleanId = preg_replace('/[^0-9]/', '', $rawOrderId);
            if (!empty($cleanId)) {
                $order = Order::with('invoice')->find((int) $cleanId);
            }
        }
        if (!$order) {
            // Tetap catat webhook untuk forensik.
            $this->recordWebhook($provider, $providerTxId, $paymentStatus, $incomingSignature, $request, null, null, 'ignored', 'Order not found / Midtrans test ping');
            // Kembalikan 200 OK agar tes webhook / simulasi Midtrans dashboard tidak mendeteksi kegagalan
            return response()->json([
                'success' => true,
                'message' => 'Notification acknowledged (order not found or dashboard test).',
            ], 200);
        }

        // Guard 4: amount match.
        // Midtrans mengirim gross_amount seperti "150000.00". Bandingkan sebagai decimal.
        $webhookAmount = (float) $grossAmount;
        $orderAmount = (float) $order->amount;
        if (abs($webhookAmount - $orderAmount) > 0.01) {
            Log::critical('Payment webhook amount mismatch', [
                'order_id' => $order->id,
                'webhook_amount' => $webhookAmount,
                'order_amount' => $orderAmount,
                'ip' => $request->ip(),
            ]);
            $this->recordWebhook($provider, $providerTxId, $paymentStatus, $incomingSignature, $request, $order->id, null, 'failed', 'Amount mismatch');
            return response()->json([
                'success' => false,
                'message' => 'Amount mismatch.',
            ], 422);
        }

        // Idempotency check + processing di dalam transaction.
        $needsNotification = false;
        try {
            $result = DB::transaction(function () use (
                $provider, $providerTxId, $paymentStatus, $incomingSignature,
                $request, $order, $validated, $grossAmount, &$needsNotification
            ) {
                // Cek apakah webhook ini sudah pernah diproses (idempotency).
                $existingEvent = WebhookEvent::where('provider', $provider)
                    ->where('event_id', $providerTxId)
                    ->lockForUpdate()
                    ->first();

                if ($existingEvent && $existingEvent->processing_status === 'processed') {
                    return [
                        'status' => 'duplicate',
                        'message' => 'Webhook already processed (idempotent).',
                        'order_status' => $order->status,
                    ];
                }

                // Buat / update webhook_events (state: received).
                $event = $existingEvent ?: WebhookEvent::create([
                    'provider' => $provider,
                    'event_id' => $providerTxId,
                    'event_type' => $paymentStatus,
                    'signature' => $incomingSignature,
                    'ip_address' => $request->ip(),
                    'payload' => $request->all(),
                    'processing_status' => 'received',
                    'order_id' => $order->id,
                ]);

                // Cari transaksi payment yang sudah ada (untuk idempotency di layer transaction).
                $tx = PaymentTransaction::where('provider', $provider)
                    ->where('provider_transaction_id', $providerTxId)
                    ->lockForUpdate()
                    ->first();

                if (!$tx) {
                    $tx = PaymentTransaction::create([
                        'order_id' => $order->id,
                        'invoice_id' => $order->invoice?->id,
                        'provider' => $provider,
                        'provider_transaction_id' => $providerTxId,
                        'provider_order_ref' => $order->id,
                        'payment_method' => $validated['payment_type'] ?? $order->payment_method,
                        'amount' => $grossAmount,
                        'currency' => 'IDR',
                        'status' => 'pending',
                        'fraud_status' => $validated['fraud_status'] ?? null,
                        'signature_key' => $incomingSignature,
                        'raw_payload' => $request->all(),
                    ]);
                }

                // Fraud challenge → tidak transisi, tunggu review manual.
                $fraudStatus = $validated['fraud_status'] ?? null;
                if ($fraudStatus === 'challenge') {
                    $tx->update(['fraud_status' => 'challenge']);
                    $event->update([
                        'processing_status' => 'ignored',
                        'processing_error' => 'Fraud challenge - awaiting manual review',
                        'processed_at' => now(),
                        'payment_transaction_id' => $tx->id,
                    ]);
                    return [
                        'status' => 'challenge',
                        'message' => 'Payment on fraud challenge, awaiting manual review.',
                        'order_status' => $order->status,
                    ];
                }

                // Route berdasarkan payment status.
                if (in_array($paymentStatus, ['settlement', 'capture', 'paid'], true)) {
                    $this->handleSettlement($order, $tx, $event, $validated);
                    $needsNotification = true;
                    return [
                        'status' => 'settled',
                        'message' => 'Payment recorded. Order transitioned to paid.',
                        'order_status' => 'paid',
                    ];
                }

                if (in_array($paymentStatus, ['cancel', 'deny', 'expire', 'failure'], true)) {
                    $this->handleFailure($order, $tx, $event, $paymentStatus);
                    return [
                        'status' => 'cancelled',
                        'message' => 'Payment cancelled/denied recorded.',
                        'order_status' => 'cancelled',
                    ];
                }

                // Status pending/authorize/lainnya → hanya update tx.
                $tx->update([
                    'status' => $this->mapProviderStatus($paymentStatus),
                    'raw_payload' => $request->all(),
                ]);
                $event->update([
                    'processing_status' => 'processed',
                    'processed_at' => now(),
                    'payment_transaction_id' => $tx->id,
                ]);

                return [
                    'status' => 'pending',
                    'message' => 'Payment status pending recorded.',
                    'order_status' => $order->status,
                ];
            });
        } catch (InvalidStateTransitionException $e) {
            // Order sudah bukan di status yang bisa transisi (misal sudah cancelled).
            // Ini bukan error webhook — catat & return 200 supaya gateway tidak retry.
            Log::warning('Payment webhook state transition rejected', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            $this->recordWebhook($provider, $providerTxId, $paymentStatus, $incomingSignature, $request, $order->id, null, 'ignored', $e->getMessage());
            return response()->json([
                'success' => true,
                'message' => 'Webhook received but state transition ignored: ' . $e->getMessage(),
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Payment webhook processing failed', [
                'order_id' => $order->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->recordWebhook($provider, $providerTxId, $paymentStatus, $incomingSignature, $request, $order->id ?? null, null, 'failed', $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Webhook processing error.',
            ], 500);
        }

        $response = response()->json([
            'success' => true,
            'message' => $result['message'],
            'order_id' => $order->id,
            'order_status' => $result['order_status'],
        ], 200);

        // Jika request diproses oleh Web Server (FastCGI / PHP-FPM / LiteSpeed),
        // segera kirimkan respons HTTP 200 ke gateway dan tutup koneksi client sekarang.
        // Dengan ini Midtrans menerima konfirmasi sukses dalam < 50ms tanpa risiko timeout.
        if (function_exists('fastcgi_finish_request')) {
            ignore_user_abort(true);
            $response->send();
            fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            ignore_user_abort(true);
            $response->send();
            litespeed_finish_request();
        }

        // Jalankan pengiriman notifikasi (render PDF & kirim email SMTP)
        // setelah koneksi Midtrans selesai dilepas atau secara sinkron bila CLI.
        if ($needsNotification) {
            $this->dispatchPostPaymentNotifications($order);
        }

        return $response;
    }

    /**
     * Sukses payment: transisi order pending → paid, tandai invoice paid.
     * HANYA modifikasi state & database di sini (cepat <10ms, bebas lock eksternal).
     */
    protected function handleSettlement(Order $order, PaymentTransaction $tx, WebhookEvent $event, array $validated): void
    {
        $this->orderStateMachine->transition(
            $order,
            'paid',
            [
                'reason' => 'Payment gateway webhook settlement received.',
                'actor_type' => 'webhook',
                'metadata' => [
                    'provider' => $tx->provider,
                    'provider_tx_id' => $tx->provider_transaction_id,
                    'payment_method' => $validated['payment_type'] ?? null,
                ],
                'onLocked' => function (Order $locked) use ($tx, $validated) {
                    $locked->paid_at = now();
                    $locked->payment_method = $validated['payment_type'] ?? $locked->payment_method;
                    $locked->save();

                    if ($locked->invoice && $locked->invoice->status !== 'paid') {
                        $locked->invoice->update([
                            'status' => 'paid',
                            'paid_at' => now(),
                            'paid_via_transaction_id' => $tx->id,
                        ]);
                    }
                },
                'allowSame' => false,
            ]
        );

        $tx->update([
            'status' => 'settled',
            'settled_at' => now(),
            'fraud_status' => $validated['fraud_status'] ?? $tx->fraud_status,
        ]);

        $event->update([
            'processing_status' => 'processed',
            'processed_at' => now(),
            'payment_transaction_id' => $tx->id,
        ]);

        if ($order->isRenewal()) {
            try {
                app(\App\Services\RenewalService::class)->handleRenewalPayment($order);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('handleSettlement.renewal_failed', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Kirim email dan notifikasi setelah pembayaran sukses.
     * Dijalankan terpisah di luar transaksi database dan setelah koneksi HTTP Midtrans dilepas.
     */
    protected function dispatchPostPaymentNotifications(Order $order): void
    {
        // Notifikasi pelanggan & admin dikirim dari titik pusat. Sudah dipicu otomatis
        // oleh OrderStateMachine saat transisi ke 'paid'; panggilan ini hanya pengaman
        // dan idempoten (orders.paid_notified_at) sehingga tidak pernah terkirim dobel.
        app(\App\Services\PaymentCompletionService::class)->handle($order->id);
    }

    /**
     * Failure/cancel/deny/expire: transisi ke cancelled + tandai invoice cancelled.
     */
    protected function handleFailure(Order $order, PaymentTransaction $tx, WebhookEvent $event, string $paymentStatus): void
    {
        // Hanya transisi kalau order masih pending. Kalau sudah paid, ignore.
        if ($order->status === 'pending') {
            $this->orderStateMachine->transition(
                $order,
                'cancelled',
                [
                    'reason' => "Payment {$paymentStatus} from gateway webhook.",
                    'actor_type' => 'webhook',
                    'metadata' => [
                        'gateway_status' => $paymentStatus,
                        'provider_tx_id' => $tx->provider_transaction_id,
                    ],
                    'onLocked' => function (Order $locked) {
                        if ($locked->invoice && $locked->invoice->status !== 'cancelled') {
                            $locked->invoice->update(['status' => 'cancelled']);
                        }
                    },
                ]
            );
        }

        $tx->update([
            'status' => in_array($paymentStatus, ['expire'], true) ? 'expired' : 'failed',
            'failed_at' => now(),
            'failure_reason' => $paymentStatus,
        ]);

        $event->update([
            'processing_status' => 'processed',
            'processed_at' => now(),
            'payment_transaction_id' => $tx->id,
        ]);
    }

    /**
     * Helper: catat webhook forensik walau gagal (untuk debugging & audit).
     * Best-effort — tidak throw kalau unique key konflik.
     */
    protected function recordWebhook(
        string $provider,
        string $eventId,
        string $eventType,
        string $signature,
        Request $request,
        ?int $orderId,
        ?int $paymentTxId,
        string $status,
        ?string $error
    ): void {
        try {
            WebhookEvent::updateOrCreate(
                ['provider' => $provider, 'event_id' => $eventId],
                [
                    'event_type' => $eventType,
                    'signature' => $signature,
                    'ip_address' => $request->ip(),
                    'payload' => $request->all(),
                    'processing_status' => $status,
                    'processing_error' => $error,
                    'processed_at' => now(),
                    'order_id' => $orderId,
                    'payment_transaction_id' => $paymentTxId,
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Failed to record webhook event', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Map raw provider status → internal PaymentTransaction status.
     */
    protected function mapProviderStatus(string $providerStatus): string
    {
        return match ($providerStatus) {
            'settlement', 'capture', 'paid' => 'settled',
            'authorize' => 'authorized',
            'pending' => 'pending',
            'cancel', 'deny', 'failure' => 'failed',
            'expire' => 'expired',
            'refund', 'partial_refund' => 'refunded',
            default => 'pending',
        };
    }

    /**
     * Webhook sentral Xendit:
     * 1. Validasi token (x-callback-token).
     * 2. Periksa prefix external_id:
     *    - 'WAG-' ➔ Teruskan (forward) ke WAGateway via HTTP internal
     *    - 'BC-'  ➔ Teruskan (forward) ke BuildClient via HTTP internal
     *    - 'VH-'  ➔ Proses langsung di VexaHost (Order VPS & Layanan)
     */
    public function handleXendit(Request $request)
    {
        // Respond to GET requests (ping / health check)
        if ($request->isMethod('get')) {
            return response()->json([
                'status' => 'ok',
                'message' => 'VexaHost Central Xendit webhook endpoint is active and healthy.',
            ], 200);
        }

        $callbackToken = $request->header('x-callback-token')
            ?? $request->header('X-CALLBACK-TOKEN')
            ?? $request->input('callback_token');

        $expectedToken = config('services.xendit.webhook_token')
            ?: PaymentGateway::credential('xendit', 'callback_token', env('XENDIT_WEBHOOK_TOKEN'));

        if (empty($callbackToken) || !hash_equals((string) $expectedToken, (string) $callbackToken)) {
            Log::warning('Xendit webhook rejected: Invalid callback token', [
                'ip' => $request->ip(),
                'provided_token' => $callbackToken ? substr($callbackToken, 0, 4) . '***' : 'null',
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Invalid or missing callback token.',
            ], 403);
        }

        $payload = $request->all();
        $externalId = (string) ($payload['external_id'] ?? '');
        $invoiceId = (string) ($payload['id'] ?? '');
        $status = strtoupper((string) ($payload['status'] ?? ''));
        $grossAmount = (string) ($payload['amount'] ?? ($payload['paid_amount'] ?? '0'));

        Log::info('Xendit central webhook received', [
            'external_id' => $externalId,
            'invoice_id' => $invoiceId,
            'status' => $status,
            'amount' => $grossAmount,
        ]);

        // ==========================================
        // ROUTE 1: FORWARD KE WAGATEWAY (Prefix WAG-)
        // ==========================================
        if (str_starts_with($externalId, 'WAG-')) {
            $wagUrl = config('services.xendit.wagateway_webhook_url') ?: env('WAGATEWAY_WEBHOOK_URL', 'https://wa.vexahostcloud.my.id/api/payment/xendit/callback');
            try {
                $forwardRes = \Illuminate\Support\Facades\Http::timeout(12)
                    ->withHeaders([
                        'X-Internal-Token' => config('services.xendit.internal_secret'),
                        'x-callback-token' => $callbackToken,
                    ])
                    ->post($wagUrl, $payload);

                Log::info('Xendit webhook forwarded to WAGateway', [
                    'external_id' => $externalId,
                    'status_code' => $forwardRes->status(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Notification forwarded to WAGateway.',
                    'target_response_code' => $forwardRes->status(),
                ], 200);
            } catch (\Throwable $e) {
                Log::error('Xendit webhook forward to WAGateway failed', [
                    'external_id' => $externalId,
                    'error' => $e->getMessage(),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to forward to WAGateway: ' . $e->getMessage(),
                ], 502);
            }
        }

        // ==========================================
        // ROUTE 2: FORWARD KE BUILDCLIENT (Prefix BC-)
        // ==========================================
        if (str_starts_with($externalId, 'BC-')) {
            $bcUrl = config('services.xendit.buildclient_webhook_url') ?: env('BUILDCLIENT_WEBHOOK_URL', 'https://client.vexahostcloud.my.id/api/payment/xendit/callback');
            try {
                $forwardRes = \Illuminate\Support\Facades\Http::timeout(12)
                    ->withHeaders([
                        'X-Internal-Token' => config('services.xendit.internal_secret'),
                        'x-callback-token' => $callbackToken,
                    ])
                    ->post($bcUrl, $payload);

                Log::info('Xendit webhook forwarded to BuildClient', [
                    'external_id' => $externalId,
                    'status_code' => $forwardRes->status(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Notification forwarded to BuildClient.',
                    'target_response_code' => $forwardRes->status(),
                ], 200);
            } catch (\Throwable $e) {
                Log::error('Xendit webhook forward to BuildClient failed', [
                    'external_id' => $externalId,
                    'error' => $e->getMessage(),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to forward to BuildClient: ' . $e->getMessage(),
                ], 502);
            }
        }

        // ==========================================
        // ROUTE 3: PROSES LOKAL VEXAHOST (Prefix VH-)
        // ==========================================
        $order = null;
        if (preg_match('/^(?:VH[-_])?(?:ORD|ORDER)[-_](\d+)/i', $externalId, $m)) {
            $order = Order::with('invoice')->find((int) $m[1]);
        }
        if (!$order && is_numeric($externalId)) {
            $order = Order::with('invoice')->find((int) $externalId);
        }
        if (!$order) {
            $tx = PaymentTransaction::where('provider', 'xendit')
                ->where(function ($q) use ($externalId, $invoiceId) {
                    $q->where('provider_order_ref', $externalId)
                      ->orWhere('provider_transaction_id', $invoiceId);
                })
                ->with('order.invoice')
                ->first();
            if ($tx && $tx->order) {
                $order = $tx->order;
            }
        }

        if (!$order) {
            $this->recordWebhook('xendit', $invoiceId ?: $externalId, strtolower($status), (string) $callbackToken, $request, null, null, 'ignored', 'Order not found');
            return response()->json([
                'success' => true,
                'message' => 'Xendit notification acknowledged (order not found).',
            ], 200);
        }

        $provider = 'xendit';
        $providerTxId = $invoiceId ?: $externalId;
        $paymentStatus = strtolower($status);
        $needsNotification = false;

        // Guard: amount match untuk status sukses (paid/settled)
        $webhookAmount = (float) $grossAmount;
        $orderAmount = (float) $order->amount;
        if (in_array($paymentStatus, ['paid', 'settled'], true) && abs($webhookAmount - $orderAmount) > 0.01) {
            Log::critical('Xendit payment webhook amount mismatch', [
                'order_id' => $order->id,
                'webhook_amount' => $webhookAmount,
                'order_amount' => $orderAmount,
                'ip' => $request->ip(),
            ]);
            $this->recordWebhook($provider, $providerTxId, $paymentStatus, (string) $callbackToken, $request, $order->id, null, 'failed', 'Amount mismatch');
            return response()->json([
                'success' => false,
                'message' => 'Amount mismatch.',
            ], 422);
        }

        try {
            $result = DB::transaction(function () use (
                $provider, $providerTxId, $paymentStatus, $callbackToken,
                $request, $order, $payload, $grossAmount, &$needsNotification
            ) {
                $existingEvent = WebhookEvent::where('provider', $provider)
                    ->where('event_id', $providerTxId)
                    ->lockForUpdate()
                    ->first();

                if ($existingEvent && $existingEvent->processing_status === 'processed') {
                    return [
                        'status' => 'duplicate',
                        'message' => 'Webhook already processed (idempotent).',
                        'order_status' => $order->status,
                    ];
                }

                $event = $existingEvent ?: WebhookEvent::create([
                    'provider' => $provider,
                    'event_id' => $providerTxId,
                    'event_type' => $paymentStatus,
                    'signature' => (string) $callbackToken,
                    'ip_address' => $request->ip(),
                    'payload' => $payload,
                    'processing_status' => 'received',
                    'order_id' => $order->id,
                ]);

                $tx = PaymentTransaction::where('provider', $provider)
                    ->where('provider_transaction_id', $providerTxId)
                    ->lockForUpdate()
                    ->first();

                $channel = $payload['payment_channel'] ?? ($payload['payment_method'] ?? 'xendit');

                if (!$tx) {
                    $tx = PaymentTransaction::create([
                        'order_id' => $order->id,
                        'invoice_id' => $order->invoice?->id,
                        'provider' => $provider,
                        'provider_transaction_id' => $providerTxId,
                        'provider_order_ref' => $payload['external_id'] ?? $order->id,
                        'payment_method' => $channel,
                        'amount' => $grossAmount,
                        'currency' => 'IDR',
                        'status' => 'pending',
                        'signature_key' => (string) $callbackToken,
                        'raw_payload' => $payload,
                    ]);
                }

                if (in_array($paymentStatus, ['paid', 'settled'], true)) {
                    $this->handleSettlement($order, $tx, $event, [
                        'payment_type' => $channel,
                        'gross_amount' => $grossAmount,
                    ]);
                    $needsNotification = true;
                    return [
                        'status' => 'settled',
                        'message' => 'Payment recorded via Xendit. Order transitioned to paid.',
                        'order_status' => 'paid',
                    ];
                }

                if (in_array($paymentStatus, ['expired', 'failed'], true)) {
                    $this->handleFailure($order, $tx, $event, $paymentStatus);
                    return [
                        'status' => 'cancelled',
                        'message' => 'Payment expired/failed recorded.',
                        'order_status' => 'cancelled',
                    ];
                }

                return [
                    'status' => 'pending',
                    'message' => 'Pending payment recorded.',
                    'order_status' => $order->status,
                ];
            });

            if ($needsNotification) {
                $this->dispatchPostPaymentNotifications($order);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'order_id' => $order->id,
                'order_status' => $result['order_status'],
            ], 200);

        } catch (InvalidStateTransitionException $e) {
            Log::warning('Xendit webhook state transition rejected', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            $this->recordWebhook($provider, $providerTxId, $paymentStatus, (string) $callbackToken, $request, $order->id, null, 'ignored', $e->getMessage());
            return response()->json([
                'success' => true,
                'message' => 'Webhook received but state transition ignored: ' . $e->getMessage(),
            ], 200);
        } catch (\Throwable $e) {
            Log::error('xendit_webhook_processing_error', ['error' => $e->getMessage(), 'order_id' => $order->id]);
            return response()->json([
                'success' => false,
                'message' => 'Internal processing error: ' . $e->getMessage(),
            ], 500);
        }
    }
}

