<?php

namespace App\Services\Payments;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Router webhook Xendit berbasis konfigurasi: meneruskan callback ber-prefix external_id
 * ke aplikasi lain sesuai config/xendit_forwards.php. Aplikasi baru cukup menambah satu entri
 * di config tersebut — tidak perlu mengubah PaymentWebhookController (hindari bentrok antar-pengerjaan).
 *
 * Kontrak ke aplikasi tujuan sama dengan WAG-/BC-: header X-Internal-Token + x-callback-token.
 */
class XenditForwarder
{
    public function tryForward(string $externalId, array $payload, ?string $callbackToken): ?JsonResponse
    {
        foreach ((array) config('xendit_forwards', []) as $prefix => $cfg) {
            if (! str_starts_with($externalId, $prefix)) {
                continue;
            }
            $name = $cfg['name'] ?? $prefix;
            $url = $cfg['url'] ?? null;
            if (empty($url)) {
                Log::error('Xendit forward target not configured', ['prefix' => $prefix]);

                return response()->json(['success' => false, 'message' => "Forward target for {$name} is not configured."], 503);
            }

            try {
                $res = Http::timeout(12)
                    ->withHeaders([
                        'X-Internal-Token' => config('services.xendit.internal_secret'),
                        'x-callback-token' => $callbackToken,
                    ])
                    ->post($url, $payload);

                Log::info("Xendit webhook forwarded to {$name}", ['external_id' => $externalId, 'status_code' => $res->status()]);

                // 5xx dari tujuan → balas 502 agar Xendit mengulang callback (jangan hilang diam-diam).
                if ($res->serverError()) {
                    return response()->json(['success' => false, 'message' => "{$name} returned {$res->status()}."], 502);
                }

                return response()->json([
                    'success' => true,
                    'message' => "Notification forwarded to {$name}.",
                    'target_response_code' => $res->status(),
                ], 200);
            } catch (\Throwable $e) {
                Log::error("Xendit webhook forward to {$name} failed", ['external_id' => $externalId, 'error' => $e->getMessage()]);

                return response()->json(['success' => false, 'message' => "Failed to forward to {$name}: ".$e->getMessage()], 502);
            }
        }

        return null;
    }
}
