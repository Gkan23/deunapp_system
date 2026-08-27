<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Courier;
use App\Models\Customer;
use App\Models\DeliveryProvider;
use App\Models\DeliveryService;
use App\Models\Rating;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShipmentStatus;
use App\Models\Trip;
use App\Models\User;
use App\Services\Rating\CreateRatingService;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RatingControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_guests_cannot_access_rating_endpoints(): void
    {
        [
            $customer,
            $provider,
            $shipment,
            $deliveryService,
        ] = $this->createCompletedScenario();

        $rating = $this->createRating(
            $deliveryService,
            $customer
        );

        $this->getJson(
            route('ratings.index')
        )->assertUnauthorized();

        $this->getJson(
            route('ratings.show', $rating)
        )->assertUnauthorized();

        $this->postJson(
            route(
                'delivery-services.ratings.store',
                $deliveryService
            ),
            [
                'punctuality_score' => 5,
                'customer_service_score' => 5,
                'package_condition_score' => 5,
            ]
        )->assertUnauthorized();
    }

    public function test_a_customer_only_lists_their_own_ratings(): void
    {
        [
            $customer,
            $provider,
            $shipment,
            $deliveryService,
        ] = $this->createCompletedScenario();

        $ownRating = $this->createRating(
            $deliveryService,
            $customer
        );

        [
            $otherCustomer,
            $otherProvider,
            $otherShipment,
            $otherService,
        ] = $this->createCompletedScenario();

        $this->createRating(
            $otherService,
            $otherCustomer
        );

        $response = $this
            ->actingAs($customer->user)
            ->getJson(route('ratings.index'));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $ownRating->id
            );
    }

    public function test_a_provider_only_lists_linked_ratings(): void
    {
        [
            $customer,
            $provider,
            $shipment,
            $deliveryService,
        ] = $this->createCompletedScenario();

        $linkedRating = $this->createRating(
            $deliveryService,
            $customer
        );

        [
            $otherCustomer,
            $otherProvider,
            $otherShipment,
            $otherService,
        ] = $this->createCompletedScenario();

        $this->createRating(
            $otherService,
            $otherCustomer
        );

        $response = $this
            ->actingAs($provider->user)
            ->getJson(route('ratings.index'));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $linkedRating->id
            );
    }

    public function test_support_and_administration_list_all_ratings(): void
    {
        [
            $firstCustomer,
            $firstProvider,
            $firstShipment,
            $firstService,
        ] = $this->createCompletedScenario();

        [
            $secondCustomer,
            $secondProvider,
            $secondShipment,
            $secondService,
        ] = $this->createCompletedScenario();

        $this->createRating(
            $firstService,
            $firstCustomer
        );

        $this->createRating(
            $secondService,
            $secondCustomer
        );

        $this
            ->actingAs(
                $this->userWithRole('SUPPORT_AGENT')
            )
            ->getJson(route('ratings.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this
            ->actingAs(
                $this->userWithRole('ADMINISTRATOR')
            )
            ->getJson(route('ratings.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $courier = Courier::factory()->create();

        $this
            ->actingAs($courier->user)
            ->getJson(route('ratings.index'))
            ->assertForbidden();
    }

    public function test_rating_details_respect_the_policy_scope(): void
    {
        [
            $customer,
            $provider,
            $shipment,
            $deliveryService,
        ] = $this->createCompletedScenario();

        $rating = $this->createRating(
            $deliveryService,
            $customer
        );

        $otherCustomer = Customer::factory()->create();
        $otherProvider = DeliveryProvider::factory()->create();

        $this
            ->actingAs($customer->user)
            ->getJson(
                route('ratings.show', $rating)
            )
            ->assertOk()
            ->assertJsonPath(
                'rating.id',
                $rating->id
            );

        $this
            ->actingAs($otherCustomer->user)
            ->getJson(
                route('ratings.show', $rating)
            )
            ->assertForbidden();

        $this
            ->actingAs($provider->user)
            ->getJson(
                route('ratings.show', $rating)
            )
            ->assertOk();

        $this
            ->actingAs($otherProvider->user)
            ->getJson(
                route('ratings.show', $rating)
            )
            ->assertForbidden();

        $this
            ->actingAs(
                $this->userWithRole('SUPPORT_AGENT')
            )
            ->getJson(
                route('ratings.show', $rating)
            )
            ->assertOk();

        $this
            ->actingAs(
                $this->userWithRole('ADMINISTRATOR')
            )
            ->getJson(
                route('ratings.show', $rating)
            )
            ->assertOk();
    }

    public function test_a_customer_can_rate_their_completed_service(): void
    {
        [
            $customer,
            $provider,
            $shipment,
            $deliveryService,
        ] = $this->createCompletedScenario();

        $response = $this
            ->actingAs($customer->user)
            ->postJson(
                route(
                    'delivery-services.ratings.store',
                    $deliveryService
                ),
                [
                    'punctuality_score' => 5,
                    'customer_service_score' => 4,
                    'package_condition_score' => 3,
                    'comment' => '  Satisfactory delivery.  ',
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Rating created successfully.'
            )
            ->assertJsonPath(
                'rating.punctuality_score',
                5
            )
            ->assertJsonPath(
                'rating.customer_service_score',
                4
            )
            ->assertJsonPath(
                'rating.package_condition_score',
                3
            )
            ->assertJsonPath(
                'rating.overall_score',
                '4.00'
            )
            ->assertJsonPath(
                'rating.comment',
                'Satisfactory delivery.'
            );

        $this->assertDatabaseHas('ratings', [
            'delivery_service_id' => $deliveryService->id,
            'customer_id' => $customer->id,
            'punctuality_score' => 5,
            'customer_service_score' => 4,
            'package_condition_score' => 3,
            'comment' => 'Satisfactory delivery.',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'table_name' => 'ratings',
            'action_type' => 'RATING_CREATED',
        ]);
    }

    public function test_a_customer_cannot_rate_another_customers_service(): void
    {
        [
            $owner,
            $provider,
            $shipment,
            $deliveryService,
        ] = $this->createCompletedScenario();

        $otherCustomer = Customer::factory()->create();

        $response = $this
            ->actingAs($otherCustomer->user)
            ->postJson(
                route(
                    'delivery-services.ratings.store',
                    $deliveryService
                ),
                [
                    'punctuality_score' => 5,
                    'customer_service_score' => 5,
                    'package_condition_score' => 5,
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Only the customer who owns the delivery service can rate it.'
            );

        $this->assertDatabaseCount('ratings', 0);
    }

    public function test_non_customers_cannot_create_ratings(): void
    {
        [
            $customer,
            $provider,
            $shipment,
            $deliveryService,
        ] = $this->createCompletedScenario();

        $payload = [
            'punctuality_score' => 5,
            'customer_service_score' => 5,
            'package_condition_score' => 5,
        ];

        $this
            ->actingAs($provider->user)
            ->postJson(
                route(
                    'delivery-services.ratings.store',
                    $deliveryService
                ),
                $payload
            )
            ->assertForbidden();

        $this
            ->actingAs(
                $this->userWithRole('SUPPORT_AGENT')
            )
            ->postJson(
                route(
                    'delivery-services.ratings.store',
                    $deliveryService
                ),
                $payload
            )
            ->assertForbidden();

        $this
            ->actingAs(
                $this->userWithRole('ADMINISTRATOR')
            )
            ->postJson(
                route(
                    'delivery-services.ratings.store',
                    $deliveryService
                ),
                $payload
            )
            ->assertForbidden();

        $this->assertDatabaseCount('ratings', 0);
    }

    public function test_rating_data_is_validated(): void
    {
        [
            $customer,
            $provider,
            $shipment,
            $deliveryService,
        ] = $this->createCompletedScenario();

        $response = $this
            ->actingAs($customer->user)
            ->postJson(
                route(
                    'delivery-services.ratings.store',
                    $deliveryService
                ),
                [
                    'punctuality_score' => 0,
                    'customer_service_score' => 6,
                    'comment' => str_repeat('C', 2001),
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'punctuality_score',
                'customer_service_score',
                'package_condition_score',
                'comment',
            ]);

        $this->assertDatabaseCount('ratings', 0);
    }

    public function test_rating_domain_errors_are_returned_as_unprocessable(): void
    {
        [
            $customer,
            $provider,
            $shipment,
            $deliveryService,
        ] = $this->createCompletedScenario();

        $deliveryService->update([
            'status' => 'IN_PROGRESS',
            'completed_at' => null,
        ]);

        $payload = [
            'punctuality_score' => 5,
            'customer_service_score' => 5,
            'package_condition_score' => 5,
        ];

        $this
            ->actingAs($customer->user)
            ->postJson(
                route(
                    'delivery-services.ratings.store',
                    $deliveryService
                ),
                $payload
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Only a completed delivery service can be rated.'
            );

        [
            $secondCustomer,
            $secondProvider,
            $secondShipment,
            $secondService,
        ] = $this->createCompletedScenario();

        $this
            ->actingAs($secondCustomer->user)
            ->postJson(
                route(
                    'delivery-services.ratings.store',
                    $secondService
                ),
                $payload
            )
            ->assertCreated();

        $this
            ->actingAs($secondCustomer->user)
            ->postJson(
                route(
                    'delivery-services.ratings.store',
                    $secondService
                ),
                $payload
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'The delivery service has already been rated.'
            );

        $this->assertDatabaseCount('ratings', 1);
    }

    /**
     * @return array{
     *     0: Customer,
     *     1: DeliveryProvider,
     *     2: Shipment,
     *     3: DeliveryService
     * }
     */
    private function createCompletedScenario(): array
    {
        $customer = Customer::factory()->create();
        $provider = DeliveryProvider::factory()->create();

        $deliveredStatus = ShipmentStatus::query()
            ->where('status_name', 'DELIVERED')
            ->firstOrFail();

        $shipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
            'shipment_status_id' => $deliveredStatus->id,
            'delivered_at' => now()->subMinutes(30),
        ]);

        $trip = Trip::factory()->create([
            'delivery_provider_id' => $provider->id,
            'status' => 'USED',
            'used_at' => now()->subHours(2),
        ]);

        $deliveryService = DeliveryService::factory()->create([
            'shipment_id' => $shipment->id,
            'customer_id' => $customer->id,
            'trip_id' => $trip->id,
            'status' => 'COMPLETED',
            'accepted_at' => now()->subHours(3),
            'started_at' => now()->subHours(2),
            'completed_at' => now()->subMinutes(30),
            'cancelled_at' => null,
            'delivery_fee' => 120.00,
        ]);

        return [
            $customer,
            $provider,
            $shipment,
            $deliveryService,
        ];
    }

    private function createRating(
        DeliveryService $deliveryService,
        Customer $customer
    ): Rating {
        return app(CreateRatingService::class)->execute(
            deliveryService: $deliveryService,
            customer: $customer,
            punctualityScore: 5,
            customerServiceScore: 4,
            packageConditionScore: 5,
            comment: 'Completed delivery rating.'
        );
    }

    private function userWithRole(string $roleName): User
    {
        $role = Role::query()
            ->where('role_name', $roleName)
            ->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
        ]);
    }
}