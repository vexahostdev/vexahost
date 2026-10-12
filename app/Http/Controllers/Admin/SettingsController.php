<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\LogsAdminAudit;
use App\Http\Controllers\Controller;
use App\Models\SystemComponent;
use App\Services\MaintenanceService;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * PHASE 1 - Halaman Pengaturan Sistem.
 *
 * Setiap tab disimpan terpisah lewat aksi sendiri, sehingga hanya kunci
 * yang memang milik tab tersebut yang boleh ditulis. Tidak ada penulisan
 * kunci sembarangan dari input pengguna.
 */
class SettingsController extends Controller
{
    use LogsAdminAudit;

    public function __construct(
        protected SettingsService $settings,
        protected MaintenanceService $maintenance
    ) {
    }

    public function index(Request $request)
    {
        $tab = $request->query('tab', 'brand');

        $components = SystemComponent::orderBy('sort_order')->get();

        return view('admin.settings', [
            'tab' => in_array($tab, ['brand', 'company', 'support', 'notification', 'maintenance'], true)
                ? $tab
                : 'brand',
            'settings' => $this->settings,
            'scopeStates' => $this->maintenance->scopeStates(),
            'components' => $components,
            'componentStatuses' => SystemComponent::STATUSES,
        ]);
    }

    /**
     * Tab Identitas Merek.
     */
    public function updateBrand(Request $request)
    {
        $validated = $request->validate([
            'brand_name' => ['required', 'string', 'max:60'],
            'brand_tagline' => ['nullable', 'string', 'max:160'],
            'brand_accent_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'brand_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:1024'],
            'brand_favicon' => ['nullable', 'image', 'mimes:png,ico,webp', 'max:256'],
        ], [
            'brand_accent_color.regex' => 'Warna aksen harus berformat heksadesimal, contoh #4A6FA5.',
            'brand_logo.max' => 'Ukuran logo maksimal 1 MB.',
            'brand_favicon.max' => 'Ukuran favicon maksimal 256 KB.',
        ]);

        $this->settings->setMany([
            'brand_name' => $validated['brand_name'],
            'brand_tagline' => $validated['brand_tagline'] ?? '',
            'brand_accent_color' => $validated['brand_accent_color'],
        ]);

        if ($request->hasFile('brand_logo')) {
            $this->replaceUploadedFile('brand_logo_path', $request->file('brand_logo'), 'branding');
        }

        if ($request->hasFile('brand_favicon')) {
            $this->replaceUploadedFile('brand_favicon_path', $request->file('brand_favicon'), 'branding');
        }

        $this->audit('settings.brand_updated', 'Memperbarui identitas merek.');

        return back()->with('success', 'Identitas merek berhasil diperbarui.');
    }

    /**
     * Tab Profil Perusahaan. Nilai di sini dipakai ulang oleh faktur dan surel.
     */
    public function updateCompany(Request $request)
    {
        $validated = $request->validate([
            'company_legal_name' => ['required', 'string', 'max:120'],
            'company_address' => ['nullable', 'string', 'max:500'],
            'company_city' => ['nullable', 'string', 'max:80'],
            'company_postal_code' => ['nullable', 'string', 'max:10'],
            'company_npwp' => ['nullable', 'string', 'max:30'],
            'company_phone' => ['nullable', 'string', 'max:30'],
            'company_email' => ['nullable', 'email', 'max:120'],
            'company_website' => ['nullable', 'url', 'max:120'],
            'invoice_signature_name' => ['nullable', 'string', 'max:80'],
            'invoice_signature_title' => ['nullable', 'string', 'max:80'],
            'invoice_footer_note' => ['nullable', 'string', 'max:300'],
        ]);

        $this->settings->setMany($validated);

        $this->audit('settings.company_updated', 'Memperbarui profil perusahaan.');

        return back()->with('success', 'Profil perusahaan berhasil diperbarui.');
    }

    /**
     * Tab Kontak & Dukungan.
     */
    public function updateSupport(Request $request)
    {
        $validated = $request->validate([
            'support_email' => ['required', 'email', 'max:120'],
            'support_whatsapp' => ['nullable', 'string', 'max:30'],
            'support_hours' => ['nullable', 'string', 'max:120'],
            'social_instagram' => ['nullable', 'url', 'max:160'],
            'social_twitter' => ['nullable', 'url', 'max:160'],
            'social_linkedin' => ['nullable', 'url', 'max:160'],
        ]);

        $this->settings->setMany($validated);

        $this->audit('settings.support_updated', 'Memperbarui kontak dukungan.');

        return back()->with('success', 'Kontak dukungan berhasil diperbarui.');
    }

    /**
     * Tab Notifikasi. Mengatur penerima digest dan ambang batasnya.
     */
    public function updateNotification(Request $request)
    {
        $validated = $request->validate([
            'admin_notification_email' => ['required', 'email', 'max:120'],
            'admin_notification_whatsapp' => ['nullable', 'string', 'max:200', 'regex:/^[0-9+\s,;\-]*$/'],
            'digest_enabled' => ['nullable'],
            'fulfillment_sla_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'renewal_grace_days_vps' => ['required', 'integer', 'min:1', 'max:6'],
            'renewal_grace_days_database' => ['required', 'integer', 'min:1', 'max:29'],
            'renewal_grace_days_app' => ['required', 'integer', 'min:1', 'max:29'],
        ], [
            'admin_notification_whatsapp.regex' => 'Nomor WA admin hanya boleh berisi angka, +, spasi, atau koma sebagai pemisah.',
            'renewal_grace_days_vps.max' => 'Tenggang VPS wajib di bawah 7 hari, karena Supplier menghapus VPS pada hari ke-7.',
            'renewal_grace_days_database.max' => 'Tenggang database wajib di bawah 30 hari, mengikuti batas Supplier.',
            'renewal_grace_days_app.max' => 'Tenggang aplikasi wajib di bawah 30 hari, mengikuti batas Supplier.',
        ]);

        $this->settings->setMany([
            'admin_notification_email' => $validated['admin_notification_email'],
            'admin_notification_whatsapp' => trim((string) ($validated['admin_notification_whatsapp'] ?? '')),
            'fulfillment_sla_minutes' => $validated['fulfillment_sla_minutes'],
            'renewal_grace_days_vps' => $validated['renewal_grace_days_vps'],
            'renewal_grace_days_database' => $validated['renewal_grace_days_database'],
            'renewal_grace_days_app' => $validated['renewal_grace_days_app'],
        ], [
            'fulfillment_sla_minutes' => 'integer',
            'renewal_grace_days_vps' => 'integer',
            'renewal_grace_days_database' => 'integer',
            'renewal_grace_days_app' => 'integer',
        ]);

        $this->settings->set('digest_enabled', $request->boolean('digest_enabled'), 'boolean');

        $this->audit('settings.notification_updated', 'Memperbarui pengaturan notifikasi.');

        return back()->with('success', 'Pengaturan notifikasi berhasil diperbarui.');
    }

    /**
     * Kirim pesan uji ke email & WA admin agar bisa dipastikan notifikasi order sampai.
     */
    public function testNotification(\App\Services\AdminAlertService $alerts)
    {
        $result = $alerts->test();

        $this->audit('settings.notification_tested', 'Mengirim tes notifikasi admin.');

        $status = fn (bool $ok) => $ok ? 'terkirim' : 'GAGAL / belum diatur';
        $message = "Tes notifikasi — Email: {$status($result['email'])}, WhatsApp: {$status($result['whatsapp'])}.";

        return back()->with($result['email'] && $result['whatsapp'] ? 'success' : 'error', $message);
    }

    /**
     * Tab Maintenance: sakelar global, sakelar per cakupan, pesan, dan jalur pintas.
     */
    public function updateMaintenance(Request $request)
    {
        $request->validate([
            'maintenance_message' => ['nullable', 'string', 'max:300'],
            'maintenance_allowed_ips' => ['nullable', 'string', 'max:300'],
        ]);

        $this->settings->set('maintenance_global_enabled', $request->boolean('maintenance_global_enabled'), 'boolean');

        foreach (array_keys(MaintenanceService::SCOPES) as $scope) {
            $this->settings->set(
                'maintenance_scope_' . $scope,
                $request->boolean('scope_' . $scope),
                'boolean'
            );
        }

        $this->settings->setMany([
            'maintenance_message' => $request->input('maintenance_message', ''),
            'maintenance_allowed_ips' => $request->input('maintenance_allowed_ips', ''),
        ]);

        $this->audit(
            'settings.maintenance_updated',
            'Memperbarui pengaturan maintenance. Global: '
                . ($request->boolean('maintenance_global_enabled') ? 'aktif' : 'nonaktif') . '.'
        );

        return back()->with('success', 'Pengaturan maintenance berhasil diperbarui.');
    }

    /**
     * Buat ulang token jalur pintas maintenance.
     */
    public function regenerateBypassToken()
    {
        $token = Str::random(40);
        $this->settings->set('maintenance_bypass_token', $token, 'string');

        $this->audit('settings.bypass_token_regenerated', 'Membuat ulang token jalur pintas maintenance.');

        return back()->with('success', 'Token jalur pintas baru berhasil dibuat.');
    }

    /**
     * Ubah status satu komponen di halaman status publik.
     */
    public function updateComponent(Request $request, int $id)
    {
        $component = SystemComponent::findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', 'in:' . implode(',', SystemComponent::STATUSES)],
            'status_note' => ['nullable', 'string', 'max:160'],
        ]);

        $component->update($validated);

        $this->audit(
            'settings.component_updated',
            "Mengubah status komponen {$component->name} menjadi {$component->status_label}."
        );

        return back()->with('success', "Status komponen {$component->name} berhasil diperbarui.");
    }

    /**
     * Simpan berkas baru dan hapus berkas lama agar storage tidak menumpuk.
     */
    protected function replaceUploadedFile(string $settingKey, $file, string $directory): void
    {
        $old = $this->settings->get($settingKey);

        $path = $file->store($directory, 'public');

        $this->settings->set($settingKey, $path, 'string', ['group' => 'brand', 'is_public' => true]);

        if ($old && $old !== $path && Storage::disk('public')->exists($old)) {
            Storage::disk('public')->delete($old);
        }
    }
}
