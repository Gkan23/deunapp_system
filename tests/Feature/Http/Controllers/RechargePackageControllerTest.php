<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AccountStatus;
use App\Models\Courier;
use App\Models\Customer;
use App\Models\DeliveryProvider;
use App\Models\RechargePackage;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RechargePackageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_guests_cannot_access_recharge_packages(): void
    {
        $package = $this->localPackage();

        $this->getJson(
            route('recharge-packages.index')
        )->assertUnauthorized();

        $this->getJson(
            route(
                'recharge-packages.show',
                $package
            )
        )->assertUnauthorized();
    }

    public function test_provider_only_lists_currently_available_packages(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $availablePackage = $this->localPackage();

        $inactivePackage = $this->createInactivePackage();

        $response = $this->actingAs($provider->user)
            ->getJson(
                route('recharge-packages.index')
            )
            ->assertOk();

        $packageIds = collect(
            $response->json('data')
        )->pluck('id')->all();

        $this->assertContains(
            $availablePackage->id,
            $packageIds
        );

        $this->assertNotContains(
            $inactivePackage->id,
            $packageIds
        );
    }

    public function test_support_and_administration_list_all_packages(): void
    {
        $inactivePackage = $this->createInactivePackage();

        $supportAgent = $this->createUserWithRole(
            'SUPPORT_AGENT'
        );

        $administrator = $this->createUserWithRole(
            'ADMINISTRATOR'
        );

        foreach ([
            $supportAgent,
            $administrator,
        ] as $user) {
            $response = $this->actingAs($user)
                ->getJson(
                    route('recharge-packages.index')
                )
                ->assertOk();

            $packageIds = collect(
                $response->json('data')
            )->pluck('id')->all();

            $this->assertContains(
                $inactivePackage->id,
                $packageIds
            );
        }
    }

    public function test_provider_can_view_an_available_package(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $package = $this->localPackage();

        $this->actingAs($provider->user)
            ->getJson(
                route(
                    'recharge-packages.show',
                    $package
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $package->id
            )
            ->assertJsonPath(
                'data.commission_rule.trip_type.type_name',
                $package->commissionRule
                    ->tripType
                    ->type_name
            );
    }

    public function test_provider_cannot_view_an_inactive_package(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $inactivePackage = $this->createInactivePackage();

        $this->actingAs($provider->user)
            ->getJson(
                route(
                    'recharge-packages.show',
                    $inactivePackage
                )
            )
            ->assertForbidden();
    }

    public function test_support_and_administration_can_view_inactive_package(): void
    {
        $inactivePackage = $this->createInactivePackage();

        $supportAgent = $this->createUserWithRole(
            'SUPPORT_AGENT'
        );

        $administrator = $this->createUserWithRole(
            'ADMINISTRATOR'
        );

        foreach ([
            $supportAgent,
            $administrator,
        ] as $user) {
            $this->actingAs($user)
                ->getJson(
                    route(
                        'recharge-packages.show',
                        $inactivePackage
                    )
                )
                ->assertOk()
                ->assertJsonPath(
                    'data.id',
                    $inactivePackage->id
                );
        }
    }

    public function test_unrelated_roles_cannot_access_package_list(): void
    {
        $customer = Customer::factory()
            ->create()
            ->user;

        $courier = Courier::factory()
            ->create()
            ->user;

        foreach ([
            $customer,
            $courier,
        ] as $user) {
            $this->actingAs($user)
                ->getJson(
                    route('recharge-packages.index')
                )
                ->assertForbidden();
        }
    }

    public function test_inactive_account_cannot_access_packages(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $suspendedStatus = AccountStatus::query()
            ->where('status_name', 'SUSPENDED')
            ->firstOrFail();

        $provider->user->update([
            'account_status_id' => $suspendedStatus->id,
        ]);

        $user = $provider->user->fresh();

        $this->actingAs($user)
            ->getJson(
                route('recharge-packages.index')
            )
            ->assertForbidden();

        $this->actingAs($user)
            ->getJson(
                route(
                    'recharge-packages.show',
                    $this->localPackage()
                )
            )
            ->assertForbidden();
    }

    private function localPackage(): RechargePackage
    {
        return RechargePackage::query()
            ->with([
                'commissionRule.tripType',
            ])
            ->where('package_name', 'LOCAL_10')
            ->firstOrFail();
    }

    private function createInactivePackage(): RechargePackage
    {
        $basePackage = $this->localPackage();

        return RechargePackage::query()->create([
            'commission_rule_id' => $basePackage
                ->commission_rule_id,
            'package_name' => 'INACTIVE_TEST_PACKAGE',
            'trip_quantity' => 5,
            'price' => 75,
            'is_active' => false,
        ]);
    }

    private function createUserWithRole(
        string $roleName
    ): User {
        return User::factory()->create([
            'role_id' => Role::query()
                ->where('role_name', $roleName)
                ->firstOrFail()
                ->id,
        ]);
    }
}