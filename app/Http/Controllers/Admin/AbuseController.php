<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\LogsAdminAudit;
use App\Http\Controllers\Controller;
use App\Models\AbuseCase;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Models\VpsInstance;
use App\Notifications\TemplatedNotification;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * PHASE 2 - Catatan kasus pelanggaran Acceptable Use Policy.
 *
 * Notifikasi dari sini memakai template abuse_suspension yang menyebut
 * pelanggaran secara spesifik. Template ini TIDAK boleh dipakai untuk
 * pemberitahuan massal: untuk itu gunakan BroadcastController.
 */
class AbuseController extends Controller
{
    use LogsAdminAudit;

    public function __construct(
        protected SettingsService $settings
    ) {
    }

    public function index(Request $request)
    {
        $query = AbuseCase::with(['user', 'vpsInstance', 'handler'])->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($severity = $request->query('severity')) {
            $query->where('severity', $severity);
        }

        return view('admin.abuse', [
            'cases' => $query->paginate(20)->withQueryString(),
            'types' => AbuseCase::types(),
            'statuses' => AbuseCase::STATUSES,
            'severities' => AbuseCase::SEVERITIES,
            'openCount' => AbuseCase::unresolved()->count(),
            'criticalCount' => AbuseCase::unresolved()->where('severity', 'critical')->count(),
            'totalCount' => AbuseCase::count(),
            'terminatedCount' => AbuseCase::where('status', 'terminated')->count(),
            'instances' => VpsInstance::with('customer')->latest()->limit(200)->get(),
            'filterStatus' => $request->query('status'),
            'filterSeverity' => $request->query('severity'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'vps_instance_id' => ['nullable', 'integer', 'exists:vps_instances,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'type' => ['required', 'in:' . implode(',', array_keys(AbuseCase::types()))],
            'severity' => ['required', 'in:' . implode(',', AbuseCase::SEVERITIES)],
            'evidence' => ['nullable', 'string', 'max:2000'],
        ]);

        $case = AbuseCase::create($validated + [
            'status' => 'open',
            'handled_by' => Auth::id(),
        ]);

        $this->audit(
            'abuse.case_created',
            "Mencatat kasus pelanggaran {$case->type_label} untuk pengguna #{$case->user_id}.",
            $case
        );

        return back()->with('success', 'Kasus pelanggaran berhasil dicatat.');
    }

    /**
     * Kirim pemberitahuan suspensi ke pelanggan yang bersangkutan saja.
     */
    public function notify(Request $request, int $id)
    {
        $case = AbuseCase::with(['user', 'vpsInstance'])->findOrFail($id);

        $request->validate([
            'deadline_days' => ['required', 'integer', 'min:1', 'max:30'],
        ]);

        $template = NotificationTemplate::active()
            ->where('code', NotificationTemplate::ABUSE_SUSPENSION)
            ->first();

        if (!$template) {
            return back()->with('error', 'Template surel suspensi tidak ditemukan atau sedang nonaktif.');
        }

        $deadline = now()->addDays((int) $request->input('deadline_days'));

        $data = [
            'nama' => $case->user->name ?? 'Pelanggan',
            'layanan' => $case->vpsInstance?->hostname ?? 'Layanan Anda',
            'jenis_pelanggaran' => $case->type_label,
            'waktu' => $case->created_at->timezone('Asia/Jakarta')->format('d M Y, H:i') . ' WIB',
            'keterangan' => $case->evidence ?: 'Tidak ada keterangan tambahan.',
            'batas_waktu' => $deadline->timezone('Asia/Jakarta')->format('d M Y, H:i') . ' WIB',
        ];

        try {
            $case->user->notify(new TemplatedNotification(
                $template->renderSubject($data),
                $template->renderBody($data),
                'PELANGGARAN KETENTUAN',
                'support'
            ));
        } catch (\Throwable $e) {
            Log::error('abuse.notify_failed', [
                'case_id' => $case->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Pemberitahuan gagal dikirim: ' . $e->getMessage());
        }

        $case->update([
            'status' => 'notified',
            'notified_at' => now(),
        ]);

        $this->audit(
            'abuse.customer_notified',
            "Mengirim pemberitahuan pelanggaran kasus #{$case->id} ke {$case->user->email}.",
            $case
        );

        return back()->with('success', 'Pemberitahuan pelanggaran terkirim ke pelanggan.');
    }

    public function resolve(Request $request, int $id)
    {
        $case = AbuseCase::findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', 'in:resolved,terminated'],
            'resolution' => ['required', 'string', 'max:1000'],
        ]);

        $case->update([
            'status' => $validated['status'],
            'resolution' => $validated['resolution'],
            'resolved_at' => now(),
            'handled_by' => Auth::id(),
        ]);

        $this->audit(
            'abuse.case_resolved',
            "Menutup kasus pelanggaran #{$case->id} dengan status {$case->status_label}.",
            $case
        );

        return back()->with('success', 'Kasus pelanggaran berhasil ditutup.');
    }
}
