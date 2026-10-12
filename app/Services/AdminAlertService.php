<?php

namespace App\Services;

use App\Models\Order;
use App\Notifications\AdminFulfillmentOverdueNotification;
use App\Notifications\AdminNewPaidOrderNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Notifikasi internal untuk admin VexaHost: WhatsApp + email.
 *
 * Penerima diambil dari SATU sumber, Admin > Pengaturan > Notifikasi:
 *  - admin_notification_email    (cadangan: config vexahost.admin_email)
 *  - admin_notification_whatsapp (boleh beberapa nomor, dipisah koma/baris baru)
 *
 * Kegagalan satu kanal tidak menghentikan kanal lain dan tidak pernah
 * melempar exception ke pemanggil (alur pembayaran tidak boleh ikut gagal).
 */
class AdminAlertService
{
    public function __construct(private SettingsService $settings)
    {
    }

    public function adminEmail(): ?string
    {
        $email = trim((string) $this->settings->get('admin_notification_email', ''));

        if ($email === '') {
            $email = trim((string) config('vexahost.admin_email', ''));
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /**
     * @return array<int, string> Nomor WA admin yang sudah dinormalisasi & unik.
     */
    public function adminWhatsAppNumbers(): array
    {
        $raw = (string) $this->settings->get('admin_notification_whatsapp', '');

        return collect(preg_split('/[\s,;]+/', $raw) ?: [])
            ->map(fn ($phone) => WhatsAppGateway::normalize(trim((string) $phone)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Order baru lunas (perlu setup) atau perpanjangan lunas (info).
     */
    public function orderPaid(Order $order): void
    {
        $order->loadMissing(['customer', 'vpsSpec']);

        $this->sendMail(fn (string $email) => Notification::route('mail', $email)
            ->notify(new AdminNewPaidOrderNotification($order)), 'order_paid', $order->id);

        $this->sendWhatsApp($order->isRenewal()
            ? $this->renewalPaidMessage($order)
            : $this->newPaidMessage($order), 'order_paid', $order->id);
    }

    /**
     * Ringkasan order lunas yang belum diserahkan (satu pesan untuk semua order).
     *
     * @param Collection<int, Order> $orders
     */
    public function fulfillmentReminder(Collection $orders): bool
    {
        if ($orders->isEmpty()) {
            return false;
        }

        $minWaited = (int) $orders->map(fn (Order $o) => $o->paid_at ? (int) $o->paid_at->diffInMinutes(now()) : 0)->min();

        $mailOk = $this->sendMail(fn (string $email) => Notification::route('mail', $email)
            ->notify(new AdminFulfillmentOverdueNotification($orders, max(1, $minWaited))), 'fulfillment_reminder');

        $waOk = $this->sendWhatsApp($this->reminderMessage($orders), 'fulfillment_reminder');

        return $mailOk || $waOk;
    }

    /**
     * Tiket baru / balasan pelanggan (termasuk permintaan reinstall & server tak bisa diakses).
     * Email tiket tetap dikirim oleh pemanggil; di sini hanya WA.
     */
    public function ticket(\App\Models\SupportTicket $ticket, string $message, string $eventType): void
    {
        try {
            $ticket->loadMissing('customer');
            $code = '#TK-' . str_pad((string) $ticket->id, 4, '0', STR_PAD_LEFT);
            $title = $eventType === 'created' ? '🎫 *TIKET BARU*' : '💬 *BALASAN TIKET DARI PELANGGAN*';

            $this->sendWhatsApp(implode("\n", [
                $title,
                '',
                "• *Tiket:* {$code}",
                '• *Subjek:* ' . ($ticket->subject ?? '-'),
                '• *Pelanggan:* ' . ($ticket->customer?->full_name ?? '-'),
                '',
                \Illuminate\Support\Str::limit(trim($message), 500),
                '',
                'Balas di: ' . $this->url("/admin/tickets/{$ticket->id}"),
            ]), 'ticket_' . $eventType);
        } catch (\Throwable $e) {
            Log::error('admin_alert.ticket_failed', ['ticket_id' => $ticket->id, 'error' => $e->getMessage()]);
        }
    }

    public function whatsAppOnly(string $text, string $context): bool
    {
        return $this->sendWhatsApp($text, $context);
    }

    /**
     * Ringkasan bebas (teks) ke admin lewat WA + email.
     */
    public function digest(string $subject, string $text): bool
    {
        $mailOk = $this->sendMail(fn (string $email) => \Illuminate\Support\Facades\Mail::raw(
            str_replace(['*', '_'], '', $text),
            fn ($message) => $message->to($email)->subject($subject)
        ), 'digest');

        $waOk = $this->sendWhatsApp($text, 'digest');

        return $mailOk || $waOk;
    }

    /**
     * Pesan uji dari halaman pengaturan.
     *
     * @return array{email: bool, whatsapp: bool}
     */
    public function test(): array
    {
        $email = $this->sendMail(fn (string $email) => \Illuminate\Support\Facades\Mail::raw(
            "Halo Admin,\n\nIni pesan uji. Jika surel ini masuk, notifikasi order lunas & pengingat setup akan sampai ke alamat ini.\n\nTim VexaHost",
            fn ($message) => $message->to($email)->subject('Tes Notifikasi Admin VexaHost')
        ), 'test');

        $wa = $this->sendWhatsApp(
            "✅ *Tes Notifikasi Admin VexaHost*\n\nJika pesan ini masuk, notifikasi order lunas & pengingat setup VPS akan dikirim ke nomor ini.",
            'test'
        );

        return ['email' => $email, 'whatsapp' => $wa];
    }

    private function newPaidMessage(Order $order): string
    {
        return implode("\n", array_filter([
            '🔔 *ORDER BARU LUNAS — PERLU SETUP*',
            '',
            "• *Order:* #{$order->id}",
            '• *Paket:* ' . ($order->vpsSpec?->name ?? 'VPS'),
            '• *Pelanggan:* ' . ($order->customer?->full_name ?? '-') . ($order->customer?->phone ? " ({$order->customer->phone})" : ''),
            '• *Nominal:* Rp ' . number_format((float) $order->amount, 0, ',', '.'),
            '• *Metode:* ' . strtoupper((string) $order->payment_method),
            $order->hostname ? "• *Hostname:* `{$order->hostname}`" : null,
            '• *Lokasi / OS:* ' . ucfirst((string) $order->datacenter_location) . ' / ' . ($order->os ?? '-'),
            '• *Dibayar:* ' . $this->jakarta($order->paid_at),
            '',
            'Segera proses di Papan Fulfillment:',
            $this->url('/admin/fulfillment'),
            '',
            '_Pengingat otomatis dikirim sampai layanan diserahkan._',
        ], fn ($line) => $line !== null));
    }

    private function renewalPaidMessage(Order $order): string
    {
        $instance = $order->resolved_instance;
        $applied = (bool) $order->renewal_applied_at;

        // Auto-renew di Supplier dimatikan: setiap perpanjangan pelanggan WAJIB
        // diikuti perpanjangan manual di Supplier lalu dicatat di Catatan Pembelian.
        $coverage = $instance ? app(SupplierCoverageService::class)->rowFor($instance->loadMissing('supplierPurchases')) : null;
        $covered = $coverage && $coverage['status'] === SupplierCoverageService::STATUS_COVERED;

        return implode("\n", array_filter([
            $covered ? '🔁 *PERPANJANGAN LUNAS*' : '🔁 *PERPANJANGAN LUNAS — PERLU TINDAKAN*',
            '',
            "• *Order:* #{$order->id}",
            '• *Layanan:* ' . ($instance?->hostname ?? $order->hostname ?? '-'),
            '• *Pelanggan:* ' . ($order->customer?->full_name ?? '-'),
            '• *Nominal:* Rp ' . number_format((float) $order->amount, 0, ',', '.'),
            '• *Metode:* ' . strtoupper((string) $order->payment_method),
            $applied && $instance?->expires_at ? '• *Aktif (pelanggan) hingga:* ' . $this->jakarta($instance->expires_at) : null,
            $coverage ? '• *Aktif di Supplier hingga:* ' . ($coverage['supplier_expires_at'] ? $this->jakarta($coverage['supplier_expires_at']) : 'belum dicatat') : null,
            '',
            $applied ? null : '⚠️ *Masa aktif pelanggan BELUM berhasil diperpanjang otomatis. Cek manual:* ' . $this->url('/admin/orders'),
            $covered
                ? '_Masa aktif di Supplier sudah menutup periode ini. Tidak perlu tindakan._'
                : "✅ *Tugas:* perpanjang VPS ini di Supplier, lalu catat pembeliannya:\n" . $this->url('/admin/supplier-purchases'),
        ], fn ($line) => $line !== null));
    }

    /**
     * @param Collection<int, Order> $orders
     */
    private function reminderMessage(Collection $orders): string
    {
        $lines = ['⏰ *PENGINGAT: ' . $orders->count() . ' ORDER LUNAS BELUM DISETUP*', ''];

        foreach ($orders->values() as $i => $order) {
            $waitedMinutes = $order->paid_at ? (int) $order->paid_at->diffInMinutes(now()) : null;
            $waited = FulfillmentService::humanMinutes($waitedMinutes);
            $slaMinutes = Order::SETUP_SLA_HOURS * 60;
            $sla = $waitedMinutes === null ? ''
                : ($waitedMinutes > $slaMinutes
                    ? ' · ⛔ *LEWAT SLA ' . Order::SETUP_SLA_HOURS . ' JAM*'
                    : ' · sisa SLA ' . FulfillmentService::humanMinutes($slaMinutes - $waitedMinutes));

            $lines[] = ($i + 1) . ". #{$order->id} · " . ($order->vpsSpec?->name ?? 'VPS')
                . ' · ' . ($order->customer?->full_name ?? '-')
                . " · menunggu *{$waited}*{$sla}";
        }

        $lines[] = '';
        $lines[] = 'Proses sekarang: ' . $this->url('/admin/fulfillment');

        return implode("\n", $lines);
    }

    private function sendMail(callable $send, string $context, ?int $orderId = null): bool
    {
        $email = $this->adminEmail();

        if (!$email) {
            Log::warning('admin_alert.email_missing', ['context' => $context, 'order_id' => $orderId]);

            return false;
        }

        try {
            $send($email);

            return true;
        } catch (\Throwable $e) {
            Log::error('admin_alert.email_failed', ['context' => $context, 'order_id' => $orderId, 'error' => $e->getMessage()]);

            return false;
        }
    }

    private function sendWhatsApp(string $message, string $context, ?int $orderId = null): bool
    {
        $numbers = $this->adminWhatsAppNumbers();

        if ($numbers === []) {
            Log::warning('admin_alert.whatsapp_missing', ['context' => $context, 'order_id' => $orderId]);

            return false;
        }

        $sent = false;

        foreach ($numbers as $phone) {
            try {
                $sent = WhatsAppGateway::send($phone, $message) || $sent;
            } catch (\Throwable $e) {
                Log::error('admin_alert.whatsapp_failed', ['context' => $context, 'order_id' => $orderId, 'error' => $e->getMessage()]);
            }
        }

        return $sent;
    }

    private function jakarta($date): string
    {
        return $date ? $date->copy()->timezone('Asia/Jakarta')->format('d M Y H:i') . ' WIB' : '-';
    }

    private function url(string $path): string
    {
        return rtrim((string) config('app.url', 'https://vexahostcloud.my.id'), '/') . $path;
    }
}
