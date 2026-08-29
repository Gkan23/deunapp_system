<?php

namespace Tests\Feature\Policies;

use App\Models\AccountStatus;
use App\Models\Courier;
use App\Models\Customer;
use App\Models\DeliveryProvider;
use App\Models\RechargePackage;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RechargePackagePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_supported_roles_can_access_the_package_list(): void
    {
        $provider = DeliveryProvider::factory()
            ->create([
                'is_active' => true,
            ])
            ->user;

        $supportAgent = $this->createUserWithRole(
            'SUPPORT_AGENT'
        );

        $administrator = $this->createUserWithRole(
            'ADMINISTRATOR'
        );

        foreach ([
            $provider,
            $supportAgent,
            $administrator,
        ] as $user) {
            $this->assertTrue(
                $this->allows(
                    $user,
                    'viewAny',
                    RechargePackage::class
                )
            );
        }

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
            $this->assertFalse(
                $this->allows(
                    $user,
                    'viewAny',
                    RechargePackage::class
                )
            );
        }
    }

    public function test_inactive_provider_profile_cannot_access_packages(): void
    {
        $provider = DeliveryProvider::factory()
            ->create([
                'is_active' => false,
            ]);

        $this->assertFalse(
            $this->allows(
                $provider->user,
                'viewAny',
                RechargePackage::class
            )
        );

        $this->assertFalse(
            $this->allows(
                $provider->user,
                'view',
                $this->localPackage()
            )
        );
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

        $this->assertFalse(
            $this->allows(
                $user,
                'viewAny',
                RechargePackage::class
            )
        );

        $this->assertFalse(
            $this->allows(
                $user,
                'view',
                $this->localPackage()
            )
        );
    }

    public function test_provider_can_view_an_available_package(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $this->assertTrue(
            $this->allows(
                $provider->user,
                'view',
                $this->localPackage()
            )
        );
    }

    public function test_provider_cannot_view_an_inactive_package(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $package = $this->localPackage();

        $package->update([
            'is_active' => false,
        ]);

        $this->assertFalse(
            $this->allows(
                $provider->user,
                'view',
                $package->fresh()
            )
        );
    }

    public function test_provider_cannot_view_package_with_invalid_commission_rule(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $package = $this->localPackage();

        $package->commissionRule()->update([
            'is_active' => false,
        ]);

        $this->assertFalse(
            $this->allows(
                $provider->user,
                'view',
                $package
            )
        );

        $package->commissionRule()->update([
            'is_active' => true,
            'valid_until' => today()->subDay(),
        ]);

        $this->assertFalse(
            $this->allows(
                $provider->user,
                'view',
                $package
            )
        );
    }

    public function test_support_and_administration_can_view_inactive_packages(): void
    {
        $package = $this->localPackage();

        $package->update([
            'is_active' => false,
        ]);

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
            $this->assertTrue(
                $this->allows(
                    $user,
                    'view',
                    $package
                )
            );
        }
    }

    public function test_packages_cannot_be_modified_directly(): void
    {
        $administrator = $this->createUserWithRole(
            'ADMINISTRATOR'
        );

        $package = $this->localPackage();

        $this->assertFalse(
            $this->allows(
                $administrator,
                'create',
                RechargePackage::class
            )
        );

        foreach ([
            'update',
            'delete',
            'restore',
            'forceDelete',
        ] as $ability) {
            $this->assertFalse(
                $this->allows(
                    $administrator,
                    $ability,
                    $package
                )
            );
        }
    }

    private function localPackage(): RechargePackage
    {
        return RechargePackage::query()
            ->where('package_name', 'LOCAL_10')
            ->firstOrFail();
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

    private function allows(
        User $user,
        string $ability,
        mixed $arguments
    ): bool {
        return Gate::forUser($user)->allows(
            $ability,
            $arguments
        );
    }
}