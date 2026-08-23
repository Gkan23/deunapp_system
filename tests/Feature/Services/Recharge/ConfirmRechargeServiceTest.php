<?php

namespace Tests\Feature\Services\Recharge;

use App\Models\DeliveryProvider;
use App\Models\RechargePackage;
use App\Services\Recharge\ConfirmRechargeService;
use Database\Seeders\CatalogSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfirmRechargeServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_it_confirms_a_recharge_atomically(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $package = RechargePackage::query()
            ->where('package_name', 'LOCAL_10')
            ->firstOrFail();

        $recharge = app(ConfirmRechargeService::class)->handle(
            $provider,
            $package,
            'TEST-RECHARGE-001'
        );

        $this->assertSame($provider->id, $recharge->delivery_provider_id);
        $this->assertSame($package->id, $recharge->recharge_package_id);
        $this->assertSame(10, $recharge->trip_quantity);

        $this->assertDatabaseCount('recharges', 1);
        $this->assertDatabaseCount('trips', 10);
        $this->assertDatabaseCount('trip_transactions', 1);

        $this->assertDatabaseHas('trips', [
            'delivery_provider_id' => $provider->id,
            'trip_type_id' => $recharge->trip_type_id,
            'status' => 'AVAILABLE',
            'used_at' => null,
        ]);

        $this->assertDatabaseHas('trip_transactions', [
            'delivery_provider_id' => $provider->id,
            'recharge_id' => $recharge->id,
            'trip_id' => null,
            'transaction_type' => 'CREDIT',
            'quantity' => 10,
        ]);
    }

    public function test_it_rejects_an_inactive_recharge_package(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $package = RechargePackage::query()
            ->where('package_name', 'LOCAL_10')
            ->firstOrFail();

        $package->update([
            'is_active' => false,
        ]);

        try {
            app(ConfirmRechargeService::class)->handle(
                $provider,
                $package,
                'TEST-INACTIVE-PACKAGE'
            );

            $this->fail(
                'An inactive recharge package was accepted.'
            );
        } catch (DomainException $exception) {
            $this->assertSame(
                'The recharge package is inactive.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseCount('recharges', 0);
        $this->assertDatabaseCount('trips', 0);
        $this->assertDatabaseCount('trip_transactions', 0);
    }

    public function test_it_rejects_a_duplicated_payment_reference(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $package = RechargePackage::query()
            ->where('package_name', 'LOCAL_10')
            ->firstOrFail();

        $service = app(ConfirmRechargeService::class);

        $service->handle(
            $provider,
            $package,
            'TEST-DUPLICATED-REFERENCE'
        );

        try {
            $service->handle(
                $provider,
                $package,
                'TEST-DUPLICATED-REFERENCE'
            );

            $this->fail(
                'A duplicated payment reference was accepted.'
            );
        } catch (DomainException $exception) {
            $this->assertSame(
                'The payment reference has already been used.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseCount('recharges', 1);
        $this->assertDatabaseCount('trips', 10);
        $this->assertDatabaseCount('trip_transactions', 1);
    }
}
