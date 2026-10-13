<?php

namespace App\Console\Commands;

use App\Models\RenewalReminder;
use App\Models\VpsInstance;
use App\Notifications\AdminRenewalDigestNotification;
use App\Notifications\InstanceRenewalReminderNotification;
use App\Services\RenewalService;
use App\Services\SettingsService;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * PHASE 3 - Pengingat perpanjangan dan perpindahan siklus hidup layanan.
 *
 * Lima tahap: H-7, H-3, H-1, jatuh tempo, dan akhir tenggang.
 * H-7 sengaja ada agar pelanggan membayar sebelum perpanjangan di Supplier
 * (auto-renew Supplier dimatikan; Supplier biasanya menagih H-3).
 *
 * Idempotensi dijamin oleh indeks unik (vps_instance_id, stage, period_expires_at)
 * di tabel renewal_reminders. Bila command berjalan dua kali, penulisan kedua
 * ditolak basis data dan surel tidak dikirim ulang.
 *
 * Pemberitahuan ke admin selalu berbentuk SATU digest harian, bukan satu surel
 * per layanan, karena panel ini dioperasikan satu orang.
 */
class SendRenewalRemindersCommand extends Command
{
    protected $signature = 'renewal:remind
        {--dry-run : Hanya tampilkan rencana tindakan, tidak mengirim apa pun}';

    protected $description = 'Kirim pengingat perpanjangan H-7/H-3/H-1/H-0 dan pindahkan status instance yang jatuh tempo.';

    public function __construct(
        protected RenewalService $renewal,
        protected SettingsService $settings
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $digest = [
            'h_minus_7' => [],
            'h_minus_3' => [],
            'h_minus_1' => [],
            'h_zero' => [],
            'grace_ended' => [],
        ];

        $this->processUpcoming(RenewalReminder::STAGE_H7, 7, $dry, $digest);
        $this->processUpcoming(RenewalReminder::STAGE_H3, 3, $dry, $digest);
        $this->processUpcoming(RenewalReminder::STAGE_H1, 1, $dry, $digest);
        $this->processDue($dry, $digest);
        $this->processGraceEnded($dry, $digest);

        $total = array_sum(array_map('count', $digest));

        if ($total > 0 && !$dry) {
            $this->sendAdminDigest($digest);
        }

        $this->info("Total layanan diproses: {$total}");

        return self::SUCCESS;
    }

    /**
     * Tahap H-3 dan H-1: layanan yang akan jatuh tempo dalam N hari.
     */
    protected function processUpcoming(string $stage, int $days, bool $dry, array &$digest): void
    {
        $start = now()->addDays($days)->startOfDay();
        $end = now()->addDays($days)->endOfDay();

        $instances = VpsInstance::with(['customer', 'order.vpsSpec'])
            ->whereIn('status', ['running', 'stopped'])
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [$start, $end])
            ->get();

        $this->info("H-{$days}: {$instances->count()} layanan akan jatuh tempo.");

        foreach ($instances as $instance) {
            $this->handleStage($instance, $stage, $days, $dry, $digest);
        }
    }

    /**
     * Tahap jatuh tempo: masa aktif habis, layanan disuspend.
     */
    protected function processDue(bool $dry, array &$digest): void
    {
        $instances = VpsInstance::with(['customer', 'order.vpsSpec'])
            ->whereIn('status', ['running', 'stopped'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        $this->info("Jatuh tempo: {$instances->count()} layanan.");

        foreach ($instances as $instance) {
            if ($dry) {
                $this->line("  → [dry] akan suspend instance #{$instance->id}");
                $digest['h_zero'][] = $this->digestRow($instance);
                continue;
            }

            // Tenggang dihitung ulang agar mengikuti pengaturan terbaru.
            $this->renewal->applyGracePeriod($instance);

            if ($this->handleStage($instance, RenewalReminder::STAGE_H0, 0, false, $digest)) {
                $instance->update(['status' => 'suspended']);
                $this->line("  ✓ Suspend instance #{$instance->id} ({$instance->hostname})");
            }
        }
    }

    /**
     * Tahap akhir tenggang: layanan diterminasi, data dianggap hilang.
     */
    protected function processGraceEnded(bool $dry, array &$digest): void
    {
        $instances = VpsInstance::with(['customer', 'order.vpsSpec'])
            ->where('status', 'suspended')
            ->whereNotNull('grace_period_ends_at')
            ->where('grace_period_ends_at', '<=', now())
            ->get();

        $this->info("Tenggang berakhir: {$instances->count()} layanan.");

        foreach ($instances as $instance) {
            if ($dry) {
                $this->line("  → [dry] akan terminate instance #{$instance->id}");
                $digest['grace_ended'][] = $this->digestRow($instance);
                continue;
            }

            if ($this->handleStage($instance, RenewalReminder::STAGE_GRACE_ENDED, -1, false, $digest)) {
                $instance->update(['status' => 'terminated']);
                $this->line("  ✓ Terminate instance #{$instance->id} ({$instance->hostname})");
            }
        }
    }

    /**
     * Catat tahap lalu kirim surel ke pelanggan.
     *
     * Pencatatan dilakukan LEBIH DULU. Bila baris sudah ada, indeks unik
     * melempar QueryException dan surel tidak jadi dikirim. Inilah yang
     * mencegah surel ganda saat command berjalan dua kali.
     */
    protected function handleStage(
        VpsInstance $instance,
        string $stage,
        int $days,
        bool $dry,
        array &$digest
    ): bool {
        if ($dry) {
            $this->line("  → [dry] akan kirim {$stage} untuk instance #{$instance->id}");
            $digest[$stage][] = $this->digestRow($instance);

            return true;
        }

        try {
            RenewalReminder::create([
                'vps_instance_id' => $instance->id,
                'stage' => $stage,
                'period_expires_at' => $instance->expires_at,
                'sent_at' => now(),
            ]);
        } catch (QueryException $e) {
            // Sudah pernah dikirim untuk periode ini. Lewati tanpa mengulang.
            return false;
        }

        $customer = $instance->customer;

        if ($customer && $customer->email) {
            try {
                $customer->notify(new InstanceRenewalReminderNotification(
                    $instance,
                    $stage,
                    $days,
                    $this->renewal->graceDaysFor($instance)
                ));
            } catch (\Throwable $e) {
                Log::error('renewal.reminder_failed', [
                    'instance_id' => $instance->id,
                    'stage' => $stage,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $digest[$stage][] = $this->digestRow($instance);

        return true;
    }

    /**
     * Satu digest harian ke admin, bukan satu surel per layanan.
     */
    protected function sendAdminDigest(array $digest): void
    {
        if (!$this->settings->bool('digest_enabled', true)) {
            return;
        }

        $this->sendAdminDigestWhatsApp($digest);

        $emails = \App\Support\MailIdentity::adminRecipients();

        if (empty($emails)) {
            $this->warn('Surel penerima digest admin belum diatur. Digest tidak dikirim.');

            return;
        }

        foreach ($emails as $email) {
            try {
                Notification::route('mail', $email)
                    ->notify(new AdminRenewalDigestNotification($digest));

                $this->info("Digest admin terkirim ke {$email}.");
            } catch (\Throwable $e) {
                Log::error('renewal.digest_failed', ['recipient' => $email, 'error' => $e->getMessage()]);
                $this->error("Digest admin gagal dikirim ke {$email}: {$e->getMessage()}");
            }
        }
    }

    /**
     * Ringkasan singkat ke WA admin (rincian lengkap tetap di surel digest).
     */
    protected function sendAdminDigestWhatsApp(array $digest): void
    {
        $labels = [
            'h_minus_7' => 'Jatuh tempo 7 hari lagi',
            'h_minus_3' => 'Jatuh tempo 3 hari lagi',
            'h_minus_1' => 'Jatuh tempo besok',
            'h_zero' => 'Jatuh tempo hari ini — disuspend',
            'grace_ended' => 'Tenggang berakhir — diterminasi',
        ];

        $lines = ['📅 *RINGKASAN PERPANJANGAN HARI INI*', ''];

        foreach ($labels as $key => $label) {
            foreach ($digest[$key] ?? [] as $row) {
                $lines[] = "• {$label}: {$row['hostname']} · {$row['customer']} · jatuh tempo {$row['expires_at']}";
            }
        }

        $lines[] = '';
        $lines[] = '_Ingat: jangan perpanjang di Supplier sebelum pelanggan membayar. VPS terminated = hentikan juga di Supplier._';

        try {
            app(\App\Services\AdminAlertService::class)->whatsAppOnly(implode("\n", $lines), 'renewal_digest');
        } catch (\Throwable $e) {
            Log::error('renewal.digest_whatsapp_failed', ['error' => $e->getMessage()]);
        }
    }

    protected function digestRow(VpsInstance $instance): array
    {
        return [
            'id' => $instance->id,
            'hostname' => $instance->hostname ?? '-',
            'customer' => $instance->customer?->full_name ?? ($instance->customer?->username ?? '-'),
            'email' => $instance->customer?->email ?? '-',
            'type' => $this->renewal->typeLabel($this->renewal->productType($instance)),
            'expires_at' => $instance->expires_at?->timezone('Asia/Jakarta')->format('d M Y') ?? '-',
            'grace_ends_at' => $instance->grace_period_ends_at?->timezone('Asia/Jakarta')->format('d M Y') ?? '-',
        ];
    }
}
