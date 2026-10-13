<?php

namespace App\Console\Commands;

use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\AdminSlaBreachNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckTicketSlaBreachCommand extends Command
{
    protected $signature = 'tickets:check-sla';
    protected $description = 'Periksa tiket bantuan yang melewati batas waktu SLA dan kirim notifikasi ke Admin';

    public function handle(): int
    {
        $adminEmails = \App\Support\MailIdentity::adminRecipients();
        $adminUser = User::whereIn('email', $adminEmails)->first() ?: User::where('is_admin', true)->first();

        if (!$adminUser) {
            $this->warn("Admin user tidak ditemukan.");
            return self::SUCCESS;
        }

        // Cari tiket open / in_progress yang belum direspons oleh admin (first_response_at null) dan sudah lewat sla_due_at
        $breachedTickets = SupportTicket::with(['customer'])
            ->whereIn('status', ['open', 'in_progress'])
            ->whereNull('first_response_at')
            ->where('sla_due_at', '<=', now())
            ->get();

        if ($breachedTickets->isEmpty()) {
            $this->info('Tidak ada tiket yang melanggar SLA.');
            return self::SUCCESS;
        }

        $this->info("Ditemukan {$breachedTickets->count()} tiket melewati batas SLA.");

        foreach ($breachedTickets as $ticket) {
            try {
                $adminUser->notify(new AdminSlaBreachNotification($ticket));
                $this->info("Notifikasi SLA breach terkirim untuk tiket #{$ticket->id} ke {$adminEmail}");
            } catch (\Throwable $e) {
                $this->error("Gagal mengirim notifikasi untuk tiket #{$ticket->id}: " . $e->getMessage());
                Log::error('tickets.sla_breach_notify_failed', [
                    'ticket_id' => $ticket->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return self::SUCCESS;
    }
}
