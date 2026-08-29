<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AccountStatus;
use App\Models\Courier;
use App\Models\Customer;
use App\Models\DeliveryProvider;
use App\Models\RechargePackage;
use App\Models\Role;
use App\Models\Trip;
use App\Models\TripTransaction;
use App\Models\User;
use App\Services\Recharge\ConfirmRechargeService;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripTransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_guests_cannot_access_transaction_endpoints(): void
    {
        $transaction = $this->creditTransactionFor(
            DeliveryProvider::factory()->create(),
            'HTTP-TRANSACTION-GUEST'
        );

        $this->getJson(
            route('trip-transactions.index')
        )->assertUnauthorized();

        $this->getJson(
            route(
                'trip-transactions.show',
                $transaction
            )
        )->assertUnauthorized();
    }

    public function test_provider_only_lists_their_own_transactions(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $otherProvider = DeliveryProvider::factory()
            ->create();

        $ownTransaction = $this->creditTransactionFor(
            $provider,
            'HTTP-TRANSACTION-OWNER'
        );

        $this->creditTransactionFor(
            $otherProvider,
            'HTTP-TRANSACTION-OTHER'
        );

        $this->actingAs($provider->user)
            ->getJson(
                route('trip-transactions.index')
            )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $ownTransaction->id
            )
            ->assertJsonPath(
                'meta.total_transactions',
                1
            );
    }

    public function test_transaction_list_contains_balance_summary(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $this->creditTransactionFor(
            $provider,
            'HTTP-TRANSACTION-BALANCE'
        );

        $this->debitTransactionFor($provider);

        $this->actingAs($provider->user)
            ->getJson(
                route('trip-transactions.index')
            )
            ->assertOk()
            ->assertJsonPath(
                'meta.total_transactions',
                2
            )
            ->assertJsonPath(
                'meta.credit_quantity',
                10
            )
            ->assertJsonPath(
                'meta.debit_quantity',
                1
            )
            ->assertJsonPath(
                'meta.net_quantity',
                9
            );
    }

    public function test_support_and_administration_list_all_transactions(): void
    {
        $this->creditTransactionFor(
            DeliveryProvider::factory()->create(),
            'HTTP-TRANSACTION-FIRST'
        );

        $this->creditTransactionFor(
            DeliveryProvider::factory()->create(),
            'HTTP-TRANSACTION-SECOND'
        );

        $users = [
            $this->createUserWithRole('SUPPORT_AGENT'),
            $this->createUserWithRole('ADMINISTRATOR'),
        ];

        foreach ($users as $user) {
            $this->actingAs($user)
                ->getJson(
                    route('trip-transactions.index')
                )
                ->assertOk()
                ->assertJsonCount(2, 'data')
                ->assertJsonPath(
                    'meta.credit_quantity',
                    20
                );
        }
    }

    public function test_provider_can_view_their_own_transaction(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $transaction = $this->creditTransactionFor(
            $provider,
            'HTTP-TRANSACTION-SHOW'
        );

        $this->actingAs($provider->user)
            ->getJson(
                route(
                    'trip-transactions.show',
                    $transaction
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $transaction->id
            )
            ->assertJsonPath(
                'data.delivery_provider_id',
                $provider->id
            );
    }

    public function test_provider_cannot_view_another_providers_transaction(): void
    {
        $owner = DeliveryProvider::factory()->create();

        $unrelatedProvider = DeliveryProvider::factory()
            ->create();

        $transaction = $this->creditTransactionFor(
            $owner,
            'HTTP-TRANSACTION-FORBIDDEN'
        );

        $this->actingAs($unrelatedProvider->user)
            ->getJson(
                route(
                    'trip-transactions.show',
                    $transaction
                )
            )
            ->assertForbidden();
    }

    public function test_support_and_administration_can_view_transaction_details(): void
    {
        $transaction = $this->creditTransactionFor(
            DeliveryProvider::factory()->create(),
            'HTTP-TRANSACTION-DETAILS'
        );

        $users = [
            $this->createUserWithRole('SUPPORT_AGENT'),
            $this->createUserWithRole('ADMINISTRATOR'),
        ];

        foreach ($users as $user) {
            $this->actingAs($user)
                ->getJson(
                    route(
                        'trip-transactions.show',
                        $transaction
                    )
                )
                ->assertOk()
                ->assertJsonPath(
                    'data.id',
                    $transaction->id
                );
        }
    }

    public function test_unauthorized_users_cannot_access_transactions(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $transaction = $this->creditTransactionFor(
            $provider,
            'HTTP-TRANSACTION-UNAUTHORIZED'
        );

        $customer = Customer::factory()
            ->create()
            ->user;

        $courier = Courier::factory()
            ->create([
                'delivery_provider_id' => $provider->id,
            ])
            ->user;

        foreach ([
            $customer,
            $courier,
        ] as $user) {
            $this->actingAs($user)
                ->getJson(
                    route('trip-transactions.index')
                )
                ->assertForbidden();

            $this->actingAs($user)
                ->getJson(
                    route(
                        'trip-transactions.show',
                        $transaction
                    )
                )
                ->assertForbidden();
        }

        $provider->user->update([
            'account_status_id' => AccountStatus::query()
                ->where('status_name', 'SUSPENDED')
                ->firstOrFail()
                ->id,
        ]);

        $this->actingAs($provider->user->fresh())
            ->getJson(
                route('trip-transactions.index')
            )
            ->assertForbidden();
    }

    private function creditTransactionFor(
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

    private function debitTransactionFor(
        DeliveryProvider $provider
    ): TripTransaction {
        $trip = Trip::query()
            ->where(
                'delivery_provider_id',
                $provider->id
            )
            ->where('status', 'AVAILABLE')
            ->firstOrFail();

        return TripTransaction::query()->create([
            'delivery_provider_id' => $provider->id,
            'recharge_id' => null,
            'trip_id' => $trip->id,
            'transaction_type' => 'DEBIT',
            'quantity' => 1,
            'description' => 'Trip consumed for a delivery service.',
            'transaction_at' => now(),
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