<?php

namespace Tests\Feature\Policies;

use App\Models\AccountStatus;
use App\Models\Courier;
use App\Models\Customer;
use App\Models\DeliveryProvider;
use App\Models\RechargePackage;
use App\Models\Role;
use App\Models\TripTransaction;
use App\Models\User;
use App\Services\Recharge\ConfirmRechargeService;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class TripTransactionPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_supported_roles_can_access_transaction_list(): void
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
                    TripTransaction::class
                )
            );
        }

        foreach ([
            Customer::factory()->create()->user,
            Courier::factory()->create()->user,
        ] as $user) {
            $this->assertFalse(
                $this->allows(
                    $user,
                    'viewAny',
                    TripTransaction::class
                )
            );
        }
    }

    public function test_inactive_account_cannot_access_transactions(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $transaction = $this->transactionFor(
            $provider,
            'TRANSACTION-INACTIVE-ACCOUNT'
        );

        $provider->user->update([
            'account_status_id' => AccountStatus::query()
                ->where('status_name', 'SUSPENDED')
                ->firstOrFail()
                ->id,
        ]);

        $user = $provider->user->fresh();

        $this->assertFalse(
            $this->allows(
                $user,
                'viewAny',
                TripTransaction::class
            )
        );

        $this->assertFalse(
            $this->allows(
                $user,
                'view',
                $transaction
            )
        );
    }

    public function test_inactive_provider_profile_cannot_access_transactions(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $transaction = $this->transactionFor(
            $provider,
            'TRANSACTION-INACTIVE-PROFILE'
        );

        $provider->update([
            'is_active' => false,
        ]);

        $this->assertFalse(
            $this->allows(
                $provider->user,
                'viewAny',
                TripTransaction::class
            )
        );

        $this->assertFalse(
            $this->allows(
                $provider->user,
                'view',
                $transaction
            )
        );
    }

    public function test_provider_can_view_their_own_transaction(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $transaction = $this->transactionFor(
            $provider,
            'TRANSACTION-OWNER'
        );

        $this->assertTrue(
            $this->allows(
                $provider->user,
                'view',
                $transaction
            )
        );
    }

    public function test_provider_cannot_view_another_providers_transaction(): void
    {
        $owner = DeliveryProvider::factory()->create();

        $unrelatedProvider = DeliveryProvider::factory()
            ->create();

        $transaction = $this->transactionFor(
            $owner,
            'TRANSACTION-UNRELATED'
        );

        $this->assertFalse(
            $this->allows(
                $unrelatedProvider->user,
                'view',
                $transaction
            )
        );
    }

    public function test_courier_cannot_view_provider_transactions(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $courier = Courier::factory()->create([
            'delivery_provider_id' => $provider->id,
        ]);

        $transaction = $this->transactionFor(
            $provider,
            'TRANSACTION-COURIER'
        );

        $this->assertFalse(
            $this->allows(
                $courier->user,
                'view',
                $transaction
            )
        );
    }

    public function test_support_and_administration_can_view_transactions(): void
    {
        $transaction = $this->transactionFor(
            DeliveryProvider::factory()->create(),
            'TRANSACTION-SUPPORT-ADMIN'
        );

        foreach ([
            $this->createUserWithRole('SUPPORT_AGENT'),
            $this->createUserWithRole('ADMINISTRATOR'),
        ] as $user) {
            $this->assertTrue(
                $this->allows(
                    $user,
                    'view',
                    $transaction
                )
            );
        }
    }

    public function test_transactions_cannot_be_modified_directly(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $transaction = $this->transactionFor(
            $provider,
            'TRANSACTION-IMMUTABLE'
        );

        $this->assertFalse(
            $this->allows(
                $provider->user,
                'create',
                TripTransaction::class
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
                    $provider->user,
                    $ability,
                    $transaction
                )
            );
        }
    }

    private function transactionFor(
        DeliveryProvider $provider,
        string $paymentReference
    ): TripTransaction {
        $package = RechargePackage::query()
            ->where('package_name', 'LOCAL_10')
            ->firstOrFail();

        $recharge = app(
            ConfirmRechargeService::class
        )->handle(
            $provider,
            $package,
            $paymentReference
        );

        return TripTransaction::query()
            ->where('recharge_id', $recharge->id)
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