<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Courier;
use App\Models\Customer;
use App\Models\DeliveryProvider;
use App\Models\DeliveryService;
use App\Models\Role;
use App\Models\Route;
use App\Models\RouteShipment;
use App\Models\RouteStatus;
use App\Models\Shipment;
use App\Models\Trip;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentShowPageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_guest_cannot_view_the_shipment_detail_page(): void
    {
        $shipment = Shipment::factory()->create();

        $this->get(
            route(
                'portal.shipments.show',
                $shipment
            )
        )->assertRedirect(
            route('login.page')
        );
    }

    public function test_an_unverified_user_is_redirected_to_verification(): void
    {
        $customer = Customer::factory()->create();

        $shipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $user = $customer->user;

        $user->forceFill([
            'email_verified_at' => null,
        ])->save();

        $user = $user->fresh();

        $this->assertFalse(
            $user->hasVerifiedEmail()
        );

        $this->actingAs($user)
            ->get(
                route(
                    'portal.shipments.show',
                    $shipment
                )
            )
            ->assertRedirect(
                route('verification.notice')
            );
    }

    public function test_a_customer_can_view_their_shipment(): void
    {
        $customer = Customer::factory()->create();

        $shipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
            'tracking_code' =>
                'DUNA-CUSTOMER-DETAIL',
        ]);

        $this->actingAs($customer->user)
            ->get(
                route(
                    'portal.shipments.show',
                    $shipment
                )
            )
            ->assertOk()
            ->assertViewIs('shipments.show')
            ->assertViewHas(
                'shipment',
                fn (Shipment $viewShipment): bool =>
                    $viewShipment->is($shipment)
            )
            ->assertSee(
                'DUNA-CUSTOMER-DETAIL'
            )
            ->assertSee(
                'Detalle del envío'
            );
    }

    public function test_a_customer_cannot_view_another_customers_shipment(): void
    {
        $customer = Customer::factory()->create();

        $otherCustomer =
            Customer::factory()->create();

        $shipment = Shipment::factory()->create([
            'customer_id' => $otherCustomer->id,
        ]);

        $this->actingAs($customer->user)
            ->get(
                route(
                    'portal.shipments.show',
                    $shipment
                )
            )
            ->assertForbidden();
    }

    public function test_a_provider_can_only_view_linked_shipments(): void
    {
        $provider =
            DeliveryProvider::factory()->create();

        $otherProvider =
            DeliveryProvider::factory()->create();

        $shipment = Shipment::factory()->create([
            'tracking_code' =>
                'DUNA-PROVIDER-DETAIL',
        ]);

        $this->linkProviderToShipment(
            $provider,
            $shipment
        );

        $this->actingAs($provider->user)
            ->get(
                route(
                    'portal.shipments.show',
                    $shipment
                )
            )
            ->assertOk()
            ->assertSee(
                'DUNA-PROVIDER-DETAIL'
            );

        $this->actingAs($otherProvider->user)
            ->get(
                route(
                    'portal.shipments.show',
                    $shipment
                )
            )
            ->assertForbidden();
    }

    public function test_a_courier_can_only_view_assigned_shipments(): void
    {
        $courier = Courier::factory()->create();

        $otherCourier =
            Courier::factory()->create();

        $shipment = Shipment::factory()->create([
            'tracking_code' =>
                'DUNA-COURIER-DETAIL',
        ]);

        $this->assignCourierToShipment(
            $courier,
            $shipment
        );

        $this->actingAs($courier->user)
            ->get(
                route(
                    'portal.shipments.show',
                    $shipment
                )
            )
            ->assertOk()
            ->assertSee(
                'DUNA-COURIER-DETAIL'
            );

        $this->actingAs($otherCourier->user)
            ->get(
                route(
                    'portal.shipments.show',
                    $shipment
                )
            )
            ->assertForbidden();
    }

    public function test_support_and_administration_can_view_any_shipment(): void
    {
        $shipment = Shipment::factory()->create([
            'tracking_code' =>
                'DUNA-OPERATION-DETAIL',
        ]);

        $users = [
            $this->userWithRole(
                'SUPPORT_AGENT'
            ),
            $this->userWithRole(
                'ADMINISTRATOR'
            ),
        ];

        foreach ($users as $user) {
            $this->actingAs($user)
                ->get(
                    route(
                        'portal.shipments.show',
                        $shipment
                    )
                )
                ->assertOk()
                ->assertSee(
                    'DUNA-OPERATION-DETAIL'
                );
        }
    }

    public function test_the_page_displays_the_shipment_information(): void
    {
        $customer = Customer::factory()->create();

        $shipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
            'tracking_code' => 'DUNA-FULL-DETAIL',
            'declared_value' => 1250.50,
            'delivery_instructions' =>
                'Llamar antes de entregar.',
            'notes' =>
                'El paquete requiere cuidado.',
        ]);

        $shipment->sender->update([
            'first_name' => 'Ana',
            'last_name' => 'Mendoza',
            'phone' => '88881111',
            'email' => 'ana@example.com',
        ]);

        $shipment->recipient->update([
            'first_name' => 'Carlos',
            'last_name' => 'López',
            'phone' => '88882222',
            'email' => 'carlos@example.com',
        ]);

        $shipment->originAddress->update([
            'address_line' =>
                'Avenida Central 100',
            'reference_note' =>
                'Frente al parque',
        ]);

        $shipment->destinationAddress->update([
            'address_line' =>
                'Barrio Nuevo 250',
            'reference_note' =>
                'Casa de portón azul',
        ]);

        $package = $shipment->packages()
            ->firstOrFail();

        $package->update([
            'content_description' =>
                'Documentos importantes',
            'weight' => 2.50,
            'is_fragile' => true,
            'declared_value' => 1250.50,
        ]);

        $this->actingAs($customer->user)
            ->get(
                route(
                    'portal.shipments.show',
                    $shipment
                )
            )
            ->assertOk()
            ->assertSee('DUNA-FULL-DETAIL')
            ->assertSee('Ana')
            ->assertSee('Mendoza')
            ->assertSee('Carlos')
            ->assertSee('López')
            ->assertSee('Avenida Central 100')
            ->assertSee('Barrio Nuevo 250')
            ->assertSee(
                'Documentos importantes'
            )
            ->assertSee('Frágil')
            ->assertSee(
                'Llamar antes de entregar.'
            )
            ->assertSee(
                'El paquete requiere cuidado.'
            )
            ->assertSee(
                'No hay cambios de estado registrados.'
            )
            ->assertSee(
                'Todavía no existe un comprobante'
            );
    }

    private function linkProviderToShipment(
        DeliveryProvider $provider,
        Shipment $shipment
    ): void {
        $trip = Trip::factory()->create([
            'delivery_provider_id' =>
                $provider->id,
            'status' => 'USED',
            'used_at' => now(),
        ]);

        DeliveryService::factory()->create([
            'shipment_id' => $shipment->id,
            'trip_id' => $trip->id,
            'trip_type_id' =>
                $trip->trip_type_id,
            'status' => 'ASSIGNED',
            'accepted_at' => now(),
        ]);
    }

    private function assignCourierToShipment(
        Courier $courier,
        Shipment $shipment
    ): void {
        $plannedStatus = RouteStatus::query()
            ->where(
                'status_name',
                'PLANNED'
            )
            ->firstOrFail();

        $route = Route::query()->create([
            'courier_id' => $courier->id,
            'route_status_id' =>
                $plannedStatus->id,
            'route_date' => today(),
            'started_at' => null,
            'finished_at' => null,
            'estimated_distance_km' => null,
        ]);

        RouteShipment::query()->create([
            'route_id' => $route->id,
            'shipment_id' => $shipment->id,
            'delivery_order' => 1,
            'delivery_status' => 'PENDING',
        ]);
    }

    private function userWithRole(
        string $roleName
    ): User {
        $role = Role::query()
            ->where(
                'role_name',
                $roleName
            )
            ->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
        ]);
    }
}