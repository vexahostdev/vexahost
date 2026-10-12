<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MidtransService
{
    /**
     * Cek apakah Midtrans sudah dikonfigurasi (Server Key tersedia).
     */
    public static function isConfigured(): bool
    {
        return !empty(self::getServerKey());
    }

    /**
     * Ambil status environment (Production atau Sandbox).
     */
    public static function isProduction(): bool
    {
        $gateway = PaymentGateway::where('code', 'midtrans')->first();
        if ($gateway && !empty($gateway->mode)) {
            return $gateway->mode === 'production';
        }

        return (bool) config('services.midtrans.is_production', false);
    }

    /**
     * Ambil Server Key dari PaymentGateway registry atau fallback ke .env.
     */
    public static function getServerKey(): ?string
    {
        return PaymentGateway::credential('midtrans', 'server_key', config('services.midtrans.server_key'));
    }

    /**
     * Ambil Client Key dari PaymentGateway registry atau fallback ke .env.
     */
    public static function getClientKey(): ?string
    {
        return PaymentGateway::credential('midtrans', 'client_key', config('services.midtrans.client_key'));
    }

    /**
     * URL script Snap JS untuk frontend.
     */
    public static function getSnapJsUrl(): string
    {
        return self::isProduction()
            ? 'https://app.midtrans.com/snap/snap.js'
            : 'https://app.sandbox.midtrans.com/snap/snap.js';
    }

    /**
     * Endpoint API Snap Transactions.
     */
    public static function getSnapApiUrl(): string
    {
        return self::isProduction()
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
    }

    /**
     * Mapping payment method kode internal VexaHost ke enabled_payments Midtrans.
     */
    public static function mapEnabledPayment(string $method): ?array
    {
        return match ($method) {
            'mandiri_va' => ['echannel'],
            'bni_va' => ['bni_va'],
            'bri_va' => ['bri_va'],
            'permata_va' => ['permata_va'],
            'cimb_va' => ['cimb_va'],
            'other_va' => ['other_va', 'permata_va'],
            'gopay' => ['gopay'],
            'midtrans_snap' => ['qris', 'other_qris', 'gopay'],
            default => null, // null berarti tidak membatasi (Midtrans menampilkan semua channel aktif)
        };
    }

    /**
     * Buat atau pulihkan transaksi Midtrans Snap untuk pesanan tertentu.
     *
     * @return array{success: bool, token?: string, redirect_url?: string, error?: string}
     */
    public static function createSnapTransaction(Order $order, ?string $paymentMethod = null): array
    {
        $serverKey = self::getServerKey();
        if (empty($serverKey)) {
            Log::error('Midtrans Snap error: MIDTRANS_SERVER_KEY tidak ditemukan.');
            return [
                'success' => false,
                'error' => 'Server Key Midtrans belum dikonfigurasi.',
            ];
        }

        $method = $paymentMethod ?: $order->payment_method;

        // 1. Cek apakah sudah pernah generate token yang masih aktif (dalam 12 jam terakhir)
        $existingTx = PaymentTransaction::where('order_id', $order->id)
            ->where('provider', 'midtrans')
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subHours(12))
            ->latest()
            ->first();

        if ($existingTx && !empty($existingTx->raw_payload['snap_token']) && !empty($existingTx->raw_payload['snap_redirect_url'])) {
            return [
                'success' => true,
                'token' => $existingTx->raw_payload['snap_token'],
                'redirect_url' => $existingTx->raw_payload['snap_redirect_url'],
                'midtrans_order_id' => $existingTx->provider_order_ref,
            ];
        }

        // 2. Format Order ID unik untuk Midtrans
        // Gunakan timestamp agar pesanan yang diulang tidak ditolak oleh Midtrans dengan error "duplicate order_id"
        $midtransOrderId = 'ORDER-' . $order->id . '-' . time();
        $grossAmount = (int) round($order->amount);

        // 3. Susun data customer
        $customer = $order->customer;
        $customerName = $customer ? ($customer->full_name ?: $customer->username ?: 'Pelanggan') : 'Pelanggan';
        $customerEmail = $customer ? $customer->email : 'support@vexahost.id';
        $customerPhone = $customer ? ($customer->phone ?: '') : '';

        // 4. Susun item details
        $specName = $order->vpsSpec ? $order->vpsSpec->name : 'Layanan Cloud VPS';
        $itemDetails = [
            [
                'id' => (string) ($order->vps_spec_id ?: $order->id),
                'price' => $grossAmount,
                'quantity' => 1,
                'name' => mb_substr($specName . ' (' . ($order->hostname ?: 'Server') . ')', 0, 50),
            ]
        ];

        // 5. Parameter transaksi Midtrans Snap
        $params = [
            'transaction_details' => [
                'order_id' => $midtransOrderId,
                'gross_amount' => $grossAmount,
            ],
            'customer_details' => [
                'first_name' => $customerName,
                'email' => $customerEmail,
                'phone' => $customerPhone,
            ],
            'item_details' => $itemDetails,
            'callbacks' => [
                'finish' => route('order.payment.status', $order->id),
            ],
            'expiry' => [
                'unit' => 'days',
                'duration' => 1,
            ],
        ];

        // Filter channel jika pelanggan memilih bank / e-wallet spesifik
        $enabledPayments = self::mapEnabledPayment($method);
        if (!empty($enabledPayments)) {
            $params['enabled_payments'] = $enabledPayments;
        }

        // 6. Panggil Snap API Midtrans
        try {
            $snapHeaders = [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ];

            try {
                $webhookUrl = route('api.webhooks.payment');
                if (filter_var($webhookUrl, FILTER_VALIDATE_URL) && !str_contains($webhookUrl, 'localhost') && !str_contains($webhookUrl, '127.0.0.1')) {
                    $snapHeaders['X-Append-Notification'] = $webhookUrl;
                    $snapHeaders['X-Override-Notification'] = $webhookUrl;
                }
            } catch (\Throwable) {
                // Ignore route generation issues in tests/CLI
            }

            $response = Http::withBasicAuth(trim($serverKey), '')
                ->withHeaders($snapHeaders)
                ->timeout(15)
                ->post(self::getSnapApiUrl(), $params);

            if (!$response->successful()) {
                Log::error('Midtrans Snap API call failed', [
                    'order_id' => $order->id,
                    'status' => $response->status(),
                    'body' => $response->json() ?: $response->body(),
                ]);

                $errMsg = $response->json('error_messages.0')
                    ?? $response->json('message')
                    ?? 'Gagal menghubungi server Midtrans (HTTP ' . $response->status() . ').';

                return [
                    'success' => false,
                    'error' => $errMsg,
                ];
            }

            $responseData = $response->json();
            $token = $responseData['token'] ?? null;
            $redirectUrl = $responseData['redirect_url'] ?? null;

            if (empty($token)) {
                return [
                    'success' => false,
                    'error' => 'Respon Midtrans tidak menyertakan Snap Token.',
                ];
            }

            // Catat transaksi pending di PaymentTransaction untuk idempotency & pelacakan
            PaymentTransaction::create([
                'order_id' => $order->id,
                'invoice_id' => $order->invoice?->id,
                'provider' => 'midtrans',
                'provider_transaction_id' => null, // akan diisi oleh webhook setelah transaksi dibuat
                'provider_order_ref' => $midtransOrderId,
                'payment_method' => $method,
                'amount' => $grossAmount,
                'currency' => 'IDR',
                'status' => 'pending',
                'raw_payload' => [
                    'snap_token' => $token,
                    'snap_redirect_url' => $redirectUrl,
                    'request_params' => $params,
                ],
            ]);

            return [
                'success' => true,
                'token' => $token,
                'redirect_url' => $redirectUrl,
                'midtrans_order_id' => $midtransOrderId,
            ];
        } catch (\Throwable $e) {
            Log::error('Midtrans Snap Exception: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Terjadi kendala koneksi ke server Midtrans: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Endpoint Core API untuk cek status transaksi.
     */
    public static function getStatusApiUrl(string $orderId): string
    {
        return self::isProduction()
            ? 'https://api.midtrans.com/v2/' . urlencode($orderId) . '/status'
            : 'https://api.sandbox.midtrans.com/v2/' . urlencode($orderId) . '/status';
    }

    /**
     * Cek status transaksi langsung ke Midtrans Core API dan otomatis sinkronkan jika telah lunas.
     */
    public static function checkAndSyncStatus(Order $order): ?array
    {
        if ($order->paid_at || in_array($order->status, ['paid', 'provisioning', 'active'], true)) {
            return [
                'success' => true,
                'status' => 'settled',
                'order_status' => $order->status,
                'already_paid' => true,
            ];
        }

        $serverKey = self::getServerKey();
        if (empty($serverKey)) {
            return null;
        }

        // Kumpulkan kandidat order_id Midtrans yang mungkin tercatat
        $transactions = PaymentTransaction::where('order_id', $order->id)
            ->where('provider', 'midtrans')
            ->orderByDesc('id')
            ->get();

        $orderRefs = [];
        foreach ($transactions as $tx) {
            if (!empty($tx->provider_order_ref)) {
                $orderRefs[] = $tx->provider_order_ref;
            }
        }
        $orderRefs[] = 'ORDER-' . $order->id;

        $orderRefs = array_unique(array_filter($orderRefs));

        foreach ($orderRefs as $refId) {
            try {
                $url = self::getStatusApiUrl($refId);
                $response = Http::withBasicAuth(trim($serverKey), '')
                    ->withHeaders(['Accept' => 'application/json'])
                    ->timeout(8)
                    ->get($url);

                if (!$response->successful()) {
                    continue;
                }

                $data = $response->json();
                $txStatus = strtolower($data['transaction_status'] ?? '');
                $fraudStatus = strtolower($data['fraud_status'] ?? '');

                if (in_array($txStatus, ['settlement', 'capture', 'paid'], true) && $fraudStatus !== 'challenge') {
                    self::settleOrderFromMidtrans($order, $data, $refId);
                    return [
                        'success' => true,
                        'status' => 'settled',
                        'data' => $data,
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('Midtrans status check error for ref ' . $refId . ': ' . $e->getMessage());
            }
        }

        return null;
    }

    /**
     * Selesaikan order & sinkronkan database ketika terverifikasi lunas oleh Midtrans.
     */
    public static function settleOrderFromMidtrans(Order $order, array $data, string $refId): void
    {
        $order->loadMissing(['customer', 'vpsSpec', 'invoice']);
        $grossAmount = $data['gross_amount'] ?? $order->amount;
        $txId = $data['transaction_id'] ?? null;
        $paymentType = $data['payment_type'] ?? $order->payment_method;

        // 1. Temukan atau buat PaymentTransaction
        $tx = PaymentTransaction::where('provider', 'midtrans')
            ->where(function ($q) use ($txId, $refId) {
                if ($txId) {
                    $q->where('provider_transaction_id', $txId);
                }
                $q->orWhere('provider_order_ref', $refId);
            })
            ->latest()
            ->first();

        if (!$tx) {
            $tx = PaymentTransaction::create([
                'order_id' => $order->id,
                'invoice_id' => $order->invoice?->id,
                'provider' => 'midtrans',
                'provider_transaction_id' => $txId,
                'provider_order_ref' => $refId,
                'payment_method' => $paymentType,
                'amount' => $grossAmount,
                'currency' => 'IDR',
                'status' => 'settled',
                'settled_at' => now(),
                'fraud_status' => $data['fraud_status'] ?? 'accept',
                'raw_payload' => $data,
            ]);
        } else {
            $tx->update([
                'status' => 'settled',
                'settled_at' => now(),
                'provider_transaction_id' => $txId ?: $tx->provider_transaction_id,
                'fraud_status' => $data['fraud_status'] ?? $tx->fraud_status,
                'raw_payload' => array_merge($tx->raw_payload ?? [], $data),
            ]);
        }

        // 2. Transisi status order ke paid via OrderStateMachine jika belum paid
        if (!in_array($order->status, ['paid', 'provisioning', 'active'], true) || !$order->paid_at) {
            try {
                $stateMachine = app(\App\Services\OrderStateMachine::class);
                $stateMachine->transition($order, 'paid', [
                    'reason' => 'Midtrans Core API transaction verified as settled.',
                    'actor_type' => 'system',
                    'metadata' => [
                        'provider' => 'midtrans',
                        'provider_tx_id' => $txId,
                        'payment_type' => $paymentType,
                        'order_ref' => $refId,
                    ],
                    'onLocked' => function (Order $locked) use ($paymentType, $tx) {
                        $locked->paid_at = now();
                        $locked->payment_method = $paymentType ?: $locked->payment_method;
                        $locked->save();

                        if ($locked->invoice && $locked->invoice->status !== 'paid') {
                            $locked->invoice->update([
                                'status' => 'paid',
                                'paid_at' => now(),
                                'paid_via_transaction_id' => $tx->id,
                            ]);
                        }
                    },
                    'allowSame' => true,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Midtrans order state machine notice: ' . $e->getMessage(), ['order_id' => $order->id]);
                // Fallback direct update jika state machine rejected
                $order->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'payment_method' => $paymentType ?: $order->payment_method,
                ]);
                if ($order->invoice && $order->invoice->status !== 'paid') {
                    $order->invoice->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                        'paid_via_transaction_id' => $tx->id,
                    ]);
                }
            }
        }

        // 2b. Jika order bertipe renewal, proses perpanjangan masa aktif instance VPS
        if ($order->isRenewal()) {
            try {
                app(\App\Services\RenewalService::class)->handleRenewalPayment($order);
            } catch (\Throwable $e) {
                Log::error('midtrans.sync.renewal_failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }
        }

        // 3. Catat WebhookEvent agar tidak terjadi proses duplikat jika webhook tiba belakangan
        if ($txId) {
            \App\Models\WebhookEvent::firstOrCreate(
                [
                    'provider' => 'midtrans',
                    'event_id' => $txId,
                ],
                [
                    'event_type' => 'settlement',
                    'signature' => $data['signature_key'] ?? 'direct-api-sync',
                    'ip_address' => request()?->ip() ?? '127.0.0.1',
                    'payload' => $data,
                    'processing_status' => 'processed',
                    'processed_at' => now(),
                    'order_id' => $order->id,
                    'payment_transaction_id' => $tx->id,
                ]
            );
        }

        // 4. Notifikasi pelanggan & admin lewat titik pusat (idempoten, tidak dobel).
        //    Juga menutup jalur fallback update langsung di atas yang tidak lewat state machine.
        app(\App\Services\PaymentCompletionService::class)->handle($order->id);
    }
}
