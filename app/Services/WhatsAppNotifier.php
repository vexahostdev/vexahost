<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\VpsInstance;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;

/**
 * Pembuat & Pengirim Pesan WhatsApp untuk seluruh kejadian pengguna di VexaHost.
 *
 * Seluruh format dan nada pesan diselaraskan dengan template email resmi VexaHost,
 * dengan format WhatsApp (bold *...*, monospace `...`, pemisah baris rapi).
 *
 * Pengiriman bersifat satu arah dari Admin/Platform ke Pengguna.
 */
class WhatsAppNotifier
{
    /**
     * Tagihan baru diterbitkan — menyertakan lampiran berkas PDF invoice.
     */
    public static function invoiceCreated(Invoice $invoice): bool
    {
        $invoice->loadMissing(['order.customer', 'order.vpsSpec']);
        $user = $invoice->order?->customer;

        if (! $user || ! $user->phone) {
            return false;
        }

        $appUrl = rtrim(config('app.url', 'https://vexahostcloud.my.id'), '/');
        $namaPelanggan = $user->full_name ?? 'Pelanggan VexaHost';
        $nominal = number_format((float) $invoice->amount, 0, ',', '.');
        $layanan = $invoice->order?->vpsSpec?->name ?? 'Cloud VPS';
        $jatuhTempo = $invoice->due_at?->timezone('Asia/Jakarta')->translatedFormat('d F Y') ?? '-';
        $bayarUrl = "{$appUrl}/order/payment/{$invoice->order_id}";

        $caption = implode("\n", [
            "Halo *{$namaPelanggan}*,",
            '',
            '📄 *TAGIHAN BARU DITERBITKAN*',
            'Invoice baru untuk layanan Anda telah diterbitkan dan menunggu pembayaran.',
            '',
            "• *Nomor Invoice:* `{$invoice->invoice_number}`",
            "• *Layanan:* {$layanan}",
            "• *Total Tagihan:* Rp {$nominal}",
            "• *Jatuh Tempo:* {$jatuhTempo}",
            '',
            'Silakan lakukan pembayaran sebelum tanggal jatuh tempo melalui tautan berikut:',
            $bayarUrl,
            '',
            'Berkas PDF invoice resmi terlampir pada pesan ini.',
            '_VexaHost Cloud Solutions_',
        ]);

        $pdfContent = self::renderInvoicePdf($invoice);

        if ($pdfContent) {
            return WhatsAppGateway::sendMedia(
                phone: $user->phone,
                fileContent: $pdfContent,
                filename: "Invoice-{$invoice->invoice_number}.pdf",
                type: 'document',
                caption: $caption
            );
        }

        return WhatsAppGateway::send($user->phone, $caption);
    }

    /**
     * Pembayaran berhasil diterima — menyertakan lampiran berkas PDF bukti transaksi lunas.
     */
    public static function paymentReceived(Order $order, ?Invoice $invoice = null): bool
    {
        $order->loadMissing(['customer', 'vpsSpec', 'invoice']);
        $user = $order->customer;

        if (! $user || ! $user->phone) {
            return false;
        }

        $inv = $invoice ?? $order->invoice;
        $invNumber = $inv?->invoice_number ?? ('INV-' . $order->id);
        $appUrl = rtrim(config('app.url', 'https://vexahostcloud.my.id'), '/');
        $namaPelanggan = $user->full_name ?? 'Pelanggan VexaHost';
        $nominal = number_format((float) $order->amount, 0, ',', '.');
        $layanan = $order->vpsSpec?->name ?? 'Cloud VPS';
        $metode = strtoupper(str_replace('_', ' ', $order->payment_method ?? 'Payment Gateway'));
        $dashboardUrl = "{$appUrl}/dashboard?payment_success=1&order_id={$order->id}";

        $caption = implode("\n", [
            "Halo *{$namaPelanggan}*,",
            '',
            '✅ *PEMBAYARAN DITERIMA & LUNAS*',
            'Pembayaran untuk pesanan Anda telah berhasil diverifikasi oleh sistem kami.',
            '',
            "• *Nomor Invoice:* `{$invNumber}`",
            "• *Paket Layanan:* {$layanan}",
            "• *Total Dibayar:* Rp {$nominal}",
            "• *Metode:* {$metode}",
            "• *Waktu:* " . now()->timezone('Asia/Jakarta')->translatedFormat('d F Y, H:i') . ' WIB',
            '',
            '⚡ *Status Layanan:* Sistem kami sedang mempersiapkan dan mengonfigurasi server Anda. Kredensial server (IP, Port, dan Password) akan segera kami kirimkan ke WhatsApp & Email Anda.',
            '',
            'Pantau status pesanan Anda di dashboard:',
            $dashboardUrl,
            '',
            'Bukti tanda terima pembayaran (PDF) terlampir.',
            '_Terima kasih atas kepercayaan Anda menggunakan VexaHost._',
        ]);

        if ($inv) {
            $pdfContent = self::renderInvoicePdf($inv);
            if ($pdfContent) {
                return WhatsAppGateway::sendMedia(
                    phone: $user->phone,
                    fileContent: $pdfContent,
                    filename: "Invoice-{$invNumber}.pdf",
                    type: 'document',
                    caption: $caption
                );
            }
        }

        return WhatsAppGateway::send($user->phone, $caption);
    }

    /**
     * Verifikasi pembayaran ditolak.
     */
    public static function paymentRejected(Order $order, string $reason): bool
    {
        $user = $order->customer;

        if (! $user || ! $user->phone) {
            return false;
        }

        $appUrl = rtrim(config('app.url', 'https://vexahostcloud.my.id'), '/');
        $namaPelanggan = $user->full_name ?? 'Pelanggan VexaHost';
        $bayarUrl = "{$appUrl}/order/payment/{$order->id}";

        $message = implode("\n", [
            "Halo *{$namaPelanggan}*,",
            '',
            '❌ *PEMBAYARAN DIBATALKAN / KADALUARSA*',
            "Transaksi pembayaran untuk pesanan #{$order->id} belum terkonfirmasi oleh gateway atau telah dibatalkan.",
            '',
            "• *Alasan:* {$reason}",
            '',
            'Silakan periksa detail pesanan Anda melalui tautan berikut:',
            $bayarUrl,
            '',
            'Jika Anda memerlukan bantuan, silakan hubungi tim support VexaHost.',
        ]);

        return WhatsAppGateway::send($user->phone, $message);
    }

    /**
     * Server VPS berhasil aktif (kredensial login, SSH, dan IP).
     */
    public static function vpsProvisioned(VpsInstance $instance): bool
    {
        $instance->loadMissing(['customer', 'order.vpsSpec']);
        $user = $instance->customer;

        if (! $user || ! $user->phone) {
            return false;
        }

        $appUrl = rtrim(config('app.url', 'https://vexahostcloud.my.id'), '/');
        $namaPelanggan = $user->full_name ?? 'Pelanggan VexaHost';
        $paket = $instance->order?->vpsSpec?->name ?? 'VPS Instance';
        $ip = $instance->public_ip ?? 'Pending';
        $port = $instance->ssh_port ?? 22;
        $sshUser = $instance->ssh_user;
        $os = strtoupper($instance->os ?? 'Ubuntu');
        $password = $instance->initial_root_password ? "`{$instance->initial_root_password}`" : '(Tersimpan di dashboard)';
        $dashboardUrl = "{$appUrl}/dashboard/vps/{$instance->id}";

        $message = implode("\n", [
            "Halo *{$namaPelanggan}*,",
            '',
            '🚀 *SERVER VPS ANDA TELAH AKTIF*',
            'Layanan Virtual Private Server (VPS) Anda telah selesai dikonfigurasi dan berstatus *Online (Running)*.',
            '',
            '📋 *Detail Kredensial Server:*',
            "• *Paket:* {$paket}",
            "• *Hostname:* `{$instance->hostname}`",
            "• *IP Public:* `{$ip}`",
            "• *Port SSH:* `{$port}`",
            "• *Username:* `{$sshUser}`",
            "• *Password:* {$password}",
            "• *Sistem Operasi:* {$os}",
            '',
            '💻 *Akses Cepat Terminal (SSH):*',
            "`ssh {$sshUser}@{$ip} -p {$port}`",
            '',
            '⚠️ _Demi keamanan, segera ganti password default Anda setelah berhasil masuk pertama kali._',
            '',
            'Kelola server Anda di Dashboard:',
            $dashboardUrl,
            '',
            '_VexaHost Cloud Solutions_',
        ]);

        return WhatsAppGateway::send($user->phone, $message);
    }

    /**
     * Tanggapan tiket bantuan dari tim support.
     */
    public static function ticketReplied(SupportTicket $ticket, string $replyMessage): bool
    {
        $ticket->loadMissing('customer');
        $user = $ticket->customer;

        if (! $user || ! $user->phone) {
            return false;
        }

        $appUrl = rtrim(config('app.url', 'https://vexahostcloud.my.id'), '/');
        $namaPelanggan = $user->full_name ?? 'Pelanggan VexaHost';
        $ticketUrl = "{$appUrl}/dashboard/support/{$ticket->id}";

        $excerpt = mb_strlen($replyMessage) > 300
            ? mb_substr($replyMessage, 0, 300) . '...'
            : $replyMessage;

        $message = implode("\n", [
            "Halo *{$namaPelanggan}*,",
            '',
            "💬 *BALASAN TIKET BANTUAN #{$ticket->id}*",
            "Tim teknis VexaHost telah membalas tiket Anda mengenai: *\"{$ticket->subject}\"*",
            '',
            '📝 *Pesan Balasan:*',
            "\"{$excerpt}\"",
            '',
            'Anda dapat melihat percakapan lengkap atau membalas tiket di Dashboard:',
            $ticketUrl,
        ]);

        return WhatsAppGateway::send($user->phone, $message);
    }

    /**
     * Pengingat jatuh tempo tagihan langganan.
     */
    public static function billingReminder(Subscription $subscription, int $daysUntil): bool
    {
        $subscription->loadMissing(['customer', 'vpsSpec']);
        $user = $subscription->customer;

        if (! $user || ! $user->phone) {
            return false;
        }

        $appUrl = rtrim(config('app.url', 'https://vexahostcloud.my.id'), '/');
        $namaPelanggan = $user->full_name ?? 'Pelanggan VexaHost';
        $layanan = $subscription->vpsSpec?->name ?? 'Cloud VPS';
        $nominal = number_format((float) $subscription->unit_amount, 0, ',', '.');
        $batasWaktu = $subscription->next_billing_at?->timezone('Asia/Jakarta')->translatedFormat('d F Y') ?? '-';
        $billingUrl = "{$appUrl}/dashboard/billing";

        $statusWaktu = $daysUntil === 0
            ? '*Jatuh Tempo Hari Ini!*'
            : "Akan jatuh tempo dalam *{$daysUntil} hari lagi*.";

        $message = implode("\n", [
            "Halo *{$namaPelanggan}*,",
            '',
            '⏰ *PENGINGAT JATUH TEMPO TAGIHAN*',
            "Tagihan perpanjangan layanan {$statusWaktu}",
            '',
            "• *Layanan:* {$layanan}",
            "• *Nominal:* Rp {$nominal}",
            "• *Batas Waktu:* {$batasWaktu}",
            '',
            'Untuk menghindari penghentian layanan otomatis, silakan selesaikan pembayaran di:',
            $billingUrl,
        ]);

        return WhatsAppGateway::send($user->phone, $message);
    }

    /**
     * Pengingat perpanjangan instans VPS.
     */
    public static function instanceRenewalReminder(VpsInstance $instance, int $daysLeft): bool
    {
        $instance->loadMissing(['customer', 'order.vpsSpec']);
        $user = $instance->customer;

        if (! $user || ! $user->phone) {
            return false;
        }

        $appUrl = rtrim(config('app.url', 'https://vexahostcloud.my.id'), '/');
        $namaPelanggan = $user->full_name ?? 'Pelanggan VexaHost';
        $layanan = $instance->order?->vpsSpec?->name ?? 'Cloud VPS';
        $billingUrl = "{$appUrl}/dashboard/billing";

        $message = implode("\n", [
            "Halo *{$namaPelanggan}*,",
            '',
            '⚠️ *PENGINGAT MASA AKTIF SERVER VPS*',
            "Masa aktif server *{$instance->hostname}* ({$layanan}) akan berakhir dalam *{$daysLeft} hari*.",
            '',
            "• *IP Public:* `{$instance->public_ip}`",
            "• *Sisa Waktu:* {$daysLeft} hari",
            '',
            'Perpanjang masa aktif sekarang agar server tidak ditangguhkan:',
            $billingUrl,
        ]);

        return WhatsAppGateway::send($user->phone, $message);
    }

    /**
     * Pemberitahuan layanan ditangguhkan (suspended).
     */
    public static function serviceSuspended(Subscription $subscription): bool
    {
        $subscription->loadMissing(['customer', 'vpsSpec']);
        $user = $subscription->customer;

        if (! $user || ! $user->phone) {
            return false;
        }

        $appUrl = rtrim(config('app.url', 'https://vexahostcloud.my.id'), '/');
        $namaPelanggan = $user->full_name ?? 'Pelanggan VexaHost';
        $layanan = $subscription->vpsSpec?->name ?? 'Cloud VPS';
        $nominal = number_format((float) $subscription->unit_amount, 0, ',', '.');
        $billingUrl = "{$appUrl}/dashboard/billing";

        $message = implode("\n", [
            "Halo *{$namaPelanggan}*,",
            '',
            '🛑 *PEMBERITAHUAN PENANGGUHAN LAYANAN*',
            'Layanan server VPS Anda telah ditangguhkan sementara karena tagihan telah melewati tanggal jatuh tempo.',
            '',
            "• *Layanan:* {$layanan}",
            "• *Tagihan Tertunggak:* Rp {$nominal}",
            "• *Status Server:* Suspended (Dihentikan Sementara)",
            '',
            'ℹ️ _Seluruh data server Anda masih tersimpan aman. Untuk mengaktifkan kembali server Anda secara instan, silakan lunasi tagihan di:_',
            $billingUrl,
        ]);

        return WhatsAppGateway::send($user->phone, $message);
    }

    /**
     * Pemberitahuan layanan dihentikan permanen (terminated).
     */
    public static function serviceTerminated(Subscription $subscription): bool
    {
        $subscription->loadMissing(['customer', 'vpsSpec']);
        $user = $subscription->customer;

        if (! $user || ! $user->phone) {
            return false;
        }

        $appUrl = rtrim(config('app.url', 'https://vexahostcloud.my.id'), '/');
        $namaPelanggan = $user->full_name ?? 'Pelanggan VexaHost';
        $layanan = $subscription->vpsSpec?->name ?? 'Cloud VPS';

        $message = implode("\n", [
            "Halo *{$namaPelanggan}*,",
            '',
            '⚠️ *PEMBERITAHUAN TERMINASI LAYANAN*',
            "Layanan server *{$layanan}* Anda telah dihentikan secara permanen karena melewati masa tenggang pembayaran.",
            '',
            'Jika Anda ingin memesan layanan baru, Anda dapat mengunjungi:',
            "{$appUrl}/#pricing",
        ]);

        return WhatsAppGateway::send($user->phone, $message);
    }

    /**
     * Jadwal pemeliharaan server (maintenance).
     */
    public static function maintenanceScheduled(
        User $user,
        string $title,
        string $description,
        ?string $startsAt = null
    ): bool {
        if (! $user->phone) {
            return false;
        }

        $namaPelanggan = $user->full_name ?? 'Pelanggan VexaHost';
        $waktu = $startsAt ? "• *Jadwal:* {$startsAt}\n" : '';

        $message = implode("\n", [
            "Halo *{$namaPelanggan}*,",
            '',
            '🔧 *PEMBERITAHUAN PEMELIHARAAN SISTEM*',
            "*{$title}*",
            '',
            $waktu,
            $description,
            '',
            '_Kami berusaha menyelesaikan pemeliharaan secepat mungkin untuk meminimalkan gangguan pada layanan Anda._',
        ]);

        return WhatsAppGateway::send($user->phone, $message);
    }

    /**
     * Kredensial selamat datang akun baru.
     */
    public static function welcomeCredentials(User $user, ?string $initialPassword = null): bool
    {
        if (! $user->phone) {
            return false;
        }

        $appUrl = rtrim(config('app.url', 'https://vexahostcloud.my.id'), '/');
        $namaPelanggan = $user->full_name ?? 'Pelanggan VexaHost';

        $passwordLine = $initialPassword
            ? "• *Password:* `{$initialPassword}`\n"
            : '';

        $message = implode("\n", [
            "Halo *{$namaPelanggan}*,",
            '',
            '👋 *SELAMAT DATANG DI VEXAHOST!*',
            'Akun pelanggan Anda telah berhasil dibuat. Berikut adalah informasi akun Anda:',
            '',
            "• *Username:* `{$user->username}`",
            "• *Email:* `{$user->email}`",
            $passwordLine,
            'Silakan login ke dashboard untuk mengelola layanan Anda:',
            "{$appUrl}/login",
            '',
            'Jika ada pertanyaan, tim dukungan VexaHost siap membantu Anda.',
        ]);

        return WhatsAppGateway::send($user->phone, $message);
    }

    /**
     * Render berkas PDF invoice dari template Blade dashboard.invoice-pdf.
     */
    public static function renderInvoicePdf(Invoice $invoice): ?string
    {
        try {
            $invoice->loadMissing(['order.customer', 'order.vpsSpec']);

            $pdf = Pdf::loadView('dashboard.invoice-pdf', ['invoice' => $invoice])
                ->setPaper('a4', 'portrait')
                ->setOptions([
                    'isHtml5ParserEnabled' => true,
                    'isRemoteEnabled'      => true,
                    'defaultFont'          => 'Helvetica',
                ]);

            return $pdf->output();
        } catch (\Throwable $e) {
            Log::error('Gagal merender berkas PDF Invoice untuk WhatsApp', [
                'invoice_id' => $invoice->id,
                'error'      => $e->getMessage(),
            ]);

            return null;
        }
    }
}
