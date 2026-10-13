<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidStateTransitionException;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\PaymentTransaction;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\VpsInstance;
use App\Models\VpsSpec;
use App\Models\VpsStatusHistory;
use App\Services\OrderStateMachine;
use App\Services\ReportExportService;
use App\Services\VpsStateMachine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    /** Pesan validasi untuk link control panel (kolom app_url). */
    private const PANEL_URL_MESSAGES = [
        'app_url.required' => 'Link control panel wajib diisi untuk paket dengan panel.',
        'app_url.url' => 'Link control panel harus lengkap dan diawali http:// atau https:// (contoh: http://103.150.10.2:8000 atau https://panel.domain.com).',
    ];

    public function __construct(
        protected OrderStateMachine $orderStateMachine,
        protected VpsStateMachine $vpsStateMachine,
        protected ReportExportService $reportExportService
    ) {
    }

    public function index()
    {
        $pending_orders = Order::where('status', 'pending')->count();
        $active_vps = VpsInstance::where('status', 'running')->count();
        $total_customers = User::where('is_admin', false)->count();
        $total_revenue = Order::whereIn('status', ['active', 'provisioning'])->sum('amount');

        $recent_orders = Order::with(['customer', 'vpsSpec'])->latest()->take(6)->get();
        $open_tickets = SupportTicket::with(['customer'])->whereIn('status', ['open', 'in_progress'])->latest()->take(5)->get();

        $orders_web = Order::where('channel', 'website')->count();
        $orders_shopee = Order::where('channel', 'shopee')->count();

        $specs = VpsSpec::withCount('orders')->get();

        return view('admin.index', compact(
            'pending_orders',
            'active_vps',
            'total_customers',
            'total_revenue',
            'recent_orders',
            'open_tickets',
            'orders_web',
            'orders_shopee',
            'specs'
        ));
    }

    public function orders(Request $request)
    {
        $query = Order::with(['customer', 'vpsSpec', 'invoice'])->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            if ($request->status === 'needs_provision') {
                $query->whereIn('status', ['paid', 'provisioning'])->whereDoesntHave('vpsInstance');
            } else {
                $query->where('status', $request->status);
            }
        }

        if ($request->filled('channel')) {
            $query->where('channel', $request->channel);
        }

        $orders = $query->paginate(15)->withQueryString();
        $specs = VpsSpec::all();

        $needsProvisionCount = Order::whereIn('status', ['paid', 'provisioning'])
            ->whereDoesntHave('vpsInstance')
            ->count();

        return view('admin.orders', compact('orders', 'specs', 'needsProvisionCount'));
    }

    /**
     * Menu Verifikasi Pembayaran (Cek Mutasi DANA Bisnis DESTINARA).
     */
    public function payments(Request $request)
    {
        $status = $request->query('status', 'pending');
        $query = Order::with(['customer', 'vpsSpec', 'invoice', 'statusHistories'])
            ->where('channel', 'website');

        if ($status === 'all') {
            // All statuses
        } elseif (in_array($status, ['pending', 'paid', 'cancelled'], true)) {
            $query->where('status', $status);
        } else {
            $query->where('status', 'pending');
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('hostname', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('full_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('invoice', function ($iq) use ($search) {
                        $iq->where('invoice_number', 'like', "%{$search}%");
                    });
            });
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();
        $pendingCount = Order::where('channel', 'website')->where('status', 'pending')->count();

        return view('admin.payments', compact('orders', 'pendingCount', 'status'));
    }

    /**
     * Setujui Pembayaran (Admin telah mencocokkan mutasi DANA Bisnis DESTINARA).
     */
    public function approvePayment(Request $request, $id)
    {
        $validated = $request->validate([
            'reference' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:500',
        ]);

        $order = Order::with('invoice')->findOrFail($id);

        if ($order->status !== 'pending') {
            return back()->with('error', "Order #{$order->id} berstatus {$order->status}, hanya order pending yang dapat disetujui.");
        }

        try {
            DB::transaction(function () use ($order, $validated) {
                $tx = PaymentTransaction::create([
                    'order_id' => $order->id,
                    'invoice_id' => $order->invoice?->id,
                    'provider' => 'qris_manual',
                    'provider_transaction_id' => 'DANA-MUTASI-' . Str::upper(Str::random(10)),
                    'provider_order_ref' => 'ORDER-' . $order->id,
                    'payment_method' => $order->payment_method ?? 'qris',
                    'amount' => $order->amount,
                    'currency' => 'IDR',
                    'status' => 'settled',
                    'settled_at' => now(),
                    'raw_payload' => [
                        'account_name' => 'DESTINARA',
                        'reference' => $validated['reference'] ?? null,
                        'note' => $validated['note'] ?? 'Mutasi DANA Bisnis diverifikasi oleh admin',
                        'admin_user_id' => Auth::id(),
                    ],
                ]);

                $this->orderStateMachine->transition(
                    $order,
                    'paid',
                    [
                        'reason' => 'Admin verifikasi pembayaran: mutasi DANA Bisnis DESTINARA cocok. ' . ($validated['note'] ?? ''),
                        'actor_type' => 'admin',
                        'metadata' => [
                            'reference' => $validated['reference'] ?? null,
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

            if ($order->isRenewal()) {
                try {
                    app(\App\Services\RenewalService::class)->handleRenewalPayment($order);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('admin.approve_payment.renewal_failed', [
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Email konfirmasi ke pelanggan (+ info admin) dikirim oleh PaymentCompletionService,
            // dipicu otomatis saat transisi ke 'paid'. Panggilan ini pengaman yang idempoten.
            app(\App\Services\PaymentCompletionService::class)->handle($order->id);
        } catch (InvalidStateTransitionException $e) {
            return back()->with('error', 'Gagal menyetujui pembayaran: ' . $e->getMessage());
        }

        return back()->with('success', "Pembayaran Order #{$order->id} (Invoice: {$order->invoice?->invoice_number}) berhasil disetujui. Email konfirmasi telah dikirim ke pelanggan.");
    }

    /**
     * Tahan / Pending Catatan Mutasi (Admin masih menunggu mutasi muncul di DANA Bisnis).
     */
    public function holdPayment(Request $request, $id)
    {
        $validated = $request->validate([
            'note' => 'required|string|max:500',
        ]);

        $order = Order::findOrFail($id);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'from_status' => $order->status,
            'to_status' => $order->status,
            'reason' => 'Verifikasi ditunda: ' . $validated['note'],
            'actor_user_id' => Auth::id(),
            'actor_type' => 'admin',
            'ip_address' => $request->ip(),
            'metadata' => [
                'admin_id' => Auth::id(),
                'note' => $validated['note'],
            ],
            'created_at' => now(),
        ]);

        return back()->with('info', "Catatan penundaan untuk Order #{$order->id} berhasil disimpan.");
    }

    /**
     * Tolak Pembayaran (Mutasi tidak masuk atau nominal tidak sesuai).
     */
    public function rejectPayment(Request $request, $id)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $order = Order::with(['customer', 'invoice'])->findOrFail($id);

        if ($order->status !== 'pending') {
            return back()->with('error', "Order #{$order->id} berstatus {$order->status}, hanya order pending yang dapat ditolak.");
        }

        try {
            DB::transaction(function () use ($order, $validated) {
                $this->orderStateMachine->transition(
                    $order,
                    'cancelled',
                    [
                        'reason' => 'Pembayaran ditolak: ' . $validated['reason'],
                        'actor_type' => 'admin',
                        'metadata' => [
                            'admin_id' => Auth::id(),
                            'reject_reason' => $validated['reason'],
                        ],
                        'onLocked' => function (Order $locked) {
                            if ($locked->invoice) {
                                $locked->invoice->update([
                                    'status' => 'cancelled',
                                ]);
                            }
                        },
                    ]
                );
            });

            // Kirim email notifikasi penolakan ke customer (sesuai template resmi)
            try {
                if ($order->customer) {
                    $order->customer->notify(new \App\Notifications\PaymentRejectedNotification($order, $order->invoice, $validated['reason']));
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('admin.reject_payment.notify_failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }
        } catch (InvalidStateTransitionException $e) {
            return back()->with('error', 'Gagal menolak pembayaran: ' . $e->getMessage());
        }

        return back()->with('success', "Pembayaran Order #{$order->id} telah ditolak & order dibatalkan. Notifikasi email telah dikirim ke pelanggan.");
    }

    /**
     * Admin mark order paid manual (untuk payment offline / rekonsiliasi).
     * Order harus di status 'pending'. Transisi via state machine + catat
     * payment_transactions dengan provider='manual'.
     */
    public function markOrderPaid(Request $request, $id)
    {
        $validated = $request->validate([
            'reference' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:500',
        ]);

        $order = Order::with('invoice')->findOrFail($id);

        try {
            DB::transaction(function () use ($order, $validated) {
                $tx = PaymentTransaction::create([
                    'order_id' => $order->id,
                    'invoice_id' => $order->invoice?->id,
                    'provider' => 'manual',
                    'provider_transaction_id' => 'MANUAL-' . Str::upper(Str::random(10)),
                    'provider_order_ref' => 'ORDER-' . $order->id,
                    'payment_method' => $order->payment_method ?? 'manual',
                    'amount' => $order->amount,
                    'currency' => 'IDR',
                    'status' => 'settled',
                    'settled_at' => now(),
                    'raw_payload' => [
                        'reference' => $validated['reference'] ?? null,
                        'note' => $validated['note'] ?? null,
                        'admin_user_id' => Auth::id(),
                    ],
                ]);

                $this->orderStateMachine->transition(
                    $order,
                    'paid',
                    [
                        'reason' => 'Admin manual mark paid. ' . ($validated['note'] ?? ''),
                        'actor_type' => 'admin',
                        'metadata' => [
                            'reference' => $validated['reference'] ?? null,
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

            // Email lunas ke pelanggan, info admin, dan perpanjangan (bila renewal) lewat
            // titik pusat. Sudah dipicu otomatis saat transisi; panggilan ini idempoten.
            app(\App\Services\PaymentCompletionService::class)->handle($order->id);
        } catch (InvalidStateTransitionException $e) {
            return back()->with('error', 'Gagal mark paid: ' . $e->getMessage());
        }

        return back()->with('success', "Order #{$order->id} berhasil di-mark paid. Silakan lanjutkan provisioning.");
    }

    /**
     * Provision VPS setelah order dibayar.
     *
     * Flow yang benar:
     *   paid → provisioning → active
     *
     * Kalau order masih 'pending' (belum bayar), tolak.
     * Kalau order sudah 'active', tolak (double provision guard).
     * Semua dibungkus DB transaction: kalau VpsInstance create gagal, rollback total.
     */
    public function provision(Request $request, $id)
    {
        $order = Order::with(['vpsSpec', 'invoice'])->findOrFail($id);

        // Guard: harus sudah paid.
        if (!in_array($order->status, ['paid', 'failed'], true)) {
            return back()->with('error',
                "Order #{$order->id} masih status [{$order->status}]. Provisioning hanya bisa dari status 'paid' atau retry 'failed'.");
        }

        // Guard: sudah punya VpsInstance? Tolak (kecuali status failed - retry).
        if ($order->status === 'paid' && $order->vpsInstance) {
            return back()->with('error', "Order #{$order->id} sudah memiliki VpsInstance terkait.");
        }

        $isDb = $order->isDatabasePackage();
        $hasPanel = $order->control_panel !== 'none' && !($isDb && $order->db_manager === 'cli_only');

        $rules = [
            'public_ip' => 'required|ip',
            'private_ip' => 'nullable|ip',
            'ssh_port' => 'nullable|integer|min:1|max:65535',
            'ssh_username' => VpsInstance::SSH_USERNAME_RULE,
            'root_password' => 'required|string|min:8|max:128',
        ];

        // Link panel wajib berformat URL lengkap agar tautan di dashboard pelanggan
        // tidak rusak (mis. "panel.domain.com" tanpa skema menjadi link relatif).
        $rules['app_url'] = [$hasPanel ? 'required' : 'nullable', 'url:http,https', 'max:255'];

        $validated = $request->validate($rules, self::PANEL_URL_MESSAGES + VpsInstance::SSH_USERNAME_MESSAGES);

        try {
            $instance = DB::transaction(function () use ($order, $validated, $isDb, $hasPanel) {
                // Step 1: paid → provisioning
                $this->orderStateMachine->transition($order, 'provisioning', [
                    'reason' => 'Admin memulai provisioning.',
                    'actor_type' => 'admin',
                    'onLocked' => function (Order $locked) {
                        $locked->provisioning_attempts = ($locked->provisioning_attempts ?? 0) + 1;
                        $locked->failure_reason = null;
                        $locked->save();
                    },
                ]);
                $order->refresh();

                $spec = $order->vpsSpec;
                // Masa aktif dihitung sejak lunas; lewat SLA 24 jam → sejak diserahkan (kompensasi).
                $startsAt = $order->provisioningStartsAt();
                $months = match ($order->billing_cycle) {
                    'quarterly' => 3,
                    'semi_annual' => 6,
                    'annual' => 12,
                    default => 1,
                };
                $expiresAt = $startsAt->copy()->addMonths($months);
                // Tenggang mengikuti jenis produk dan selalu di bawah batas hapus Supplier.
                $graceEndsAt = $expiresAt->copy()->addDays(app(\App\Services\RenewalService::class)->graceDaysForOrder($order));

                // Parameter pesanan pelanggan yang terkunci (tidak diubah admin)
                $hostname = $order->hostname ?: ('vps-' . $order->id);
                $os = $order->os ?: 'Ubuntu 24.04 LTS';
                $rootPassword = $validated['root_password'];

                $dbEngine = $order->db_engine ?: ($isDb ? 'postgres' : null);
                $dbManager = $order->db_manager ?: ($isDb ? 'cloudbeaver' : null);
                $dbPort = $order->db_port ?: ($isDb ? match($dbEngine) {
                    'mysql' => 3306,
                    'redis' => 6379,
                    'mongodb' => 27017,
                    'vector' => 6333,
                    default => 5432,
                } : null);
                $dbPassword = $isDb ? ($order->db_password ?: $rootPassword) : null;
                $appUrl = $hasPanel ? ($validated['app_url'] ?? null) : null;
                $appName = $order->isAiPackage() ? $order->vpsSpec?->name : (($isDb && $dbManager === 'cloudbeaver') ? 'CloudBeaver Web GUI' : null);
                $appGuide = $order->isAiPackage() ? $order->vpsSpec?->solution : null;

                $instance = VpsInstance::create([
                    'customer_id' => $order->customer_id,
                    'order_id' => $order->id,
                    'organization_id' => $order->organization_id,
                    'hostname' => $hostname,
                    'public_ip' => $validated['public_ip'],
                    'private_ip' => $validated['private_ip'] ?? null,
                    'ssh_port' => $validated['ssh_port'] ?? 22,
                    'ssh_username' => VpsInstance::normalizeSshUsername($validated['ssh_username'] ?? null),
                    'initial_root_password' => $rootPassword,
                    'os' => $os,
                    'datacenter_location' => $order->datacenter_location ?? 'ID-CGK01',
                    'status' => 'provisioning',
                    'cpu' => $spec->cpu ?? 2,
                    'ram' => $spec->ram ?? 4,
                    'disk' => $spec->disk ?? 60,
                    'control_panel' => $order->control_panel,
                    'db_engine' => $dbEngine,
                    'db_manager' => $dbManager,
                    'db_name' => $isDb ? ($order->db_name ?: 'vexadb_production') : null,
                    'db_user' => $isDb ? ($order->db_user ?: 'admin_vexa') : null,
                    'db_password' => $dbPassword,
                    'db_port' => $dbPort,
                    'provider' => $order->provider ?? 'manual',
                    'app_url' => $appUrl,
                    'app_name' => $appName,
                    'app_guide' => $appGuide,
                    'uptime_percent' => 99.98,
                    'billing_cycle' => $order->billing_cycle ?? 'monthly',
                    'starts_at' => $startsAt,
                    'expires_at' => $expiresAt,
                    'grace_period_ends_at' => $graceEndsAt,
                    'auto_renew' => true,
                ]);

                // Catat status awal VPS di history.
                VpsStatusHistory::create([
                    'vps_instance_id' => $instance->id,
                    'from_status' => null,
                    'to_status' => 'provisioning',
                    'reason' => 'VPS instance dibuat.',
                    'actor_user_id' => Auth::id(),
                    'actor_type' => 'admin',
                    'ip_address' => request()->ip(),
                    'metadata' => ['order_id' => $order->id],
                    'created_at' => now(),
                ]);

                // Step 2: VPS provisioning → running
                $this->vpsStateMachine->transition($instance, 'running', [
                    'reason' => 'VPS sukses dikonfigurasi.',
                    'actor_type' => 'admin',
                ]);

                // Step 3: order provisioning → active
                $this->orderStateMachine->transition($order, 'active', [
                    'reason' => 'Provisioning sukses.',
                    'actor_type' => 'admin',
                    'metadata' => ['vps_instance_id' => $instance->id],
                    'onLocked' => function (Order $locked) use ($startsAt, $expiresAt, $graceEndsAt, $rootPassword, $isDb, $dbPassword) {
                        $locked->starts_at = $startsAt;
                        $locked->expires_at = $expiresAt;
                        $locked->grace_period_ends_at = $graceEndsAt;
                        $locked->root_password = $rootPassword;
                        if ($isDb && empty($locked->db_password)) {
                            $locked->db_password = $dbPassword;
                        }
                        $locked->save();
                    },
                ]);

                $instance->refresh()->logActivity(
                    action: 'provision',
                    description: "VPS {$instance->hostname} (IP: {$instance->public_ip}) berhasil di-provision oleh Administrator.",
                    status: 'completed',
                    userId: Auth::id()
                );

                if ($order->setupSlaBreached()) {
                    $instance->logActivity(
                        action: 'sla_compensation',
                        description: 'Setup melewati batas ' . Order::SETUP_SLA_HOURS . ' jam sejak pembayaran. '
                            . 'Sebagai kompensasi, masa aktif dihitung sejak server diserahkan.',
                        status: 'completed',
                        userId: Auth::id()
                    );
                }

                // Pastikan invoice paid (redundant safety).
                if ($order->invoice && $order->invoice->status !== 'paid') {
                    $order->invoice->update(['status' => 'paid', 'paid_at' => now()]);
                }

                return $instance;
            });
        } catch (InvalidStateTransitionException $e) {
            return back()->with('error', 'Provisioning gagal: ' . $e->getMessage());
        } catch (\Throwable $e) {
            // Rollback sudah terjadi. Tandai order sebagai failed supaya bisa retry.
            $order->refresh();
            try {
                if ($order->status === 'provisioning') {
                    $this->orderStateMachine->transition($order, 'failed', [
                        'reason' => 'Provisioning exception: ' . $e->getMessage(),
                        'actor_type' => 'system',
                        'metadata' => ['error' => $e->getMessage()],
                        'onLocked' => function (Order $locked) use ($e) {
                            $locked->failure_reason = mb_substr($e->getMessage(), 0, 500);
                            $locked->save();
                        },
                    ]);
                }
            } catch (\Throwable $inner) {
                // ignore
            }
            return back()->with('error', 'Provisioning error: ' . $e->getMessage());
        }

        // Kirim notifikasi kredensial VPS siap pakai ke customer
        try {
            $order->loadMissing(['customer', 'vpsSpec']);
            if ($order->customer) {
                $order->customer->notify(new \App\Notifications\VpsProvisionedNotification($instance));
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('admin.provision.notify_failed', ['instance_id' => $instance->id, 'error' => $e->getMessage()]);
        }

        // PHASE 5 - tandai serah terima di papan fulfillment dan tautkan catatan
        // pembelian Supplier ke instance yang baru dibuat. Dijalankan di luar
        // transaksi provisioning agar kegagalan di sini tidak membatalkan provisioning.
        try {
            $order->update([
                'fulfillment_stage' => 'delivered',
                'delivered_at' => now(),
            ]);

            \App\Models\SupplierPurchase::where('order_id', $order->id)
                ->whereNull('vps_instance_id')
                ->update(['vps_instance_id' => $instance->id]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('admin.provision.fulfillment_mark_failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }

        return back()->with('success',
            "VPS {$instance->hostname} (IP: {$validated['public_ip']}) berhasil di-provision. Status order diubah ke Active.");
    }

    /**
     * Retry provisioning untuk order yang gagal.
     */
    public function retryProvision(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        if ($order->status !== 'failed') {
            return back()->with('error', "Retry hanya untuk order status 'failed'. Status sekarang: {$order->status}.");
        }

        // Transisi failed → provisioning (retry pathway), lalu redirect admin ke form provision.
        try {
            $this->orderStateMachine->transition($order, 'provisioning', [
                'reason' => 'Admin retry provisioning setelah gagal.',
                'actor_type' => 'admin',
                'onLocked' => function (Order $locked) {
                    $locked->failure_reason = null;
                    $locked->save();
                },
            ]);
        } catch (InvalidStateTransitionException $e) {
            return back()->with('error', 'Gagal retry: ' . $e->getMessage());
        }

        // Rollback ke 'paid' agar admin masuk lagi ke form provision.
        // (State machine memang menyediakan jalur; di sini kita cukup arahkan ke form.)
        return back()->with('info', "Order #{$order->id} disiapkan untuk retry. Silakan buka form provisioning.");
    }

    public function orderHistory($id)
    {
        $order = Order::with(['customer', 'vpsSpec'])->findOrFail($id);
        $histories = OrderStatusHistory::where('order_id', $order->id)
            ->with('actor')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.order-history', compact('order', 'histories'));
    }

    public function cancelOrder(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        $reason = $request->input('reason', 'Dibatalkan oleh Administrator.');

        try {
            $this->orderStateMachine->transition($order, 'cancelled', [
                'reason' => $reason,
                'actor_type' => 'admin',
                'onLocked' => function (Order $locked) {
                    if ($locked->invoice && $locked->invoice->status !== 'cancelled') {
                        $locked->invoice->update(['status' => 'cancelled']);
                    }
                },
            ]);
        } catch (InvalidStateTransitionException $e) {
            return back()->with('error', "Order #{$order->id} tidak bisa dibatalkan: " . $e->getMessage());
        }

        return back()->with('success', 'Order #' . $order->id . ' berhasil dibatalkan.');
    }

    public function showShopeeForm(Request $request)
    {
        $specs = VpsSpec::where('is_active', true)->orderBy('sell_price', 'asc')->get();

        $query = Order::where('channel', 'shopee')
            ->with(['customer', 'vpsSpec', 'vpsInstance', 'invoice'])
            ->latest();

        if ($search = trim((string)$request->input('q'))) {
            $query->where(function ($q) use ($search) {
                $q->where('shopee_order_id', 'like', "%{$search}%")
                  ->orWhere('id', 'like', "%{$search}%")
                  ->orWhere('hostname', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('username', 'like', "%{$search}%")
                         ->orWhere('full_name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%")
                         ->orWhere('phone', 'like', "%{$search}%");
                  })
                  ->orWhereHas('vpsInstance', function ($iq) use ($search) {
                      $iq->where('public_ip', 'like', "%{$search}%");
                  });
            });
        }

        if ($status = $request->input('status')) {
            if ($status === 'needs_provision') {
                $query->where('status', 'paid')->whereDoesntHave('vpsInstance');
            } elseif ($status === 'active') {
                $query->where('status', 'active');
            } elseif ($status === 'cancelled') {
                $query->where('status', 'cancelled');
            }
        }

        $recentShopeeOrders = $query->paginate(15)->withQueryString();
        $operatingSystems = Order::operatingSystems();
        $providerLabels = Order::providerLabels();
        $stackLabels = Order::stackLabels();

        return view('admin.shopee', compact(
            'specs',
            'recentShopeeOrders',
            'operatingSystems',
            'providerLabels',
            'stackLabels'
        ));
    }

    public function processShopeeOrder(Request $request)
    {
        $validated = $request->validate([
            'shopee_order_id' => 'required|string|max:100',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'nullable|string|max:25',
            'vps_spec_id' => 'required|exists:vps_specs,id',
            'provider' => 'nullable|string|in:tencent,cloudeka',
            'control_panel' => 'required|string',
            'datacenter_location' => 'required|in:singapore,indonesia',
            'os' => 'required|string',
            'hostname' => ['nullable', 'string', 'max:63', 'regex:/^[A-Za-z0-9][A-Za-z0-9-]*$/'],
            'db_engine' => 'nullable|string|in:postgres,mysql,redis,mongodb,vector',
            'db_manager' => 'nullable|string|in:cloudbeaver,cli_only',
            'auto_provision' => 'nullable|boolean',
            'public_ip' => 'nullable|required_if:auto_provision,1|ip',
            'ssh_port' => 'nullable|integer|min:1|max:65535',
            'ssh_username' => VpsInstance::SSH_USERNAME_RULE,
            'root_password' => 'nullable|string|min:8',
            'app_url' => ['nullable', 'url:http,https', 'max:255'],
        ], self::PANEL_URL_MESSAGES + VpsInstance::SSH_USERNAME_MESSAGES);

        $existingOrder = Order::where('shopee_order_id', $validated['shopee_order_id'])->first();
        if ($existingOrder) {
            return back()->withInput()->withErrors([
                'shopee_order_id' => 'Shopee Order ID ini sudah pernah diproses sebelumnya (Order #' . $existingOrder->id . ').'
            ]);
        }

        $spec = VpsSpec::findOrFail($validated['vps_spec_id']);
        $requestedProvider = $validated['provider'] ?? null;
        $provider = ($requestedProvider && $spec->isProviderAllowed($requestedProvider))
            ? $requestedProvider
            : $spec->defaultProvider();
        $datacenterLocation = ($provider === 'cloudeka')
            ? 'indonesia'
            : ($validated['datacenter_location'] ?? 'singapore');

        $isDb = $spec->isDatabasePackage() || $validated['control_panel'] === 'managed_database';
        $dbEngine = $validated['db_engine'] ?? ($isDb ? 'postgres' : null);
        $dbManager = $validated['db_manager'] ?? ($isDb ? 'cloudbeaver' : null);
        $dbPort = $isDb ? match($dbEngine) {
            'mysql' => 3306,
            'redis' => 6379,
            'mongodb' => 27017,
            'vector' => 6333,
            default => 5432,
        } : null;
        $dbUser = $isDb ? 'admin_vexa' : null;
        $dbName = $isDb ? 'vexadb_production' : null;

        $generatedUsername = 'vx_' . Str::lower(Str::random(8));
        while (User::where('username', $generatedUsername)->exists()) {
            $generatedUsername = 'vx_' . Str::lower(Str::random(8));
        }

        $plainPassword = Str::password(16, true, true, false, false);

        $user = User::where('email', strtolower($validated['customer_email']))->first();
        if (!$user) {
            $user = User::create([
                'username' => $generatedUsername,
                'email' => strtolower($validated['customer_email']),
                'password' => Hash::make($plainPassword),
                'full_name' => $validated['customer_name'],
                'phone' => $validated['customer_phone'] ?? null,
                'channel' => 'shopee',
                'shopee_order_id' => $validated['shopee_order_id'],
            ]);
            $isNewUser = true;
        } else {
            $isNewUser = false;
            if (empty($user->phone) && !empty($validated['customer_phone'])) {
                $user->update(['phone' => $validated['customer_phone']]);
            }
        }

        $organization = $user->currentOrganization
            ?? $user->organizations()->first()
            ?? $user->createPersonalOrganization();
        $user->switchToOrganization($organization);

        $cleanHostname = !empty($validated['hostname'])
            ? Str::slug($validated['hostname'])
            : ('vps-' . Str::slug($user->username));

        // Semua dalam satu transaction: create order pending, transisi pending->paid,
        // catat payment_transactions, dan faktur.
        try {
            $result = DB::transaction(function () use ($user, $organization, $spec, $validated, $provider, $datacenterLocation, $isDb, $dbEngine, $dbManager, $dbPort, $dbUser, $dbName, $cleanHostname) {
                $order = Order::create([
                    'customer_id' => $user->id,
                    'organization_id' => $organization->id,
                    'vps_spec_id' => $spec->id,
                    'control_panel' => $validated['control_panel'],
                    'db_engine' => $dbEngine,
                    'db_manager' => $dbManager,
                    'db_name' => $dbName,
                    'db_user' => $dbUser,
                    'db_port' => $dbPort,
                    'provider' => $provider,
                    'hostname' => $cleanHostname,
                    'root_password' => $validated['root_password'] ?? Str::password(16, true, true, false, false),
                    'datacenter_location' => $datacenterLocation,
                    'os' => $validated['os'],
                    'billing_cycle' => 'monthly',
                    'status' => 'pending',
                    'channel' => 'shopee',
                    'shopee_order_id' => $validated['shopee_order_id'],
                    'payment_method' => 'shopee_pay',
                    'amount' => $spec->sell_price,
                    'setup_fee' => 0,
                    'paid_at' => null,
                    'last_status_change_at' => now(),
                ]);

                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'from_status' => null,
                    'to_status' => 'pending',
                    'reason' => 'Order Shopee dibuat oleh Admin.',
                    'actor_user_id' => Auth::id(),
                    'actor_type' => 'admin',
                    'ip_address' => request()->ip(),
                    'metadata' => ['shopee_order_id' => $validated['shopee_order_id']],
                    'created_at' => now(),
                ]);

                $invoiceNumber = 'INV-SHP-' . date('Ym') . '-' . str_pad($order->id, 4, '0', STR_PAD_LEFT);
                $invoice = Invoice::create([
                    'order_id' => $order->id,
                    'invoice_number' => $invoiceNumber,
                    'amount' => $spec->sell_price,
                    'status' => 'sent',
                    'issued_at' => now(),
                    'due_at' => now()->addDays(30),
                    'paid_at' => null,
                ]);

                // Payment transaction: shopee sebagai provider, sudah settled.
                $tx = PaymentTransaction::create([
                    'order_id' => $order->id,
                    'invoice_id' => $invoice->id,
                    'provider' => 'shopee',
                    'provider_transaction_id' => 'SHOPEE-' . $validated['shopee_order_id'],
                    'provider_order_ref' => $validated['shopee_order_id'],
                    'payment_method' => 'shopee_pay',
                    'amount' => $spec->sell_price,
                    'currency' => 'IDR',
                    'status' => 'settled',
                    'settled_at' => now(),
                    'raw_payload' => [
                        'shopee_order_id' => $validated['shopee_order_id'],
                        'admin_user_id' => Auth::id(),
                    ],
                ]);

                // Transisi: pending → paid.
                $this->orderStateMachine->transition($order, 'paid', [
                    'reason' => 'Order Shopee ditandai lunas.',
                    'actor_type' => 'admin',
                    'metadata' => ['transaction_id' => $tx->id],
                    'onLocked' => function (Order $locked) use ($tx, $invoice) {
                        $locked->paid_at = now();
                        $locked->save();
                        $invoice->update([
                            'status' => 'paid',
                            'paid_at' => now(),
                            'paid_via_transaction_id' => $tx->id,
                        ]);
                    },
                ]);

                return $order->fresh();
            });
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Gagal memproses order Shopee: ' . $e->getMessage());
        }

        $order = $result;
        $createdInstance = null;

        // Auto-provision kalau diminta.
        if (!empty($validated['auto_provision']) && !empty($validated['public_ip'])) {
            try {
                $createdInstance = DB::transaction(function () use ($order, $user, $spec, $validated, $provider, $datacenterLocation, $isDb, $dbEngine, $dbManager, $dbPort, $dbUser, $dbName, $cleanHostname) {
                    $this->orderStateMachine->transition($order, 'provisioning', [
                        'reason' => 'Auto-provision dari Shopee flow.',
                        'actor_type' => 'admin',
                        'onLocked' => function (Order $locked) {
                            $locked->provisioning_attempts = ($locked->provisioning_attempts ?? 0) + 1;
                            $locked->save();
                        },
                    ]);
                    $order->refresh();

                    // Masa aktif dihitung sejak lunas; lewat SLA 24 jam → sejak diserahkan (kompensasi).
                    $startsAt = $order->provisioningStartsAt();
                    $expiresAt = $startsAt->copy()->addMonth();
                    // Tenggang mengikuti jenis produk dan selalu di bawah batas hapus Supplier.
                    $graceEndsAt = $expiresAt->copy()->addDays(app(\App\Services\RenewalService::class)->graceDaysForOrder($order));
                    $rootPassword = !empty($validated['root_password'])
                        ? $validated['root_password']
                        : Str::password(16, true, true, false, false);
                    $sshPort = !empty($validated['ssh_port']) ? (int)$validated['ssh_port'] : 22;

                    $isAiPlan = $spec->isAiPackage();
                    // Link yang diisi admin diutamakan; tanpa isian, hanya CloudBeaver
                    // (paket Database) yang punya alamat bawaan.
                    $hasPanel = $validated['control_panel'] !== 'none' && !($isDb && $dbManager === 'cli_only');
                    $appUrl = $hasPanel && !empty($validated['app_url'])
                        ? $validated['app_url']
                        : (($isDb && $dbManager === 'cloudbeaver') ? "https://{$validated['public_ip']}:8080" : null);
                    $appName = $isAiPlan ? $spec->name : (($isDb && $dbManager === 'cloudbeaver') ? 'CloudBeaver Web GUI' : null);
                    $appGuide = $isAiPlan ? $spec->solution : null;
                    $osLabel = Order::osLabels()[$validated['os']] ?? $validated['os'];

                    $instance = VpsInstance::create([
                        'customer_id' => $user->id,
                        'order_id' => $order->id,
                        'organization_id' => $order->organization_id,
                        'hostname' => $cleanHostname,
                        'public_ip' => $validated['public_ip'],
                        'private_ip' => null,
                        'ssh_port' => $sshPort,
                        'ssh_username' => VpsInstance::normalizeSshUsername($validated['ssh_username'] ?? null),
                        'initial_root_password' => $rootPassword,
                        'os' => $osLabel,
                        'datacenter_location' => $datacenterLocation === 'singapore' ? 'SG-SIN01' : 'ID-CGK01',
                        'status' => 'provisioning',
                        'cpu' => $spec->cpu,
                        'ram' => $spec->ram,
                        'disk' => $spec->disk,
                        'control_panel' => $validated['control_panel'],
                        'db_engine' => $dbEngine,
                        'db_manager' => $dbManager,
                        'db_name' => $dbName,
                        'db_user' => $dbUser,
                        'db_password' => $isDb ? $rootPassword : null,
                        'db_port' => $dbPort,
                        'provider' => $provider,
                        'app_url' => $appUrl,
                        'app_name' => $appName,
                        'app_guide' => $appGuide,
                        'uptime_percent' => null,
                        'billing_cycle' => 'monthly',
                        'starts_at' => $startsAt,
                        'expires_at' => $expiresAt,
                        'grace_period_ends_at' => $graceEndsAt,
                        'auto_renew' => false,
                    ]);

                    VpsStatusHistory::create([
                        'vps_instance_id' => $instance->id,
                        'from_status' => null,
                        'to_status' => 'provisioning',
                        'reason' => 'Shopee auto-provision.',
                        'actor_user_id' => Auth::id(),
                        'actor_type' => 'admin',
                        'ip_address' => request()->ip(),
                        'metadata' => ['order_id' => $order->id],
                        'created_at' => now(),
                    ]);

                    $this->vpsStateMachine->transition($instance, 'running', [
                        'reason' => 'Auto-provision selesai.',
                        'actor_type' => 'admin',
                    ]);

                    $this->orderStateMachine->transition($order, 'active', [
                        'reason' => 'Auto-provision Shopee selesai.',
                        'actor_type' => 'admin',
                        'metadata' => ['vps_instance_id' => $instance->id],
                        'onLocked' => function (Order $locked) use ($startsAt, $expiresAt, $graceEndsAt) {
                            $locked->starts_at = $startsAt;
                            $locked->expires_at = $expiresAt;
                            $locked->grace_period_ends_at = $graceEndsAt;
                            $locked->save();
                        },
                    ]);

                    $instance->fresh()->logActivity(
                        action: 'provision',
                        description: "VPS auto-provisioning untuk Order Shopee {$validated['shopee_order_id']} oleh Administrator.",
                        status: 'completed',
                        userId: Auth::id()
                    );

                    return $instance;
                });
            } catch (\Throwable $e) {
                return back()->with('error', 'Order Shopee dibuat & dibayar, tetapi auto-provisioning gagal: ' . $e->getMessage());
            }
        }

        // Kirim email notifikasi ke pelanggan Shopee
        try {
            if ($isNewUser) {
                $user->notify(new \App\Notifications\WelcomeCredentialsNotification($plainPassword));
            }
            if (!empty($createdInstance)) {
                $user->notify(new \App\Notifications\VpsProvisionedNotification($createdInstance));
            } else {
                $order->loadMissing(['customer', 'vpsSpec', 'invoice']);
                $user->notify(new \App\Notifications\PaymentReceivedNotification($order, $order->invoice));
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('admin.shopee.notify_failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }

        // Kirim notifikasi ke Admin
        try {
            $adminEmails = \App\Support\MailIdentity::adminRecipients();
            $adminUser = User::whereIn('email', $adminEmails)->first() ?: User::where('is_admin', true)->first();
            if ($adminUser) {
                $order->loadMissing(['customer', 'vpsSpec']);
                $adminUser->notify(new \App\Notifications\AdminShopeeOrderNotification($order));
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('admin.shopee.admin_notify_failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }

        $osDisplay = Order::osLabels()[$validated['os']] ?? $validated['os'];
        $stackDisplay = Order::stackLabels()[$validated['control_panel']] ?? strtoupper($validated['control_panel']);
        $providerDisplay = Order::providerLabels()[$provider] ?? strtoupper($provider);
        $dcDisplay = ($datacenterLocation === 'singapore') ? 'Singapore (Tier-3 Gateway)' : 'Indonesia (Cyber Jakarta)';

        return back()->with('shopee_credentials', [
            'username' => $user->username,
            'password' => $isNewUser ? $plainPassword : '(Gunakan password akun VexaHost lama Anda)',
            'is_new_user' => $isNewUser,
            'customer_name' => $user->full_name,
            'customer_email' => $user->email,
            'customer_phone' => $user->phone ?? null,
            'shopee_order_id' => $validated['shopee_order_id'],
            'package' => $spec->name,
            'spec_specs' => "{$spec->cpu} Core CPU / {$spec->ram} GB RAM / {$spec->disk} GB NVMe",
            'provider' => $providerDisplay,
            'datacenter' => $dcDisplay,
            'os' => $osDisplay,
            'control_panel' => $stackDisplay,
            'order_id' => $order->id,
            'login_url' => route('login'),
            'is_provisioned' => !empty($createdInstance),
            'hostname' => $createdInstance?->hostname ?? $cleanHostname,
            'public_ip' => $createdInstance?->public_ip,
            'ssh_port' => $createdInstance?->ssh_port ?? 22,
            'ssh_username' => $createdInstance?->ssh_user ?? 'root',
            'root_password' => $createdInstance?->initial_root_password,
            'app_url' => $createdInstance?->app_url,
            'db_engine' => $dbEngine ? strtoupper($dbEngine) : null,
        ])->with('success', 'Order Shopee ' . $validated['shopee_order_id'] . ' berhasil diproses dan dicatat ke sistem.');
    }

    public function instances(Request $request)
    {
        $category = $request->input('category', 'all');
        $status = $request->input('status', 'all');
        $search = trim((string) $request->input('search', ''));

        $query = VpsInstance::with(['customer', 'order.vpsSpec', 'activityLogs' => fn($q) => $q->latest()->take(5)])
            ->orderBy('created_at', 'desc');

        if ($status !== 'all' && in_array($status, ['running', 'stopped', 'provisioning', 'suspended', 'terminated', 'error'], true)) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $escapedSearch = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
            $query->where(function ($q) use ($escapedSearch) {
                $q->where('hostname', 'like', "%{$escapedSearch}%")
                  ->orWhere('public_ip', 'like', "%{$escapedSearch}%")
                  ->orWhere('private_ip', 'like', "%{$escapedSearch}%")
                  ->orWhere('control_panel', 'like', "%{$escapedSearch}%")
                  ->orWhere('db_engine', 'like', "%{$escapedSearch}%")
                  ->orWhere('app_name', 'like', "%{$escapedSearch}%")
                  ->orWhereHas('customer', function ($cq) use ($escapedSearch) {
                      $cq->where('full_name', 'like', "%{$escapedSearch}%")
                         ->orWhere('username', 'like', "%{$escapedSearch}%")
                         ->orWhere('email', 'like', "%{$escapedSearch}%");
                  });
            });
        }

        $aiPanels = ['vscode_server', 'hermes_agent', 'hermes_omniroute', 'claude_opencode', 'dify_ollama', 'anythingllm', 'ollama'];

        // app_url tidak dipakai sebagai penanda paket AI: VPS biasa juga menyimpan
        // link control panel (mis. Coolify) di kolom itu. Sama dengan VpsInstance::isAiPackage().
        if ($category === 'ai') {
            $query->where(function ($q) use ($aiPanels) {
                $q->whereIn('control_panel', $aiPanels)
                  ->orWhereHas('order.vpsSpec', function ($sq) {
                      $sq->where('category', 'ai_combo')
                         ->orWhereIn('id', [7, 8, 9, 10]);
                  });
            });
        } elseif ($category === 'database') {
            $query->where(function ($q) {
                $q->where('control_panel', 'managed_database')
                  ->orWhereNotNull('db_engine')
                  ->orWhere('hostname', 'like', 'vx-db-%')
                  ->orWhereHas('order.vpsSpec', function ($sq) {
                      $sq->where('category', 'managed_db')
                         ->orWhereIn('id', [11, 12, 13]);
                  });
            });
        } elseif ($category === 'vps') {
            $query->where(function ($q) use ($aiPanels) {
                $q->whereNotIn('control_panel', array_merge($aiPanels, ['managed_database']))
                  ->whereNull('db_engine')
                  ->where('hostname', 'not like', 'vx-db-%')
                  ->where(function ($subQ) {
                      $subQ->doesntHave('order.vpsSpec')
                           ->orWhereHas('order.vpsSpec', function ($sq) {
                               $sq->whereNotIn('category', ['ai_combo', 'managed_db'])
                                  ->whereNotIn('id', [7, 8, 9, 10, 11, 12, 13]);
                           });
                  });
            });
        }

        $instances = $query->paginate(15)->withQueryString();

        $statusCounts = [
            'all' => VpsInstance::count(),
            'running' => VpsInstance::where('status', 'running')->count(),
            'stopped' => VpsInstance::where('status', 'stopped')->count(),
            'provisioning' => VpsInstance::where('status', 'provisioning')->count(),
            'suspended' => VpsInstance::where('status', 'suspended')->count(),
            'terminated' => VpsInstance::where('status', 'terminated')->count(),
        ];

        $aiCount = VpsInstance::where(function ($q) use ($aiPanels) {
            $q->whereIn('control_panel', $aiPanels)
              ->orWhereHas('order.vpsSpec', function ($sq) {
                  $sq->where('category', 'ai_combo')->orWhereIn('id', [7, 8, 9, 10]);
              });
        })->count();

        $databaseCount = VpsInstance::where(function ($q) {
            $q->where('control_panel', 'managed_database')
              ->orWhereNotNull('db_engine')
              ->orWhere('hostname', 'like', 'vx-db-%')
              ->orWhereHas('order.vpsSpec', function ($sq) {
                  $sq->where('category', 'managed_db')->orWhereIn('id', [11, 12, 13]);
              });
        })->count();

        $allCount = $statusCounts['all'];
        $vpsCount = max(0, $allCount - $aiCount - $databaseCount);

        $categoryCounts = [
            'all' => $allCount,
            'vps' => $vpsCount,
            'ai' => $aiCount,
            'database' => $databaseCount,
        ];

        $recentLogs = \App\Models\VpsActivityLog::with(['user', 'vpsInstance'])
            ->latest()
            ->take(15)
            ->get();

        return view('admin.instances', compact('instances', 'recentLogs', 'statusCounts', 'categoryCounts', 'category', 'status', 'search'));
    }

    public function monitoring()
    {
        $statusCounts = VpsInstance::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $problemInstances = VpsInstance::with('customer')->whereIn('status', ['error', 'suspended', 'terminated'])->latest('updated_at')->take(25)->get();
        $staleProvisioning = VpsInstance::with('customer')->where('status', 'provisioning')->where('created_at', '<', now()->subMinutes(30))->latest()->get();
        $slaBreaches = SupportTicket::with(['customer', 'assignee'])->whereIn('status', ['open', 'in_progress'])
            ->whereNotNull('sla_due_at')->where('sla_due_at', '<', now())->whereNull('first_response_at')->latest('sla_due_at')->get();
        $recentTransitions = VpsStatusHistory::with('vpsInstance')->latest('created_at')->take(25)->get();

        return view('admin.monitoring', compact('statusCounts', 'problemInstances', 'staleProvisioning', 'slaBreaches', 'recentTransitions'));
    }

    public function reports(Request $request)
    {
        $days = $request->input('days', 30);
        $days = ($days === 'all') ? 'all' : (int) $days;
        abort_unless(in_array($days, [30, 90, 365, 'all'], true), 422);

        $data = $this->reportExportService->getReportData('executive', $days);

        return view('admin.reports', [
            'days' => $days,
            'periodLabel' => $data['periodLabel'],
            'revenue' => $data['revenue'],
            'paidInvoices' => $data['paidInvoices'],
            'orders' => $data['orders'],
            'invoices' => $data['invoices'],
            'tickets' => $data['tickets'],
            'vpsOrders' => $data['vpsOrders'] ?? collect(),
            'aiOrders' => $data['aiOrders'] ?? collect(),
            'dbOrders' => $data['dbOrders'] ?? collect(),
            'vpsRevenue' => $data['vpsRevenue'] ?? 0,
            'aiRevenue' => $data['aiRevenue'] ?? 0,
            'dbRevenue' => $data['dbRevenue'] ?? 0,
            'avgFirstResponse' => $data['avgFirstResponse'] ?? null,
            'dailyRevenue' => $data['dailyRevenue'] ?? collect(),
            'orderStatuses' => $data['orderStatuses'] ?? collect(),
            'ticketStatuses' => $data['ticketStatuses'] ?? collect(),
            'reportRef' => $data['reportRef'] ?? 'VXH-RPT',
            'generatedBy' => $data['generatedBy'] ?? 'Administrator',
        ]);
    }

    public function previewReport(Request $request)
    {
        $type = $request->input('type', 'executive');
        if ($type === 'ai') {
            $type = 'ai_combo';
        } elseif ($type === 'managed_db') {
            $type = 'database';
        }

        abort_unless(in_array($type, ['executive', 'orders', 'vps', 'ai_combo', 'database', 'invoices', 'tickets'], true), 404);

        $days = $request->input('days', 30);
        $days = ($days === 'all') ? 'all' : (int) $days;
        abort_unless(in_array($days, [30, 90, 365, 'all'], true), 422);

        return $this->reportExportService->generatePdf($type, $days, inline: true);
    }

    public function exportReport(Request $request)
    {
        $validated = $request->validate([
            'type' => 'nullable|in:executive,orders,vps,ai_combo,ai,database,managed_db,invoices,tickets',
            'format' => 'nullable|in:pdf,docx,word,csv',
            'days' => 'nullable',
        ]);

        $type = $validated['type'] ?? 'executive';
        if ($type === 'ai') {
            $type = 'ai_combo';
        } elseif ($type === 'managed_db') {
            $type = 'database';
        }

        $format = strtolower($validated['format'] ?? 'csv');

        $days = $request->input('days', 30);
        $days = ($days === 'all') ? 'all' : (int) $days;
        if (!in_array($days, [30, 90, 365, 'all'], true)) {
            $days = 30;
        }

        if ($format === 'pdf') {
            return $this->reportExportService->generatePdf($type, $days, inline: false);
        }

        if ($format === 'docx' || $format === 'word') {
            return $this->reportExportService->generateDocx($type, $days);
        }

        return $this->reportExportService->streamCsv($type, $days);
    }

    public function updateInstanceStatus(Request $request, $id)
    {
        $instance = VpsInstance::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:running,stopped,provisioning,error,suspended,terminated',
            'reason' => 'nullable|string|max:255',
        ]);

        try {
            $this->vpsStateMachine->transition($instance, $validated['status'], [
                'reason' => $validated['reason'] ?? 'Update status oleh Administrator.',
                'actor_type' => 'admin',
            ]);

            $instance->refresh()->logActivity(
                action: 'status_change',
                description: "Status VPS diubah ke {$validated['status']} oleh Administrator.",
                status: 'completed',
                userId: Auth::id()
            );
        } catch (InvalidStateTransitionException $e) {
            return back()->with('error', 'Gagal ubah status: ' . $e->getMessage());
        }

        return back()->with('success', 'Status VPS ' . ($instance->hostname ?? $instance->id) . ' berhasil diubah menjadi ' . $validated['status'] . '.');
    }

    /**
     * Ubah link control panel (kolom app_url) yang tampil di dashboard pelanggan.
     * Dipakai bila link belum diisi saat serah terima atau perlu dikoreksi.
     */
    public function updateInstancePanelUrl(Request $request, $id)
    {
        $instance = VpsInstance::findOrFail($id);

        $validated = $request->validate([
            'app_url' => ['nullable', 'url:http,https', 'max:255'],
        ], self::PANEL_URL_MESSAGES);

        $newUrl = $validated['app_url'] ?? null;
        $instance->update(['app_url' => $newUrl]);

        $instance->logActivity(
            action: 'panel_url_updated',
            description: $newUrl ? "Link control panel diperbarui tim: {$newUrl}" : 'Link control panel dikosongkan oleh tim.',
            status: 'completed',
            userId: Auth::id()
        );

        $serverName = $instance->hostname ?? ('#' . $instance->id);

        return back()->with('success', "Link control panel {$serverName} berhasil disimpan.");
    }

    /**
     * Ubah username SSH VPS aktif. Pelanggan otomatis diberi tahu (email + WA + notifikasi dasbor).
     */
    public function updateInstanceSshUsername(Request $request, $id)
    {
        $validated = $request->validate([
            'ssh_username' => array_merge(['required'], array_slice(VpsInstance::SSH_USERNAME_RULE, 1)),
        ], VpsInstance::SSH_USERNAME_MESSAGES);

        $instance = VpsInstance::with('customer')->findOrFail($id);
        $serverName = $instance->hostname ?? ('#' . $instance->id);
        $oldUsername = $instance->ssh_user;
        $newUsername = VpsInstance::normalizeSshUsername($validated['ssh_username']);

        if ($oldUsername === $newUsername) {
            return back()->with('success', "Username SSH {$serverName} tidak berubah ({$newUsername}).");
        }

        $instance->update(['ssh_username' => $newUsername]);

        $instance->logActivity(
            action: 'ssh_username_updated',
            description: "Username SSH diperbarui tim: {$oldUsername} → {$newUsername}",
            status: 'completed',
            userId: Auth::id()
        );

        try {
            $instance->customer?->notify(new \App\Notifications\VpsSshUsernameChangedNotification($instance->fresh(), $oldUsername));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('admin.ssh_username.notify_failed', ['instance_id' => $instance->id, 'error' => $e->getMessage()]);
        }

        return back()->with('success', "Username SSH {$serverName} diubah ke {$newUsername}. Pelanggan sudah diberi tahu.");
    }

    /**
     * PILAR 2 - Custom Renewal Price Override per-VPS Instance.
     */
    public function updateInstanceRenewalPrice(Request $request, $id)
    {
        $validated = $request->validate([
            'custom_renewal_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $instance = VpsInstance::findOrFail($id);
        $newPrice = $request->filled('custom_renewal_price') ? (float) $validated['custom_renewal_price'] : null;

        $instance->update([
            'custom_renewal_price' => $newPrice,
        ]);

        $serverName = $instance->hostname ?? ('#' . $instance->id);
        $msg = $newPrice !== null
            ? "Tarif perpanjangan khusus server {$serverName} berhasil diatur ke Rp " . number_format($newPrice, 0, ',', '.') . "/bln."
            : "Tarif perpanjangan server {$serverName} dikembalikan mengikuti katalog umum.";

        $instance->logActivity('custom_renewal_price_updated', $msg, 'completed', Auth::id());

        return back()->with('success', $msg);
    }

    public function suspendInstance(Request $request, $id)
    {
        $instance = VpsInstance::findOrFail($id);
        $reason = $request->input('reason', 'Penangguhan layanan oleh Administrator.');

        try {
            $this->vpsStateMachine->transition($instance, 'suspended', [
                'reason' => $reason,
                'actor_type' => 'admin',
            ]);

            $instance->refresh()->logActivity(
                action: 'suspend',
                description: "VPS ditangguhkan oleh Administrator. Alasan: {$reason}",
                status: 'completed',
                userId: Auth::id()
            );
        } catch (InvalidStateTransitionException $e) {
            return back()->with('error', 'Gagal suspend: ' . $e->getMessage());
        }

        return back()->with('success', "VPS {$instance->hostname} berhasil ditangguhkan (Suspended).");
    }

    public function unsuspendInstance(Request $request, $id)
    {
        $instance = VpsInstance::findOrFail($id);
        $reason = $request->input('reason', 'Penangguhan dicabut oleh Administrator.');

        try {
            $this->vpsStateMachine->transition($instance, 'running', [
                'reason' => $reason,
                'actor_type' => 'admin',
            ]);

            $instance->refresh()->logActivity(
                action: 'unsuspend',
                description: "Penangguhan VPS dicabut oleh Administrator. Status kembali Running.",
                status: 'completed',
                userId: Auth::id()
            );
        } catch (InvalidStateTransitionException $e) {
            return back()->with('error', 'Gagal unsuspend: ' . $e->getMessage());
        }

        return back()->with('success', "Penangguhan VPS {$instance->hostname} berhasil dicabut. Server kembali aktif (Running).");
    }

    public function terminateInstance(Request $request, $id)
    {
        $instance = VpsInstance::with('order')->findOrFail($id);
        $reason = $request->input('reason', 'Terminasi permanen oleh Administrator.');

        try {
            DB::transaction(function () use ($instance, $reason) {
                $this->vpsStateMachine->transition($instance, 'terminated', [
                    'reason' => $reason,
                    'actor_type' => 'admin',
                ]);

                if ($instance->order && $instance->order->status !== 'terminated') {
                    // Order juga di-terminate.
                    try {
                        $this->orderStateMachine->transition($instance->order, 'terminated', [
                            'reason' => "Terkait terminasi VPS #{$instance->id}. Alasan: {$reason}",
                            'actor_type' => 'admin',
                        ]);
                    } catch (InvalidStateTransitionException $e) {
                        // Order sudah di terminal state lain (cancelled/expired). Aman diabaikan.
                    }
                }
            });

            $instance->refresh()->logActivity(
                action: 'terminate',
                description: "VPS dan alokasi resource di-terminasi permanen. Alasan: {$reason}",
                status: 'completed',
                userId: Auth::id()
            );
        } catch (InvalidStateTransitionException $e) {
            return back()->with('error', 'Gagal terminate: ' . $e->getMessage());
        }

        return back()->with('success', "VPS {$instance->hostname} berhasil di-terminasi (Terminated).");
    }

    public function customers(Request $request)
    {
        $query = User::where('is_admin', false)->withCount(['vpsInstances', 'orders'])->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $search = trim($request->search);
            $escapedSearch = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
            $query->where(function ($q) use ($escapedSearch) {
                $q->where('full_name', 'like', "%{$escapedSearch}%")
                  ->orWhere('username', 'like', "%{$escapedSearch}%")
                  ->orWhere('email', 'like', "%{$escapedSearch}%")
                  ->orWhere('shopee_order_id', 'like', "%{$escapedSearch}%");
            });
        }

        if ($request->filled('channel')) {
            $query->where('channel', $request->channel);
        }

        $customers = $query->paginate(15)->withQueryString();

        return view('admin.customers', compact('customers'));
    }

    public function tickets(Request $request)
    {
        $query = SupportTicket::with(['customer', 'vpsInstance', 'latestMessage', 'assignee'])->orderBy('updated_at', 'desc');

        if ($request->filled('status')) {
            // 'active' = semua tiket yang masih perlu ditangani (open + in_progress).
            if ($request->status === 'active') {
                $query->whereIn('status', SupportTicket::OPEN_STATUSES);
            } else {
                $query->where('status', $request->status);
            }
        }

        if ($request->type === 'service') {
            // Semua permintaan layanan server (reinstall + server tidak bisa diakses).
            $query->serviceRequests();
        } elseif ($request->filled('type') && array_key_exists($request->type, SupportTicket::typeLabels())) {
            $query->where('type', $request->type);
        }

        // Jumlah permintaan layanan server yang masih menunggu, untuk penanda di daftar.
        $openServiceRequestCount = SupportTicket::serviceRequests()->awaitingTeam()->count();

        $tickets = $query->paginate(15)->withQueryString();
        return view('admin.tickets', compact('tickets', 'openServiceRequestCount'));
    }

    public function showTicket($id)
    {
        $ticket = SupportTicket::with(['customer', 'vpsInstance', 'messages.user', 'assignee'])->findOrFail($id);
        $admins = User::where('is_admin', true)->orderBy('full_name')->get();
        return view('admin.ticket-show', compact('ticket', 'admins'));
    }

    /**
     * Selesaikan permintaan reinstall OS dari pelanggan.
     *
     * Reinstall sudah dikerjakan admin secara manual di dashboard supplier
     * (server dibeli retail, tanpa API). Di sini admin mencatat OS/stack yang
     * terpasang dan password root baru, menutup tiket, lalu memberi tahu
     * pelanggan. Password tidak pernah dikirim lewat email: pelanggan melihatnya
     * di dashboard (tab Akses, ikon mata).
     */
    public function completeReinstall(Request $request, $id)
    {
        $ticket = SupportTicket::with('vpsInstance')->findOrFail($id);

        if ($ticket->type !== SupportTicket::TYPE_REINSTALL) {
            return back()->with('error', 'Tiket ini bukan permintaan reinstall.');
        }
        if (!$ticket->isOpen()) {
            return back()->with('error', 'Permintaan reinstall ini sudah ditutup.');
        }

        $vps = $ticket->vpsInstance;
        if (!$vps) {
            return back()->with('error', 'Server yang terkait dengan tiket ini tidak ditemukan.');
        }

        $osOptions = $vps->reinstallOsOptions();
        $stackOptions = $vps->reinstallStackOptions();

        $rules = [
            'os' => ['required', 'string', Rule::in(array_keys($osOptions))],
            'root_password' => ['required', 'string', 'min:8', 'max:128'],
            'ssh_username' => VpsInstance::SSH_USERNAME_RULE,
            'message' => ['nullable', 'string', 'max:2000'],
        ];
        if (!$vps->hasFixedStack()) {
            $rules['control_panel'] = ['required', 'string', Rule::in(array_keys($stackOptions))];
        }
        $validated = $request->validate($rules, VpsInstance::SSH_USERNAME_MESSAGES);
        // Kosong = pertahankan username yang sekarang.
        $sshUsername = trim((string) ($validated['ssh_username'] ?? '')) !== ''
            ? VpsInstance::normalizeSshUsername($validated['ssh_username'])
            : $vps->ssh_user;

        $controlPanel = $vps->hasFixedStack() ? $vps->control_panel : $validated['control_panel'];
        $osLabel = $osOptions[$validated['os']];
        $serverName = $vps->hostname ?? ('VPS-' . $vps->id);
        $ticketCode = '#TK-' . str_pad((string) $ticket->id, 4, '0', STR_PAD_LEFT);

        $customerMessage = trim((string) ($validated['message'] ?? ''));
        if ($customerMessage === '') {
            $customerMessage = "Reinstall OS {$osLabel} untuk server {$serverName} sudah selesai.\n\n"
                . "Username SSH: {$sshUsername}. Password baru dapat dilihat di Dashboard > Detail VPS > tab Akses (klik ikon mata). "
                . "Setelah login, segera ganti password dengan perintah passwd.";
        }

        $completed = DB::transaction(function () use ($ticket, $vps, $validated, $controlPanel, $osLabel, $customerMessage, $ticketCode, $sshUsername) {
            // Kunci tiket agar dua admin tidak menyelesaikan permintaan yang sama.
            $lockedTicket = SupportTicket::whereKey($ticket->id)->lockForUpdate()->first();
            if (!$lockedTicket || !$lockedTicket->isOpen()) {
                return false;
            }

            $lockedVps = VpsInstance::whereKey($vps->id)->lockForUpdate()->firstOrFail();
            $lockedVps->os = $validated['os'];
            $lockedVps->control_panel = $controlPanel;
            $lockedVps->initial_root_password = $validated['root_password'];
            $lockedVps->ssh_username = $sshUsername;
            // Reset agar pelanggan melihat password baru sebagai belum pernah dibuka.
            $lockedVps->root_password_revealed_at = null;
            $lockedVps->save();

            TicketMessage::create([
                'ticket_id' => $lockedTicket->id,
                'user_id' => Auth::id(),
                'message' => $customerMessage,
                'is_admin_reply' => true,
                'is_internal' => false,
            ]);

            $lockedTicket->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'first_response_at' => $lockedTicket->first_response_at ?: now(),
            ]);

            $lockedVps->logActivity(
                action: 'reinstall_completed',
                description: "Reinstall OS {$osLabel} selesai dikerjakan tim. Password root baru tersedia di tab Akses. ({$ticketCode})",
                status: 'completed',
                userId: Auth::id()
            );

            return true;
        });

        if (!$completed) {
            return back()->with('error', 'Permintaan reinstall ini sudah diselesaikan oleh admin lain.');
        }

        try {
            $ticket->refresh()->customer?->notify(new \App\Notifications\TicketRepliedNotification($ticket, $customerMessage));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('admin.ticket_reinstall.notify_failed', ['ticket_id' => $ticket->id, 'error' => $e->getMessage()]);
        }

        return back()->with('success', "Reinstall {$serverName} ditandai selesai. Password root baru tersimpan dan pelanggan sudah diberi tahu.");
    }

    public function replyTicket(Request $request, $id)
    {
        $ticket = SupportTicket::findOrFail($id);
        $validated = $request->validate([
            'message' => 'required|string|min:2',
            'status' => 'required|in:open,in_progress,resolved,closed',
            'is_internal' => 'nullable|boolean',
        ]);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'message' => $validated['message'],
            'is_admin_reply' => true,
            'is_internal' => $request->boolean('is_internal'),
        ]);

        $updates = ['status' => $validated['status']];
        if (!$request->boolean('is_internal') && !$ticket->first_response_at) {
            $updates['first_response_at'] = now();
        }
        if ($validated['status'] === 'resolved' || $validated['status'] === 'closed') {
            $updates['resolved_at'] = $ticket->resolved_at ?: now();
        } elseif ($validated['status'] === 'open' || $validated['status'] === 'in_progress') {
            $updates['resolved_at'] = null;
        }
        $ticket->update($updates);
        $ticket->touch();

        // Kirim email notifikasi balasan ke customer jika bukan internal note
        if (!$request->boolean('is_internal')) {
            try {
                $customer = $ticket->customer;
                if ($customer) {
                    $customer->notify(new \App\Notifications\TicketRepliedNotification($ticket, $validated['message']));
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('admin.ticket_reply.notify_failed', ['ticket_id' => $ticket->id, 'error' => $e->getMessage()]);
            }
        }

        return back()->with('success', 'Balasan admin berhasil dikirim ke tiket pelanggan.');
    }

    public function assignTicket(Request $request, $id)
    {
        $validated = $request->validate(['assigned_to' => 'nullable|exists:users,id']);
        if (!empty($validated['assigned_to']) && !User::where('id', $validated['assigned_to'])->where('is_admin', true)->exists()) {
            return back()->with('error', 'Petugas yang dipilih bukan administrator.');
        }
        SupportTicket::findOrFail($id)->update(['assigned_to' => $validated['assigned_to'] ?? null]);
        return back()->with('success', 'Assignment tiket berhasil diperbarui.');
    }

    /**
     * Tampilkan halaman kelola Paket VPS (CRUD).
     */
    public function packages()
    {
        $specs = VpsSpec::with(['replacementSpec'])
            ->withCount(['orders'])
            ->orderBy('sell_price', 'asc')
            ->get();

        $corePlanNames = [
            'Student Basic',
            'Mahasiswa Basic',
            'Standard',
            'Premium',
            'Startup',
            'Business',
            'Terminal Coding Agent',
            'Cloud AI Workstation',
            'Hermes Autonomous Hub',
            'Enterprise Private AI & RAG',
            'DB Micro',
            'DB Standard',
            'DB Enterprise / AI Vector',
        ];

        return view('admin.packages', compact('specs', 'corePlanNames'));
    }

    /**
     * Simpan paket VPS baru ke database.
     */
    public function storePackage(Request $request)
    {
        if ($request->filled('payment_url')) {
            $url = trim((string) $request->input('payment_url'));
            if (!preg_match('~^https?://~i', $url)) {
                $url = 'https://' . $url;
            }
            $request->merge(['payment_url' => $url]);
        } elseif ($request->has('payment_url')) {
            $request->merge(['payment_url' => null]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:vps_specs,name',
            'category' => 'nullable|string|in:vps,ai_combo,managed_db',
            'tagline' => 'nullable|string|max:255',
            'badge' => 'nullable|string|max:100',
            'target_audience' => 'nullable|string|max:255',
            'solution' => 'nullable|string|max:1000',
            'default_stack' => 'nullable|string|max:50',
            'allowed_providers' => 'nullable|array',
            'allowed_providers.*' => 'in:tencent,cloudeka',
            'default_provider' => 'nullable|string|in:tencent,cloudeka',
            'features' => 'nullable',
            'cpu' => 'required|integer|min:1|max:128',
            'ram' => 'required|integer|min:1|max:1024',
            'disk' => 'required|integer|min:5|max:10000',
            'bandwidth' => 'required|integer|min:1|max:10000',
            'cost_price' => 'required|numeric|min:0',
            'sell_price' => 'required|numeric|min:0',
            'payment_url' => 'nullable|url|max:500',
            'is_active' => 'nullable|boolean',
            'is_renewable' => 'nullable|boolean',
            'replacement_spec_id' => 'nullable|integer|exists:vps_specs,id',
        ]);

        $validated['category'] = $validated['category'] ?? 'vps';
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['is_renewable'] = $request->boolean('is_renewable', true);
        $validated['replacement_spec_id'] = $request->filled('replacement_spec_id') ? (int) $request->input('replacement_spec_id') : null;

        // Format features if provided as string
        if (isset($validated['features']) && is_string($validated['features'])) {
            $validated['features'] = array_values(array_filter(array_map('trim', explode("\n", $validated['features']))));
        }

        // Format allowed providers & default provider
        if (empty($validated['allowed_providers'])) {
            $validated['allowed_providers'] = ['tencent'];
        }
        if (empty($validated['default_provider']) || !in_array($validated['default_provider'], $validated['allowed_providers'], true)) {
            $validated['default_provider'] = $validated['allowed_providers'][0];
        }

        VpsSpec::create($validated);
        \Illuminate\Support\Facades\Cache::forget('landing.specs');

        return back()->with('success', "Paket '{$validated['name']}' berhasil ditambahkan.");
    }

    /**
     * Update data paket VPS yang ada.
     */
    public function updatePackage(Request $request, $id)
    {
        $spec = VpsSpec::findOrFail($id);

        if ($request->filled('payment_url')) {
            $url = trim((string) $request->input('payment_url'));
            if (!preg_match('~^https?://~i', $url)) {
                $url = 'https://' . $url;
            }
            $request->merge(['payment_url' => $url]);
        } elseif ($request->has('payment_url')) {
            $request->merge(['payment_url' => null]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:vps_specs,name,' . $spec->id,
            'category' => 'nullable|string|in:vps,ai_combo,managed_db',
            'tagline' => 'nullable|string|max:255',
            'badge' => 'nullable|string|max:100',
            'target_audience' => 'nullable|string|max:255',
            'solution' => 'nullable|string|max:1000',
            'default_stack' => 'nullable|string|max:50',
            'allowed_providers' => 'nullable|array',
            'allowed_providers.*' => 'in:tencent,cloudeka',
            'default_provider' => 'nullable|string|in:tencent,cloudeka',
            'features' => 'nullable',
            'cpu' => 'required|integer|min:1|max:128',
            'ram' => 'required|integer|min:1|max:1024',
            'disk' => 'required|integer|min:5|max:10000',
            'bandwidth' => 'required|integer|min:1|max:10000',
            'cost_price' => 'required|numeric|min:0',
            'sell_price' => 'required|numeric|min:0',
            'payment_url' => 'nullable|url|max:500',
            'is_active' => 'nullable|boolean',
            'is_renewable' => 'nullable|boolean',
            'replacement_spec_id' => 'nullable|integer|exists:vps_specs,id',
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_renewable'] = $request->boolean('is_renewable');
        $replacementId = $request->filled('replacement_spec_id') ? (int) $request->input('replacement_spec_id') : null;
        if ($replacementId === (int) $spec->id) {
            $replacementId = null;
        }
        $validated['replacement_spec_id'] = $replacementId;

        // Format features if provided as string
        if (isset($validated['features']) && is_string($validated['features'])) {
            $validated['features'] = array_values(array_filter(array_map('trim', explode("\n", $validated['features']))));
        }

        // Format allowed providers & default provider
        if (!empty($validated['allowed_providers'])) {
            if (empty($validated['default_provider']) || !in_array($validated['default_provider'], $validated['allowed_providers'], true)) {
                $validated['default_provider'] = $validated['allowed_providers'][0];
            }
        }

        $spec->update($validated);
        \Illuminate\Support\Facades\Cache::forget('landing.specs');

        return back()->with('success', "Paket '{$spec->name}' berhasil diperbarui.");
    }

    /**
     * Hapus paket VPS (dengan proteksi terhadap paket inti, AI combo, managed DB & order aktif).
     */
    public function destroyPackage($id)
    {
        $spec = VpsSpec::findOrFail($id);

        // Proteksi paket inti utama, AI combo & Managed DB
        $corePlanNames = [
            'student basic',
            'mahasiswa basic',
            'standard',
            'premium',
            'startup',
            'business',
            'terminal coding agent',
            'cloud ai workstation',
            'hermes autonomous hub',
            'enterprise private ai & rag',
            'db micro',
            'db standard',
            'db enterprise / ai vector',
        ];

        if (in_array(strtolower($spec->name), $corePlanNames, true) || in_array((int)$spec->id, [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13], true)) {
            return back()->with('error', "Paket inti '{$spec->name}' dilindungi oleh sistem dan tidak boleh dihapus.");
        }

        // Proteksi jika sudah memiliki pesanan / order terkait
        if ($spec->orders()->exists()) {
            return back()->with('error', "Paket '{$spec->name}' memiliki riwayat pesanan di database dan tidak dapat dihapus.");
        }

        $specName = $spec->name;
        $spec->delete();
        \Illuminate\Support\Facades\Cache::forget('landing.specs');

        return back()->with('success', "Paket '{$specName}' berhasil dihapus.");
    }
}
