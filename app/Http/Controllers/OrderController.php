<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Models\TermsAcceptance;
use App\Models\User;
use App\Models\VpsSpec;
use App\Models\WebhookEvent;
use App\Services\OrderStateMachine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function __construct(protected OrderStateMachine $orderStateMachine)
    {
    }

    public function checkout(Request $request, $spec_id = null)
    {
        // Harga modal disembunyikan: halaman ini mengirim data paket ke browser
        // lewat Js::from(), sehingga seluruh atribut dapat dibaca dari view-source.
        // Tidak disembunyikan di level model karena form edit paket admin membutuhkannya.
        $specs = VpsSpec::orderBy('sell_price', 'asc')->get()
            ->each->makeHidden(['cost_price']);
        $defaultSpec = $specs->firstWhere('is_active', true) ?? $specs->first();
        $hasExplicitSpec = !empty($spec_id) || $request->has('spec_id');
        $selectedSpecId = $spec_id ?? $request->query('spec_id', $defaultSpec->id ?? 1);
        $selectedSpec = $specs->firstWhere('id', $selectedSpecId) ?? $defaultSpec;

        return view('order.checkout', compact('specs', 'selectedSpec', 'hasExplicitSpec'));
    }

    public function quickLogin(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        $login = trim((string) $request->input('login'));
        $normalizedLogin = strtolower(preg_replace('/@([a-zA-Z0-9.-]+),([a-zA-Z]{2,})/i', '@$1.$2', $login));
        $user = User::whereRaw('LOWER(email) = ?', [$normalizedLogin])
            ->orWhereRaw('LOWER(username) = ?', [strtolower($login)])
            ->first();

        if (!$user || !Hash::check($request->input('password'), $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email/username atau password tidak sesuai.',
            ], 422);
        }

        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put('2fa.pending_user_id', $user->id);
            $request->session()->put('2fa.remember', true);
            return response()->json([
                'success' => false,
                'requires_2fa' => true,
                'redirect' => route('two-factor.challenge'),
                'message' => 'Autentikasi dua faktor diperlukan.',
            ], 200);
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        $request->session()->put('2fa.passed', true);

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone ?? '',
            ],
            'csrf_token' => csrf_token(),
            'message' => 'Login berhasil.',
        ]);
    }

    public function store(Request $request)
    {
        $specId = $request->input('vps_spec_id');
        $spec = VpsSpec::find($specId);
        $isAiPackage = $spec && $spec->isAiPackage();
        $isDbPackage = $spec && $spec->isDatabasePackage();

        if ($isAiPackage) {
            $cleanName = Str::slug($spec->name);
            $randomSuffix = Str::lower(Str::random(4));
            $request->merge([
                'hostname' => $request->input('hostname') ?: ('vx-ai-' . substr($cleanName, 0, 16) . '-' . $randomSuffix),
                'control_panel' => $request->input('control_panel') ?: ($spec->default_stack ?: 'vscode_server'),
                'provider' => $request->input('provider') ?: $spec->defaultProvider(),
                'datacenter_location' => $request->input('datacenter_location') ?: 'indonesia',
                'os' => $request->input('os') ?: 'ubuntu2404',
                'billing_cycle' => $request->input('billing_cycle') ?: 'monthly',
            ]);
        } elseif ($isDbPackage) {
            $cleanName = Str::slug($spec->name);
            $randomSuffix = Str::lower(Str::random(4));
            $request->merge([
                'hostname' => $request->input('hostname') ?: ('vx-db-' . substr($cleanName, 0, 16) . '-' . $randomSuffix),
                'control_panel' => $request->input('control_panel') ?: ($spec->default_stack ?: 'managed_database'),
                'provider' => $request->input('provider') ?: $spec->defaultProvider(),
                'datacenter_location' => $request->input('datacenter_location') ?: 'indonesia',
                'os' => $request->input('os') ?: 'ubuntu2404',
                'billing_cycle' => $request->input('billing_cycle') ?: 'monthly',
                'db_engine' => $request->input('db_engine') ?: 'postgres',
                'db_manager' => $request->input('db_manager') ?: 'cloudbeaver',
            ]);
        }

        $validated = $request->validate([
            'vps_spec_id' => 'required|exists:vps_specs,id',
            'control_panel' => 'required|in:none,coolify,dokploy,aapanel,cloudpanel,docker,cyberpanel,hestiacp,hermes_agent,openclaw,omniroute,9router,agent_zero,n8n,ollama,anythingllm,librechat,vscode_server,gitea_forgejo,uptime_kuma,netdata_beszel,wordpress,ghost,strapi_directus,prestashop_bagisto,claude_opencode,dify_ollama,managed_database',
            'provider' => 'required|in:tencent,cloudeka',
            'datacenter_location' => 'required|in:singapore,indonesia',
            'os' => 'required|string',
            'billing_cycle' => 'nullable|string|in:monthly',
            'hostname' => ['required', 'string', 'max:63', 'regex:/^[A-Za-z0-9][A-Za-z0-9-]*$/'],
            'root_password' => 'nullable|string|max:255',
            'payment_method' => 'required|string|in:online_payment,midtrans_snap,qris,lynk,bca_va,mandiri_va,bni_va,bri_va,cimb_va,permata_va,other_va,bsi_va,danamon_va,seabank_va,credit_card,gopay,shopeepay,ovo,dana,indomaret,astrapay,akulaku',
            'db_engine' => 'nullable|string|in:postgres,mysql,redis,mongodb,vector',
            'db_manager' => 'nullable|string|in:cloudbeaver,cli_only',
            // Guest fields if not logged in
            'full_name' => 'nullable|string|max:255',
            'username' => 'nullable|string|min:3|max:50|alpha_dash|unique:users,username',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:25',
            'password' => 'nullable|string|min:8',
            'redirect_to_dashboard' => 'nullable',
            // PHASE 2 - persetujuan ketentuan bersifat WAJIB (clickwrap).
            // Divalidasi di sisi server agar tidak dapat dilewati dari peramban.
            'terms_accepted' => 'accepted',
        ], [
            'terms_accepted.accepted' => 'Anda wajib menyetujui Ketentuan Layanan dan Ketentuan Penggunaan sebelum melanjutkan pemesanan.',
        ]);

        // PHASE 4 - metode pembayaran wajib milik gateway yang aktif. UI checkout
        // sudah mengunci metode nonaktif, pemeriksaan ini menutup jalur kirim langsung.
        if (!PaymentGateway::isMethodActive($validated['payment_method'])) {
            $methodMessage = 'Metode pembayaran ini sedang tidak tersedia. Silakan pilih metode lain.';
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $methodMessage,
                    'errors' => ['payment_method' => [$methodMessage]],
                ], 422);
            }
            return back()->withInput()->withErrors(['payment_method' => $methodMessage]);
        }

        $spec = VpsSpec::findOrFail($validated['vps_spec_id']);
        if (!$spec->is_active) {
            $inactiveMessage = 'Paket layanan ini sedang dinonaktifkan sementara oleh tim VexaHost untuk pemeliharaan sistem.';
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $inactiveMessage,
                    'errors' => ['vps_spec_id' => [$inactiveMessage]],
                ], 422);
            }
            return back()->withInput()->withErrors(['vps_spec_id' => $inactiveMessage]);
        }

        if (!$spec->isProviderAllowed($validated['provider'])) {
            $msg = in_array('cloudeka', $spec->allowedProviders(), true)
                ? "Paket {$spec->name} hanya tersedia di provider Cloudeka by Lintasarta (Datacenter Indonesia)."
                : "Paket {$spec->name} hanya tersedia di provider Tencent Cloud.";
            return back()->withInput()->withErrors([
                'provider' => $msg,
            ]);
        }

        $providerRegions = ['tencent' => ['indonesia', 'singapore'], 'cloudeka' => ['indonesia']];
        $providerOs = Order::operatingSystems()[$validated['provider']] ?? [];
        validator($validated, [
            'datacenter_location' => [Rule::in($providerRegions[$validated['provider']])],
            'os' => [Rule::in(array_keys($providerOs))],
        ])->validate();

        $user = Auth::user();

        // If not logged in, register or authenticate
        if (!$user) {
            $request->validate([
                'full_name' => 'required|string|max:255',
                'username' => 'nullable|string|min:3|max:50|alpha_dash|unique:users,username',
                'email' => 'required|email|max:255',
                'phone' => 'nullable|string|max:25',
                'password' => 'required|string|min:8',
            ]);

            $cleanEmail = strtolower(trim((string)$validated['email']));
            $cleanEmail = preg_replace('/@([a-zA-Z0-9.-]+),([a-zA-Z]{2,})/i', '@$1.$2', $cleanEmail);

            $existingUser = User::whereRaw('LOWER(email) = ?', [$cleanEmail])->first();
            if ($existingUser) {
                if (!Hash::check($validated['password'], $existingUser->password)) {
                    if ($request->expectsJson()) {
                        return response()->json([
                            'message' => 'Email ini sudah terdaftar. Silakan pilih tab "Sudah Punya Akun" untuk login atau masukkan password yang sesuai.',
                            'errors' => [
                                'email' => ['Email ini sudah terdaftar. Silakan login atau gunakan password yang sesuai.']
                            ]
                        ], 422);
                    }
                    return back()->withInput()->withErrors([
                        'email' => 'Email ini sudah terdaftar. Silakan login terlebih dahulu atau gunakan password yang benar.',
                    ]);
                }
                $user = $existingUser;
            } else {
                $requestedUsername = $request->input('username');
                if (!empty($requestedUsername)) {
                    $generatedUsername = strtolower(trim($requestedUsername));
                } else {
                    $generatedUsername = 'vx_' . Str::lower(Str::random(7));
                    while (User::where('username', $generatedUsername)->exists()) {
                        $generatedUsername = 'vx_' . Str::lower(Str::random(7));
                    }
                }

                $user = User::create([
                    'full_name' => $validated['full_name'],
                    'username' => $generatedUsername,
                    'email' => $cleanEmail,
                    'phone' => $validated['phone'] ?? null,
                    'password' => Hash::make($validated['password']),
                    'channel' => 'website',
                ]);
            }

            Auth::login($user);
            $request->session()->regenerate();
            $request->session()->put('2fa.passed', true);
        } else {
            // Update phone if previously empty
            if (!empty($validated['phone']) && empty($user->phone)) {
                $user->update(['phone' => $validated['phone']]);
            }
        }

        // Checkout is public (guest route), so ensure a tenant context exists
        // before creating any billable resource.
        $organization = $user->currentOrganization;
        if (!$organization) {
            $organization = $user->organizations()->first() ?? $user->createPersonalOrganization();
            $user->switchToOrganization($organization);
        }

        if ($organization->quota && !$organization->quota->canAddVps()) {
            return back()->withInput()->with('error', 'Kuota VPS organisasi sudah penuh. Upgrade kuota atau pilih organisasi lain.');
        }

        $spec = VpsSpec::findOrFail($validated['vps_spec_id']);

        $cycle = 'monthly';
        $netAmount = (float) $spec->sell_price;
        $setupFee = 0;

        // PHASE 2 - bukti persetujuan dicatat bersama order dalam satu transaksi.
        $termsVersion = config('legal.terms_version');
        $termsIp = $request->ip();
        $termsAgent = $request->userAgent();

        // Bungkus dalam transaction agar order + invoice + status history atomic.
        $order = DB::transaction(function () use (
            $user, $organization, $spec, $validated, $cycle, $netAmount, $setupFee,
            $termsVersion, $termsIp, $termsAgent
        ) {
            $order = Order::create([
                'customer_id' => $user->id,
                'organization_id' => $organization->id,
                'vps_spec_id' => $spec->id,
                'control_panel' => $validated['control_panel'],
                'db_engine' => $spec->isDatabasePackage() ? ($validated['db_engine'] ?? 'postgres') : null,
                'db_manager' => $spec->isDatabasePackage() ? ($validated['db_manager'] ?? 'cloudbeaver') : null,
                'db_name' => $spec->isDatabasePackage() ? 'vexadb_production' : null,
                'db_user' => $spec->isDatabasePackage() ? 'admin_vexa' : null,
                'db_password' => $spec->isDatabasePackage() ? ($validated['root_password'] ?? null) : null,
                'db_port' => $spec->isDatabasePackage() ? match($validated['db_engine'] ?? 'postgres') {
                    'mysql' => 3306,
                    'redis' => 6379,
                    'mongodb' => 27017,
                    'vector' => 6333,
                    default => 5432,
                } : null,
                'provider' => $validated['provider'],
                'hostname' => strtolower($validated['hostname']),
                'root_password' => $validated['root_password'] ?? null,
                'datacenter_location' => $validated['datacenter_location'],
                'os' => $validated['os'],
                'billing_cycle' => $cycle,
                'status' => 'pending',
                'channel' => 'website',
                'payment_method' => $validated['payment_method'],
                'amount' => $netAmount,
                'setup_fee' => $setupFee,
                'paid_at' => null,
                'starts_at' => null,
                'expires_at' => null,
                'last_status_change_at' => now(),
                'terms_version' => $termsVersion,
            ]);

            // Baris ini adalah alat bukti. Tanpa versi yang tersimpan, kita tidak
            // dapat membuktikan naskah mana yang disetujui bila isinya berubah.
            TermsAcceptance::create([
                'user_id' => $user->id,
                'order_id' => $order->id,
                'terms_version' => $termsVersion,
                'accepted_at' => now(),
                'ip_address' => $termsIp,
                'user_agent' => $termsAgent,
            ]);

            $invoiceNumber = 'INV-' . date('Ym') . '-' . str_pad($order->id, 4, '0', STR_PAD_LEFT);
            Invoice::create([
                'order_id' => $order->id,
                'organization_id' => $organization->id,
                'invoice_number' => $invoiceNumber,
                'amount' => $netAmount,
                'status' => 'sent',
                'issued_at' => now(),
                'due_at' => now()->addDays(1),
                'paid_at' => null,
            ]);

            // Catat entry awal di history (from_status = null berarti "created").
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => null,
                'to_status' => 'pending',
                'reason' => 'Order dibuat, menunggu pembayaran.',
                'actor_user_id' => $user->id,
                'actor_type' => 'customer',
                'ip_address' => request()->ip(),
                'metadata' => [
                    'amount' => $netAmount,
                    'payment_method' => $validated['payment_method'],
                    'billing_cycle' => $cycle,
                ],
                'created_at' => now(),
            ]);

            return $order;
        });

        // Simpan ID order aktif di session browser agar bisa dipulihkan saat kembali dari Lynk/gateway
        session(['last_order_id' => $order->id]);

        // VPS Instance TIDAK dibuat di sini.
        // Instance hanya dibuat oleh Admin setelah payment terkonfirmasi via webhook.

        $snapToken = null;
        $snapRedirectUrl = null;
        $xenditInvoiceUrl = null;

        // Dispatch ke Gateway yang sesuai dengan metode pembayaran yang dipilih
        if (PaymentGateway::isMidtransMethod($order->payment_method)) {
            $snapResult = \App\Services\Payments\MidtransService::createSnapTransaction($order);
            if (!empty($snapResult['success'])) {
                $snapToken = $snapResult['token'] ?? null;
                $snapRedirectUrl = $snapResult['redirect_url'] ?? null;
            }
        } elseif (PaymentGateway::isXenditMethod($order->payment_method) && \App\Services\Payments\XenditService::isConfigured()) {
            $xenditResult = \App\Services\Payments\XenditService::createInvoice($order, $order->payment_method);
            if (!empty($xenditResult['success'])) {
                $xenditInvoiceUrl = $xenditResult['invoice_url'] ?? null;
            }
        }

        $redirectUrl = ($order->payment_method === 'lynk' && !empty($spec->payment_url))
            ? $spec->payment_url
            : ($xenditInvoiceUrl ?: ($snapRedirectUrl ?: route('order.payment', $order->id)));

        // If user explicitly chose to postpone payment and view dashboard (locked state)
        if ($request->filled('redirect_to_dashboard') && $request->input('redirect_to_dashboard') == '1') {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'order_id' => $order->id,
                    'redirect_url' => route('dashboard.index'),
                ]);
            }

            return redirect()->route('dashboard.index')
                ->with('warning', 'Pesanan VPS Anda telah tersimpan! Layanan saat ini TERKUNCI menunggu pembayaran. Silakan selesaikan pembayaran untuk mengaktifkan.');
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'order_id' => $order->id,
                'invoice_url' => $xenditInvoiceUrl,
                'snap_token' => $snapToken,
                'snap_redirect_url' => $snapRedirectUrl,
                'redirect_url' => $redirectUrl,
            ]);
        }

        // Jika metode pembayaran Lynk dan paket memiliki payment_url, langsung alihkan ke Lynk checkout
        if ($order->payment_method === 'lynk' && !empty($spec->payment_url)) {
            return redirect()->away($spec->payment_url);
        }

        // Jika ada Xendit Invoice URL, langsung alihkan ke halaman invoice Xendit
        if ($xenditInvoiceUrl) {
            return redirect()->away($xenditInvoiceUrl);
        }

        // Jika metode Midtrans dan ada snap redirect url, alihkan langsung ke halaman pembayaran Midtrans
        if ($snapRedirectUrl) {
            return redirect()->away($snapRedirectUrl);
        }

        return redirect()->route('order.payment', $order->id)
            ->with('info', 'Pesanan berhasil dibuat. Silakan selesaikan pembayaran sesuai metode yang Anda pilih.');
    }

    public function payment($id)
    {
        $order = Order::with(['customer', 'vpsSpec', 'invoice'])->findOrFail($id);

        if (!Auth::check() || ($order->customer_id !== Auth::id() && !Auth::user()->is_admin)) {
            abort(403);
        }

        // Cek sinkronisasi real-time ke Xendit / Midtrans jika order masih pending
        if (!$order->paid_at) {
            $revalidateResponse = $this->revalidatePendingOrder($order);
            if ($revalidateResponse) {
                return $revalidateResponse;
            }

            if (PaymentGateway::isXenditMethod($order->payment_method)) {
                \App\Services\Payments\XenditService::checkAndSyncStatus($order);
            } elseif (PaymentGateway::isMidtransMethod($order->payment_method)) {
                \App\Services\Payments\MidtransService::checkAndSyncStatus($order);
            }
            $order->refresh();
        }

        // If order already paid, redirect directly to success callback view
        if ($order->paid_at) {
            return redirect()->route('order.success', $order->id);
        }

        // Untuk Xendit, jika sudah ada invoice URL, langsung buka atau alihkan ke status
        if (PaymentGateway::isXenditMethod($order->payment_method)) {
            $tx = PaymentTransaction::where('order_id', $order->id)->where('provider', 'xendit')->latest()->first();
            $invoiceUrl = $tx?->raw_payload['invoice_url'] ?? null;
            if ($invoiceUrl) {
                return redirect()->away($invoiceUrl);
            }
            return redirect()->route('order.payment.status', $order->id);
        }

        // Untuk metode pembayaran Midtrans, alihkan langsung ke halaman status pembayaran
        if (PaymentGateway::isMidtransMethod($order->payment_method)) {
            return redirect()->route('order.payment.status', $order->id);
        }

        $paymentMethodNames = [
            'online_payment' => 'Pembayaran Otomatis Online (Instan)',
            'qris' => 'QRIS Instan',
            'indomaret' => 'Indomaret Retail',
            'astrapay' => 'AstraPay E-Wallet',
            'akulaku' => 'Akulaku PayLater',
            'midtrans_snap' => 'QRIS Otomatis',
            'lynk' => 'Lynk.id Checkout',
            'bca_va' => 'BCA Virtual Account',
            'bsi_va' => 'BSI Virtual Account',
            'danamon_va' => 'Danamon Virtual Account',
            'seabank_va' => 'SeaBank Virtual Account',
            'credit_card' => 'Kartu Kredit / Debit',
            'mandiri_va' => 'Mandiri Virtual Account',
            'bni_va' => 'BNI Virtual Account',
            'bri_va' => 'BRI Virtual Account',
            'permata_va' => 'Permata Virtual Account',
            'cimb_va' => 'CIMB Niaga VA',
            'other_va' => 'Virtual Account Bank Lainnya',
            'gopay' => 'GoPay',
            'ovo' => 'OVO',
            'dana' => 'DANA',
            'shopeepay' => 'ShopeePay',
        ];

        $devSimulateEnabled = config('app.env') === 'local' && (bool) config('vexahost.dev_simulate_payment', false);

        // Generate dynamic QRIS khusus nominal pesanan ini
        $qrisService = app(\App\Services\QrisService::class);
        $qrisDataUri = $qrisService->generateDataUri((float) $order->amount);
        $qrisPayload = $qrisService->generatePayload((float) $order->amount);
        $elapsedSeconds = (int) max(0, now()->diffInSeconds($order->created_at));

        // Integrasi Midtrans Snap
        $isMidtrans = PaymentGateway::isMidtransMethod($order->payment_method);
        $snapToken = null;
        $snapRedirectUrl = null;
        $snapJsUrl = null;
        $snapClientKey = null;
        $snapError = null;

        if ($isMidtrans) {
            $snapResult = \App\Services\Payments\MidtransService::createSnapTransaction($order);
            if ($snapResult['success']) {
                $snapToken = $snapResult['token'];
                $snapRedirectUrl = $snapResult['redirect_url'];
            } else {
                $snapError = $snapResult['error'] ?? 'Gagal membuat sesi pembayaran Midtrans.';
            }
            $snapJsUrl = \App\Services\Payments\MidtransService::getSnapJsUrl();
            $snapClientKey = \App\Services\Payments\MidtransService::getClientKey();
        }

        return view('order.payment-gateway', compact(
            'order',
            'devSimulateEnabled',
            'qrisDataUri',
            'qrisPayload',
            'elapsedSeconds',
            'isMidtrans',
            'snapToken',
            'snapRedirectUrl',
            'snapJsUrl',
            'snapClientKey',
            'snapError'
        ));
    }

    /**
     * Halaman "menunggu konfirmasi pembayaran" — read-only.
     * Frontend polling paymentStatusJson secara berkala; ketika paid_at terisi
     * (oleh webhook atau sync otomatis) baru redirect ke success page.
     */
    public function paymentStatus($id)
    {
        $order = Order::with(['customer', 'vpsSpec', 'invoice'])->findOrFail($id);

        if (!Auth::check() && session('last_order_id') == $order->id && $order->customer_id) {
            Auth::loginUsingId($order->customer_id);
        }

        if (!Auth::check() || ($order->customer_id !== Auth::id() && !Auth::user()->is_admin)) {
            abort(403);
        }

        // Cek sinkronisasi real-time ke Midtrans jika masih pending
        if (!$order->paid_at) {
            $revalidateResponse = $this->revalidatePendingOrder($order);
            if ($revalidateResponse) {
                return $revalidateResponse;
            }

            if (PaymentGateway::isMidtransMethod($order->payment_method)) {
                \App\Services\Payments\MidtransService::checkAndSyncStatus($order);
            }
            $order->refresh();
        }

        if ($order->paid_at) {
            return redirect()->route('order.success', $order->id);
        }

        $midtransTx = \App\Models\PaymentTransaction::where('order_id', $order->id)
            ->where('provider', 'midtrans')
            ->latest()
            ->first();
        $snapToken = $midtransTx?->raw_payload['snap_token'] ?? null;
        $snapRedirectUrl = $midtransTx?->raw_payload['snap_redirect_url'] ?? null;

        if (PaymentGateway::isMidtransMethod($order->payment_method) && empty($snapToken)) {
            $snapResult = \App\Services\Payments\MidtransService::createSnapTransaction($order);
            if (!empty($snapResult['success'])) {
                $snapToken = $snapResult['token'] ?? null;
                $snapRedirectUrl = $snapResult['redirect_url'] ?? null;
            }
        }

        $snapJsUrl = \App\Services\Payments\MidtransService::getSnapJsUrl();
        $snapClientKey = \App\Services\Payments\MidtransService::getClientKey();

        return view('order.payment-pending', compact(
            'order',
            'snapToken',
            'snapRedirectUrl',
            'snapJsUrl',
            'snapClientKey'
        ));
    }

    /**
     * JSON endpoint untuk polling status.
     * Secara otomatis menyinkronkan status dengan Core API Midtrans jika belum lunas.
     */
    public function paymentStatusJson($id)
    {
        $order = Order::with('invoice')->findOrFail($id);

        if (!Auth::check() && session('last_order_id') == $order->id && $order->customer_id) {
            Auth::loginUsingId($order->customer_id);
        }

        if (!Auth::check() || ($order->customer_id !== Auth::id() && !Auth::user()->is_admin)) {
            abort(403);
        }

        // Status dibaca dari DB saja — sinkronisasi Midtrans dilakukan via
        // webhook dan scheduled command (CheckMidtransStatusCommand).
        // JANGAN panggil MidtransService::checkAndSyncStatus() di sini
        // karena endpoint ini di-poll frontend setiap beberapa detik.

        return response()->json([
            'order_id' => $order->id,
            'status' => $order->status,
            'paid_at' => optional($order->paid_at)?->toIso8601String(),
            'invoice_status' => $order->invoice?->status,
            'redirect_to' => $order->paid_at ? route('order.success', $order->id) : null,
        ]);
    }

    /**
     * DEV ONLY: simulasi payment untuk testing lokal tanpa gateway asli.
     * Ini menggantikan lubang keamanan `completePayment` yang lama.
     *
     * Endpoint ini:
     *   1. Hanya aktif di APP_ENV=local + env APP_DEV_SIMULATE_PAYMENT=true.
     *   2. Hanya bisa dipanggil oleh admin ATAU pemilik order.
     *   3. Rate-limited (5/min).
     *   4. Membuat payment_transactions record + transisi order via state machine.
     *   5. Ditandai jelas sebagai "dev simulation" di metadata & log.
     *
     * Di production, endpoint ini otomatis abort(403) karena guard di atas.
     */
    public function devSimulatePayment(Request $request, $id)
    {
        // Guard 1: environment
        if (!app()->environment('local')) {
            abort(404, 'Endpoint ini hanya tersedia di environment lokal.');
        }
        // Guard 2: feature flag
        if (!config('vexahost.dev_simulate_payment', false)) {
            abort(404, 'Simulasi pembayaran dinonaktifkan. Set APP_DEV_SIMULATE_PAYMENT=true di .env untuk mengaktifkan.');
        }

        $order = Order::with('invoice')->findOrFail($id);

        // Guard 3: hanya pemilik order atau admin
        if (!Auth::check() || ($order->customer_id !== Auth::id() && !Auth::user()->is_admin)) {
            abort(403);
        }

        if ($order->paid_at) {
            return redirect()->route('order.success', $order->id)
                ->with('info', 'Order ini sudah tercatat lunas sebelumnya.');
        }

        try {
            DB::transaction(function () use ($order) {
                // Catat transaksi simulasi.
                $tx = PaymentTransaction::create([
                    'order_id' => $order->id,
                    'invoice_id' => $order->invoice?->id,
                    'provider' => 'dev_simulator',
                    'provider_transaction_id' => 'DEV-' . Str::upper(Str::random(12)),
                    'provider_order_ref' => 'ORDER-' . $order->id,
                    'payment_method' => $order->payment_method,
                    'amount' => $order->amount,
                    'currency' => 'IDR',
                    'status' => 'settled',
                    'raw_payload' => [
                        'note' => 'Dev simulation, not a real payment.',
                        'triggered_by_user_id' => Auth::id(),
                        'ip' => request()->ip(),
                    ],
                    'settled_at' => now(),
                ]);

                // Transisi order pending → paid via state machine.
                $this->orderStateMachine->transition(
                    $order,
                    'paid',
                    [
                        'reason' => 'Dev simulator marked payment settled.',
                        'actor_type' => 'system',
                        'metadata' => [
                            'source' => 'dev_simulator',
                            'transaction_id' => $tx->id,
                        ],
                        'onLocked' => function (Order $locked) use ($tx) {
                            $locked->paid_at = now();
                            $locked->save();

                            if ($locked->invoice) {
                                $locked->invoice->update([
                                    'status' => 'paid',
                                    'paid_at' => now(),
                                    'paid_via_transaction_id' => $tx->id,
                                ]);
                            }
                        },
                    ]
                );
            });

            Log::warning('order.payment.dev_simulated', [
                'order_id' => $order->id,
                'user_id' => Auth::id(),
                'ip' => $request->ip(),
            ]);

            // Notifikasi pelanggan & admin dikirim otomatis oleh PaymentCompletionService
            // (dipicu OrderStateMachine saat transisi ke 'paid').
        } catch (\Throwable $e) {
            Log::error('order.payment.dev_simulate_failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            return back()->with('error', 'Simulasi pembayaran gagal: ' . $e->getMessage());
        }

        return redirect()->route('order.success', $order->id)
            ->with('success', '[DEV] Pembayaran disimulasi. Order menunggu provisioning oleh admin.');
    }

    public function success($id)
    {
        $order = Order::with(['customer', 'vpsSpec', 'invoice', 'vpsInstance'])->findOrFail($id);

        if (!Auth::check() && session('last_order_id') == $order->id && $order->customer_id) {
            Auth::loginUsingId($order->customer_id);
        }

        if (!Auth::check() || ($order->customer_id !== Auth::id() && !Auth::user()->is_admin)) {
            abort(403);
        }

        // Cek sinkronisasi real-time ke Midtrans jika belum tercatat paid_at
        if (!$order->paid_at && PaymentGateway::isMidtransMethod($order->payment_method)) {
            \App\Services\Payments\MidtransService::checkAndSyncStatus($order);
            $order->refresh();
        }

        // Jika pesanan belum lunas dan bukan admin, arahkan ke halaman status pembayaran
        if (!$order->paid_at && !in_array($order->status, ['paid', 'provisioning', 'active'], true) && !Auth::user()->is_admin) {
            return redirect()->route('order.payment.status', $order->id);
        }

        return view('order.success', compact('order'));
    }

    /**
     * Callback handler setelah pembayaran dari payment gateway (Lynk, Shopee, dsb).
     */
    public function paymentCallback(Request $request)
    {
        $orderId = $request->query('order_id')
            ?? $request->query('refId')
            ?? $request->query('ref_id')
            ?? $request->query('reference')
            ?? $request->query('transaction_id')
            ?? $request->query('order_number')
            ?? session('last_order_id');

        $user = Auth::user();
        $order = null;

        if ($orderId) {
            // 1. Cek numeric primary key (orders.id)
            if (is_numeric($orderId)) {
                $order = Order::find($orderId);
            }

            // 2. Cek Shopee order ID
            if (!$order) {
                $order = Order::where('shopee_order_id', $orderId)->first();
            }

            // 3. Cek via PaymentTransaction (misal refId Lynk)
            if (!$order) {
                $tx = PaymentTransaction::where('provider_transaction_id', $orderId)
                    ->orWhere('provider_order_ref', $orderId)
                    ->latest()
                    ->first();
                if ($tx && $tx->order_id) {
                    $order = Order::find($tx->order_id);
                }
            }

            // 4. Cek via WebhookEvent (misal event_id Lynk)
            if (!$order) {
                $evt = WebhookEvent::where('event_id', $orderId)
                    ->whereNotNull('order_id')
                    ->latest()
                    ->first();
                if ($evt && $evt->order_id) {
                    $order = Order::find($evt->order_id);
                }
            }
        }

        // 5. Fallback pencarian order terbaru user yang sedang login atau session
        if (!$order && $user) {
            $order = Order::where('customer_id', $user->id)->latest()->first();
        }

        if (!$order && session('last_order_id')) {
            $order = Order::find(session('last_order_id'));
        }

        // 6. Pemulihan Sesi Pengguna secara aman:
        // Jika user belum login di browser ini tapi browser memiliki session order yang cocok,
        // login-kan kembali agar user tidak terlempar ke form login kosong
        if (!$user && $order && $order->customer_id) {
            if (session('last_order_id') == $order->id) {
                Auth::loginUsingId($order->customer_id);
                $user = Auth::user();
            }
        }

        // 7. Penanganan Redirect:
        // A. Pesanan sudah lunas / diproses
        if ($order && in_array($order->status, ['paid', 'provisioning', 'active'], true) && $order->paid_at) {
            if ($user) {
                return redirect()->route('dashboard.index', [
                    'payment_success' => 1,
                    'order_id' => $order->id,
                ])->with('payment_success_order_id', $order->id);
            }

            return redirect()->route('order.success', $order->id);
        }

        // B. Pesanan masih pending (webhook sedang berjalan / menunggu pembayaran)
        if ($order && $order->status === 'pending') {
            return redirect()->route('order.payment.status', $order->id)
                ->with('info', 'Pembayaran sedang diproses atau menunggu verifikasi sistem. Silakan pantau status pembayaran Anda.');
        }

        // C. User terotentikasi tapi order tidak spesifik
        if ($user) {
            return redirect()->route('dashboard.index')
                ->with('info', 'Selamat datang di Dashboard. Pantau status pesanan dan layanan server Anda di sini.');
        }

        return redirect()->route('login')
            ->with('info', 'Silakan masuk ke akun VexaHost Anda untuk memantau status pesanan.');
    }

    /**
     * PILAR 3 - Price & Availability Re-Validation untuk order yang masih pending.
     *
     * Mengecek apakah:
     * 1. Order telah kedaluwarsa (> 24 jam belum dibayar).
     * 2. Paket dinonaktifkan di supplier / katalog (is_active / is_renewable).
     * 3. Harga katalog atau modal naik/berubah sejak order dibuat.
     */
    protected function revalidatePendingOrder(Order $order): ?\Illuminate\Http\RedirectResponse
    {
        if ($order->paid_at || $order->status !== 'pending') {
            return null;
        }

        // Cek 1: TTL 24 Jam
        if ($order->created_at && $order->created_at->lt(now()->subHours(24))) {
            try {
                $this->orderStateMachine->transition($order, 'expired', [
                    'reason' => 'Batas waktu pembayaran 24 jam telah berakhir.',
                    'actor_type' => 'system',
                ]);
            } catch (\Throwable $e) {
                $order->update(['status' => 'expired']);
            }
            return redirect()->route('dashboard.index')
                ->with('error', "Pesanan #{$order->id} telah kedaluwarsa karena tidak diselesaikan dalam 24 jam.");
        }

        // Cek 2: Ketersediaan & Siklus Produk
        if ($order->isRenewal()) {
            $instance = $order->resolved_instance ?? ($order->vps_instance_id ? \App\Models\VpsInstance::find($order->vps_instance_id) : $order->vpsInstance);
            $spec = $order->vpsSpec;

            if ($spec && !$spec->is_renewable) {
                // Paket lama End-of-Life
                if ($spec->replacement_spec_id) {
                    $replacement = $spec->replacementSpec ?? \App\Models\VpsSpec::find($spec->replacement_spec_id);
                    if ($replacement) {
                        if ($order->vps_spec_id !== $replacement->id) {
                            $order->vps_spec_id = $replacement->id;
                            $order->save();
                        }
                    }
                } else {
                    // EOL permanen tanpa pengganti: batalkan order
                    try {
                        $this->orderStateMachine->transition($order, 'cancelled', [
                            'reason' => 'Paket VPS telah dihentikan secara permanen (End-of-Life) oleh penyedia upstream.',
                            'actor_type' => 'system',
                        ]);
                    } catch (\Throwable $e) {
                        $order->update(['status' => 'cancelled']);
                    }

                    $targetRedirect = $instance ? route('dashboard.vps.show', $instance->id) : route('dashboard.billing');
                    return redirect($targetRedirect)->with('error', 'Maaf, paket VPS untuk server ini telah dihentikan (End-of-Life) oleh penyedia infrastruktur dan tidak dapat diperpanjang lagi. Silakan hubungi tim dukungan kami untuk proses migrasi.');
                }
            }

            // Hitung harga perpanjangan yang diharapkan saat ini
            $expectedPrice = (float) ($instance ? $instance->renewal_price : ($spec ? $spec->effectiveRenewalPrice() : $order->amount));
            if ($expectedPrice > 0 && abs((float) $order->amount - $expectedPrice) > 0.01) {
                $order->amount = $expectedPrice;
                $order->save();

                if ($order->invoice) {
                    $order->invoice->update([
                        'amount' => $expectedPrice,
                        'subtotal' => $expectedPrice,
                        'total' => $expectedPrice,
                    ]);
                }

                // Invalidate transaksi/token pending lama
                PaymentTransaction::where('order_id', $order->id)
                    ->where('status', 'pending')
                    ->delete();

                session()->flash('warning', 'Tarif perpanjangan telah disesuaikan dengan skema katalog terbaru (Rp ' . number_format($expectedPrice, 0, ',', '.') . '). Silakan lanjutkan pembayaran dengan nominal terbaru.');
            }
        } else {
            // Order Baru
            $spec = $order->vpsSpec;
            if (!$spec || !$spec->is_active) {
                try {
                    $this->orderStateMachine->transition($order, 'cancelled', [
                        'reason' => 'Paket VPS tidak lagi aktif untuk pendaftaran baru.',
                        'actor_type' => 'system',
                    ]);
                } catch (\Throwable $e) {
                    $order->update(['status' => 'cancelled']);
                }

                return redirect()->route('order.checkout')
                    ->with('error', 'Maaf, ketersediaan paket pada pesanan yang belum dibayar ini telah ditutup oleh penyedia infrastruktur. Pesanan dibatalkan otomatis.');
            }

            $expectedPrice = (float) $spec->sell_price;
            if (abs((float) $order->amount - $expectedPrice) > 0.01) {
                $order->amount = $expectedPrice;
                $order->save();

                if ($order->invoice) {
                    $order->invoice->update([
                        'amount' => $expectedPrice,
                        'subtotal' => $expectedPrice,
                        'total' => $expectedPrice,
                    ]);
                }

                PaymentTransaction::where('order_id', $order->id)
                    ->where('status', 'pending')
                    ->delete();

                session()->flash('warning', 'Penyesuaian tarif infrastruktur: nominal tagihan telah disesuaikan ke harga terbaru Rp ' . number_format($expectedPrice, 0, ',', '.') . '.');
            }
        }

        return null;
    }
}
