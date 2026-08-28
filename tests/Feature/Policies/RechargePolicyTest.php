<?php

namespace Tests\Feature\Policies;

use App\Models\AccountStatus;
use App\Models\Courier;
use App\Models\Customer;
use App\Models\DeliveryProvider;
use App\Models\Recharge;
use App\Models\RechargePackage;
use App\Models\Role;
use App\Models\User;
use App\Services\Recharge\ConfirmRechargeService;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RechargePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_supported_roles_can_access_the_recharge_list(): void
    {
        $provider = DeliveryProvider::factory()
            ->create()
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
                    Recharge::class
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
                    Recharge::class
                )
            );
        }
    }

    public function test_an_inactive_user_cannot_access_recharges(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $recharge = $this->rechargeFor(
            $provider,
            'POLICY-INACTIVE-USER'
        );

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
                Recharge::class
            )
        );

        $this->assertFalse(
            $this->allows(
                $user,
                'view',
                $recharge
            )
        );

        $this->assertFalse(
            $this->allows(
                $user,
                'create',
                Recharge::class
            )
        );
    }

    public function test_only_an_active_provider_can_create_recharges(): void
    {
        $activeProvider = DeliveryProvider::factory()
            ->create([
                'is_active' => true,
            ]);

        $inactiveProvider = DeliveryProvider::factory()
            ->create([
                'is_active' => false,
            ]);

        $this->assertTrue(
            $this->allows(
                $activeProvider->user,
                'create',
                Recharge::class
            )
        );

        $this->assertFalse(
            $this->allows(
                $inactiveProvider->user,
                'create',
                Recharge::class
            )
        );
    }

    public function test_non_provider_roles_cannot_create_recharges(): void
    {
        $customer = Customer::factory()
            ->create()
            ->user;

        $courier = Courier::factory()
            ->create()
            ->user;

        $supportAgent = $this->createUserWithRole(
            'SUPPORT_AGENT'
        );

        $administrator = $this->createUserWithRole(
            'ADMINISTRATOR'
        );

        foreach ([
            $customer,
            $courier,
            $supportAgent,
            $administrator,
        ] as $user) {
            $this->assertFalse(
                $this->allows(
                    $user,
                    'create',
                    Recharge::class
                )
            );
        }
    }

    public function test_a_provider_can_view_their_own_recharge(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $recharge = $this->rechargeFor(
            $provider,
            'POLICY-OWNER'
        );

        $this->assertTrue(
            $this->allows(
                $provider->user,
                'view',
                $recharge
            )
        );
    }

    public function test_a_provider_cannot_view_another_providers_recharge(): void
    {
        $owner = DeliveryProvider::factory()->create();

        $unrelatedProvider = DeliveryProvider::factory()
            ->create();

        $recharge = $this->rechargeFor(
            $owner,
            'POLICY-UNRELATED'
        );

        $this->assertFalse(
            $this->allows(
                $unrelatedProvider->user,
                'view',
                $recharge
            )
        );
    }

    public function test_support_and_administration_can_view_recharges(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $recharge = $this->rechargeFor(
            $provider,
            'POLICY-SUPPORT-ADMIN'
        );

        $supportAgent = $this->createUserWithRole(
            'SUPPORT_AGENT'
        );

        $administrator = $this->createUserWithRole(
            'ADMINISTRATOR'
        );

        $this->assertTrue(
            $this->allows(
                $supportAgent,
                'view',
                $recharge
            )
        );

        $this->assertTrue(
            $this->allows(
                $administrator,
                'view',
                $recharge
            )
        );
    }

    public function test_recharges_cannot_be_modified_or_deleted(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $recharge = $this->rechargeFor(
            $provider,
            'POLICY-IMMUTABLE'
        );

        foreach ([
            'update',
            'delete',
            'restore',
            'forceDelete',
        ] as $ability) {
            $this->assertFalse(
                $this->allows(
                    $provider->user,
                    $ability,
                    $recharge
                )
            );
        }
    }

    private function rechargeFor(
        DeliveryProvider $provider,
        string $paymentReference
    ): Recharge {
        $package = RechargePackage::query()
            ->where('package_name', 'LOCAL_10')
            ->firstOrFail();

        return app(
            ConfirmRechargeService::class
        )->handle(
            $provider,
            $package,
            $paymentReference
        );
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