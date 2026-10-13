<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InvalidStateTransitionException;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\VpsInstance;
use App\Models\VpsSpec;
use App\Models\VpsStatusHistory;
use App\Services\OrderStateMachine;
use App\Services\VpsStateMachine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminApiController extends Controller
{
    public function __construct(
        protected OrderStateMachine $orderStateMachine,
        protected VpsStateMachine $vpsStateMachine
    ) {
    }

    /**
     * PRD 10.1: Provisioning Workflow via API.
     * Sekarang lewat state machine: paid → provisioning → active.
     * Kalau order masih 'pending' (belum bayar), tolak.
     */
    public function provision(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'server_ip' => 'required|ip',
            'private_ip' => 'nullable|ip',
            'server_root_password' => 'nullable|string',
            'os' => 'required|string',
            'datacenter_location' => 'required|string',
            'control_panel' => 'required|string',
            'hostname' => 'required|string',
            'ssh_port' => 'nullable|integer|min:1|max:65535',
            'ssh_username' => VpsInstance::SSH_USERNAME_RULE,
        ], VpsInstance::SSH_USERNAME_MESSAGES);

        $order = Order::with('vpsSpec')->findOrFail($validated['order_id']);

        // Guard: order harus sudah dibayar.
        if (!in_array($order->status, ['paid', 'failed'], true)) {
            return response()->json([
                'success' => false,
                'message' => "Order tidak dalam status yang bisa di-provision. Status: {$order->status}.",
            ], 409);
        }

        if ($order->status === 'paid' && VpsInstance::where('order_id', $order->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Order sudah pernah di-provision.',
            ], 409);
        }

        try {
            $instance = DB::transaction(function () use ($order, $validated) {
                $this->orderStateMachine->transition($order, 'provisioning', [
                    'reason' => 'Admin API provision request.',
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
                $months = match ($order->billing_cycle ?? 'monthly') {
                    'quarterly' => 3,
                    'semi_annual' => 6,
                    'annual' => 12,
                    default => 1,
                };
                $expiresAt = $startsAt->copy()->addMonths($months);
                // Tenggang mengikuti jenis produk dan selalu di bawah batas hapus Supplier.
                $graceEndsAt = $expiresAt->copy()->addDays(app(\App\Services\RenewalService::class)->graceDaysForOrder($order));

                $isDb = $order->isDatabasePackage();
                $dbEngine = $order->db_engine ?: ($isDb ? 'postgres' : null);
                $dbManager = $order->db_manager ?: ($isDb ? 'cloudbeaver' : null);
                $dbPort = $order->db_port ?: ($isDb ? match($dbEngine) {
                    'mysql' => 3306,
                    'redis' => 6379,
                    'mongodb' => 27017,
                    'vector' => 6333,
                    default => 5432,
                } : null);
                $dbPassword = $isDb ? ($order->db_password ?: ($validated['server_root_password'] ?? null)) : null;
                $appUrl = ($isDb && $dbManager === 'cloudbeaver') ? "https://{$validated['server_ip']}:8080" : null;
                $appName = ($isDb && $dbManager === 'cloudbeaver') ? 'CloudBeaver Web GUI' : null;

                $instance = VpsInstance::create([
                    'customer_id' => $order->customer_id,
                    'order_id' => $order->id,
                    'organization_id' => $order->organization_id,
                    'hostname' => $validated['hostname'],
                    'public_ip' => $validated['server_ip'],
                    'private_ip' => $validated['private_ip'] ?? null,
                    'ssh_port' => $validated['ssh_port'] ?? 22,
                    'ssh_username' => VpsInstance::normalizeSshUsername($validated['ssh_username'] ?? null),
                    'initial_root_password' => $validated['server_root_password'] ?? null,
                    'os' => $validated['os'],
                    'datacenter_location' => $validated['datacenter_location'],
                    'status' => 'provisioning',
                    'cpu' => $spec->cpu ?? 2,
                    'ram' => $spec->ram ?? 4,
                    'disk' => $spec->disk ?? 60,
                    'control_panel' => $validated['control_panel'],
                    'db_engine' => $dbEngine,
                    'db_manager' => $dbManager,
                    'db_name' => $isDb ? ($order->db_name ?: 'vexadb_production') : null,
                    'db_user' => $isDb ? ($order->db_user ?: 'admin_vexa') : null,
                    'db_password' => $dbPassword,
                    'db_port' => $dbPort,
                    'app_url' => $appUrl,
                    'app_name' => $appName,
                    'provider' => $order->provider ?? 'tencent',
                    'uptime_percent' => null,
                    'billing_cycle' => $order->billing_cycle ?? 'monthly',
                    'starts_at' => $startsAt,
                    'expires_at' => $expiresAt,
                    'grace_period_ends_at' => $graceEndsAt,
                    'auto_renew' => true,
                ]);

                VpsStatusHistory::create([
                    'vps_instance_id' => $instance->id,
                    'from_status' => null,
                    'to_status' => 'provisioning',
                    'reason' => 'VPS instance dibuat via Admin API.',
                    'actor_user_id' => auth()->id(),
                    'actor_type' => 'admin',
                    'ip_address' => request()->ip(),
                    'metadata' => ['order_id' => $order->id],
                    'created_at' => now(),
                ]);

                $this->vpsStateMachine->transition($instance, 'running', [
                    'reason' => 'Provisioning selesai via Admin API.',
                    'actor_type' => 'admin',
                ]);

                $this->orderStateMachine->transition($order, 'active', [
                    'reason' => 'Provisioning sukses via Admin API.',
                    'actor_type' => 'admin',
                    'metadata' => ['vps_instance_id' => $instance->id],
                    'onLocked' => function (Order $locked) use ($startsAt, $expiresAt, $graceEndsAt, $validated, $isDb, $dbPassword) {
                        $locked->starts_at = $startsAt;
                        $locked->expires_at = $expiresAt;
                        $locked->grace_period_ends_at = $graceEndsAt;
                        if (!empty($validated['server_root_password'])) {
                            $locked->root_password = $validated['server_root_password'];
                        }
                        if ($isDb && empty($locked->db_password)) {
                            $locked->db_password = $dbPassword;
                        }
                        $locked->save();
                    },
                ]);

                $instance->refresh()->logActivity(
                    action: 'provision',
                    description: "VPS {$instance->hostname} (IP: {$instance->public_ip}) berhasil di-provision via API.",
                    status: 'completed'
                );

                return $instance;
            });
        } catch (InvalidStateTransitionException $e) {
            return response()->json([
                'success' => false,
                'message' => 'State transition ditolak: ' . $e->getMessage(),
            ], 409);
        } catch (\Throwable $e) {
            // Rollback sudah terjadi. Tandai order failed supaya bisa retry.
            $order->refresh();
            try {
                if ($order->status === 'provisioning') {
                    $this->orderStateMachine->transition($order, 'failed', [
                        'reason' => 'Provisioning API error: ' . $e->getMessage(),
                        'actor_type' => 'system',
                        'onLocked' => function (Order $locked) use ($e) {
                            $locked->failure_reason = mb_substr($e->getMessage(), 0, 500);
                            $locked->save();
                        },
                    ]);
                }
            } catch (\Throwable $inner) {
                // ignore
            }
            return response()->json([
                'success' => false,
                'message' => 'Provisioning gagal: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'vps_instance_id' => $instance->id,
            'message' => 'VPS provisioned successfully',
        ], 200);
    }

    /**
     * PRD 10.2: Shopee Order Processing API.
     * Sekarang: create order pending → paid (Shopee sudah bayar external),
     * catat PaymentTransaction provider='shopee', lewat state machine.
     */
    public function processShopeeOrder(Request $request)
    {
        $validated = $request->validate([
            'shopee_order_id' => 'required|string',
            'customer_name' => 'required|string',
            'customer_email' => 'required|email',
            'package' => 'required|string',
            'control_panel' => 'required|string',
            'datacenter_location' => 'required|string',
            'os' => 'required|string',
        ]);

        $existingOrder = Order::where('shopee_order_id', $validated['shopee_order_id'])->first();
        if ($existingOrder) {
            return response()->json([
                'success' => false,
                'message' => 'Shopee order ID already processed',
            ], 409);
        }

        $generatedUsername = 'vx_' . Str::lower(Str::random(8));
        while (User::where('username', $generatedUsername)->exists()) {
            $generatedUsername = 'vx_' . Str::lower(Str::random(8));
        }

        $user = User::where('email', strtolower($validated['customer_email']))->first();
        $isNewUser = false;
        $password = null;

        if (!$user) {
            $isNewUser = true;
            $password = Str::password(16, true, true, false, false);

            $user = User::create([
                'username' => $generatedUsername,
                'email' => strtolower($validated['customer_email']),
                'password' => Hash::make($password),
                'full_name' => $validated['customer_name'],
                'channel' => 'shopee',
                'shopee_order_id' => $validated['shopee_order_id'],
            ]);
        }

        $organization = $user->currentOrganization
            ?? $user->organizations()->first()
            ?? $user->createPersonalOrganization();
        $user->switchToOrganization($organization);

        $packageKey = strtolower(trim($validated['package']));
        $packageMap = [
            'student basic' => 1,
            'student-basic' => 1,
            'student' => 1,
            'basic' => 1,
            'standard' => 2,
            'standar' => 2,
            'premium' => 3,
            'startup' => 4,
            'business' => 5,
            'bisnis' => 5,
            'mahasiswa basic' => 6,
            'mahasiswa-basic' => 6,
            'mahasiswa' => 6,
            'terminal coding agent' => 7,
            'terminal-coding-agent' => 7,
            'terminal' => 7,
            'claude opencode' => 7,
            'cloud ai workstation' => 8,
            'cloud-ai-workstation' => 8,
            'ai workstation' => 8,
            'vscode server' => 8,
            'hermes autonomous hub' => 9,
            'hermes-autonomous-hub' => 9,
            'hermes' => 9,
            'omniroute' => 9,
            'enterprise private ai & rag' => 10,
            'enterprise private ai and rag' => 10,
            'enterprise private ai' => 10,
            'enterprise-private-ai-rag' => 10,
            'private ai' => 10,
            'dify' => 10,
            'ollama' => 10,
            'db micro' => 11,
            'db-micro' => 11,
            'micro' => 11,
            'db standard' => 12,
            'db-standard' => 12,
            'db enterprise' => 13,
            'db-enterprise' => 13,
            'db enterprise / ai vector' => 13,
            'ai vector' => 13,
        ];
        $specId = $packageMap[$packageKey] ?? (is_numeric($packageKey) ? (int)$packageKey : null);
        $spec = ($specId ? VpsSpec::find($specId) : null)
            ?? VpsSpec::whereRaw('LOWER(name) = ?', [$packageKey])->first()
            ?? VpsSpec::find(2)
            ?? VpsSpec::first();

        $requestedProvider = $validated['provider'] ?? null;
        $provider = ($requestedProvider && $spec->isProviderAllowed($requestedProvider))
            ? $requestedProvider
            : $spec->defaultProvider();
        $datacenterLocation = ($provider === 'cloudeka')
            ? 'indonesia'
            : ($validated['datacenter_location'] ?? 'singapore');

        try {
            $order = DB::transaction(function () use ($user, $organization, $spec, $validated, $provider, $datacenterLocation) {
                $order = Order::create([
                    'customer_id' => $user->id,
                    'organization_id' => $organization->id,
                    'vps_spec_id' => $spec->id,
                    'control_panel' => $validated['control_panel'],
                    'provider' => $provider,
                    'datacenter_location' => $datacenterLocation,
                    'os' => $validated['os'],
                    'status' => 'pending',
                    'channel' => 'shopee',
                    'shopee_order_id' => $validated['shopee_order_id'],
                    'payment_method' => 'shopee',
                    'amount' => $spec->sell_price,
                    'paid_at' => null,
                    'last_status_change_at' => now(),
                ]);

                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'from_status' => null,
                    'to_status' => 'pending',
                    'reason' => 'Order Shopee dibuat via Admin API.',
                    'actor_user_id' => auth()->id(),
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
                        'admin_api' => true,
                    ],
                ]);

                $this->orderStateMachine->transition($order, 'paid', [
                    'reason' => 'Shopee order ditandai lunas via Admin API.',
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
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses order Shopee: ' . $e->getMessage(),
            ], 500);
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
            \Illuminate\Support\Facades\Log::error('api.admin.shopee.admin_notify_failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }

        return response()->json([
            'success' => true,
            'is_new_user' => $isNewUser,
            'customer_id' => $user->id,
            'username' => $user->username,
            'temporary_password' => $isNewUser ? $password : null,
            'order_id' => $order->id,
            'message' => $isNewUser
                ? 'Order processed. Credentials generated for new user.'
                : 'Order processed. Customer account already exists (password unchanged).',
        ], 200);
    }

    /**
     * PRD 10.4: VPS Status Check.
     */
    public function vpsStatus($id)
    {
        $vps = VpsInstance::findOrFail($id);

        return response()->json([
            'id' => $vps->id,
            'hostname' => $vps->hostname,
            'public_ip' => $vps->public_ip,
            'status' => $vps->status,
            'cpu' => $vps->cpu,
            'ram' => $vps->ram,
            'disk' => $vps->disk,
            'uptime_percent' => $vps->uptime_percent,
            'os' => $vps->os,
            'control_panel' => $vps->control_panel,
            'billing_cycle' => $vps->billing_cycle,
            'starts_at' => $vps->starts_at?->toIso8601String(),
            'expires_at' => $vps->expires_at?->toIso8601String(),
            'last_check' => now()->toIso8601String(),
        ], 200);
    }
}
