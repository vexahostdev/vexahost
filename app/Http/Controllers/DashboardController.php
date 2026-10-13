<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\Subscription;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\VpsInstance;
use App\Services\RenewalService;
use App\Services\SettingsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    public function __construct(protected SettingsService $settings)
    {
    }

    private function organizationId(): int
    {
        return (int) Auth::user()->current_organization_id;
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $organizationId = $this->organizationId();
        $vps = VpsInstance::where('organization_id', $organizationId)->orderBy('created_at', 'desc')->get();
        $invoicesCount = Invoice::where('organization_id', $organizationId)->count();
        $openTicketsCount = SupportTicket::where('organization_id', $organizationId)
            ->whereIn('status', ['open', 'in_progress'])
            ->count();

        // Unpaid orders (Layanan terkunci menunggu pembayaran)
        $unpaidOrders = Order::where(function ($q) use ($organizationId, $user) {
                $q->where('organization_id', $organizationId)
                  ->orWhere('customer_id', $user->id);
            })
            ->whereNull('paid_at')
            ->with(['vpsSpec', 'invoice'])
            ->latest()
            ->get();

        // Status pembayaran Midtrans di-sync via webhook + scheduled command
        // (CheckMidtransStatusCommand), BUKAN di sini. Sinkronisasi sinkron
        // di setiap page load menyebabkan delay 5-30 detik dan bisa membuat
        // server jebol saat banyak user akses bersamaan.

        // Orders yang sudah dibayar dan sedang dalam antrean/proses setup server oleh admin VexaHost
        $provisioningOrders = Order::where(function ($q) use ($organizationId, $user) {
                $q->where('organization_id', $organizationId)
                  ->orWhere('customer_id', $user->id);
            })
            ->whereNotNull('paid_at')
            ->whereIn('status', ['paid', 'provisioning'])
            ->whereDoesntHave('vpsInstance')
            ->with(['vpsSpec', 'invoice', 'latestProvisioningTask'])
            ->latest()
            ->get();

        $pendingOrdersCount = $provisioningOrders->count();

        // Deteksi apakah ada order sukses dibayar yang harus dimunculkan alert konfirmasi suksesnya
        $paymentSuccessOrder = null;
        $shownSuccessOrders = session('shown_payment_success_orders', []);

        // Skenario 1: Callback dari URL dengan parameter payment_success=1 & order_id=X
        if ($request->filled('payment_success') && $request->filled('order_id')) {
            $reqOrderId = $request->query('order_id');
            $candidate = Order::where('id', $reqOrderId)
                ->where(function ($q) use ($organizationId, $user) {
                    $q->where('organization_id', $organizationId)
                      ->orWhere('customer_id', $user->id);
                })
                ->whereNotNull('paid_at')
                ->whereIn('status', ['paid', 'provisioning', 'active'])
                ->with(['vpsSpec', 'invoice'])
                ->first();

            if ($candidate) {
                $paymentSuccessOrder = $candidate;
                if (!in_array($candidate->id, $shownSuccessOrders, true)) {
                    $shownSuccessOrders[] = $candidate->id;
                    session(['shown_payment_success_orders' => $shownSuccessOrders]);
                }
            }
        }

        // Skenario 2: User langsung buka Dashboard (misal baru di-ACC admin di QRIS) tanpa klik link email
        if (!$paymentSuccessOrder && $provisioningOrders->isNotEmpty()) {
            foreach ($provisioningOrders as $pOrder) {
                if (!in_array($pOrder->id, $shownSuccessOrders, true)) {
                    $paymentSuccessOrder = $pOrder;
                    $shownSuccessOrders[] = $pOrder->id;
                    session(['shown_payment_success_orders' => $shownSuccessOrders]);
                    break;
                }
            }
        }

        return view('dashboard.index', compact('vps', 'invoicesCount', 'openTicketsCount', 'pendingOrdersCount', 'unpaidOrders', 'provisioningOrders', 'paymentSuccessOrder'));
    }

    /**
     * Endpoint JSON untuk polling progress provisioning dari dashboard customer.
     * Read-only, tidak mengubah state.
     */
    public function provisioningStatus($id)
    {
        $vps = VpsInstance::where('organization_id', $this->organizationId())
            ->with('latestProvisioningTask')
            ->findOrFail($id);

        $task = $vps->latestProvisioningTask;

        return response()->json([
            'vps_id' => $vps->id,
            'vps_status' => $vps->status,
            'task' => $task ? [
                'id' => $task->id,
                'uuid' => $task->uuid,
                'kind' => $task->kind,
                'status' => $task->status,
                'progress' => (int) $task->progress,
                'current_step' => $task->current_step,
                'attempts' => (int) $task->attempts,
                'error' => $task->error,
                'started_at' => $task->started_at?->toIso8601String(),
                'finished_at' => $task->finished_at?->toIso8601String(),
            ] : null,
        ]);
    }

    public function show($id)
    {
        $vps = VpsInstance::where('organization_id', $this->organizationId())
            ->with(['activityLogs' => function ($query) {
                $query->latest()->take(25);
            }])
            ->findOrFail($id);

        // Tiket terbaru untuk server ini (permintaan layanan maupun bantuan umum).
        $serverTickets = SupportTicket::where('organization_id', $this->organizationId())
            ->where('vps_instance_id', $vps->id)
            ->latest('id')
            ->take(10)
            ->get();

        // Permintaan layanan yang masih diproses, dikunci per tipe
        // (paling banyak satu reinstall dan satu laporan terbuka per server).
        $openRequests = SupportTicket::where('organization_id', $this->organizationId())
            ->where('vps_instance_id', $vps->id)
            ->serviceRequests()
            ->awaitingTeam()
            ->latest('id')
            ->get()
            ->unique('type')
            ->keyBy('type');

        $supportHours = $this->settings->get('support_hours', 'jam kerja');
        $slaHours = SupportTicket::SERVICE_REQUEST_SLA_WORKING_HOURS;
        // Lama masa tenggang mengikuti pengaturan admin per jenis layanan.
        $graceDays = app(RenewalService::class)->graceDaysFor($vps);

        return view('dashboard.show', compact(
            'vps',
            'serverTickets',
            'openRequests',
            'supportHours',
            'slaHours',
            'graceDays'
        ));
    }

    /**
     * Pengajuan install ulang OS.
     *
     * Server dibeli retail dari supplier tanpa API, jadi reinstall TIDAK
     * dijalankan otomatis. Permintaan dicatat sebagai tiket bertipe
     * 'reinstall', lalu admin mengerjakannya di dashboard supplier dan
     * mengisi password root baru lewat panel admin.
     *
     * Data server (OS, stack, password) sengaja tidak diubah di sini agar
     * dashboard tidak menampilkan kondisi yang belum benar-benar terjadi.
     */
    public function requestReinstall(Request $request, $id)
    {
        $vps = VpsInstance::where('organization_id', $this->organizationId())->findOrFail($id);

        if (!$vps->acceptsServiceRequests()) {
            return back()->with('error', $this->serviceRequestBlockedMessage($vps));
        }

        $serverName = $vps->hostname ?? ('VPS-' . $vps->id);
        $osOptions = $vps->reinstallOsOptions();
        $stackOptions = $vps->reinstallStackOptions();

        $rules = [
            'os' => ['required', 'string', Rule::in(array_keys($osOptions))],
            'confirm_hostname' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
        if (!$vps->hasFixedStack()) {
            $rules['control_panel'] = ['required', 'string', Rule::in(array_keys($stackOptions))];
        }

        $validated = $request->validate($rules, [
            'os.in' => 'Sistem operasi yang dipilih tidak tersedia untuk server ini.',
            'control_panel.in' => 'Stack yang dipilih tidak tersedia untuk server ini.',
            'confirm_hostname.required' => 'Ketik nama server untuk konfirmasi.',
        ]);

        // Reinstall menghapus seluruh data, jadi pelanggan wajib mengetik nama server.
        if (strcasecmp(trim($validated['confirm_hostname']), $serverName) !== 0) {
            return back()->withInput()->withErrors([
                'confirm_hostname' => 'Nama server tidak cocok. Ketik persis: ' . $serverName,
            ]);
        }

        $controlPanel = $vps->hasFixedStack() ? $vps->control_panel : $validated['control_panel'];
        $osLabel = $osOptions[$validated['os']];
        $stackLabel = $stackOptions[(string) $controlPanel] ?? $vps->control_panel_label;

        $lines = [
            'Saya mengajukan install ulang OS untuk server ' . $serverName . ' (IP ' . ($vps->public_ip ?? '-') . ').',
            '',
            'Sistem operasi: ' . $osLabel,
            'Stack / control panel: ' . $stackLabel,
            '',
            'Saya memahami seluruh data di server ini akan terhapus.',
        ];
        if (!empty($validated['notes'])) {
            $lines[] = '';
            $lines[] = 'Catatan: ' . $validated['notes'];
        }

        $result = $this->openServiceRequest(
            vps: $vps,
            type: SupportTicket::TYPE_REINSTALL,
            subject: 'Permintaan Reinstall OS - ' . $serverName,
            message: implode("\n", $lines),
            requestData: ['os' => $validated['os'], 'control_panel' => $controlPanel],
            activityAction: 'reinstall_requested',
            activityDescription: "Permintaan reinstall OS ({$osLabel}, stack {$stackLabel}) diajukan. Menunggu dikerjakan tim.",
        );

        $ticketCode = $this->ticketCode($result['ticket']);

        if (!$result['created']) {
            return redirect()->route('dashboard.support.show', $result['ticket']->id)
                ->with('info', "Permintaan reinstall untuk server ini masih diproses ({$ticketCode}). Pantau perkembangannya di tiket ini.");
        }

        return redirect()->route('dashboard.vps.show', $vps->id)
            ->with('success', "Permintaan reinstall diterima ({$ticketCode}). Tim kami memprosesnya maksimal "
                . SupportTicket::SERVICE_REQUEST_SLA_WORKING_HOURS . ' jam kerja dan Anda akan menerima email saat selesai. '
                . 'Server tetap berjalan seperti biasa sampai tim mulai mengerjakan.');
    }

    /**
     * Laporan server tidak bisa diakses (hang, SSH tidak merespons).
     *
     * Pelanggan bisa reboot sendiri lewat SSH selama server masih merespons.
     * Kalau server tidak merespons, hanya admin yang bisa me-restart dari
     * dashboard supplier, jadi laporan ini masuk sebagai tiket prioritas tinggi.
     */
    public function reportUnreachable(Request $request, $id)
    {
        $vps = VpsInstance::where('organization_id', $this->organizationId())->findOrFail($id);

        if (!$vps->acceptsServiceRequests()) {
            return back()->with('error', $this->serviceRequestBlockedMessage($vps));
        }

        $validated = $request->validate([
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $serverName = $vps->hostname ?? ('VPS-' . $vps->id);

        $lines = [
            'Server ' . $serverName . ' (IP ' . ($vps->public_ip ?? '-') . ') tidak bisa diakses.',
            'Mohon dicek dan di-restart dari sisi infrastruktur.',
        ];
        if (!empty($validated['description'])) {
            $lines[] = '';
            $lines[] = 'Keterangan: ' . $validated['description'];
        }

        $result = $this->openServiceRequest(
            vps: $vps,
            type: SupportTicket::TYPE_UNREACHABLE,
            subject: 'Server Tidak Bisa Diakses - ' . $serverName,
            message: implode("\n", $lines),
            requestData: [],
            activityAction: 'unreachable_reported',
            activityDescription: 'Laporan server tidak bisa diakses dikirim. Menunggu pengecekan tim.',
        );

        $ticketCode = $this->ticketCode($result['ticket']);

        if (!$result['created']) {
            return redirect()->route('dashboard.support.show', $result['ticket']->id)
                ->with('info', "Laporan untuk server ini sudah kami terima dan masih diproses ({$ticketCode}). Pantau perkembangannya di tiket ini.");
        }

        return redirect()->route('dashboard.vps.show', $vps->id)
            ->with('success', "Laporan diterima ({$ticketCode}). Tim akan mengecek dan me-restart server dari sisi infrastruktur, maksimal "
                . SupportTicket::SERVICE_REQUEST_SLA_WORKING_HOURS . ' jam kerja. Anda akan menerima email saat selesai.');
    }

    /**
     * Buat tiket permintaan layanan, atau kembalikan tiket yang masih terbuka
     * bila jenis yang sama sudah pernah diajukan (mencegah tiket ganda karena
     * dobel klik atau pengajuan berulang).
     *
     * @return array{ticket: SupportTicket, created: bool}
     */
    private function openServiceRequest(
        VpsInstance $vps,
        string $type,
        string $subject,
        string $message,
        array $requestData,
        string $activityAction,
        string $activityDescription,
    ): array {
        $organizationId = $this->organizationId();

        $result = DB::transaction(function () use ($vps, $type, $subject, $message, $requestData, $activityAction, $activityDescription, $organizationId) {
            // Kunci baris VPS supaya dua request bersamaan tidak membuat dua tiket.
            VpsInstance::whereKey($vps->id)->lockForUpdate()->first();

            $existing = SupportTicket::where('vps_instance_id', $vps->id)
                ->where('type', $type)
                ->awaitingTeam()
                ->latest('id')
                ->first();

            if ($existing) {
                return ['ticket' => $existing, 'created' => false];
            }

            $ticket = SupportTicket::create([
                'customer_id' => Auth::id(),
                'organization_id' => $organizationId,
                'vps_instance_id' => $vps->id,
                'subject' => $subject,
                'type' => $type,
                'request_data' => $requestData ?: null,
                'priority' => 'high',
                'status' => 'open',
                'sla_due_at' => now()->addHours(SupportTicket::SERVICE_REQUEST_SLA_WORKING_HOURS),
                'last_customer_reply_at' => now(),
            ]);

            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'user_id' => Auth::id(),
                'message' => $message,
                'is_admin_reply' => false,
            ]);

            // Log bersifat permanen, jadi statusnya 'submitted' (pengajuan tercatat),
            // bukan 'pending' yang akan terlihat menggantung setelah permintaan selesai.
            $vps->logActivity(
                action: $activityAction,
                description: $activityDescription . ' (' . $this->ticketCode($ticket) . ')',
                status: 'submitted'
            );

            return ['ticket' => $ticket, 'created' => true];
        });

        if ($result['created']) {
            $this->notifyAdminOfTicket($result['ticket'], $message, 'created');
        }

        return $result;
    }

    private function serviceRequestBlockedMessage(VpsInstance $vps): string
    {
        if ($vps->isExpired()) {
            return 'Masa aktif server sudah berakhir. Perpanjang layanan terlebih dahulu, atau hubungi support.';
        }

        return match ($vps->status) {
            'suspended' => 'Layanan sedang ditangguhkan. Selesaikan tagihan atau hubungi support.',
            'provisioning' => 'Server masih disiapkan tim. Permintaan bisa diajukan setelah server aktif.',
            'rebooting', 'reinstalling' => 'Server sedang diproses tim. Tunggu hingga selesai sebelum mengajukan permintaan baru.',
            'terminated' => 'Layanan server ini sudah dihentikan.',
            default => 'Permintaan belum bisa diajukan untuk server ini. Silakan hubungi support.',
        };
    }

    private function ticketCode(SupportTicket $ticket): string
    {
        return '#TK-' . str_pad((string) $ticket->id, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Kirim email ke admin tentang tiket baru atau balasan pelanggan.
     * Kegagalan kirim email tidak boleh menggagalkan request pelanggan.
     */
    private function notifyAdminOfTicket(SupportTicket $ticket, string $message, string $eventType): void
    {
        try {
            $ticket->loadMissing('customer');
            $adminEmails = \App\Support\MailIdentity::adminRecipients();
            foreach ($adminEmails as $adminEmail) {
                \Illuminate\Support\Facades\Notification::route('mail', $adminEmail)
                    ->notify(new \App\Notifications\AdminTicketNotification($ticket, $message, $eventType));
            }
        } catch (\Throwable $e) {
            $logKey = $eventType === 'created' ? 'support.ticket_create.notify_failed' : 'support.ticket_reply.notify_failed';
            \Illuminate\Support\Facades\Log::error($logKey, ['ticket_id' => $ticket->id, 'error' => $e->getMessage()]);
        }

        // WA admin: tiket & permintaan layanan (reinstall, server tak bisa diakses) tidak
        // boleh hanya mengandalkan surel. Tidak pernah melempar exception.
        app(\App\Services\AdminAlertService::class)->ticket($ticket, $message, $eventType);
    }

    /**
     * Ikon mata di tab Akses: kembalikan password SSH awal (didekripsi dari kolom
     * terenkripsi) tanpa meminta password akun. Pengaman yang tetap berlaku:
     * login + organisasi pemilik + izin vps.credentials, batas laju, dan log aktivitas.
     */
    public function revealPassword(Request $request, $id)
    {
        $throttleKey = 'reveal_password|' . Auth::id() . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 30)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'success' => false,
                'message' => "Terlalu sering membuka password. Silakan coba lagi dalam {$seconds} detik.",
            ], 429);
        }

        RateLimiter::hit($throttleKey, 300);

        $vps = VpsInstance::where('organization_id', $this->organizationId())->findOrFail($id);

        if (empty($vps->initial_root_password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password belum tersedia untuk server ini. Silakan hubungi support.',
            ], 404);
        }

        $vps->update([
            'root_password_revealed_at' => now(),
        ]);

        $vps->logActivity(
            action: 'password_reveal',
            description: 'Password SSH dibuka melalui ikon mata di tab Akses',
            status: 'completed'
        );

        return response()->json([
            'success' => true,
            'password' => $vps->initial_root_password,
            'revealed_at' => $vps->root_password_revealed_at->timezone('Asia/Jakarta')->format('d M Y H:i:s'),
        ]);
    }

    public function billing()
    {
        $invoices = Invoice::where('organization_id', $this->organizationId())
            ->with('order.vpsSpec')->orderBy('created_at', 'desc')->paginate(10);
        $subscriptions = Subscription::where('organization_id', $this->organizationId())
            ->with(['vpsSpec', 'vpsInstance'])->latest()->get();
        $instances = VpsInstance::where('organization_id', $this->organizationId())
            ->whereNotIn('status', ['terminated'])
            ->with(['order.vpsSpec'])
            ->orderBy('expires_at', 'asc')
            ->get();

        return view('dashboard.billing', compact('invoices', 'subscriptions', 'instances'));
    }

    /**
     * PILAR 4 - Self-Service Renewal Checkout.
     *
     * Membuat Order bertipe 'renewal' yang tertaut langsung ke vps_instance_id.
     * Menghitung tarif perpanjangan sesuai aturan harga dinamis (Pilar 2) dan lifecycle (Pilar 1).
     */
    public function renewVps(Request $request, $id)
    {
        $instance = VpsInstance::where('organization_id', $this->organizationId())
            ->with(['order.vpsSpec'])
            ->findOrFail($id);

        if (!$instance->canBeRenewed()) {
            if ($instance->isEolWithoutReplacement()) {
                return back()->with('error', 'Paket server ini telah dihentikan secara permanen (End-of-Life). Silakan hubungi tim dukungan kami untuk proses migrasi.');
            }
            return back()->with('error', "Server dengan status '{$instance->status}' tidak dapat diperpanjang.");
        }

        $spec = $instance->renewal_spec ?? $instance->order?->vpsSpec;
        if (!$spec) {
            return back()->with('error', 'Spesifikasi paket untuk server ini tidak ditemukan.');
        }

        $renewalPrice = $instance->renewal_price;
        if ($renewalPrice <= 0) {
            return back()->with('error', 'Nominal perpanjangan tidak valid.');
        }

        // Pilihan metode pembayaran (opsional, default: midtrans_snap)
        $paymentMethod = $request->input('payment_method', 'midtrans_snap');
        $billingCycle = $request->input('billing_cycle', $instance->billing_cycle ?? 'monthly');

        // Jika pelanggan memilih potong Saldo Credit
        if (in_array($paymentMethod, ['credit', 'balance', 'saldo'], true)) {
            $user = Auth::user();
            $balance = \App\Models\CreditTransaction::balanceFor($user->id);
            if ($balance < $renewalPrice) {
                return back()->with('error', 'Saldo credit Anda (Rp ' . number_format($balance, 0, ',', '.') . ') tidak mencukupi untuk biaya perpanjangan Rp ' . number_format($renewalPrice, 0, ',', '.') . '.');
            }

            $order = DB::transaction(function () use ($instance, $spec, $renewalPrice, $billingCycle, $user) {
                $order = Order::create([
                    'customer_id' => $user->id,
                    'organization_id' => $instance->organization_id,
                    'vps_spec_id' => $spec->id,
                    'order_type' => 'renewal',
                    'vps_instance_id' => $instance->id,
                    'control_panel' => $instance->control_panel ?? 'none',
                    'datacenter_location' => $instance->datacenter_location ?? 'indonesia',
                    'os' => $instance->os ?? 'ubuntu2404',
                    'billing_cycle' => $billingCycle,
                    'status' => 'paid',
                    'channel' => 'website',
                    'payment_method' => 'credit_balance',
                    'amount' => $renewalPrice,
                    'currency' => 'IDR',
                    'setup_fee' => 0,
                    'hostname' => $instance->hostname,
                    'fulfillment_stage' => 'delivered',
                    'paid_at' => now(),
                    'delivered_at' => now(),
                    'terms_version' => 'v3',
                    'last_status_change_at' => now(),
                ]);

                // Potong saldo
                $billingService = app(\App\Services\BillingService::class);
                $billingService->deductCredit($user, $renewalPrice, "Perpanjangan server {$instance->hostname} (Order #{$order->id})");

                // Generate Invoice Lunas
                $breakdown = $billingService->calculatePrice($spec, $billingCycle, null, false, $user);
                $breakdown['total'] = $renewalPrice;
                $breakdown['gross'] = $renewalPrice;
                $invoice = $billingService->generateInvoice($order, $breakdown, ['is_renewal' => true]);
                $invoice->update(['status' => 'paid', 'paid_at' => now()]);

                // Eksekusi perpanjangan
                app(\App\Services\RenewalService::class)->handleRenewalPayment($order);

                return $order;
            });

            // Order ini dibuat langsung berstatus 'paid' (tanpa state machine), jadi titik
            // "setelah lunas" dipanggil manual: email pelanggan + info admin (idempoten).
            app(\App\Services\PaymentCompletionService::class)->handle($order->id);

            return redirect()->route('dashboard.vps.show', $instance->id)
                ->with('success', 'Perpanjangan layanan berhasil menggunakan Saldo Akun! Masa aktif server telah diperpanjang.');
        }

        // Cek apakah sudah ada pending renewal order yang dibuat < 24 jam untuk instance ini
        $existingOrder = Order::where('vps_instance_id', $instance->id)
            ->where('order_type', 'renewal')
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subHours(24))
            ->latest()
            ->first();

        if ($existingOrder) {
            $existingOrder->update([
                'amount' => $renewalPrice,
                'payment_method' => $paymentMethod,
                'vps_spec_id' => $spec->id,
            ]);

            if ($existingOrder->invoice) {
                $existingOrder->invoice->update([
                    'amount' => $renewalPrice,
                    'total' => $renewalPrice,
                    'subtotal' => $renewalPrice,
                ]);
            }

            return redirect()->route('order.payment', $existingOrder->id);
        }

        // Buat Order Renewal Baru
        $order = DB::transaction(function () use ($instance, $spec, $renewalPrice, $paymentMethod, $billingCycle) {
            $order = Order::create([
                'customer_id' => Auth::id(),
                'organization_id' => $instance->organization_id,
                'vps_spec_id' => $spec->id,
                'order_type' => 'renewal',
                'vps_instance_id' => $instance->id,
                'control_panel' => $instance->control_panel ?? 'none',
                'datacenter_location' => $instance->datacenter_location ?? 'indonesia',
                'os' => $instance->os ?? 'ubuntu2404',
                'billing_cycle' => $billingCycle,
                'status' => 'pending',
                'channel' => 'website',
                'payment_method' => $paymentMethod,
                'amount' => $renewalPrice,
                'currency' => 'IDR',
                'setup_fee' => 0,
                'hostname' => $instance->hostname,
                'fulfillment_stage' => 'delivered',
                'terms_version' => 'v3',
                'last_status_change_at' => now(),
            ]);

            // Generate invoice
            $billingService = app(\App\Services\BillingService::class);
            $breakdown = $billingService->calculatePrice($spec, $billingCycle, null, false, Auth::user());
            $breakdown['total'] = $renewalPrice;
            $breakdown['gross'] = $renewalPrice;
            $billingService->generateInvoice($order, $breakdown, ['is_renewal' => true]);

            return $order;
        });

        return redirect()->route('order.payment', $order->id);
    }

    public function toggleAutoRenew(Request $request, $id)
    {
        $subscription = Subscription::where('organization_id', $this->organizationId())->findOrFail($id);
        $subscription->update(['auto_renew' => $request->boolean('auto_renew')]);

        return back()->with('success', $subscription->auto_renew
            ? 'Perpanjangan otomatis berhasil diaktifkan.'
            : 'Perpanjangan otomatis berhasil dimatikan.');
    }

    public function cancelSubscription($id)
    {
        $subscription = Subscription::where('organization_id', $this->organizationId())->findOrFail($id);
        abort_unless($subscription->isCancellable(), 422, 'Langganan ini tidak dapat dibatalkan.');

        $subscription->update([
            'auto_renew' => false,
            'cancelled_at' => now(),
        ]);

        return back()->with('success', 'Langganan dibatalkan setelah periode berjalan berakhir. Layanan tetap aktif sampai tanggal jatuh tempo.');
    }

    public function resumeSubscription($id)
    {
        $subscription = Subscription::where('organization_id', $this->organizationId())->findOrFail($id);
        abort_unless($subscription->status === 'active' && $subscription->cancelled_at && (!$subscription->current_period_end || $subscription->current_period_end->isFuture()), 422, 'Langganan ini tidak dapat dilanjutkan.');

        $subscription->update(['auto_renew' => true, 'cancelled_at' => null]);

        return back()->with('success', 'Langganan dilanjutkan dan akan diperpanjang otomatis.');
    }

    public function printInvoice($id)
    {
        $user = Auth::user();
        $invoice = Invoice::with(['order.customer', 'order.vpsSpec'])->findOrFail($id);

        if ($invoice->organization_id !== $this->organizationId() && !$user->is_admin) {
            abort(403);
        }

        // Auto-sinkron status invoice jika order sudah lunas
        if ($invoice->order && ($invoice->order->paid_at || in_array($invoice->order->status, ['paid', 'provisioning', 'active'], true))) {
            if ($invoice->status !== 'paid') {
                $invoice->update([
                    'status' => 'paid',
                    'paid_at' => $invoice->order->paid_at ?? now(),
                ]);
                $invoice->refresh();
            }
        }

        if (request()->query('format') === 'html') {
            return view('dashboard.invoice-print', compact('invoice'));
        }

        $pdf = Pdf::loadView('dashboard.invoice-pdf', compact('invoice'))
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'Helvetica',
            ]);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Invoice-' . $invoice->invoice_number . '.pdf"',
        ]);
    }

    public function support()
    {
        $user = Auth::user();
        $tickets = SupportTicket::where('organization_id', $this->organizationId())
            ->with(['vpsInstance', 'latestCustomerMessage'])
            ->orderBy('updated_at', 'desc')
            ->paginate(10);

        $vpsList = VpsInstance::where('organization_id', $this->organizationId())->get();

        return view('dashboard.support', compact('tickets', 'vpsList'));
    }

    public function storeTicket(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'vps_instance_id' => [
                'nullable',
                Rule::exists('vps_instances', 'id')->where('organization_id', $this->organizationId()),
            ],
            'priority' => 'required|in:low,medium,high',
            'message' => 'required|string|min:10',
        ]);

        $ticket = SupportTicket::create([
            'customer_id' => Auth::id(),
            'organization_id' => $this->organizationId(),
            'vps_instance_id' => $validated['vps_instance_id'] ?? null,
            'subject' => $validated['subject'],
            'priority' => $validated['priority'],
            'status' => 'open',
            'sla_due_at' => now()->addHours(4),
            'last_customer_reply_at' => now(),
        ]);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'message' => $validated['message'],
            'is_admin_reply' => false,
        ]);

        // Notifikasi ke email admin bahwa ada tiket support baru
        $this->notifyAdminOfTicket($ticket, $validated['message'], 'created');

        return redirect()->route('dashboard.support.show', $ticket->id)->with('success', 'Tiket bantuan #' . $ticket->id . ' berhasil dikirim. Tim VexaHost akan merespon dalam waktu maksimal 4 jam.');
    }

    public function showTicket($id)
    {
        $user = Auth::user();
        $ticket = SupportTicket::with(['customer', 'vpsInstance', 'customerMessages.user'])->findOrFail($id);

        if ($ticket->organization_id !== $this->organizationId() && !$user->is_admin) {
            abort(403);
        }

        return view('dashboard.ticket-show', compact('ticket'));
    }

    public function replyTicket(Request $request, $id)
    {
        $user = Auth::user();
        $ticket = SupportTicket::where('organization_id', $this->organizationId())->findOrFail($id);

        $validated = $request->validate([
            'message' => 'required|string|min:2',
        ]);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => $validated['message'],
            'is_admin_reply' => !empty($user->is_admin),
            'is_internal' => false,
        ]);

        $ticket->update(['last_customer_reply_at' => now(), 'status' => $ticket->status === 'resolved' ? 'open' : $ticket->status]);

        if ($user->is_admin) {
            $ticket->update(['status' => 'in_progress']);
        } else {
            if ($ticket->status === 'resolved' || $ticket->status === 'closed') {
                $ticket->update(['status' => 'open']);
            }

            // Notifikasi ke email admin bahwa customer membalas tiket
            $this->notifyAdminOfTicket($ticket, $validated['message'], 'replied');
        }
        $ticket->touch();

        return back()->with('success', 'Pesan balasan berhasil terkirim.');
    }

    public function settings()
    {
        $user = Auth::user();
        return view('dashboard.settings', compact('user'));
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:25',
            'company' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
        ]);

        $user->update($validated);

        return back()->with('success', 'Profil akun Anda berhasil diperbarui.');
    }

    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if (!Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Password saat ini yang Anda masukkan salah.']);
        }

        $user->update([
            'password' => Hash::make($validated['new_password']),
        ]);

        return back()->with('success', 'Password akun Anda berhasil diperbarui.');
    }
}
