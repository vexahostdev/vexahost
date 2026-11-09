<?php

namespace Tests\Feature;

use App\Models\CreditTransaction;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Role;
use App\Models\User;
use App\Models\VpsInstance;
use App\Models\VpsSpec;
use App\Services\RenewalService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PackageLifecycleAndRenewalEnterpriseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    protected function createCustomerWithOrg(): array
    {
        $user = User::create([
            'full_name' => 'Enterprise Customer',
            'username' => 'entcustomer_' . uniqid(),
            'email' => 'customer_' . uniqid() . '@vexatest.id',
            'password' => bcrypt('password123'),
            'is_admin' => false,
        ]);

        $org = Organization::create([
            'name' => $user->full_name . ' Org',
            'type' => 'personal',
            'owner_id' => $user->id,
            'billing_email' => $user->email,
            'billing_name' => $user->full_name,
        ]);

        $ownerRole = Role::bySlug('owner');
        if ($ownerRole) {
            $org->attachMember($user, $ownerRole);
        } else {
            OrganizationMember::create([
                'organization_id' => $org->id,
                'user_id' => $user->id,
                'role_id' => 1,
                'joined_at' => now(),
            ]);
        }

        $user->forceFill(['current_organization_id' => $org->id])->save();

        return [$user, $org];
    }

    /**
     * PILAR 1: 3-Tier Product Lifecycle Verification.
     */
    public function test_pillar_1_three_tier_lifecycle_logic(): void
    {
        // 1. Tier 1: Active
        $activeSpec = VpsSpec::create([
            'name' => 'Active Plan 1',
            'cpu' => 2,
            'ram' => 4,
            'disk' => 50,
            'bandwidth' => 1000,
            'cost_price' => 50000,
            'sell_price' => 100000,
            'is_active' => true,
            'is_renewable' => true,
            'category' => 'vps',
            'provider' => 'tencent',
        ]);
        $this->assertTrue($activeSpec->canNewCheckout());
        $this->assertTrue($activeSpec->canRenew());
        $this->assertEquals('active', $activeSpec->lifecycle_status);

        // 2. Tier 2: Legacy (Stop Selling, Pelanggan Lama Boleh Perpanjang)
        $legacySpec = VpsSpec::create([
            'name' => 'Legacy Promo Plan',
            'cpu' => 2,
            'ram' => 4,
            'disk' => 50,
            'bandwidth' => 1000,
            'cost_price' => 50000,
            'sell_price' => 90000,
            'is_active' => false,
            'is_renewable' => true,
            'category' => 'vps',
            'provider' => 'tencent',
        ]);
        $this->assertFalse($legacySpec->canNewCheckout());
        $this->assertTrue($legacySpec->canRenew());
        $this->assertEquals('legacy', $legacySpec->lifecycle_status);

        // 3. Tier 3: Discontinued without Replacement (Paket Hilang di Retail)
        $eolSpec = VpsSpec::create([
            'name' => 'Deleted Upstream Plan',
            'cpu' => 1,
            'ram' => 1,
            'disk' => 20,
            'bandwidth' => 1000,
            'cost_price' => 40000,
            'sell_price' => 60000,
            'is_active' => false,
            'is_renewable' => false,
            'replacement_spec_id' => null,
            'category' => 'vps',
            'provider' => 'tencent',
        ]);
        $this->assertFalse($eolSpec->canNewCheckout());
        $this->assertFalse($eolSpec->canRenew());
        $this->assertEquals('discontinued', $eolSpec->lifecycle_status);

        // 4. Tier 3 with Fallback / Replacement Spec
        $eolWithReplacementSpec = VpsSpec::create([
            'name' => 'Discontinued Plan Migrated',
            'cpu' => 2,
            'ram' => 2,
            'disk' => 30,
            'bandwidth' => 1000,
            'cost_price' => 50000,
            'sell_price' => 75000,
            'is_active' => false,
            'is_renewable' => false,
            'replacement_spec_id' => $activeSpec->id,
            'category' => 'vps',
            'provider' => 'tencent',
        ]);
        $this->assertFalse($eolWithReplacementSpec->canNewCheckout());
        $this->assertTrue($eolWithReplacementSpec->canRenew()); // can renew because fallback replacement exists
        $this->assertEquals($activeSpec->id, $eolWithReplacementSpec->effectiveRenewalSpec()->id);
        $this->assertEquals(100000, $eolWithReplacementSpec->effectiveRenewalPrice());
    }

    /**
     * PILAR 2: Dynamic Catalog Pricing & Custom Renewal Price Override.
     */
    public function test_pillar_2_dynamic_catalog_pricing_and_custom_override(): void
    {
        [$customer, $org] = $this->createCustomerWithOrg();
        $admin = User::where('email', 'vexahostcloudtech@gmail.com')->first();

        $spec = VpsSpec::create([
            'name' => 'Dynamic Spec',
            'cpu' => 2,
            'ram' => 4,
            'disk' => 40,
            'bandwidth' => 1000,
            'cost_price' => 70000,
            'sell_price' => 110000,
            'is_active' => true,
            'is_renewable' => true,
            'category' => 'vps',
            'provider' => 'tencent',
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $org->id,
            'vps_spec_id' => $spec->id,
            'status' => 'active',
            'amount' => 110000,
            'order_type' => 'new',
            'billing_cycle' => 'monthly',
            'control_panel' => 'coolify',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'channel' => 'website',
        ]);

        $instance = VpsInstance::create([
            'customer_id' => $customer->id,
            'organization_id' => $org->id,
            'order_id' => $order->id,
            'hostname' => 'vm-dynamic-pricing',
            'public_ip' => '103.150.190.44',
            'status' => 'running',
            'os' => 'ubuntu2404',
            'provider' => 'tencent',
            'expires_at' => now()->addDays(15),
        ]);

        // 1. Initial renewal price matches current catalog sell_price
        $this->assertEquals(110000, $instance->renewal_price);

        // 2. Upstream supplier price increases -> Admin updates catalog sell_price
        $spec->update(['sell_price' => 135000, 'cost_price' => 90000]);
        $instance->refresh();
        $this->assertEquals(135000, $instance->renewal_price);

        // 3. Admin sets custom_renewal_price override for loyal customer
        $response = $this->actingAs($admin)->post('/admin/instances/' . $instance->id . '/renewal-price', [
            'custom_renewal_price' => 105000,
        ]);
        $response->assertRedirect();

        $instance->refresh();
        $this->assertEquals(105000, $instance->custom_renewal_price);
        $this->assertEquals(105000, $instance->renewal_price);

        // Catalog price updates again -> Instance renewal price remains locked to custom override
        $spec->update(['sell_price' => 150000]);
        $instance->refresh();
        $this->assertEquals(105000, $instance->renewal_price);

        // 4. Admin removes override (set null/empty) -> Reverts to current catalog price
        $this->actingAs($admin)->post('/admin/instances/' . $instance->id . '/renewal-price', [
            'custom_renewal_price' => '',
        ]);
        $instance->refresh();
        $this->assertNull($instance->custom_renewal_price);
        $this->assertEquals(150000, $instance->renewal_price);
    }

    /**
     * PILAR 2: Margin Guardrail (Anti-Boncos Alert).
     */
    public function test_pillar_2_margin_guardrail_detection(): void
    {
        // Healthy margin
        $healthySpec = VpsSpec::create([
            'name' => 'Healthy Spec',
            'cpu' => 2,
            'ram' => 4,
            'disk' => 40,
            'bandwidth' => 1000,
            'cost_price' => 80000,
            'sell_price' => 120000,
            'is_active' => true,
            'is_renewable' => true,
            'category' => 'vps',
            'provider' => 'tencent',
        ]);
        $this->assertFalse($healthySpec->hasDeficitMargin());

        // Deficit / Defisit margin (cost >= sell)
        $deficitSpec = VpsSpec::create([
            'name' => 'Deficit Spec',
            'cpu' => 4,
            'ram' => 8,
            'disk' => 80,
            'bandwidth' => 1000,
            'cost_price' => 150000,
            'sell_price' => 140000,
            'is_active' => true,
            'is_renewable' => true,
            'category' => 'vps',
            'provider' => 'tencent',
        ]);
        $this->assertTrue($deficitSpec->hasDeficitMargin());
    }

    /**
     * PILAR 3: 24h Auto-Expire Command & Pending Order Revalidation.
     */
    public function test_pillar_3_pending_order_expiration_and_revalidation(): void
    {
        [$customer, $org] = $this->createCustomerWithOrg();

        $spec = VpsSpec::create([
            'name' => 'Spec P3',
            'cpu' => 2,
            'ram' => 4,
            'disk' => 40,
            'bandwidth' => 1000,
            'cost_price' => 50000,
            'sell_price' => 100000,
            'is_active' => true,
            'is_renewable' => true,
            'category' => 'vps',
            'provider' => 'tencent',
        ]);

        // Order 1: Expired (> 24 hours)
        $oldOrder = Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $org->id,
            'vps_spec_id' => $spec->id,
            'status' => 'pending',
            'amount' => 100000,
            'order_type' => 'new',
            'control_panel' => 'coolify',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'channel' => 'website',
        ]);
        DB::table('orders')->where('id', $oldOrder->id)->update(['created_at' => now()->subHours(26)]);

        Invoice::create([
            'order_id' => $oldOrder->id,
            'customer_id' => $customer->id,
            'organization_id' => $org->id,
            'invoice_number' => 'INV-OLD-' . $oldOrder->id,
            'amount' => 100000,
            'total' => 100000,
            'subtotal' => 100000,
            'status' => 'unpaid',
        ]);

        // Order 2: Fresh (< 24 hours)
        $freshOrder = Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $org->id,
            'vps_spec_id' => $spec->id,
            'status' => 'pending',
            'amount' => 100000,
            'order_type' => 'new',
            'control_panel' => 'coolify',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'channel' => 'website',
        ]);
        Invoice::create([
            'order_id' => $freshOrder->id,
            'customer_id' => $customer->id,
            'organization_id' => $org->id,
            'invoice_number' => 'INV-FRESH-' . $freshOrder->id,
            'amount' => 100000,
            'total' => 100000,
            'subtotal' => 100000,
            'status' => 'unpaid',
        ]);

        // Run artisan command
        $this->artisan('orders:expire-pending')->assertSuccessful();

        $oldOrder->refresh();
        $this->assertEquals('expired', $oldOrder->status);
        $this->assertEquals('cancelled', $oldOrder->invoice->refresh()->status);

        $freshOrder->refresh();
        $this->assertEquals('pending', $freshOrder->status);

        // Revalidation Test: Package price changed in catalog while order pending
        $spec->update(['sell_price' => 125000]);
        $response = $this->actingAs($customer)->get(route('order.payment', $freshOrder->id));
        $response->assertStatus(200);

        $freshOrder->refresh();
        $this->assertEquals(125000, $freshOrder->amount);
        $this->assertEquals(125000, $freshOrder->invoice->refresh()->amount);
    }

    /**
     * PILAR 4: End-to-End Self-Service Renewal with Saldo / Credit.
     */
    public function test_pillar_4_self_service_renewal_via_credit_balance(): void
    {
        [$customer, $org] = $this->createCustomerWithOrg();

        $spec = VpsSpec::create([
            'name' => 'Standard Cloud 2GB',
            'cpu' => 2,
            'ram' => 2,
            'disk' => 40,
            'bandwidth' => 1000,
            'cost_price' => 60000,
            'sell_price' => 120000,
            'is_active' => true,
            'is_renewable' => true,
            'category' => 'vps',
            'provider' => 'tencent',
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $org->id,
            'vps_spec_id' => $spec->id,
            'status' => 'active',
            'amount' => 120000,
            'order_type' => 'new',
            'billing_cycle' => 'monthly',
            'control_panel' => 'coolify',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'channel' => 'website',
        ]);

        // Pre-paid server with 5 days remaining in future
        $initialExpiry = now()->addDays(5);
        $instance = VpsInstance::create([
            'customer_id' => $customer->id,
            'organization_id' => $org->id,
            'order_id' => $order->id,
            'hostname' => 'vm-self-service-renew',
            'public_ip' => '103.150.190.55',
            'status' => 'running',
            'os' => 'ubuntu2404',
            'provider' => 'tencent',
            'expires_at' => $initialExpiry,
        ]);

        // Top up user balance
        CreditTransaction::create([
            'customer_id' => $customer->id,
            'organization_id' => $org->id,
            'type' => 'topup',
            'amount' => 200000,
            'balance_after' => 200000,
            'reason' => 'Deposit test',
        ]);
        $this->assertEquals(200000, $customer->creditBalance());

        // Customer submits renewal
        $response = $this->actingAs($customer)->post('/dashboard/vps/' . $instance->id . '/renew', [
            'payment_method' => 'credit',
        ]);

        $response->assertRedirect(route('dashboard.vps.show', $instance->id));
        $response->assertSessionHas('success');

        // Verify credit balance deducted
        $this->assertEquals(80000, $customer->creditBalance());

        // Verify VPS instance expires_at extended from max(now(), expires_at)
        $instance->refresh();
        $expectedExpiry = $initialExpiry->copy()->addMonth();
        $this->assertEquals(
            $expectedExpiry->format('Y-m-d H:i'),
            $instance->expires_at->format('Y-m-d H:i')
        );

        // Verify renewal order was generated and settled
        $renewalOrder = Order::where('vps_instance_id', $instance->id)
            ->where('order_type', 'renewal')
            ->first();
        $this->assertNotNull($renewalOrder);
        $this->assertEquals('active', $renewalOrder->status);
        $this->assertEquals(120000, $renewalOrder->amount);
        $this->assertEquals('credit_balance', $renewalOrder->payment_method);
    }

    /**
     * PILAR 4: Renewal of Suspended VPS Instance Restores Status to Running.
     */
    public function test_pillar_4_renewal_unsuspends_vps_instance(): void
    {
        [$customer, $org] = $this->createCustomerWithOrg();

        $spec = VpsSpec::create([
            'name' => 'Suspended Recovery Plan',
            'cpu' => 1,
            'ram' => 1,
            'disk' => 20,
            'bandwidth' => 1000,
            'cost_price' => 30000,
            'sell_price' => 60000,
            'is_active' => true,
            'is_renewable' => true,
            'category' => 'vps',
            'provider' => 'tencent',
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $org->id,
            'vps_spec_id' => $spec->id,
            'status' => 'active',
            'amount' => 60000,
            'order_type' => 'new',
            'control_panel' => 'coolify',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'channel' => 'website',
        ]);

        // Server expired 1 day ago and is suspended (still within the 2-day grace)
        $instance = VpsInstance::create([
            'customer_id' => $customer->id,
            'organization_id' => $org->id,
            'order_id' => $order->id,
            'hostname' => 'vm-suspended-test',
            'public_ip' => '103.150.190.66',
            'status' => 'suspended',
            'os' => 'ubuntu2404',
            'provider' => 'tencent',
            'expires_at' => now()->subDay(),
        ]);

        // Top up user credit
        CreditTransaction::create([
            'customer_id' => $customer->id,
            'organization_id' => $org->id,
            'type' => 'topup',
            'amount' => 100000,
            'balance_after' => 100000,
            'reason' => 'Deposit test',
        ]);

        // Customer renews
        $response = $this->actingAs($customer)->post('/dashboard/vps/' . $instance->id . '/renew', [
            'payment_method' => 'credit',
        ]);
        $response->assertRedirect();

        $instance->refresh();
        // Server must be restored to running!
        $this->assertEquals('running', $instance->status);
        // Expiry is calculated from now() since old expiry was in the past
        $this->assertTrue($instance->expires_at->isFuture());
        $this->assertTrue($instance->grace_period_ends_at->isFuture());
    }

    /**
     * PILAR 4: External Online Payment Settlement (Webhook/Admin approval) extends instance.
     */
    public function test_pillar_4_renewal_settlement_via_renewal_service(): void
    {
        [$customer, $org] = $this->createCustomerWithOrg();

        $spec = VpsSpec::create([
            'name' => 'Gateway Spec',
            'cpu' => 2,
            'ram' => 4,
            'disk' => 40,
            'bandwidth' => 1000,
            'cost_price' => 50000,
            'sell_price' => 100000,
            'is_active' => true,
            'is_renewable' => true,
            'category' => 'vps',
            'provider' => 'tencent',
        ]);

        $instance = VpsInstance::create([
            'customer_id' => $customer->id,
            'organization_id' => $org->id,
            'hostname' => 'vm-gateway-renew',
            'public_ip' => '103.150.190.77',
            'status' => 'running',
            'os' => 'ubuntu2404',
            'provider' => 'tencent',
            'expires_at' => now()->addDays(10),
        ]);

        $renewalOrder = Order::create([
            'customer_id' => $customer->id,
            'organization_id' => $org->id,
            'vps_spec_id' => $spec->id,
            'vps_instance_id' => $instance->id,
            'order_type' => 'renewal',
            'status' => 'paid',
            'amount' => 100000,
            'payment_method' => 'midtrans_snap',
            'control_panel' => 'coolify',
            'datacenter_location' => 'indonesia',
            'os' => 'ubuntu2404',
            'channel' => 'website',
        ]);

        $oldExpiry = $instance->expires_at->copy();

        // Process renewal settlement
        app(RenewalService::class)->handleRenewalPayment($renewalOrder);

        $instance->refresh();
        $this->assertEquals(
            $oldExpiry->copy()->addMonth()->format('Y-m-d H:i'),
            $instance->expires_at->format('Y-m-d H:i')
        );

        $renewalOrder->refresh();
        $this->assertEquals('active', $renewalOrder->status);
    }
}
