<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AccountStatus;
use App\Models\Courier;
use App\Models\DeliveryProvider;
use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleStatus;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleStatusControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_guest_cannot_change_a_vehicle_status(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->patchJson(
            route(
                'vehicles.status.update',
                $vehicle
            ),
            [
                'status' => 'MAINTENANCE',
                'comment' =>
                    'Scheduled maintenance.',
            ]
        )->assertUnauthorized();
    }

    public function test_the_provider_can_place_a_vehicle_in_maintenance(): void
    {
        [$provider, $vehicle] =
            $this->providerVehicle();

        $this->actingAs($provider->user)
            ->patchJson(
                route(
                    'vehicles.status.update',
                    $vehicle
                ),
                [
                    'status' =>
                        ' maintenance ',
                    'comment' =>
                        '  Scheduled maintenance.  ',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Vehicle status updated successfully.'
            )
            ->assertJsonPath(
                'data.vehicle_status',
                'MAINTENANCE'
            );

        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'vehicle_status_id' =>
                $this->vehicleStatus(
                    'MAINTENANCE'
                )->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'performed_by_user_id' =>
                $provider->user->id,
            'table_name' => 'vehicles',
            'record_id' => $vehicle->id,
            'action_type' =>
                'VEHICLE_STATUS_CHANGED',
        ]);
    }

    public function test_a_maintenance_vehicle_can_return_to_available(): void
    {
        [$provider, $vehicle] =
            $this->providerVehicle(
                'MAINTENANCE'
            );

        $this->actingAs($provider->user)
            ->patchJson(
                route(
                    'vehicles.status.update',
                    $vehicle
                ),
                [
                    'status' => 'AVAILABLE',
                    'comment' =>
                        'Maintenance completed.',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.vehicle_status',
                'AVAILABLE'
            );
    }

    public function test_an_available_vehicle_can_be_deactivated(): void
    {
        [$provider, $vehicle] =
            $this->providerVehicle();

        $this->actingAs($provider->user)
            ->patchJson(
                route(
                    'vehicles.status.update',
                    $vehicle
                ),
                [
                    'status' => 'INACTIVE',
                    'comment' =>
                        'Vehicle removed from service.',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.vehicle_status',
                'INACTIVE'
            );
    }

    public function test_the_in_use_status_cannot_be_assigned_manually(): void
    {
        [$provider, $vehicle] =
            $this->providerVehicle();

        $this->actingAs($provider->user)
            ->patchJson(
                route(
                    'vehicles.status.update',
                    $vehicle
                ),
                [
                    'status' => 'IN_USE',
                    'comment' =>
                        'Manual in-use attempt.',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'The IN_USE status can only be assigned by an internal route operation.'
            );

        $this->assertVehicleStatus(
            $vehicle,
            'AVAILABLE'
        );
    }

    public function test_the_requested_status_must_be_different(): void
    {
        [$provider, $vehicle] =
            $this->providerVehicle();

        $this->actingAs($provider->user)
            ->patchJson(
                route(
                    'vehicles.status.update',
                    $vehicle
                ),
                [
                    'status' => 'AVAILABLE',
                    'comment' =>
                        'No effective change.',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'The vehicle is already in the requested status.'
            );
    }

    public function test_status_and_comment_are_required(): void
    {
        [$provider, $vehicle] =
            $this->providerVehicle();

        $this->actingAs($provider->user)
            ->patchJson(
                route(
                    'vehicles.status.update',
                    $vehicle
                ),
                []
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
                'comment',
            ]);
    }

    public function test_the_status_must_exist(): void
    {
        [$provider, $vehicle] =
            $this->providerVehicle();

        $this->actingAs($provider->user)
            ->patchJson(
                route(
                    'vehicles.status.update',
                    $vehicle
                ),
                [
                    'status' =>
                        'UNKNOWN_STATUS',
                    'comment' =>
                        'Unknown status attempt.',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
            ]);
    }

    public function test_a_provider_cannot_change_another_providers_vehicle(): void
    {
        $provider =
            DeliveryProvider::factory()->create();

        $otherVehicle =
            Vehicle::factory()->create();

        $this->actingAs($provider->user)
            ->patchJson(
                route(
                    'vehicles.status.update',
                    $otherVehicle
                ),
                [
                    'status' =>
                        'MAINTENANCE',
                    'comment' =>
                        'Unauthorized change.',
                ]
            )
            ->assertForbidden();
    }

    public function test_non_provider_roles_cannot_change_vehicle_statuses(): void
    {
        [, $vehicle] =
            $this->providerVehicle();

        foreach ([
            'CUSTOMER',
            'COURIER',
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ] as $roleName) {
            $user = $this->userWithRole(
                $roleName
            );

            $this->actingAs($user)
                ->patchJson(
                    route(
                        'vehicles.status.update',
                        $vehicle
                    ),
                    [
                        'status' =>
                            'MAINTENANCE',
                        'comment' =>
                            'Unauthorized role.',
                    ]
                )
                ->assertForbidden();
        }
    }

    public function test_inactive_providers_cannot_change_vehicle_statuses(): void
    {
        [$provider, $vehicle] =
            $this->providerVehicle();

        $provider->user->update([
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
            $provider->user->fresh()
        )
            ->patchJson(
                route(
                    'vehicles.status.update',
                    $vehicle
                ),
                [
                    'status' =>
                        'MAINTENANCE',
                    'comment' =>
                        'Suspended account attempt.',
                ]
            )
            ->assertForbidden();

        $provider->user->update([
            'account_status_id' =>
                AccountStatus::query()
                    ->where(
                        'status_name',
                        'ACTIVE'
                    )
                    ->firstOrFail()
                    ->id,
        ]);

        $provider->update([
            'is_active' => false,
        ]);

        $this->actingAs(
            $provider->user->fresh()
        )
            ->patchJson(
                route(
                    'vehicles.status.update',
                    $vehicle
                ),
                [
                    'status' =>
                        'MAINTENANCE',
                    'comment' =>
                        'Inactive profile attempt.',
                ]
            )
            ->assertForbidden();
    }

    /**
     * @return array{
     *     0: DeliveryProvider,
     *     1: Vehicle
     * }
     */
    private function providerVehicle(
        string $statusName = 'AVAILABLE'
    ): array {
        $provider =
            DeliveryProvider::factory()->create();

        $courier = Courier::factory()->create([
            'delivery_provider_id' =>
                $provider->id,
        ]);

        $vehicle = Vehicle::factory()->create([
            'courier_id' => $courier->id,
            'vehicle_status_id' =>
                $this->vehicleStatus(
                    $statusName
                )->id,
        ]);

        return [
            $provider,
            $vehicle,
        ];
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

    private function assertVehicleStatus(
        Vehicle $vehicle,
        string $statusName
    ): void {
        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'vehicle_status_id' =>
                $this->vehicleStatus(
                    $statusName
                )->id,
        ]);
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
}