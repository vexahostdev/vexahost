<?php

namespace App\Services;

use App\Exceptions\InvalidStateTransitionException;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * State machine untuk Order VexaHost.
 *
 * Flow SaaS yang benar:
 *
 *   pending ──► paid ──► provisioning ──► active ──► grace_period ──► suspended ──► terminated
 *      │         │            │              │             │                │
 *      │         │            └─► failed ────┤             └─► active (renewal)
 *      │         │                  │        └─► expired ──► terminated
 *      │         │                  └─► provisioning (retry)
 *      │         └─► cancelled (refund path)
 *      └─► cancelled
 *
 * Setiap transisi dibungkus DB::transaction + lockForUpdate untuk mencegah
 * race condition (mis. webhook + admin klik di detik yang sama).
 *
 * Setiap perubahan status ditulis ke order_status_history untuk audit trail.
 */
class OrderStateMachine
{
    /** Semua status legal (harus match dengan enum di database). */
    public const STATUSES = [
        'pending',
        'paid',
        'provisioning',
        'active',
        'grace_period',
        'suspended',
        'cancelled',
        'expired',
        'terminated',
        'failed',
    ];

    /**
     * Peta transisi yang diizinkan: from => [to, to, ...]
     */
    public const TRANSITIONS = [
        'pending' => ['paid', 'cancelled', 'expired'],
        'paid' => ['provisioning', 'active', 'cancelled', 'failed'],
        'provisioning' => ['active', 'failed', 'cancelled'],
        'active' => ['grace_period', 'suspended', 'expired', 'terminated'],
        'grace_period' => ['active', 'suspended', 'terminated'],
        'suspended' => ['active', 'terminated'],
        'failed' => ['provisioning', 'cancelled', 'terminated'], // retry provisioning
        'expired' => ['terminated', 'active'],                    // renewal setelah expired
        'cancelled' => [],                                        // terminal
        'terminated' => [],                                       // terminal
    ];

    /**
     * Lakukan transisi status. Wraps DB transaction + row lock.
     *
     * @param  Order   $order   Order instance (akan di-refresh dari lock).
     * @param  string  $to      Status tujuan.
     * @param  array   $options {
     *     @type string      $reason      Alasan transisi (masuk ke history).
     *     @type string      $actor_type  system|admin|customer|webhook (default: auto-detect).
     *     @type int|null    $actor_id    User id pelaku (default: Auth::id()).
     *     @type array       $metadata    Data tambahan (tx id, error, dll).
     *     @type Closure     $onLocked    Callback yang dijalankan di dalam lock,
     *                                    setelah validasi transisi. Menerima Order.
     *     @type bool        $allowSame   Kalau true, transisi ke status yang sama = no-op.
     * }
     *
     * @throws InvalidStateTransitionException
     */
    public function transition(Order $order, string $to, array $options = []): Order
    {
        if (!in_array($to, self::STATUSES, true)) {
            throw new InvalidStateTransitionException(
                'order', $order->status ?? '?', $to,
                'Status tujuan tidak dikenal.'
            );
        }

        $reason = $options['reason'] ?? null;
        $actorType = $options['actor_type'] ?? $this->detectActorType();
        $actorId = $options['actor_id'] ?? Auth::id();
        $metadata = $options['metadata'] ?? [];
        $onLocked = $options['onLocked'] ?? null;
        $allowSame = $options['allowSame'] ?? false;

        return DB::transaction(function () use (
            $order, $to, $reason, $actorType, $actorId, $metadata, $onLocked, $allowSame
        ) {
            // Row lock: cegah race webhook vs admin klik provision.
            /** @var Order $locked */
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();
            if (!$locked) {
                throw new InvalidStateTransitionException(
                    'order', $order->status ?? '?', $to,
                    'Order tidak ditemukan saat lock.'
                );
            }

            $from = $locked->status;

            if ($from === $to) {
                if ($allowSame) {
                    // Idempotent no-op tapi tetap catat kalau dipaksa.
                    return $locked;
                }
                throw new InvalidStateTransitionException(
                    'order', $from, $to,
                    'Order sudah berada pada status ini (idempotent guard).'
                );
            }

            $allowed = self::TRANSITIONS[$from] ?? [];
            if (!in_array($to, $allowed, true)) {
                throw new InvalidStateTransitionException(
                    'order', $from, $to,
                    'Transisi tidak ada di peta legal.'
                );
            }

            // Jalankan callback di dalam lock (mis. buat VpsInstance saat paid→provisioning).
            if ($onLocked instanceof Closure) {
                $onLocked($locked, $from, $to);
            }

            $locked->status = $to;
            $locked->last_status_change_at = now();
            $locked->save();

            // Pastikan jika order mencapai status berbayar, invoice status selalu sinkron ke 'paid'
            if (in_array($to, ['paid', 'provisioning', 'active'], true)) {
                if ($locked->invoice && $locked->invoice->status !== 'paid') {
                    $locked->invoice->update([
                        'status' => 'paid',
                        'paid_at' => $locked->paid_at ?? now(),
                    ]);
                }
            }

            OrderStatusHistory::create([
                'order_id' => $locked->id,
                'from_status' => $from,
                'to_status' => $to,
                'reason' => $reason,
                'actor_user_id' => $actorId,
                'actor_type' => $actorType,
                'ip_address' => request()?->ip(),
                'metadata' => $metadata ?: null,
                'created_at' => now(),
            ]);

            Log::info('order.state_transition', [
                'order_id' => $locked->id,
                'from' => $from,
                'to' => $to,
                'actor_type' => $actorType,
                'reason' => $reason,
            ]);

            // Titik tunggal "setelah lunas": perpanjangan + notifikasi pelanggan & admin.
            // Dijalankan setelah transaksi terluar commit agar data (invoice, dll) sudah final.
            if ($to === 'paid') {
                $orderId = $locked->id;
                DB::afterCommit(fn () => app(PaymentCompletionService::class)->handle($orderId));
            }

            return $locked;
        });
    }

    /**
     * Cek apakah transisi legal tanpa melakukan perubahan.
     */
    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * Daftar next legal states dari status tertentu.
     */
    public function nextStates(string $from): array
    {
        return self::TRANSITIONS[$from] ?? [];
    }

    /**
     * Deteksi actor type berdasarkan context request.
     */
    protected function detectActorType(): string
    {
        $user = Auth::user();
        if ($user) {
            return !empty($user->is_admin) ? 'admin' : 'customer';
        }
        return 'system';
    }
}
