<?php

namespace App\Notifications;

use App\Channels\WhatsAppChannel;
use App\Models\Invoice;
use App\Models\Order;
use App\Services\WhatsAppMessage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class PaymentReceivedNotification extends Notification
{
    use Queueable;
    use \App\Notifications\Concerns\UsesMailIdentity;

    public function __construct(
        public Order $order,
        public ?Invoice $invoice = null
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['mail', 'database'];

        if (config('whatsapp.enabled') && ! empty($notifiable->phone)) {
            $channels[] = WhatsAppChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invoice = $this->invoice ?? $this->order->invoice ?? Invoice::where('order_id', $this->order->id)->first();
        $invNo = $invoice?->invoice_number ?? ('INV-' . $this->order->id);

        $mail = $this->newMailMessage()
            ->subject("Pembayaran diterima — {$invNo}")
            ->view('emails.payment-received', [
                'order' => $this->order,
                'invoice' => $invoice,
            ]);

        if ($invoice) {
            try {
                $invoice->loadMissing(['order.customer', 'order.vpsSpec']);
                $pdf = Pdf::loadView('dashboard.invoice-pdf', ['invoice' => $invoice])
                    ->setPaper('a4', 'portrait')
                    ->setOptions([
                        'isHtml5ParserEnabled' => true,
                        'isRemoteEnabled' => true,
                        'defaultFont' => 'Helvetica',
                    ]);

                $mail->attachData(
                    $pdf->output(),
                    "Invoice-{$invNo}.pdf",
                    ['mime' => 'application/pdf']
                );
            } catch (\Throwable $e) {
                Log::error('notification.invoice_pdf_attach_failed', [
                    'order_id' => $this->order->id,
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'invoice_id' => $this->invoice?->id,
            'amount' => (float) $this->order->amount,
        ];
    }

    public function toWhatsApp(object $notifiable): ?WhatsAppMessage
    {
        $this->order->loadMissing(['customer', 'vpsSpec', 'invoice']);
        $invoice = $this->invoice ?? $this->order->invoice ?? Invoice::where('order_id', $this->order->id)->first();
        $invNumber = $invoice?->invoice_number ?? ('INV-' . $this->order->id);
        $appUrl = rtrim(config('app.url', 'https://vexahostcloud.my.id'), '/');
        $namaPelanggan = $notifiable->full_name ?? 'Pelanggan VexaHost';
        $nominal = number_format((float) $this->order->amount, 0, ',', '.');
        $layanan = $this->order->vpsSpec?->name ?? 'Cloud VPS';
        $metode = strtoupper(str_replace('_', ' ', $this->order->payment_method ?? 'Payment Gateway'));
        $dashboardUrl = "{$appUrl}/dashboard?payment_success=1&order_id={$this->order->id}";

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
            '⚡ *Status Layanan:* Sistem kami sedang mempersiapkan server VPS Anda. Kredensial server (IP, Port SSH, dan Password) akan segera kami kirimkan ke WhatsApp & Email Anda.',
            '',
            'Pantau status pesanan Anda di dashboard:',
            $dashboardUrl,
            '',
            'Bukti tanda terima transaksi (PDF) terlampir.',
            '_Terima kasih atas kepercayaan Anda menggunakan VexaHost._',
        ]);

        $msg = WhatsAppMessage::create($caption);

        if ($invoice) {
            try {
                $invoice->loadMissing(['order.customer', 'order.vpsSpec']);
                $pdf = Pdf::loadView('dashboard.invoice-pdf', ['invoice' => $invoice])
                    ->setPaper('a4', 'portrait')
                    ->setOptions([
                        'isHtml5ParserEnabled' => true,
                        'isRemoteEnabled'      => true,
                        'defaultFont'          => 'Helvetica',
                    ]);

                $pdfBytes = $pdf->output();
                if ($pdfBytes) {
                    $msg->document($pdfBytes, "Invoice-{$invNumber}.pdf", $caption);
                }
            } catch (\Throwable $e) {
                Log::error('notification.payment_whatsapp_pdf_failed', [
                    'order_id'   => $this->order->id,
                    'invoice_id' => $invoice->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return $msg;
    }
}
