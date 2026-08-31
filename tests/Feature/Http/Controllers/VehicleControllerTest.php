<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AccountStatus;
use App\Models\Courier;
use App\Models\DeliveryProvider;
use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleStatus;
use App\Models\VehicleType;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_guests_cannot_access_vehicle_endpoints(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->getJson(
            route('vehicles.index')
        )->assertUnauthorized();

        $this->postJson(
            route('vehicles.store'),
            []
        )->assertUnauthorized();

        $this->getJson(
            route('vehicles.show', $vehicle)
        )->assertUnauthorized();
    }

    public function test_a_provider_only_lists_their_vehicles(): void
    {
        $provider =
            DeliveryProvider::factory()->create();

        $courier = Courier::factory()->create([
            'delivery_provider_id' =>
                $provider->id,
        ]);

        $ownVehicle = Vehicle::factory()->create([
            'courier_id' => $courier->id,
        ]);

        $otherVehicle =
            Vehicle::factory()->create();

        $this->actingAs($provider->user)
            ->getJson(
                route('vehicles.index')
            )
            ->assertOk()
            ->assertJsonFragment([
                'id' => $ownVehicle->id,
                'plate_number' =>
                    $ownVehicle->plate_number,
            ])
            ->assertJsonMissing([
                'id' => $otherVehicle->id,
                'plate_number' =>
                    $otherVehicle->plate_number,
            ]);
    }

    public function test_a_courier_only_lists_their_vehicles(): void
    {
        $courier = Courier::factory()->create();

        $ownVehicle = Vehicle::factory()->create([
            'courier_id' => $courier->id,
        ]);

        $otherVehicle =
            Vehicle::factory()->create();

        $this->actingAs($courier->user)
            ->getJson(
                route('vehicles.index')
            )
            ->assertOk()
            ->assertJsonFragment([
                'id' => $ownVehicle->id,
                'plate_number' =>
                    $ownVehicle->plate_number,
            ])
            ->assertJsonMissing([
                'id' => $otherVehicle->id,
                'plate_number' =>
                    $otherVehicle->plate_number,
            ]);
    }

    public function test_support_and_administration_list_all_vehicles(): void
    {
        Vehicle::factory()->count(2)->create();

        foreach ([
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ] as $roleName) {
            $user = $this->userWithRole(
                $roleName
            );

            $this->actingAs($user)
                ->getJson(
                    route('vehicles.index')
                )
                ->assertOk()
                ->assertJsonPath(
                    'meta.total',
                    2
                );
        }
    }

    public function test_a_provider_can_create_a_vehicle_for_their_courier(): void
    {
        $provider =
            DeliveryProvider::factory()->create();

        $courier = Courier::factory()->create([
            'delivery_provider_id' =>
                $provider->id,
        ]);

        $response = $this
            ->actingAs($provider->user)
            ->postJson(
                route('vehicles.store'),
                [
                    'courier_id' => $courier->id,
                    'vehicle_type' =>
                        ' motorcycle ',
                    'plate_number' =>
                        '  m   123456  ',
                    'brand' => ' Honda ',
                    'model' => ' CB-125 ',
                    'color' => ' Red ',
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Vehicle created successfully.'
            )
            ->assertJsonPath(
                'data.plate_number',
                'M 123456'
            )
            ->assertJsonPath(
                'data.vehicle_type',
                'MOTORCYCLE'
            )
            ->assertJsonPath(
                'data.vehicle_status',
                'AVAILABLE'
            )
            ->assertJsonPath(
                'data.courier.id',
                $courier->id
            );

        $vehicleId = $response->json(
            'data.id'
        );

        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicleId,
            'courier_id' => $courier->id,
            'vehicle_type_id' =>
                $this->vehicleType(
                    'MOTORCYCLE'
                )->id,
            'vehicle_status_id' =>
                $this->vehicleStatus(
                    'AVAILABLE'
                )->id,
            'plate_number' => 'M 123456',
            'brand' => 'Honda',
            'model' => 'CB-125',
            'color' => 'Red',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'performed_by_user_id' =>
                $provider->user->id,
            'table_name' => 'vehicles',
            'record_id' => $vehicleId,
            'action_type' =>
                'VEHICLE_CREATED',
        ]);
    }

    public function test_optional_vehicle_fields_can_be_null(): void
    {
        $provider =
            DeliveryProvider::factory()->create();

        $courier = Courier::factory()->create([
            'delivery_provider_id' =>
                $provider->id,
        ]);

        $this->actingAs($provider->user)
            ->postJson(
                route('vehicles.store'),
                [
                    'courier_id' => $courier->id,
                    'vehicle_type' => 'BICYCLE',
                    'plate_number' => 'BICYCLE-01',
                    'brand' => '   ',
                    'model' => '',
                    'color' => null,
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.brand',
                null
            )
            ->assertJsonPath(
                'data.model',
                null
            )
            ->assertJsonPath(
                'data.color',
                null
            );
    }

    public function test_vehicle_creation_data_is_validated(): void
    {
        $provider =
            DeliveryProvider::factory()->create();

        $this->actingAs($provider->user)
            ->postJson(
                route('vehicles.store'),
                []
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'courier_id',
                'vehicle_type',
                'plate_number',
            ]);

        $courier = Courier::factory()->create([
            'delivery_provider_id' =>
                $provider->id,
        ]);

        $this->actingAs($provider->user)
            ->postJson(
                route('vehicles.store'),
                [
                    'courier_id' => $courier->id,
                    'vehicle_type' =>
                        'UNKNOWN_TYPE',
                    'plate_number' =>
                        'M 100001',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'vehicle_type',
            ]);
    }

    public function test_the_plate_number_must_be_unique(): void
    {
        $provider =
            DeliveryProvider::factory()->create();

        $courier = Courier::factory()->create([
            'delivery_provider_id' =>
                $provider->id,
        ]);

        Vehicle::factory()->create([
            'plate_number' => 'M 654321',
        ]);

        $this->actingAs($provider->user)
            ->postJson(
                route('vehicles.store'),
                [
                    'courier_id' => $courier->id,
                    'vehicle_type' =>
                        'MOTORCYCLE',
                    'plate_number' =>
                        ' m   654321 ',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'plate_number',
            ]);
    }

    public function test_a_provider_cannot_assign_a_vehicle_to_another_providers_courier(): void
    {
        $provider =
            DeliveryProvider::factory()->create();

        $otherCourier =
            Courier::factory()->create();

        $this->actingAs($provider->user)
            ->postJson(
                route('vehicles.store'),
                [
                    'courier_id' =>
                        $otherCourier->id,
                    'vehicle_type' =>
                        'MOTORCYCLE',
                    'plate_number' =>
                        'M 200001',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'The courier does not belong to the delivery provider.'
            );

        $this->assertDatabaseMissing(
            'vehicles',
            [
                'plate_number' =>
                    'M 200001',
            ]
        );
    }

    public function test_vehicle_details_respect_the_policy_scope(): void
    {
        $provider =
            DeliveryProvider::factory()->create();

        $courier = Courier::factory()->create([
            'delivery_provider_id' =>
                $provider->id,
        ]);

        $vehicle = Vehicle::factory()->create([
            'courier_id' => $courier->id,
        ]);

        $otherProvider =
            DeliveryProvider::factory()->create();

        $this->actingAs($provider->user)
            ->getJson(
                route(
                    'vehicles.show',
                    $vehicle
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $vehicle->id
            );

        $this->actingAs($courier->user)
            ->getJson(
                route(
                    'vehicles.show',
                    $vehicle
                )
            )
            ->assertOk();

        $this->actingAs(
            $otherProvider->user
        )
            ->getJson(
                route(
                    'vehicles.show',
                    $vehicle
                )
            )
            ->assertForbidden();
    }

    public function test_non_provider_roles_cannot_create_vehicles(): void
    {
        $courier = Courier::factory()->create();

        foreach ([
            'CUSTOMER',
            'COURIER',
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ] as $roleName) {
            $user = $roleName === 'COURIER'
                ? $courier->user
                : $this->userWithRole(
                    $roleName
                );

            $this->actingAs($user)
                ->postJson(
                    route('vehicles.store'),
                    [
                        'courier_id' =>
                            $courier->id,
                        'vehicle_type' =>
                            'MOTORCYCLE',
                        'plate_number' =>
                            'TEST-'.$user->id,
                    ]
                )
                ->assertForbidden();
        }
    }

    public function test_inactive_providers_cannot_create_vehicles(): void
    {
        $suspendedProvider =
            DeliveryProvider::factory()->create();

        $suspendedCourier =
            Courier::factory()->create([
                'delivery_provider_id' =>
                    $suspendedProvider->id,
            ]);

        $suspendedProvider->user->update([
            'account_status_id' =>
                AccountStatus::query()
                    ->where(
                        'status_name',
                        'SUSPENDED'
                    )
                    ->firstOrFail()
                    ->id,
        ]);

        $this->actingAs(
            $suspendedProvider->user->fresh()
        )
            ->postJson(
                route('vehicles.store'),
                [
                    'courier_id' =>
                        $suspendedCourier->id,
                    'vehicle_type' =>
                        'MOTORCYCLE',
                    'plate_number' =>
                        'M 300001',
                ]
            )
            ->assertForbidden();

        $inactiveProvider =
            DeliveryProvider::factory()->create([
                'is_active' => false,
            ]);

        $inactiveCourier =
            Courier::factory()->create([
                'delivery_provider_id' =>
                    $inactiveProvider->id,
            ]);

        $this->actingAs(
            $inactiveProvider->user
        )
            ->postJson(
                route('vehicles.store'),
                [
                    'courier_id' =>
                        $inactiveCourier->id,
                    'vehicle_type' =>
                        'MOTORCYCLE',
                    'plate_number' =>
                        'M 300002',
                ]
            )
            ->assertForbidden();
    }

    private function userWithRole(
        string $roleName
    ): User {
        return User::factory()->create([
            'role_id' => Role::query()
                ->where(
                    'role_name',
                    $roleName
                )
                ->firstOrFail()
                ->id,
        ]);
    }

    private function vehicleType(
        string $typeName
    ): VehicleType {
        return VehicleType::query()
            ->where(
                'type_name',
                $typeName
            )
            ->firstOrFail();
    }

    private function vehicleStatus(
        string $statusName
    ): VehicleStatus {
        return VehicleStatus::query()
            ->where(
                'status_name',
                $statusName
            )
            ->firstOrFail();
    }
}