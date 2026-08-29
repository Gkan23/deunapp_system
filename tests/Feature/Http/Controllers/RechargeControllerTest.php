<?php

namespace Tests\Feature\Http\Controllers;

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
use Tests\TestCase;

class RechargeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_guests_cannot_access_recharge_endpoints(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $recharge = $this->rechargeFor(
            $provider,
            'HTTP-GUEST-RECHARGE'
        );

        $this->getJson(
            route('recharges.index')
        )->assertUnauthorized();

        $this->postJson(
            route('recharges.store'),
            []
        )->assertUnauthorized();

        $this->getJson(
            route('recharges.show', $recharge)
        )->assertUnauthorized();
    }

    public function test_provider_only_lists_their_own_recharges(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $otherProvider = DeliveryProvider::factory()
            ->create();

        $ownRecharge = $this->rechargeFor(
            $provider,
            'HTTP-OWNER-RECHARGE'
        );

        $this->rechargeFor(
            $otherProvider,
            'HTTP-OTHER-RECHARGE'
        );

        $this->actingAs($provider->user)
            ->getJson(route('recharges.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $ownRecharge->id
            );
    }

    public function test_support_and_administration_list_all_recharges(): void
    {
        $firstProvider = DeliveryProvider::factory()
            ->create();

        $secondProvider = DeliveryProvider::factory()
            ->create();

        $this->rechargeFor(
            $firstProvider,
            'HTTP-LIST-FIRST'
        );

        $this->rechargeFor(
            $secondProvider,
            'HTTP-LIST-SECOND'
        );

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
                ->getJson(route('recharges.index'))
                ->assertOk()
                ->assertJsonCount(2, 'data');
        }
    }

    public function test_recharge_details_respect_the_policy_scope(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $unrelatedProvider = DeliveryProvider::factory()
            ->create();

        $recharge = $this->rechargeFor(
            $provider,
            'HTTP-RECHARGE-DETAILS'
        );

        $this->actingAs($provider->user)
            ->getJson(
                route('recharges.show', $recharge)
            )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $recharge->id
            );

        $this->actingAs($unrelatedProvider->user)
            ->getJson(
                route('recharges.show', $recharge)
            )
            ->assertForbidden();

        $supportAgent = $this->createUserWithRole(
            'SUPPORT_AGENT'
        );

        $this->actingAs($supportAgent)
            ->getJson(
                route('recharges.show', $recharge)
            )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $recharge->id
            );
    }

    public function test_active_provider_can_confirm_a_recharge(): void
    {
        $provider = DeliveryProvider::factory()->create([
            'is_active' => true,
        ]);

        $package = $this->localPackage();

        $this->actingAs($provider->user)
            ->postJson(
                route('recharges.store'),
                [
                    'recharge_package_id' => $package->id,
                    'payment_reference' => ' HTTP-CONFIRM-001 ',
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Recharge confirmed successfully.'
            )
            ->assertJsonPath(
                'data.delivery_provider_id',
                $provider->id
            )
            ->assertJsonPath(
                'data.recharge_package_id',
                $package->id
            )
            ->assertJsonPath(
                'data.payment_reference',
                'HTTP-CONFIRM-001'
            )
            ->assertJsonPath(
                'data.trip_quantity',
                10
            );

        $this->assertDatabaseHas('recharges', [
            'delivery_provider_id' => $provider->id,
            'recharge_package_id' => $package->id,
            'payment_reference' => 'HTTP-CONFIRM-001',
            'trip_quantity' => 10,
        ]);

        $this->assertDatabaseCount('trips', 10);

        $this->assertDatabaseHas('trip_transactions', [
            'delivery_provider_id' => $provider->id,
            'transaction_type' => 'CREDIT',
            'quantity' => 10,
        ]);
    }

    public function test_inactive_provider_cannot_confirm_recharges(): void
    {
        $provider = DeliveryProvider::factory()->create([
            'is_active' => false,
        ]);

        $this->actingAs($provider->user)
            ->postJson(
                route('recharges.store'),
                [
                    'recharge_package_id' => $this
                        ->localPackage()
                        ->id,
                    'payment_reference' => 'HTTP-INACTIVE-PROVIDER',
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseCount('recharges', 0);
        $this->assertDatabaseCount('trips', 0);
        $this->assertDatabaseCount(
            'trip_transactions',
            0
        );
    }

    public function test_non_provider_roles_cannot_confirm_recharges(): void
    {
        $package = $this->localPackage();

        $users = [
            Customer::factory()->create()->user,
            Courier::factory()->create()->user,
            $this->createUserWithRole('SUPPORT_AGENT'),
            $this->createUserWithRole('ADMINISTRATOR'),
        ];

        foreach ($users as $user) {
            $this->actingAs($user)
                ->postJson(
                    route('recharges.store'),
                    [
                        'recharge_package_id' => $package->id,
                        'payment_reference' => 'UNAUTHORIZED-'.$user->id,
                    ]
                )
                ->assertForbidden();
        }

        $this->assertDatabaseCount('recharges', 0);
    }

    public function test_recharge_data_is_validated(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $this->actingAs($provider->user)
            ->postJson(
                route('recharges.store'),
                [
                    'recharge_package_id' => 999999,
                    'payment_reference' => str_repeat(
                        'R',
                        101
                    ),
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'recharge_package_id',
                'payment_reference',
            ]);

        $this->assertDatabaseCount('recharges', 0);
    }

    public function test_duplicated_payment_reference_is_returned_as_unprocessable(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $payload = [
            'recharge_package_id' => $this
                ->localPackage()
                ->id,
            'payment_reference' => 'HTTP-DUPLICATED-REFERENCE',
        ];

        $this->actingAs($provider->user)
            ->postJson(
                route('recharges.store'),
                $payload
            )
            ->assertCreated();

        $this->actingAs($provider->user)
            ->postJson(
                route('recharges.store'),
                $payload
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'The payment reference has already been used.'
            );

        $this->assertDatabaseCount('recharges', 1);
        $this->assertDatabaseCount('trips', 10);
        $this->assertDatabaseCount(
            'trip_transactions',
            1
        );
    }

    public function test_inactive_package_is_returned_as_unprocessable(): void
    {
        $provider = DeliveryProvider::factory()->create();

        $package = $this->localPackage();

        $package->update([
            'is_active' => false,
        ]);

        $this->actingAs($provider->user)
            ->postJson(
                route('recharges.store'),
                [
                    'recharge_package_id' => $package->id,
                    'payment_reference' => 'HTTP-INACTIVE-PACKAGE',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'The recharge package is inactive.'
            );

        $this->assertDatabaseCount('recharges', 0);
        $this->assertDatabaseCount('trips', 0);
        $this->assertDatabaseCount(
            'trip_transactions',
            0
        );
    }

    private function rechargeFor(
        DeliveryProvider $provider,
        string $paymentReference
    ): Recharge {
        return app(
            ConfirmRechargeService::class
        )->handle(
            $provider,
            $this->localPackage(),
            $paymentReference
        );
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
}