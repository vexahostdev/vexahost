<?php

namespace App\Notifications;

use App\Channels\WhatsAppChannel;
use App\Models\Invoice;
use App\Services\WhatsAppMessage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class InvoiceCreatedNotification extends Notification
{
    use Queueable;
    use \App\Notifications\Concerns\UsesMailIdentity;

    public function __construct(
        public Invoice $invoice
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
        $mail = $this->newMailMessage()
            ->subject("Tagihan Baru Diterbitkan — {$this->invoice->invoice_number}")
            ->view('emails.invoice-created', [
                'invoice' => $this->invoice,
            ]);

        try {
            $this->invoice->loadMissing(['order.customer', 'order.vpsSpec']);
            $pdf = Pdf::loadView('dashboard.invoice-pdf', ['invoice' => $this->invoice])
                ->setPaper('a4', 'portrait')
                ->setOptions([
                    'isHtml5ParserEnabled' => true,
                    'isRemoteEnabled' => true,
                    'defaultFont' => 'Helvetica',
                ]);

            $mail->attachData(
                $pdf->output(),
                "Invoice-{$this->invoice->invoice_number}.pdf",
                ['mime' => 'application/pdf']
            );
        } catch (\Throwable $e) {
            Log::error('notification.invoice_pdf_attach_failed', [
                'invoice_id' => $this->invoice->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'amount' => (float) $this->invoice->amount,
        ];
    }

    public function toWhatsApp(object $notifiable): ?WhatsAppMessage
    {
        $this->invoice->loadMissing(['order.customer', 'order.vpsSpec']);
        $appUrl = rtrim(config('app.url', 'https://vexahostcloud.my.id'), '/');
        $namaPelanggan = $notifiable->full_name ?? 'Pelanggan VexaHost';
        $nominal = number_format((float) $this->invoice->amount, 0, ',', '.');
        $layanan = $this->invoice->order?->vpsSpec?->name ?? 'Cloud VPS';
        $jatuhTempo = $this->invoice->due_at?->timezone('Asia/Jakarta')->translatedFormat('d F Y') ?? '-';
        $bayarUrl = "{$appUrl}/order/payment/{$this->invoice->order_id}";

        $caption = implode("\n", [
            "Halo *{$namaPelanggan}*,",
            '',
            '📄 *TAGIHAN BARU DITERBITKAN*',
            'Invoice baru untuk layanan Anda telah diterbitkan dan menunggu pembayaran.',
            '',
            "• *Nomor Invoice:* `{$this->invoice->invoice_number}`",
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

        $msg = WhatsAppMessage::create($caption);

        try {
            $pdf = Pdf::loadView('dashboard.invoice-pdf', ['invoice' => $this->invoice])
                ->setPaper('a4', 'portrait')
                ->setOptions([
                    'isHtml5ParserEnabled' => true,
                    'isRemoteEnabled'      => true,
                    'defaultFont'          => 'Helvetica',
                ]);

            $pdfBytes = $pdf->output();
            if ($pdfBytes) {
                $msg->document($pdfBytes, "Invoice-{$this->invoice->invoice_number}.pdf", $caption);
            }
        } catch (\Throwable $e) {
            Log::error('notification.invoice_whatsapp_pdf_failed', [
                'invoice_id' => $this->invoice->id,
                'error'      => $e->getMessage(),
            ]);
        }

        return $msg;
    }
}
