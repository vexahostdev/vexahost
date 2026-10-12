<?php

namespace App\Services;

use App\Models\VpsInstance;

/**
 * PHASE 3 - Penentu masa tenggang per jenis produk.
 *
 * Angka tenggang tidak boleh tunggal. Supplier menghapus VPS pada hari ke-7,
 * sedangkan database dan aplikasi pada hari ke-30. Tenggang yang kita janjikan
 * ke pelanggan selalu lebih pendek, agar tersisa ruang untuk bertindak
 * sebelum penghapusan permanen di sisi supplier terjadi.
 */
class RenewalService
{
    /**
     * Batas penghapusan di sisi Supplier, dalam hari.
     * Sumber: Terms of Service Supplier pasal 11.
     */
    public const SUPPLIER_LIMITS = [
        'vps' => 7,
        'database' => 30,
        'app' => 30,
    ];

    public function __construct(
        protected SettingsService $settings
    ) {
    }

    /**
     * Tentukan jenis produk sebuah instance.
     */
    public function productType(VpsInstance $instance): string
    {
        if ($instance->isDatabasePackage()) {
            return 'database';
        }

        if ($instance->isAiPackage()) {
            return 'app';
        }

        return 'vps';
    }

    /**
     * Jenis produk dari sebuah order, dipakai sebelum instance dibuat.
     */
    public function productTypeForOrder(\App\Models\Order $order): string
    {
        if ($order->isDatabasePackage()) {
            return 'database';
        }

        if ($order->isAiPackage()) {
            return 'app';
        }

        return 'vps';
    }

    /**
     * Tenggang untuk order yang sedang diprovision. Menggantikan angka 7 hari
     * yang dulu tertulis langsung di alur provisioning: 7 hari justru batas
     * penghapusan VPS di Supplier, sehingga tidak menyisakan ruang aman.
     */
    public function graceDaysForOrder(\App\Models\Order $order): int
    {
        return $this->graceDaysForType($this->productTypeForOrder($order));
    }

    /**
     * Jumlah hari tenggang yang dijanjikan ke pelanggan untuk instance ini.
     */
    public function graceDaysFor(VpsInstance $instance): int
    {
        return $this->graceDaysForType($this->productType($instance));
    }

    public function graceDaysForType(string $type): int
    {
        $configured = match ($type) {
            'database' => $this->settings->int('renewal_grace_days_database', 25),
            'app' => $this->settings->int('renewal_grace_days_app', 25),
            default => $this->settings->int('renewal_grace_days_vps', 5),
        };

        $limit = self::SUPPLIER_LIMITS[$type] ?? 7;

        // Pengaman keras: tenggang kita tidak boleh menyentuh batas supplier.
        // Bila pengaturan terlanjur diisi terlalu besar, dipangkas di sini.
        return max(1, min($configured, $limit - 1));
    }

    /**
     * Ruang aman yang tersisa sebelum supplier menghapus data, dalam hari.
     */
    public function safetyMarginFor(VpsInstance $instance): int
    {
        $type = $this->productType($instance);

        return (self::SUPPLIER_LIMITS[$type] ?? 7) - $this->graceDaysForType($type);
    }

    /**
     * Hitung dan simpan tanggal akhir tenggang berdasarkan expires_at.
     */
    public function applyGracePeriod(VpsInstance $instance): VpsInstance
    {
        if (!$instance->expires_at) {
            return $instance;
        }

        $instance->grace_period_ends_at = $instance->expires_at
            ->copy()
            ->addDays($this->graceDaysFor($instance));

        $instance->save();

        return $instance;
    }

    /**
     * Label jenis produk untuk ditampilkan di antarmuka dan surel.
     */
    public function typeLabel(string $type): string
    {
        return match ($type) {
            'database' => 'Database Terkelola',
            'app' => 'Aplikasi & AI',
            default => 'VPS',
        };
    }

    /**
     * Proses perpanjangan instance VPS saat order bertipe renewal dibayar lunas.
     *
     * 1. Menghitung tanggal kedaluwarsa baru dari max(now(), expires_at)
     *    sehingga sisa hari aktif pelanggan tidak pernah hangus.
     * 2. Menghitung ulang masa tenggang via applyGracePeriod.
     * 3. Mengembalikan status instance dari suspended kembali ke running jika sebelumnya disuspend.
     * 4. Menandai stage order menjadi delivered dan beralih ke active.
     * 5. Mencatat aktivitas perpanjangan ke VpsActivityLog.
     */
    public function handleRenewalPayment(\App\Models\Order $order): ?VpsInstance
    {
        // Idempoten: dipanggil dari banyak jalur (webhook, cek status halaman,
        // verifikasi admin, PaymentCompletionService). Masa aktif hanya boleh
        // ditambah SEKALI per order, jadi baris order dikunci lalu ditandai.
        return \Illuminate\Support\Facades\DB::transaction(function () use ($order) {
            $locked = \App\Models\Order::whereKey($order->id)->lockForUpdate()->first();

            if (!$locked) {
                return null;
            }

            if ($locked->renewal_applied_at) {
                $order->setRawAttributes($locked->getAttributes(), true);

                return $locked->resolved_instance
                    ?? ($locked->vps_instance_id ? VpsInstance::find($locked->vps_instance_id) : null);
            }

            $instance = $this->applyRenewal($locked);

            if ($instance) {
                $locked->forceFill(['renewal_applied_at' => now()])->save();
            }

            $order->setRawAttributes($locked->fresh()->getAttributes(), true);

            return $instance;
        });
    }

    private function applyRenewal(\App\Models\Order $order): ?VpsInstance
    {
        $instance =$order->resolved_instance ?? ($order->vps_instance_id ? VpsInstance::find($order->vps_instance_id) : $order->vpsInstance);
        if (!$instance) {
            \Illuminate\Support\Facades\Log::warning('Renewal order paid but no vps_instance found', [
                'order_id' => $order->id,
                'vps_instance_id' => $order->vps_instance_id,
            ]);
            return null;
        }

        // Patokan = waktu pelunasan (bukan waktu diproses), agar perpanjangan yang
        // diterapkan terlambat tidak menggeser jatuh tempo. Sisa masa aktif tetap utuh.
        $paidAt = $order->activationStartsAt();
        $baseDate = ($instance->expires_at && $instance->expires_at->greaterThan($paidAt))
            ? $instance->expires_at->copy()
            : $paidAt;

        $cycle = $order->billing_cycle ?? $instance->billing_cycle ?? 'monthly';
        $months = match ($cycle) {
            'quarterly' => 3,
            'semi_annual', 'semi_annually' => 6,
            'annual', 'annually', 'yearly' => 12,
            default => 1,
        };

        $newExpiresAt = $baseDate->addMonths($months);
        $instance->expires_at = $newExpiresAt;

        // Jika VPS sebelumnya suspended karena terlambat bayar, aktifkan kembali
        if ($instance->status === 'suspended') {
            $instance->status = 'running';
        }

        $this->applyGracePeriod($instance);
        $instance->save();

        // Tandai order fulfillment delivered
        $order->fulfillment_stage = 'delivered';
        $order->delivered_at = now();
        $order->save();

        // Transisi status order ke active via OrderStateMachine
        try {
            $stateMachine = app(OrderStateMachine::class);
            if ($order->status !== 'active') {
                $stateMachine->transition($order, 'active', [
                    'reason' => 'Perpanjangan layanan berhasil diproses dan masa aktif diperbarui.',
                    'actor_type' => 'system',
                    'allowSame' => true,
                ]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('Order transition to active notice: ' . $e->getMessage());
        }

        // Catat di log aktivitas instance
        $formattedDate = $newExpiresAt->timezone('Asia/Jakarta')->format('d M Y, H:i');
        $instance->logActivity(
            'renewal',
            "Layanan diperpanjang hingga {$formattedDate} WIB melalui Order #{$order->id} ({$order->payment_method_name}).",
            'completed',
            $order->customer_id
        );

        return $instance;
    }
}
