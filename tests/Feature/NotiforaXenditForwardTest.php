<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Routing callback Xendit ber-prefix NTF- ke Notifora lewat config/xendit_forwards.php.
 * File test terpisah (tidak mengubah XenditForwarderTest milik pengerjaan Postivo).
 */
class NotiforaXenditForwardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.xendit.webhook_token', 'cb-token');
        Config::set('services.xendit.internal_secret', 'internal-s');
        // Pakai konfigurasi asli (entri NTF- dan PSV-), hanya URL tujuan yang diganti agar tidak keluar jaringan.
        Config::set('xendit_forwards.NTF-.url', 'https://notifora.test/api/payment/xendit/callback');
        Config::set('xendit_forwards.PSV-.url', 'https://postivo.test/api/payment/xendit/callback');
    }

    private function hook(array $payload, string $token = 'cb-token')
    {
        return $this->withHeaders(['x-callback-token' => $token])->postJson('/api/payment/xendit/webhook', $payload);
    }

    public function test_ntf_entry_is_registered_with_production_default_url(): void
    {
        $fresh = require base_path('config/xendit_forwards.php');

        $this->assertSame('Notifora', $fresh['NTF-']['name']);
        $this->assertStringContainsString('/api/payment/xendit/callback', $fresh['NTF-']['url']);
        $this->assertArrayHasKey('PSV-', $fresh, 'Entri Postivo tidak boleh hilang.');
    }

    public function test_ntf_prefix_is_forwarded_to_notifora_with_internal_and_callback_tokens(): void
    {
        Http::fake(['notifora.test/*' => Http::response(['status' => 'success'], 200)]);

        $this->hook(['id' => 'inv_1', 'external_id' => 'NTF-INV-2026-000001-AB12', 'status' => 'PAID', 'paid_amount' => 897000])
            ->assertOk()->assertJsonPath('target_response_code', 200);

        Http::assertSent(fn ($r) => $r->url() === 'https://notifora.test/api/payment/xendit/callback'
            && $r->hasHeader('X-Internal-Token', 'internal-s') && $r->hasHeader('x-callback-token', 'cb-token')
            && $r['external_id'] === 'NTF-INV-2026-000001-AB12' && $r['paid_amount'] === 897000);
    }

    public function test_notifora_5xx_returns_502_so_xendit_retries(): void
    {
        Http::fake(['notifora.test/*' => Http::response('verification unavailable', 503)]);

        $this->hook(['external_id' => 'NTF-INV-2026-000001-AB12', 'status' => 'PAID'])->assertStatus(502);
    }

    public function test_invalid_callback_token_is_rejected_before_any_forwarding(): void
    {
        Http::fake();

        $this->hook(['external_id' => 'NTF-INV-2026-000001-AB12', 'status' => 'PAID'], 'wrong')->assertStatus(403);
        Http::assertNothingSent();
    }

    public function test_notifora_and_postivo_prefixes_route_to_their_own_targets_only(): void
    {
        Http::fake([
            'notifora.test/*' => Http::response(['ok' => true], 200),
            'postivo.test/*' => Http::response(['ok' => true], 200),
        ]);

        $this->hook(['external_id' => 'PSV-ORD-9', 'status' => 'PAID'])->assertOk();
        Http::assertSent(fn ($r) => str_contains($r->url(), 'postivo.test'));
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'notifora.test'));

        $this->hook(['external_id' => 'NTF-INV-2026-000002-ZZ99', 'status' => 'PAID'])->assertOk();
        Http::assertSent(fn ($r) => str_contains($r->url(), 'notifora.test') && $r['external_id'] === 'NTF-INV-2026-000002-ZZ99');
    }
}
