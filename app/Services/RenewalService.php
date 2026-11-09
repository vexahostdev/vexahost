<?php

namespace App\Services;

use App\Models\VpsInstance;

/**
 * PHASE 3 - Penentu masa tenggang per jenis produk.
 *
 * Angka tenggang tidak boleh tunggal. Supplier menghentikan VPS pada H+3,
 * sedangkan database dan aplikasi pada hari ke-30. Tenggang yang kita janjikan
 * ke pelanggan selalu lebih pendek, agar tersisa ruang untuk bertindak
 * sebelum penghapusan permanen di sisi supplier terjadi.
 *
 * Batas akhir tenggang bersifat mutlak: begitu terlewati, perpanjangan dan
 * pembayarannya tertutup (lihat isRenewalClosed).
 */
class RenewalService
{
    /**
     * Batas penghapusan di sisi Supplier, dalam hari.
     * VPS: pemberitahuan Supplier (November 2026), dihentikan/dihapus pada H+3.
     * Database dan aplikasi: Terms of Service Supplier pasal 11.
     */
    public const SUPPLIER_LIMITS = [
        'vps' => 3,
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
     * Tenggang untuk order yang sedang diprovision, mengikuti batas per produk
     * agar selalu menyisakan ruang aman sebelum penghapusan di Supplier.
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
            default => $this->settings->int('renewal_grace_days_vps', 2),
        };

        $limit = self::SUPPLIER_LIMITS[$type] ?? self::SUPPLIER_LIMITS['vps'];

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

        return (self::SUPPLIER_LIMITS[$type] ?? self::SUPPLIER_LIMITS['vps']) - $this->graceDaysForType($type);
    }

    /**
     * Batas akhir perpanjangan. Dihitung langsung dari masa aktif dan aturan
     * terbaru, bukan dari kolom tersimpan yang bisa tertinggal aturan lama.
     */
    public function renewalDeadlineFor(VpsInstance $instance): ?\Illuminate\Support\Carbon
    {
        return $instance->expires_at?->copy()->addDays($this->graceDaysFor($instance));
    }

    /**
     * Apakah perpanjangan sudah tertutup untuk pembayaran baru, saat ini.
     */
    public function isRenewalClosed(VpsInstance $instance): bool
    {
        if ($instance->status === 'terminated') {
            return true;
        }

        $deadline = $this->renewalDeadlineFor($instance);

        return $deadline !== null && now()->greaterThan($deadline);
    }

    /**
     * Apakah pembayaran pada waktu tersebut terlambat.
     *
     * Berbeda dari isRenewalClosed: layanan yang diterminasi karena tagihan masih
     * bisa diselamatkan bila bukti bayarnya sebelum batas (misal admin baru
     * memverifikasi transfer setelah terminasi). Terminasi karena sebab lain
     * tidak pernah bisa dibatalkan oleh pembayaran.
     */
    public function isPaymentTooLate(VpsInstance $instance, \Carbon\CarbonInterface $paidAt): bool
    {
        if ($instance->status === 'terminated' && !$this->wasTerminatedForNonPayment($instance)) {
            return true;
        }

        $deadline = $this->renewalDeadlineFor($instance);

        return $deadline !== null && $paidAt->greaterThan($deadline);
    }

    private function wasTerminatedForNonPayment(VpsInstance $instance): bool
    {
        return \App\Models\RenewalReminder::where('vps_instance_id', $instance->id)
            ->where('stage', \App\Models\RenewalReminder::STAGE_GRACE_ENDED)
            ->where('period_expires_at', $instance->expires_at)
            ->exists();
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

            if ($locked->renewal_rejected_at) {
                $order->setRawAttributes($locked->getAttributes(), true);

                return null;
            }

            // Patokan waktu bayar, bukan waktu diproses: pelanggan yang membayar
            // tepat waktu tidak boleh ditolak hanya karena webhook datang terlambat.
            $target = $locked->resolved_instance
                ?? ($locked->vps_instance_id ? VpsInstance::find($locked->vps_instance_id) : null);

            if ($target && $this->isPaymentTooLate($target, $locked->paymentMadeAt())) {
                $this->rejectLateRenewal($locked, $target);
                $order->setRawAttributes($locked->fresh()->getAttributes(), true);

                return null;
            }

            $instance = $this->applyRenewal($locked);

            if ($instance) {
                $locked->forceFill(['renewal_applied_at' => now()])->save();
            }

            $order->setRawAttributes($locked->fresh()->getAttributes(), true);

            return $instance;
        });
    }

    /**
     * Pembayaran masuk setelah batas akhir: masa aktif tidak ditambah, order
     * dibatalkan, dan invoice tetap lunas agar admin bisa memprosesnya sebagai
     * refund transfer manual di Billing Center.
     *
     * paid_notified_at ikut diisi supaya pelanggan tidak menerima surel
     * "pembayaran diterima" yang menyesatkan.
     */
    private function rejectLateRenewal(\App\Models\Order $order, VpsInstance $instance): void
    {
        $order->forceFill([
            'renewal_rejected_at' => now(),
            'paid_notified_at' => $order->paid_notified_at ?? now(),
        ])->save();

        $stateMachine = app(OrderStateMachine::class);

        if ($stateMachine->canTransition($order->status, 'cancelled')) {
            $stateMachine->transition($order, 'cancelled', [
                'reason' => 'Pembayaran perpanjangan masuk setelah batas akhir. Dana perlu dikembalikan.',
                'actor_type' => 'system',
            ]);
        }

        $orderId = $order->id;
        $instanceId = $instance->id;

        \Illuminate\Support\Facades\DB::afterCommit(function () use ($orderId, $instanceId) {
            $this->notifyLateRenewal($orderId, $instanceId);
        });
    }

    private function notifyLateRenewal(int $orderId, int $instanceId): void
    {
        $order = \App\Models\Order::with('customer')->find($orderId);
        $instance = VpsInstance::find($instanceId);

        if (!$order || !$instance) {
            return;
        }

        $deadline = $this->renewalDeadlineFor($instance);

        try {
            $order->customer?->notify(new \App\Notifications\LateRenewalPaymentNotification($order, $instance, $deadline));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('renewal.late_payment_notify_failed', ['order_id' => $orderId, 'error' => $e->getMessage()]);
        }

        try {
            app(AdminAlertService::class)->lateRenewalPayment($order, $instance, $deadline);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('renewal.late_payment_alert_failed', ['order_id' => $orderId, 'error' => $e->getMessage()]);
        }
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

        // Aktifkan kembali. Status terminated hanya sampai di sini bila terminasinya
        // karena tagihan dan bukti bayarnya sebelum batas (lihat isPaymentTooLate).
        if (in_array($instance->status, ['suspended', 'terminated'], true)) {
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
