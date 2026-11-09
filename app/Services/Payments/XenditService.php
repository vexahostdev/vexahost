<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class XenditService
{
    protected const BASE_URL = 'https://api.xendit.co';

    /**
     * Dapatkan Secret Key Xendit dari config/services atau PaymentGateway registry.
     */
    public static function getSecretKey(): ?string
    {
        $key = config('services.xendit.secret_key');
        if (empty($key)) {
            $key = PaymentGateway::credential('xendit', 'secret_key');
        }
        return $key ?: null;
    }

    /**
     * Dapatkan Webhook / Callback Token Xendit.
     */
    public static function getCallbackToken(): ?string
    {
        $token = config('services.xendit.webhook_token');
        if (empty($token)) {
            $token = PaymentGateway::credential('xendit', 'callback_token');
        }
        return $token ?: null;
    }

    /**
     * Cek apakah konfigurasi Xendit sudah terpasang.
     */
    public static function isConfigured(): bool
    {
        return !empty(self::getSecretKey());
    }

    /**
     * Pemetaan kode channel pembayaran internal ke kode Xendit Invoice.
     * Sesuai instruksi: satu channel terpilih dikunci agar UI Xendit langsung menampilkan channel tersebut.
     */
    public static function mapPaymentMethod(string $method): array
    {
        return match ($method) {
            'online_payment'                       => [
                'QRIS', 'INDOMARET', 'MANDIRI', 'BNI', 'BRI', 'CIMB', 'PERMATA', 'ASTRAPAY', 'AKULAKU'
            ],
            'qris', 'qris_xendit', 'midtrans_snap' => ['QRIS'],
            'indomaret', 'retail_indomaret'        => ['INDOMARET'],
            'mandiri_va'                           => ['MANDIRI'],
            'bni_va'                               => ['BNI'],
            'bri_va'                               => ['BRI'],
            'cimb_va'                              => ['CIMB'],
            'permata_va', 'other_va'               => ['PERMATA'],
            'astrapay', 'astrapay_va'              => ['ASTRAPAY'],
            'akulaku', 'paylater_akulaku'          => ['AKULAKU'],
            default => [
                'QRIS', 'INDOMARET', 'MANDIRI', 'BNI', 'BRI', 'CIMB', 'PERMATA', 'ASTRAPAY', 'AKULAKU'
            ],
        };
    }

    /**
     * Buat Invoice Xendit untuk Order VexaHost.
     * Menggunakan prefix VH- pada external_id untuk routing webhook sentral.
     */
    public static function createInvoice(Order $order, ?string $selectedMethod = null): array
    {
        $secretKey = self::getSecretKey();
        if (empty($secretKey)) {
            Log::error('xendit.create_invoice_failed: Secret key is not configured.');
            return [
                'success' => false,
                'error'   => 'Xendit payment gateway belum dikonfigurasi.',
            ];
        }

        $methodToLock = $selectedMethod ?: $order->payment_method ?: 'qris';
        $paymentMethods = self::mapPaymentMethod($methodToLock);

        $externalId = 'VH-ORD-' . $order->id . '-' . time();
        $amount = (float) $order->amount;

        $customer = $order->customer;
        $customerName = $customer?->full_name ?: ($customer?->username ?: 'Customer');
        $customerEmail = $customer?->email ?: 'billing@vexahost.id';
        $customerPhone = $customer?->phone ?: null;

        $successUrl = route('order.success', $order->id);
        $failureUrl = route('order.payment', $order->id);

        $payload = [
            'external_id'          => $externalId,
            'amount'               => $amount,
            'description'          => 'Pembayaran VPS VexaHost Order #' . $order->id,
            'invoice_duration'     => 86400, // 24 jam
            'payment_methods'      => $paymentMethods,
            'currency'             => 'IDR',
            'payer_email'          => $customerEmail,
            'success_redirect_url' => $successUrl,
            'failure_redirect_url' => $failureUrl,
            'customer' => array_filter([
                'given_names'   => $customerName,
                'email'         => $customerEmail,
                'mobile_number' => !empty($customerPhone) ? (string) $customerPhone : null,
            ]),
            'items' => [
                [
                    'name'     => 'VPS Hosting - ' . ($order->vpsSpec?->name ?: 'Paket Komputasi'),
                    'quantity' => 1,
                    'price'    => $amount,
                    'category' => 'Cloud Hosting',
                ]
            ],
        ];

        try {
            $response = Http::withBasicAuth($secretKey, '')
                ->timeout(15)
                ->post(self::BASE_URL . '/v2/invoices', $payload);

            if ($response->successful()) {
                $data = $response->json();

                // Simpan atau update transaksi di database
                PaymentTransaction::updateOrCreate(
                    [
                        'order_id' => $order->id,
                        'provider' => 'xendit',
                    ],
                    [
                        'invoice_id'              => $order->invoice?->id,
                        'provider_transaction_id' => $data['id'] ?? null,
                        'provider_order_ref'      => $externalId,
                        'payment_method'          => $methodToLock,
                        'amount'                  => $amount,
                        'currency'                => 'IDR',
                        'status'                  => 'pending',
                        'raw_payload'             => $data,
                    ]
                );

                Log::info('xendit.invoice_created', [
                    'order_id'    => $order->id,
                    'external_id' => $externalId,
                    'invoice_id'  => $data['id'] ?? null,
                    'invoice_url' => $data['invoice_url'] ?? null,
                ]);

                return [
                    'success'     => true,
                    'invoice_id'  => $data['id'] ?? null,
                    'invoice_url' => $data['invoice_url'] ?? null,
                    'external_id' => $externalId,
                ];
            }

            Log::error('xendit.invoice_failed', [
                'order_id' => $order->id,
                'status'   => $response->status(),
                'response' => $response->json(),
            ]);

            return [
                'success' => false,
                'error'   => $response->json()['message'] ?? 'Gagal membuat invoice di Xendit.',
            ];
        } catch (\Throwable $e) {
            Log::error('xendit.invoice_exception', [
                'order_id' => $order->id,
                'message'  => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error'   => 'Terjadi kesalahan koneksi ke Xendit: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Ambil status Invoice langsung dari API Xendit.
     */
    public static function getInvoice(string $invoiceId): ?array
    {
        $secretKey = self::getSecretKey();
        if (empty($secretKey)) {
            return null;
        }

        try {
            $response = Http::withBasicAuth($secretKey, '')
                ->timeout(10)
                ->get(self::BASE_URL . '/v2/invoices/' . $invoiceId);

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Throwable $e) {
            Log::error('xendit.get_invoice_exception', ['invoice_id' => $invoiceId, 'error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Cek dan sinkronisasi status pembayaran Order jika pengguna kembali ke halaman status.
     */
    public static function checkAndSyncStatus(Order $order): bool
    {
        if ($order->paid_at) {
            return true;
        }

        $tx = PaymentTransaction::where('order_id', $order->id)
            ->where('provider', 'xendit')
            ->latest()
            ->first();

        if (!$tx || empty($tx->provider_transaction_id)) {
            return false;
        }

        $invoiceData = self::getInvoice($tx->provider_transaction_id);
        if (!$invoiceData) {
            return false;
        }

        $status = strtoupper($invoiceData['status'] ?? '');
        if (in_array($status, ['PAID', 'SETTLED'], true)) {
            // Transisi order ke paid via state machine jika belum
            if ($order->status !== 'paid' && $order->status !== 'active') {
                try {
                    app(\App\Services\OrderStateMachine::class)->transition($order, 'paid', [
                        'reason' => 'Xendit checkAndSyncStatus invoice settled.',
                        'actor_type' => 'system',
                        'metadata' => [
                            'provider' => 'xendit',
                            'provider_tx_id' => $tx->provider_transaction_id,
                        ],
                        'onLocked' => function (Order $locked) use ($tx, $invoiceData) {
                            $locked->paid_at = now();
                            $locked->recordPaymentReceivedAt($invoiceData['paid_at'] ?? null);
                            if ($tx->payment_method) {
                                $locked->payment_method = $tx->payment_method;
                            }
                            $locked->save();
                        },
                    ]);
                } catch (\App\Exceptions\InvalidStateTransitionException $e) {
                    Log::warning('xendit.checkAndSyncStatus transition rejected: ' . $e->getMessage());
                }
            } else {
                $order->update([
                    'paid_at' => $order->paid_at ?: now(),
                    'payment_method' => $tx->payment_method ?: $order->payment_method,
                ]);
            }

            if ($order->invoice && $order->invoice->status !== 'paid') {
                $order->invoice->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'paid_via_transaction_id' => $tx->id,
                ]);
            }

            $tx->update([
                'status' => 'settled',
                'settled_at' => now(),
            ]);

            return true;
        }

        return false;
    }
}
