<?php

use App\Http\Controllers\Admin\AbuseController as AdminAbuseController;
use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Admin\BillingCenterController as AdminBillingCenterController;
use App\Http\Controllers\Admin\BroadcastController as AdminBroadcastController;
use App\Http\Controllers\Admin\FulfillmentController as AdminFulfillmentController;
use App\Http\Controllers\Admin\ImpersonationController as AdminImpersonationController;
use App\Http\Controllers\Admin\MaintenanceController as AdminMaintenanceController;
use App\Http\Controllers\Admin\NotificationTemplateController as AdminTemplateController;
use App\Http\Controllers\Admin\PaymentGatewayController as AdminPaymentGatewayController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\SupplierPurchaseController as AdminSupplierPurchaseController;
use App\Http\Controllers\Admin\WebhookLogController as AdminWebhookLogController;
use App\Http\Controllers\Admin\WhatsAppController as AdminWhatsAppController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ProdukController;
use App\Http\Controllers\SecurityController;
use App\Http\Controllers\StatusController;
use App\Http\Controllers\TwoFactorController;
use App\Models\VpsSpec;
use App\Services\QrisService;
use App\Support\KatalogDokumentasi;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

// Public Landing Page
Route::get('/', function () {
    $vpsSpecs = VpsSpec::where('category', 'vps')->orderBy('sell_price', 'asc')->get();
    $aiSpecs = VpsSpec::where('category', 'ai_combo')->orderBy('sell_price', 'asc')->get();
    $dbSpecs = VpsSpec::where('category', 'managed_db')->orderBy('sell_price', 'asc')->get();
    $specs = $vpsSpecs;

    return view('landing', compact('specs', 'vpsSpecs', 'aiSpecs', 'dbSpecs'));
})->name('home');

// Public Content & Info Pages
// Dokumentasi: satu indeks dan satu halaman per kelompok topik. Alasan
// pemecahannya ada di App\Support\KatalogDokumentasi.
Route::get('/docs', [DocsController::class, 'index'])->name('docs');
Route::get('/docs/{slug}', [DocsController::class, 'kelompok'])
    ->whereIn('slug', array_keys(KatalogDokumentasi::kelompok()))
    ->name('docs.kelompok');
// Halaman status membaca keadaan nyata dari system_components (Phase 1).
Route::get('/status', StatusController::class)->name('status');
Route::redirect('/kontak', '/#kontak')->name('contact');

// Public Direct Package Section Routes
// Halaman produk. Sebelumnya ketiganya cuma redirect ke anchor beranda —
// bagi pengunjung berfungsi, bagi mesin pencari tidak ada halaman di sana sama
// sekali. Alasan lengkapnya di App\Support\KatalogProduk.
Route::get('/vps', ProdukController::class)->defaults('slug', 'vps')->name('packages.vps');
Route::get('/ai', ProdukController::class)->defaults('slug', 'ai')->name('packages.ai');
Route::get('/database', ProdukController::class)->defaults('slug', 'database')->name('packages.db');

// Alamat lama tetap dijawab supaya tautan yang sudah tersebar tidak mati.
Route::redirect('/tambah-vps', '/vps');
Route::redirect('/ai-agent', '/ai');
Route::redirect('/tambah-ai-agent', '/ai');
Route::redirect('/db', '/database');
Route::redirect('/tambah-database', '/database');

// Public Legal Pages
Route::view('/terms', 'pages.terms')->name('terms');
Route::view('/privacy', 'pages.privacy')->name('privacy');
// Halaman SLA dihapus: VexaHost tidak menjanjikan persentase uptime karena server
// dibeli retail dari supplier. URL lama dialihkan agar tautan lama tidak 404.
Route::permanentRedirect('/sla', '/terms');
Route::view('/refund', 'pages.refund')->name('refund');

// Web App Manifest (PWA & Mobile Bookmark)
Route::get('/site.webmanifest', function () {
    $manifestPath = public_path('site.webmanifest');
    if (file_exists($manifestPath)) {
        return response()->file($manifestPath, [
            'Content-Type' => 'application/manifest+json; charset=utf-8',
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }

    return response()->json([
        'name' => 'VexaHost Cloud',
        'short_name' => 'VexaHost',
        'start_url' => '/',
        'display' => 'standalone',
    ], 200, ['Content-Type' => 'application/manifest+json; charset=utf-8']);
});

// Public XML Sitemap (Google Search Console)
Route::get('/sitemap.xml', function () {
    $baseUrl = rtrim(config('app.url', url('/')), '/');

    // lastmod dibaca dari berkas view-nya, bukan now(). Sitemap yang menyebut
    // setiap URL "baru saja berubah" pada setiap permintaan tidak dipercaya
    // Google — tanggalnya harus benar-benar menandai perubahan.
    $diubah = function (string $view): ?string {
        $berkas = resource_path('views/'.str_replace('.', '/', $view).'.blade.php');

        return is_file($berkas)
            ? Carbon::createFromTimestamp(filemtime($berkas))->toAtomString()
            : null;
    };

    // Halaman checkout sengaja tidak didaftarkan. Bagi perayap isinya formulir
    // kosong, dan mendaftarkannya dengan prioritas tinggi membuatnya bersaing
    // melawan beranda untuk pencarian nama merek — persis yang terjadi sebelumnya.
    // Halamannya sendiri sudah bertanda noindex.
    $urls = [
        ['loc' => $baseUrl.'/', 'changefreq' => 'daily', 'priority' => '1.0', 'lastmod' => $diubah('landing')],
        ['loc' => $baseUrl.'/vps', 'changefreq' => 'weekly', 'priority' => '0.9', 'lastmod' => $diubah('pages.produk')],
        ['loc' => $baseUrl.'/ai', 'changefreq' => 'weekly', 'priority' => '0.9', 'lastmod' => $diubah('pages.produk')],
        ['loc' => $baseUrl.'/database', 'changefreq' => 'weekly', 'priority' => '0.9', 'lastmod' => $diubah('pages.produk')],
        ['loc' => $baseUrl.'/docs', 'changefreq' => 'weekly', 'priority' => '0.9', 'lastmod' => $diubah('pages.docs.index')],
        ['loc' => $baseUrl.'/status', 'changefreq' => 'hourly', 'priority' => '0.8', 'lastmod' => $diubah('pages.status')],
        ['loc' => $baseUrl.'/terms', 'changefreq' => 'monthly', 'priority' => '0.3', 'lastmod' => $diubah('pages.terms')],
        ['loc' => $baseUrl.'/privacy', 'changefreq' => 'monthly', 'priority' => '0.3', 'lastmod' => $diubah('pages.privacy')],
        ['loc' => $baseUrl.'/refund', 'changefreq' => 'monthly', 'priority' => '0.3', 'lastmod' => $diubah('pages.refund')],
    ];

    // Tiap kelompok dokumentasi punya alamatnya sendiri sejak isinya dipecah;
    // tanpa didaftarkan di sini, halaman-halaman itu hanya bisa ditemukan lewat
    // sidebar — dan yang hanya bisa dicapai lewat sidebar mudah terlewat perayap.
    foreach (array_keys(KatalogDokumentasi::kelompok()) as $slugKelompok) {
        $urls[] = [
            'loc' => $baseUrl.'/docs/'.$slugKelompok,
            'changefreq' => 'weekly',
            'priority' => '0.8',
            'lastmod' => KatalogDokumentasi::diubahPada($slugKelompok),
        ];
    }

    $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

    foreach ($urls as $u) {
        $xml .= "    <url>\n";
        $xml .= '        <loc>'.htmlspecialchars($u['loc'], ENT_XML1)."</loc>\n";
        if (! empty($u['lastmod'])) {
            $xml .= '        <lastmod>'.$u['lastmod']."</lastmod>\n";
        }
        $xml .= '        <changefreq>'.$u['changefreq']."</changefreq>\n";
        $xml .= '        <priority>'.$u['priority']."</priority>\n";
        $xml .= "    </url>\n";
    }

    $xml .= '</urlset>';

    return response($xml, 200, [
        'Content-Type' => 'application/xml; charset=utf-8',
    ]);
})->name('sitemap');

// Website Self-Serve Order Flow (PRD 7.1.1)
// Cakupan 'checkout' dapat ditutup terpisah saat stok habis atau ada maintenance,
// tanpa menutup seluruh situs.
Route::get('/checkout/{spec_id?}', [OrderController::class, 'checkout'])
    ->middleware('maintenance:checkout')->name('checkout');
Route::post('/checkout', [OrderController::class, 'store'])
    ->middleware('maintenance:checkout')->name('order.store');
Route::post('/checkout/quick-login', [OrderController::class, 'quickLogin'])->name('checkout.quick-login');
Route::get('/order/payment/{id}', [OrderController::class, 'payment'])->name('order.payment')->middleware('auth');
// Halaman polling status pembayaran (read-only, diotentikasi via controller & session order).
Route::get('/order/payment/{id}/status', [OrderController::class, 'paymentStatus'])->name('order.payment.status');
Route::get('/order/payment/status/{id}', fn ($id) => redirect()->route('order.payment.status', $id));
// JSON endpoint untuk polling status by frontend.
Route::get('/order/payment/{id}/status.json', [OrderController::class, 'paymentStatusJson'])->name('order.payment.status.json');
// DEV ONLY: simulate payment - dipagari APP_ENV=local dan APP_DEV_SIMULATE_PAYMENT=true.
Route::post('/order/payment/{id}/dev-simulate', [OrderController::class, 'devSimulatePayment'])
    ->name('order.payment.dev-simulate')
    ->middleware(['auth', 'throttle:5,1']);
Route::get('/order/success/{id}', [OrderController::class, 'success'])->name('order.success');
Route::get('/order/callback', [OrderController::class, 'paymentCallback'])->name('order.callback');
Route::get('/payment/callback', [OrderController::class, 'paymentCallback'])->name('payment.callback');
Route::get('/qris/render', function (Request $request, QrisService $qrisService) {
    $amount = max(1000, (float) $request->input('amount', 80000));

    return response()->json([
        'amount' => $amount,
        'payload' => $qrisService->generatePayload($amount),
        'svg_data_uri' => $qrisService->generateDataUri($amount),
    ]);
})->name('qris.render');

// Authentication Routes (PRD 12.1)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');

    // Google OAuth (PRD 12.2)
    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

    // Password reset (Poin 5)
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email')->middleware('throttle:5,1');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update')->middleware('throttle:5,1');
});

// Two-factor challenge — dapat diakses tanpa full auth (session masih pending).
Route::get('/two-factor/challenge', [TwoFactorController::class, 'showChallenge'])->name('two-factor.challenge');
Route::post('/two-factor/challenge', [TwoFactorController::class, 'verifyChallenge'])->name('two-factor.verify')->middleware('throttle:10,1');

// Public invitation view (butuh login untuk accept)
Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept'])->name('invitations.accept')->middleware('auth');

// PHASE 7 - kembali dari "masuk sebagai pelanggan". Hanya butuh 'auth' agar
// admin selalu bisa keluar, apa pun keadaan middleware lain.
Route::post('/impersonate/leave', [AdminImpersonationController::class, 'leave'])
    ->middleware('auth')->name('impersonate.leave');

// Authenticated User Routes
// org.context middleware memastikan setiap user login punya currentOrganization.
Route::middleware(['auth', '2fa', 'org.context'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Email verification (Laravel built-in)
    Route::get('/email/verify', [AuthController::class, 'showVerificationNotice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [AuthController::class, 'resendVerification'])
        ->middleware('throttle:6,1')->name('verification.send');

    // Security settings (Poin 5)
    Route::prefix('security')->name('security.')->group(function () {
        Route::get('/', [SecurityController::class, 'index'])->name('settings');
        Route::get('/login-activity', [SecurityController::class, 'loginActivity'])->name('login-activity');
        Route::get('/sessions', [SecurityController::class, 'sessions'])->name('sessions');
        Route::post('/sessions/{sessionId}/revoke', [SecurityController::class, 'revokeSession'])->name('sessions.revoke');
        Route::post('/sessions/revoke-all', [SecurityController::class, 'revokeAllOtherSessions'])->name('sessions.revoke-all');
        Route::post('/api-keys', [SecurityController::class, 'createApiKey'])->name('api-keys.create');
        Route::post('/api-keys/{id}/revoke', [SecurityController::class, 'revokeApiKey'])->name('api-keys.revoke');
        Route::post('/api-keys/{id}/rotate', [SecurityController::class, 'rotateApiKey'])->name('api-keys.rotate');
        Route::post('/ssh-keys', [SecurityController::class, 'createSshKey'])->name('ssh-keys.create');
        Route::delete('/ssh-keys/{id}', [SecurityController::class, 'deleteSshKey'])->name('ssh-keys.delete');

        // Two-factor
        Route::get('/two-factor/enable', [TwoFactorController::class, 'showEnable'])->name('two-factor.enable');
        Route::post('/two-factor/enable', [TwoFactorController::class, 'enable'])->name('two-factor.confirm');
        Route::post('/two-factor/disable', [TwoFactorController::class, 'disable'])->name('two-factor.disable');
        Route::get('/two-factor/recovery-codes', [TwoFactorController::class, 'showRecoveryCodes'])->name('recovery-codes');
        Route::post('/two-factor/recovery-codes/regenerate', [TwoFactorController::class, 'regenerateRecoveryCodes'])->name('recovery-codes.regenerate');
    });

    // Organizations (Poin 6)
    Route::prefix('organizations')->name('organizations.')->group(function () {
        Route::get('/', [OrganizationController::class, 'index'])->name('index');
        Route::get('/create', [OrganizationController::class, 'create'])->name('create');
        Route::post('/', [OrganizationController::class, 'store'])->name('store');
        Route::get('/{id}', [OrganizationController::class, 'show'])->name('show');
        Route::post('/{id}/switch', [OrganizationController::class, 'switch'])->name('switch');
        Route::post('/{id}/billing', [OrganizationController::class, 'updateBilling'])->name('billing');
        Route::post('/{id}/invite', [OrganizationController::class, 'invite'])->name('invite');
        Route::post('/{id}/members/{memberId}/remove', [OrganizationController::class, 'removeMember'])->name('members.remove');
        Route::post('/{id}/members/{memberId}/role', [OrganizationController::class, 'updateMemberRole'])->name('members.role');
        Route::post('/invitations/{id}/revoke', [InvitationController::class, 'revoke'])->name('invitations.revoke');
    });

    // Customer Dashboard Routes (PRD 8.1)
    Route::prefix('dashboard')->name('dashboard.')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('index');
        Route::get('/vps/{id}', [DashboardController::class, 'show'])->middleware('permission:vps.read')->name('vps.show');
        // Polling endpoint untuk progress provisioning (Poin 4).
        Route::get('/vps/{id}/provisioning-status.json', [DashboardController::class, 'provisioningStatus'])->middleware('permission:vps.read')->name('vps.provisioning-status');
        // Server dibeli retail tanpa API supplier, jadi panel tidak menjalankan
        // start/stop/reboot/reinstall sendiri. Reboot biasa dilakukan pelanggan
        // lewat SSH (panduan di halaman detail). Aksi yang butuh akses supplier
        // diajukan sebagai tiket dan dikerjakan admin secara manual.
        Route::post('/vps/{id}/requests/reinstall', [DashboardController::class, 'requestReinstall'])->middleware('permission:vps.reinstall')->middleware('maintenance:vps_actions')->middleware('throttle:10,1')->name('vps.request-reinstall');
        Route::post('/vps/{id}/requests/unreachable', [DashboardController::class, 'reportUnreachable'])->middleware('permission:vps.manage')->middleware('maintenance:vps_actions')->middleware('throttle:10,1')->name('vps.report-unreachable');
        Route::post('/vps/{id}/reveal-password', [DashboardController::class, 'revealPassword'])->middleware('permission:vps.credentials')->name('vps.reveal-password');
        Route::post('/vps/{id}/renew', [DashboardController::class, 'renewVps'])->middleware('permission:vps.read')->name('vps.renew');
        Route::get('/billing', [DashboardController::class, 'billing'])->middleware('permission:billing.read')->name('billing');
        Route::post('/subscriptions/{id}/auto-renew', [DashboardController::class, 'toggleAutoRenew'])->middleware('permission:billing.read')->name('subscriptions.auto-renew');
        Route::post('/subscriptions/{id}/cancel', [DashboardController::class, 'cancelSubscription'])->middleware('permission:billing.read')->name('subscriptions.cancel');
        Route::post('/subscriptions/{id}/resume', [DashboardController::class, 'resumeSubscription'])->middleware('permission:billing.read')->name('subscriptions.resume');
        Route::get('/invoices/{id}/print', [DashboardController::class, 'printInvoice'])->middleware('permission:billing.read')->name('invoice.print');
        Route::get('/support', [DashboardController::class, 'support'])->name('support');
        Route::post('/support', [DashboardController::class, 'storeTicket'])->middleware('maintenance:support')->name('support.store');
        Route::get('/support/{id}', [DashboardController::class, 'showTicket'])->name('support.show');
        Route::post('/support/{id}/reply', [DashboardController::class, 'replyTicket'])->name('support.reply');
        Route::get('/settings', [DashboardController::class, 'settings'])->name('settings');
        Route::post('/settings/profile', [DashboardController::class, 'updateProfile'])->name('settings.profile');
        Route::post('/settings/password', [DashboardController::class, 'updatePassword'])->name('settings.password');
    });

    // Admin Panel Routes (Ryan Only - PRD 8.2)
    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('index');
        Route::get('/payments', [AdminController::class, 'payments'])->name('payments');
        Route::post('/payments/{id}/approve', [AdminController::class, 'approvePayment'])->name('payments.approve');
        Route::post('/payments/{id}/hold', [AdminController::class, 'holdPayment'])->name('payments.hold');
        Route::post('/payments/{id}/reject', [AdminController::class, 'rejectPayment'])->name('payments.reject');
        Route::get('/orders', [AdminController::class, 'orders'])->name('orders');
        Route::post('/orders/{id}/provision', [AdminController::class, 'provision'])->name('orders.provision');
        Route::post('/orders/{id}/cancel', [AdminController::class, 'cancelOrder'])->name('orders.cancel');
        // Admin manual mark paid (untuk payment offline / rekonsiliasi manual).
        Route::post('/orders/{id}/mark-paid', [AdminController::class, 'markOrderPaid'])->name('orders.mark-paid');
        // Retry provisioning yang gagal.
        Route::post('/orders/{id}/retry-provision', [AdminController::class, 'retryProvision'])->name('orders.retry-provision');
        // Riwayat status order (audit trail).
        Route::get('/orders/{id}/history', [AdminController::class, 'orderHistory'])->name('orders.history');
        Route::get('/shopee', [AdminController::class, 'showShopeeForm'])->name('shopee');
        Route::post('/shopee/process', [AdminController::class, 'processShopeeOrder'])->name('shopee.process');
        Route::get('/instances', [AdminController::class, 'instances'])->name('instances');
        Route::get('/monitoring', [AdminController::class, 'monitoring'])->name('monitoring');
        Route::get('/reports', [AdminController::class, 'reports'])->name('reports');
        Route::get('/reports/preview', [AdminController::class, 'previewReport'])->name('reports.preview');
        Route::get('/reports/export', [AdminController::class, 'exportReport'])->name('reports.export');
        Route::post('/instances/{id}/status', [AdminController::class, 'updateInstanceStatus'])->name('instances.status');
        Route::post('/instances/{id}/panel-url', [AdminController::class, 'updateInstancePanelUrl'])->name('instances.panel-url');
        Route::post('/instances/{id}/ssh-username', [AdminController::class, 'updateInstanceSshUsername'])->name('instances.ssh-username');
        Route::post('/instances/{id}/renewal-price', [AdminController::class, 'updateInstanceRenewalPrice'])->name('instances.renewal-price');
        Route::post('/instances/{id}/suspend', [AdminController::class, 'suspendInstance'])->name('instances.suspend');
        Route::post('/instances/{id}/unsuspend', [AdminController::class, 'unsuspendInstance'])->name('instances.unsuspend');
        Route::post('/instances/{id}/terminate', [AdminController::class, 'terminateInstance'])->name('instances.terminate');
        Route::get('/packages', [AdminController::class, 'packages'])->name('packages.index');
        Route::post('/packages', [AdminController::class, 'storePackage'])->name('packages.store');
        Route::put('/packages/{id}', [AdminController::class, 'updatePackage'])->name('packages.update');
        Route::delete('/packages/{id}', [AdminController::class, 'destroyPackage'])->name('packages.destroy');
        Route::get('/customers', [AdminController::class, 'customers'])->name('customers');
        Route::get('/tickets', [AdminController::class, 'tickets'])->name('tickets');
        Route::get('/tickets/{id}', [AdminController::class, 'showTicket'])->name('tickets.show');
        Route::post('/tickets/{id}/reply', [AdminController::class, 'replyTicket'])->name('tickets.reply');
        Route::post('/tickets/{id}/assign', [AdminController::class, 'assignTicket'])->name('tickets.assign');
        Route::post('/tickets/{id}/complete-reinstall', [AdminController::class, 'completeReinstall'])->name('tickets.complete-reinstall');

        // ============================================================
        // PHASE 1 - Pengaturan Sistem, Branding, dan Maintenance
        // ============================================================
        Route::get('/settings', [AdminSettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings/brand', [AdminSettingsController::class, 'updateBrand'])->name('settings.brand');
        Route::post('/settings/company', [AdminSettingsController::class, 'updateCompany'])->name('settings.company');
        Route::post('/settings/support', [AdminSettingsController::class, 'updateSupport'])->name('settings.support');
        Route::post('/settings/notification', [AdminSettingsController::class, 'updateNotification'])->name('settings.notification');
        Route::post('/settings/notification/test', [AdminSettingsController::class, 'testNotification'])->middleware('throttle:5,1')->name('settings.notification.test');
        Route::post('/settings/maintenance', [AdminSettingsController::class, 'updateMaintenance'])->name('settings.maintenance');
        Route::post('/settings/bypass-token', [AdminSettingsController::class, 'regenerateBypassToken'])->name('settings.bypass-token');
        Route::post('/settings/components/{id}', [AdminSettingsController::class, 'updateComponent'])->name('settings.components.update');

        Route::get('/maintenance', [AdminMaintenanceController::class, 'index'])->name('maintenance.index');
        Route::post('/maintenance', [AdminMaintenanceController::class, 'store'])->name('maintenance.store');
        Route::put('/maintenance/{id}', [AdminMaintenanceController::class, 'update'])->name('maintenance.update');
        Route::post('/maintenance/{id}/start', [AdminMaintenanceController::class, 'start'])->name('maintenance.start');
        Route::post('/maintenance/{id}/complete', [AdminMaintenanceController::class, 'complete'])->name('maintenance.complete');
        Route::post('/maintenance/{id}/cancel', [AdminMaintenanceController::class, 'cancel'])->name('maintenance.cancel');
        Route::post('/maintenance/{id}/notify', [AdminMaintenanceController::class, 'notify'])->name('maintenance.notify');

        // ============================================================
        // PHASE 2 - Legal, AUP, dan Notifikasi Insiden
        // ============================================================
        Route::get('/abuse', [AdminAbuseController::class, 'index'])->name('abuse.index');
        Route::post('/abuse', [AdminAbuseController::class, 'store'])->name('abuse.store');
        Route::post('/abuse/{id}/notify', [AdminAbuseController::class, 'notify'])->name('abuse.notify');
        Route::post('/abuse/{id}/resolve', [AdminAbuseController::class, 'resolve'])->name('abuse.resolve');

        Route::get('/templates', [AdminTemplateController::class, 'index'])->name('templates.index');
        Route::put('/templates/{id}', [AdminTemplateController::class, 'update'])->name('templates.update');

        Route::get('/broadcast', [AdminBroadcastController::class, 'index'])->name('broadcast.index');
        Route::post('/broadcast', [AdminBroadcastController::class, 'send'])->name('broadcast.send');
        Route::get('/broadcast/export-contacts', [AdminBroadcastController::class, 'exportContacts'])->name('broadcast.export');

        // ============================================================
        // WhatsApp Gateway Management & Test
        // ============================================================
        Route::get('/whatsapp', [AdminWhatsAppController::class, 'index'])->name('whatsapp.index');
        Route::post('/whatsapp/settings', [AdminWhatsAppController::class, 'updateSettings'])->name('whatsapp.settings');
        Route::post('/whatsapp/settings/forget', [AdminWhatsAppController::class, 'forgetSetting'])->name('whatsapp.settings.forget');
        Route::get('/whatsapp/status', [AdminWhatsAppController::class, 'status'])->name('whatsapp.status');
        Route::post('/whatsapp/test', [AdminWhatsAppController::class, 'test'])->name('whatsapp.test');

        // ============================================================
        // PHASE 4 - Payment Gateway dan Webhook Log
        // ============================================================
        Route::get('/payment-gateways', [AdminPaymentGatewayController::class, 'index'])->name('gateways.index');
        Route::put('/payment-gateways/{id}', [AdminPaymentGatewayController::class, 'update'])->name('gateways.update');
        Route::get('/webhooks', [AdminWebhookLogController::class, 'index'])->name('webhooks.index');
        Route::post('/webhooks/{id}/replay', [AdminWebhookLogController::class, 'replay'])
            ->middleware('throttle:10,1')->name('webhooks.replay');

        // ============================================================
        // PHASE 5 - Fulfillment Workboard
        // ============================================================
        Route::get('/fulfillment', [AdminFulfillmentController::class, 'index'])->name('fulfillment.index');
        Route::post('/fulfillment/{id}/purchase', [AdminFulfillmentController::class, 'recordPurchase'])->name('fulfillment.purchase');
        Route::post('/fulfillment/{id}/stage', [AdminFulfillmentController::class, 'moveStage'])->name('fulfillment.stage');
        Route::post('/fulfillment/{id}/steps/{step}', [AdminFulfillmentController::class, 'toggleStep'])
            ->where('step', '[a-z_]+')->name('fulfillment.step');

        // ============================================================
        // PHASE 6 - Billing Center
        // ============================================================
        Route::get('/billing', [AdminBillingCenterController::class, 'index'])->name('billing.index');
        Route::post('/billing/invoices/{id}/resend', [AdminBillingCenterController::class, 'resendInvoice'])
            ->middleware('throttle:10,1')->name('billing.invoices.resend');
        Route::post('/billing/subscriptions/{id}/auto-renew', [AdminBillingCenterController::class, 'toggleAutoRenew'])->name('billing.subscriptions.auto-renew');
        Route::post('/billing/refunds', [AdminBillingCenterController::class, 'storeRefund'])->name('billing.refunds.store');
        Route::post('/billing/refunds/{id}/approve', [AdminBillingCenterController::class, 'approveRefund'])->name('billing.refunds.approve');
        Route::post('/billing/refunds/{id}/cancel', [AdminBillingCenterController::class, 'cancelRefund'])->name('billing.refunds.cancel');
        Route::post('/billing/coupons', [AdminBillingCenterController::class, 'storeCoupon'])->name('billing.coupons.store');
        Route::put('/billing/coupons/{id}', [AdminBillingCenterController::class, 'updateCoupon'])->name('billing.coupons.update');
        Route::post('/billing/taxes', [AdminBillingCenterController::class, 'storeTaxRate'])->name('billing.taxes.store');
        Route::put('/billing/taxes/{id}', [AdminBillingCenterController::class, 'updateTaxRate'])->name('billing.taxes.update');
        Route::post('/billing/credits/adjust', [AdminBillingCenterController::class, 'adjustCredit'])->name('billing.credits.adjust');

        // ============================================================
        // PHASE 7 - Impersonation dan Audit Log
        // ============================================================
        Route::post('/customers/{id}/impersonate', [AdminImpersonationController::class, 'start'])
            ->middleware('throttle:10,1')->name('impersonate.start');
        Route::get('/audit-logs', [AdminAuditLogController::class, 'index'])->name('audit.index');

        // ============================================================
        // PHASE 8 - Catatan Pembelian Supplier
        // ============================================================
        Route::get('/supplier-purchases', [AdminSupplierPurchaseController::class, 'index'])->name('supplier.index');
        Route::post('/supplier-purchases', [AdminSupplierPurchaseController::class, 'store'])->name('supplier.store');
        Route::put('/supplier-purchases/{id}', [AdminSupplierPurchaseController::class, 'update'])->name('supplier.update');
        Route::delete('/supplier-purchases/{id}', [AdminSupplierPurchaseController::class, 'destroy'])->name('supplier.destroy');
    });
});
