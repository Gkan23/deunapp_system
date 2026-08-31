<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Courier;
use App\Models\Customer;
use App\Models\Role;
use App\Models\Route as DeliveryRoute;
use App\Models\RouteShipment;
use App\Models\RouteStatus;
use App\Models\Shipment;
use App\Models\ShipmentStatus;
use App\Models\ShipmentStatusHistory;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentStatusHistoryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_guest_cannot_view_shipment_status_history(): void
    {
        $shipment = Shipment::factory()->create();

        $this->getJson(
            route(
                'shipments.status-history.index',
                $shipment
            )
        )->assertUnauthorized();
    }

    public function test_the_customer_can_view_their_shipment_timeline(): void
    {
        $customer = Customer::factory()->create();

        $deliveredStatus = $this->shipmentStatus(
            'DELIVERED'
        );

        $shipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
            'shipment_status_id' =>
                $deliveredStatus->id,
        ]);

        $inTransitStatus = $this->shipmentStatus(
            'IN_TRANSIT'
        );

        $requestedStatus = $this->shipmentStatus(
            'REQUESTED'
        );

        $this->createHistory(
            shipment: $shipment,
            shipmentStatus: $inTransitStatus,
            changedBy: $customer->user,
            comment: 'Shipment is in transit.',
            changedAt: now()->subHour()
        );

        $this->createHistory(
            shipment: $shipment,
            shipmentStatus: $requestedStatus,
            changedBy: $customer->user,
            comment: 'Shipment was requested.',
            changedAt: now()->subHours(2)
        );

        $this->createHistory(
            shipment: $shipment,
            shipmentStatus: $deliveredStatus,
            changedBy: $customer->user,
            comment: 'Shipment was delivered.',
            changedAt: now()
        );

        $this->actingAs($customer->user)
            ->getJson(
                route(
                    'shipments.status-history.index',
                    $shipment
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'data.shipment.id',
                $shipment->id
            )
            ->assertJsonPath(
                'data.shipment.tracking_code',
                $shipment->tracking_code
            )
            ->assertJsonPath(
                'data.shipment.current_status',
                'DELIVERED'
            )
            ->assertJsonCount(
                3,
                'data.history'
            )
            ->assertJsonPath(
                'data.history.0.status.name',
                'REQUESTED'
            )
            ->assertJsonPath(
                'data.history.1.status.name',
                'IN_TRANSIT'
            )
            ->assertJsonPath(
                'data.history.2.status.name',
                'DELIVERED'
            )
            ->assertJsonPath(
                'data.history.2.comment',
                'Shipment was delivered.'
            )
            ->assertJsonPath(
                'data.history.2.changed_by.id',
                $customer->user->id
            )
            ->assertJsonPath(
                'data.history.2.changed_by.name',
                $customer->user->name
            )
            ->assertJsonPath(
                'data.history.2.changed_by.role',
                'CUSTOMER'
            );
    }

    public function test_an_empty_status_history_returns_an_empty_timeline(): void
    {
        $customer = Customer::factory()->create();

        $shipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $this->actingAs($customer->user)
            ->getJson(
                route(
                    'shipments.status-history.index',
                    $shipment
                )
            )
            ->assertOk()
            ->assertJsonCount(
                0,
                'data.history'
            )
            ->assertJsonPath(
                'data.history',
                []
            );
    }

    public function test_an_unrelated_customer_cannot_view_the_timeline(): void
    {
        $owner = Customer::factory()->create();

        $unrelatedCustomer =
            Customer::factory()->create();

        $shipment = Shipment::factory()->create([
            'customer_id' => $owner->id,
        ]);

        $this->actingAs(
            $unrelatedCustomer->user
        )
            ->getJson(
                route(
                    'shipments.status-history.index',
                    $shipment
                )
            )
            ->assertForbidden();
    }

    public function test_the_assigned_courier_can_view_the_timeline(): void
    {
        $shipment = Shipment::factory()->create();

        $courier = Courier::factory()->create();

        $this->assignCourier(
            $shipment,
            $courier
        );

        $this->createHistory(
            shipment: $shipment,
            shipmentStatus:
                $this->shipmentStatus('IN_TRANSIT'),
            changedBy: $courier->user,
            comment: 'The package is moving.',
            changedAt: now()
        );

        $this->actingAs($courier->user)
            ->getJson(
                route(
                    'shipments.status-history.index',
                    $shipment
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'data.history.0.status.name',
                'IN_TRANSIT'
            );
    }

    public function test_support_and_administration_can_view_the_timeline(): void
    {
        $shipment = Shipment::factory()->create();

        $this->createHistory(
            shipment: $shipment,
            shipmentStatus:
                $this->shipmentStatus('REQUESTED'),
            changedBy: null,
            comment: null,
            changedAt: now()
        );

        foreach ([
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ] as $roleName) {
            $user = User::factory()->create([
                'role_id' => Role::query()
                    ->where(
                        'role_name',
                        $roleName
                    )
                    ->firstOrFail()
                    ->id,
            ]);

            $this->actingAs($user)
                ->getJson(
                    route(
                        'shipments.status-history.index',
                        $shipment
                    )
                )
                ->assertOk()
                ->assertJsonCount(
                    1,
                    'data.history'
                );
        }
    }

    public function test_a_system_generated_history_has_no_changed_user(): void
    {
        $customer = Customer::factory()->create();

        $shipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $entry = $this->createHistory(
            shipment: $shipment,
            shipmentStatus:
                $this->shipmentStatus('REQUESTED'),
            changedBy: null,
            comment: null,
            changedAt: now()
        );

        $this->actingAs($customer->user)
            ->getJson(
                route(
                    'shipments.status-history.index',
                    $shipment
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'data.history.0.id',
                $entry->id
            )
            ->assertJsonPath(
                'data.history.0.comment',
                null
            )
            ->assertJsonPath(
                'data.history.0.changed_by',
                null
            );
    }

    public function test_an_unverified_customer_cannot_view_the_timeline(): void
    {
        $customer = Customer::factory()->create();

        $shipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $customer->user->forceFill([
            'email_verified_at' => null,
        ])->save();

        $this->actingAs(
            $customer->user->fresh()
        )
            ->getJson(
                route(
                    'shipments.status-history.index',
                    $shipment
                )
            )
            ->assertForbidden();
    }

    private function shipmentStatus(
        string $statusName
    ): ShipmentStatus {
        return ShipmentStatus::query()
            ->where(
                'status_name',
                $statusName
            )
            ->firstOrFail();
    }

    private function createHistory(
        Shipment $shipment,
        ShipmentStatus $shipmentStatus,
        ?User $changedBy,
        ?string $comment,
        mixed $changedAt
    ): ShipmentStatusHistory {
        return ShipmentStatusHistory::query()
            ->create([
                'shipment_id' => $shipment->id,
                'shipment_status_id' =>
                    $shipmentStatus->id,
                'changed_by_user_id' =>
                    $changedBy?->id,
                'comment' => $comment,
                'changed_at' => $changedAt,
            ]);
    }

    private function assignCourier(
        Shipment $shipment,
        Courier $courier
    ): RouteShipment {
        $activeStatus = RouteStatus::query()
            ->where('status_name', 'ACTIVE')
            ->firstOrFail();

        $route = DeliveryRoute::query()->create([
            'courier_id' => $courier->id,
            'route_status_id' =>
                $activeStatus->id,
            'route_date' => today(),
            'started_at' => now(),
            'finished_at' => null,
            'estimated_distance_km' => 10.00,
        ]);

        return RouteShipment::query()->create([
            'route_id' => $route->id,
            'shipment_id' => $shipment->id,
            'delivery_order' => 1,
            'delivery_status' => 'IN_PROGRESS',
        ]);
    }
}