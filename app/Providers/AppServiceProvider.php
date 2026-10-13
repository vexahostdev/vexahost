<?php

namespace App\Providers;

use App\Models\AbuseCase;
use App\Models\MaintenanceWindow;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Observers\LinkedAccountObserver;
use App\Services\ImpersonationService;
use App\Services\MaintenanceService;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Singleton agar cache tingkat request di dalamnya benar-benar dipakai ulang.
        $this->app->singleton(SettingsService::class, fn () => new SettingsService());
        $this->app->singleton(
            MaintenanceService::class,
            fn ($app) => new MaintenanceService($app->make(SettingsService::class))
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Cegah Chrome warning: "was preloaded using link preload but not used" akibat Cloudflare Early Hints / Rocket Loader
        Vite::usePreloadTagAttributes(fn () => false);

        if (str_contains(request()->header('x-forwarded-proto', ''), 'https') || request()->isSecure()) {
            URL::forceScheme('https');
        }

        // Terapkan pengaturan WhatsApp Gateway dari database jika ada
        \App\Services\WhatsAppSettings::apply();

        // Setiap perubahan identitas masuk ikut dikirim ke VexaHost WA Gateway,
        // dari jalur mana pun perubahannya datang (akun tertaut, hanya auth).
        User::observe(LinkedAccountObserver::class);

        // PHASE 7 - logout saat sedang masuk sebagai pelanggan tetap menutup
        // catatan sesi impersonation. Event Logout dipicu sebelum sesi dihapus,
        // sehingga data impersonation masih dapat dibaca di sini.
        Event::listen(Logout::class, function () {
            $request = request();

            if (!$request->hasSession()) {
                return;
            }

            $data = $request->session()->get(ImpersonationService::SESSION_KEY);

            if (is_array($data) && !empty($data['log_id'])) {
                app(ImpersonationService::class)->closeLog((int) $data['log_id'], 'logout');
            }
        });

        // Share open ticket count with dashboard layout to avoid N+1 query in blade
        View::composer('layouts.dashboard', function ($view) {
            $userOpenTickets = 0;
            if (Auth::check()) {
                $userOpenTickets = SupportTicket::where('organization_id', Auth::user()->current_organization_id)
                    ->whereIn('status', ['open', 'in_progress'])
                    ->count();
            }
            $view->with('userOpenTickets', $userOpenTickets);
        });

        // Share admin sidebar counts with admin layout.
        // Cached for 60 seconds to avoid 8+ COUNT queries on every page load.
        View::composer('layouts.admin', function ($view) {
            $counts = \Illuminate\Support\Facades\Cache::remember('admin.sidebar.counts', 60, function () {
                $data = [];
                $data['adminPendingOrders'] = Order::where('status', 'pending')->count();
                $data['adminOpenTickets'] = SupportTicket::whereIn('status', ['open', 'in_progress'])->count();
                $data['adminOpenAbuse'] = Schema::hasTable('abuse_cases') ? AbuseCase::unresolved()->count() : 0;
                $data['adminRunningMaintenance'] = Schema::hasTable('maintenance_windows') ? MaintenanceWindow::active()->count() : 0;
                $data['adminFulfillmentWorking'] = Order::whereIn('status', ['paid', 'failed', 'provisioning'])->count();
                $data['adminPendingRefunds'] = \App\Models\Refund::where('status', 'pending')->count();
                $data['adminSupplierUrgent'] = Schema::hasTable('supplier_purchases')
                    ? app(\App\Services\SupplierCoverageService::class)->urgentCount()
                    : 0;
                $data['adminFailedWebhooks'] = Schema::hasColumn('webhook_events', 'http_status')
                    ? WebhookEvent::where('processing_status', 'failed')->count()
                    : 0;
                return $data;
            });

            foreach ($counts as $key => $value) {
                $view->with($key, $value);
            }
        });

        // Listener MessageSending: pengaman untuk memastikan Reply-To selalu terisi
        Event::listen(\Illuminate\Mail\Events\MessageSending::class, function (\Illuminate\Mail\Events\MessageSending $event) {
            if (empty($event->message->getReplyTo())) {
                $replyTo = \App\Support\MailIdentity::replyTo();
                $event->message->replyTo(new \Symfony\Component\Mime\Address($replyTo['address'], $replyTo['name']));
            }
        });

        // Branding dibagikan ke SELURUH view agar nama merek, logo, dan warna
        // tidak lagi ditulis langsung di blade. Diambil dari tabel settings (Phase 1).
        // View::share dieksekusi sekali per request (bukan per @include/@component).
        try {
            $settings = app(SettingsService::class);
            View::share('brand', [
                'name' => $settings->get('brand_name', config('app.name', 'VexaHost')),
                'tagline' => $settings->get('brand_tagline', ''),
                'logo' => $settings->get('brand_logo_path')
                    ? asset('storage/' . $settings->get('brand_logo_path'))
                    : asset('images/logo.png'),
                'favicon' => $settings->get('brand_favicon_path')
                    ? asset('storage/' . $settings->get('brand_favicon_path'))
                    : asset('images/logo.png'),
                'accent' => $settings->get('brand_accent_color', '#4A6FA5'),
                'support_email' => \App\Support\MailIdentity::contactEmail(),
                'support_whatsapp' => $settings->get('support_whatsapp', ''),
            ]);
        } catch (\Throwable) {
            View::share('brand', [
                'name' => config('app.name', 'VexaHost'),
                'tagline' => '',
                'logo' => asset('images/logo.png'),
                'favicon' => asset('images/logo.png'),
                'accent' => '#4A6FA5',
                'support_email' => \App\Support\MailIdentity::contactEmail(),
                'support_whatsapp' => '',
            ]);
        }

        // Spanduk maintenance terjadwal di dasbor klien.
        View::composer('layouts.dashboard', function ($view) {
            $view->with('upcomingMaintenance', app(MaintenanceService::class)->upcomingWindows());
        });
    }
}
