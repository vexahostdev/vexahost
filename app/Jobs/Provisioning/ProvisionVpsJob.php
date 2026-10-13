<?php

namespace App\Jobs\Provisioning;

use App\Exceptions\InvalidStateTransitionException;
use App\Models\Order;
use App\Models\ProvisioningTask;
use App\Models\VpsInstance;
use App\Services\OrderStateMachine;
use App\Services\VpsStateMachine;
use Illuminate\Support\Facades\Log;

/**
 * Provision VPS baru:
 *   Order paid → provisioning
 *   VpsInstance provisioning → running
 *   Order provisioning → active
 *
 * Kalau semua attempt gagal → Order pindah ke `failed`, VPS ke `error`.
 */
class ProvisionVpsJob extends AbstractProvisioningJob
{
    protected function kind(): string
    {
        return 'provision';
    }

    protected function run($provider, ProvisioningTask $task): array
    {
        $instance = $task->vpsInstance;
        if (!$instance) {
            throw new \RuntimeException('VpsInstance not found for task ' . $task->id);
        }
        $task->markProgress(10, 'Memulai provisioning');
        $result = $provider->provision($instance, $task);
        $task->markProgress(80, 'Menyimpan detail server');
        return $result;
    }

    protected function onSuccess(ProvisioningTask $task, array $output): void
    {
        $instance = $task->vpsInstance;
        if (!$instance) return;

        $instance->fill([
            'public_ip' => $output['public_ip'] ?? $instance->public_ip,
            'private_ip' => $output['private_ip'] ?? $instance->private_ip,
            'ssh_port' => $output['ssh_port'] ?? $instance->ssh_port,
            'initial_root_password' => $output['root_password'] ?? $instance->initial_root_password,
            'provider_resource_id' => $output['resource_id'] ?? $instance->provider_resource_id,
            'provider_meta' => array_merge($instance->provider_meta ?? [], $output['meta'] ?? []),
        ])->save();

        try {
            app(VpsStateMachine::class)->transition($instance, 'running', [
                'reason' => 'Provisioning selesai (job sukses).',
                'actor_type' => 'system',
                'metadata' => ['task_id' => $task->id, 'provider' => $task->provider],
            ]);
        } catch (InvalidStateTransitionException $e) {
            Log::warning('provisioning.vps_transition_failed_on_success', [
                'task_id' => $task->id,
                'error' => $e->getMessage(),
            ]);
        }

        $order = $task->order;
        if ($order) {
            try {
                app(OrderStateMachine::class)->transition($order, 'active', [
                    'reason' => 'Provisioning sukses.',
                    'actor_type' => 'system',
                    'metadata' => ['task_id' => $task->id, 'vps_instance_id' => $instance->id],
                    'onLocked' => function (Order $locked) use ($instance) {
                        $locked->starts_at = $locked->starts_at ?? $locked->activationStartsAt();
                        $locked->expires_at = $locked->expires_at ?? $instance->expires_at;
                        $locked->grace_period_ends_at = $locked->grace_period_ends_at ?? $instance->grace_period_ends_at;
                        $locked->save();
                    },
                ]);
            } catch (InvalidStateTransitionException $e) {
                Log::warning('provisioning.order_transition_failed_on_success', [
                    'task_id' => $task->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Kirim notifikasi kredensial server VPS ke pelanggan
        try {
            $customer = $instance->customer;
            if ($customer) {
                $customer->notify(new \App\Notifications\VpsProvisionedNotification($instance));
            }
        } catch (\Throwable $e) {
            Log::error('provisioning.notify_customer_failed', [
                'instance_id' => $instance->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function onFailure(ProvisioningTask $task, string $error): void
    {
        $instance = $task->vpsInstance;
        if ($instance) {
            try {
                app(VpsStateMachine::class)->transition($instance, 'error', [
                    'reason' => 'Provisioning gagal: ' . $error,
                    'actor_type' => 'system',
                    'metadata' => ['task_id' => $task->id],
                ]);
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $order = $task->order;
        if ($order) {
            try {
                app(OrderStateMachine::class)->transition($order, 'failed', [
                    'reason' => 'Provisioning gagal setelah semua retry.',
                    'actor_type' => 'system',
                    'metadata' => ['task_id' => $task->id, 'error' => $error],
                    'onLocked' => function (Order $locked) use ($error) {
                        $locked->failure_reason = mb_substr($error, 0, 500);
                        $locked->save();
                    },
                ]);
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // Kirim alert darurat ke admin jika provisioning otomatis gagal
        try {
            $adminEmails = \App\Support\MailIdentity::adminRecipients();
            foreach ($adminEmails as $adminEmail) {
                \Illuminate\Support\Facades\Notification::route('mail', $adminEmail)
                    ->notify(new \App\Notifications\AdminProvisioningFailedNotification($task, $error));
            }
        } catch (\Throwable $e) {
            Log::error('provisioning.notify_admin_failed', [
                'task_id' => $task->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
