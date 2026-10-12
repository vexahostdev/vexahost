<?php

namespace App\Services;

use App\Models\FulfillmentChecklist;
use App\Models\Order;
use Illuminate\Support\Collection;

/**
 * PHASE 5 - Aturan papan kerja fulfillment.
 *
 * Kolom papan diturunkan dari orders.status (tidak diubah di sini) ditambah
 * orders.fulfillment_stage. Service ini TIDAK memindahkan status order;
 * perpindahan status tetap lewat OrderStateMachine di alur yang sudah ada
 * (tandai lunas, provisioning, batal).
 */
class FulfillmentService
{
    public const COLUMN_AWAITING_PAYMENT = 'awaiting_payment';
    public const COLUMN_READY = 'ready';
    public const COLUMN_PURCHASED = 'purchased';
    public const COLUMN_SETUP = 'setup';
    public const COLUMN_DELIVERED = 'delivered';

    public const STAGES = ['purchased', 'setup', 'delivered'];

    public function __construct(
        protected SettingsService $settings
    ) {
    }

    public static function columns(): array
    {
        return [
            self::COLUMN_AWAITING_PAYMENT => 'Menunggu Pembayaran',
            self::COLUMN_READY => 'Siap Diproses',
            self::COLUMN_PURCHASED => 'Dibeli di Supplier',
            self::COLUMN_SETUP => 'Sedang Setup',
            self::COLUMN_DELIVERED => 'Terkirim',
        ];
    }

    /**
     * Tentukan kolom sebuah order.
     */
    public function columnFor(Order $order): ?string
    {
        return match (true) {
            $order->status === 'pending' => self::COLUMN_AWAITING_PAYMENT,
            in_array($order->status, ['paid', 'failed', 'provisioning'], true) => match ($order->fulfillment_stage) {
                'purchased' => self::COLUMN_PURCHASED,
                'setup' => self::COLUMN_SETUP,
                default => self::COLUMN_READY,
            },
            $order->status === 'active' => self::COLUMN_DELIVERED,
            default => null,
        };
    }

    /**
     * Langkah setup per jenis paket.
     */
    public function stepsFor(Order $order): array
    {
        if ($order->isDatabasePackage()) {
            $steps = [
                'db_install_engine' => 'Pasang mesin database sesuai pesanan',
                'db_create_schema' => 'Buat database dan pengguna aplikasi',
                'db_network' => 'Atur port, firewall, dan akses jarak jauh',
            ];

            if ($order->db_manager !== 'cli_only') {
                $steps['db_gui'] = 'Pasang CloudBeaver dan amankan aksesnya';
            }

            $steps['db_test'] = 'Uji koneksi dari luar dengan kredensial pelanggan';

            return $steps;
        }

        if ($order->isAiPackage()) {
            return [
                'ai_base' => 'Perbarui OS dan pasang dependensi dasar',
                'ai_stack' => 'Pasang stack ' . $order->control_panel_label,
                'ai_url' => 'Atur domain atau URL aplikasi beserta HTTPS',
                'ai_verify' => 'Pastikan aplikasi berjalan dan dapat diakses',
                'ai_guide' => 'Siapkan panduan awal untuk pelanggan',
            ];
        }

        $steps = [
            'vps_hostname' => 'Setel hostname dan kata sandi root sesuai pesanan',
            'vps_update' => 'Perbarui paket sistem operasi',
            'vps_firewall' => 'Aktifkan firewall dasar',
        ];

        if ($order->control_panel && $order->control_panel !== 'none') {
            $steps['vps_panel'] = 'Pasang ' . $order->control_panel_label;
        }

        $steps['vps_ssh'] = 'Uji akses SSH dengan kredensial pelanggan';

        return $steps;
    }

    /**
     * Butir daftar periksa beserta statusnya, dalam urutan langkah.
     *
     * @return Collection<int, array{key:string,label:string,done:bool,completed_at:mixed}>
     */
    public function checklistFor(Order $order): Collection
    {
        $saved = $order->relationLoaded('fulfillmentChecklists')
            ? $order->fulfillmentChecklists->keyBy('step_key')
            : $order->fulfillmentChecklists()->get()->keyBy('step_key');

        return collect($this->stepsFor($order))->map(function (string $label, string $key) use ($saved) {
            /** @var FulfillmentChecklist|null $row */
            $row = $saved->get($key);

            return [
                'key' => $key,
                'label' => $label,
                'done' => (bool) ($row?->is_done),
                'completed_at' => $row?->completed_at,
            ];
        })->values();
    }

    /**
     * Persentase kemajuan daftar periksa, 0-100.
     */
    public function progressFor(Order $order): int
    {
        $items = $this->checklistFor($order);

        if ($items->isEmpty()) {
            return 100;
        }

        return (int) round($items->where('done', true)->count() / $items->count() * 100);
    }

    /**
     * Menit sejak pembayaran diterima. Null bila belum dibayar.
     */
    public function minutesSincePaid(Order $order): ?int
    {
        if (!$order->paid_at) {
            return null;
        }

        return (int) $order->paid_at->diffInMinutes($order->delivered_at ?? now());
    }

    public function slaMinutes(): int
    {
        return max(5, $this->settings->int('fulfillment_sla_minutes', 120));
    }

    /**
     * Order yang dibayar tetapi belum diserahkan melewati ambang waktu tanggap.
     */
    public function isOverdue(Order $order): bool
    {
        if (!in_array($order->status, ['paid', 'failed', 'provisioning'], true)) {
            return false;
        }

        $minutes = $this->minutesSincePaid($order);

        return $minutes !== null && $minutes > $this->slaMinutes();
    }

    /**
     * Teks serah terima siap salin, dari template fulfillment_handover.
     * Null bila order belum punya instance atau template tidak aktif.
     */
    public function handoverText(Order $order, ?\App\Models\NotificationTemplate $template): ?string
    {
        $instance = $order->vpsInstance;

        if (!$instance || !$template || !$template->is_active) {
            return null;
        }

        return $template->renderBody([
            'nama' => $order->customer?->full_name ?: 'Pelanggan',
            'layanan' => $instance->hostname ?: ('Order #' . $order->id),
            'ip' => $instance->public_ip ?: '-',
            'port_ssh' => (string) ($instance->ssh_port ?: 22),
            'pengguna' => $instance->ssh_user,
            'os' => $order->os_label ?: ($instance->os ?: '-'),
            'url_aplikasi' => $instance->app_url ? 'URL aplikasi: ' . $instance->app_url : '',
            'dasbor' => rtrim((string) config('app.url'), '/') . '/dashboard',
            'masa_aktif' => $instance->expires_at?->timezone('Asia/Jakarta')->format('d M Y') ?? '-',
        ]);
    }

    /**
     * Teks durasi yang mudah dibaca, mis. "2 jam 15 menit".
     */
    public static function humanMinutes(?int $minutes): string
    {
        if ($minutes === null) {
            return '—';
        }

        if ($minutes < 60) {
            return $minutes . ' menit';
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        if ($hours >= 24) {
            $days = intdiv($hours, 24);
            $hours %= 24;

            return $days . ' hari' . ($hours ? ' ' . $hours . ' jam' : '');
        }

        return $hours . ' jam' . ($rest ? ' ' . $rest . ' menit' : '');
    }
}
