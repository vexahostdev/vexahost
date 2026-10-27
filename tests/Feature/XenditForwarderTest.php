<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class XenditForwarderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.xendit.webhook_token', 'cb-token');
        Config::set('services.xendit.internal_secret', 'internal-s');
        Config::set('xendit_forwards', ['PSV-' => ['name' => 'Postivo', 'url' => 'https://postivo.test/api/payment/xendit/callback']]);
    }

    private function hook(array $payload)
    {
        return $this->withHeaders(['x-callback-token' => 'cb-token'])->postJson('/api/payment/xendit/webhook', $payload);
    }

    public function test_psv_prefix_is_forwarded_with_internal_and_callback_tokens(): void
    {
        Http::fake(['postivo.test/*' => Http::response(['status' => 'success'], 200)]);

        $this->hook(['external_id' => 'PSV-ORD-1', 'status' => 'PAID', 'amount' => 99000])
            ->assertOk()->assertJsonPath('target_response_code', 200);

        Http::assertSent(fn ($r) => $r->url() === 'https://postivo.test/api/payment/xendit/callback'
            && $r->hasHeader('X-Internal-Token', 'internal-s') && $r->hasHeader('x-callback-token', 'cb-token')
            && $r['external_id'] === 'PSV-ORD-1');
    }

    public function test_target_5xx_makes_central_webhook_return_502_so_xendit_retries(): void
    {
        Http::fake(['postivo.test/*' => Http::response('boom', 500)]);

        $this->hook(['external_id' => 'PSV-ORD-1', 'status' => 'PAID'])->assertStatus(502);
    }

    public function test_invalid_callback_token_is_rejected_before_forwarding(): void
    {
        Http::fake();

        $this->withHeaders(['x-callback-token' => 'wrong'])->postJson('/api/payment/xendit/webhook', ['external_id' => 'PSV-ORD-1'])->assertStatus(403);
        Http::assertNothingSent();
    }

    public function test_unconfigured_target_returns_503(): void
    {
        Config::set('xendit_forwards', ['PSV-' => ['name' => 'Postivo', 'url' => null]]);

        $this->hook(['external_id' => 'PSV-ORD-1'])->assertStatus(503);
    }
}
