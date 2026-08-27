<?php

namespace Tests\Feature\Http\Requests;

use App\Http\Requests\CancelShipmentRequest;
use App\Http\Requests\UpdateShipmentStatusRequest;
use App\Models\AccountStatus;
use App\Models\Courier;
use App\Models\Customer;
use App\Models\DeliveryProvider;
use App\Models\DeliveryService;
use App\Models\Role;
use App\Models\Route;
use App\Models\RouteShipment;
use App\Models\RouteStatus;
use App\Models\Shipment;
use App\Models\ShipmentStatus;
use App\Models\Trip;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ShipmentActionRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_related_operational_users_can_submit_status_updates(): void
    {
        $provider = DeliveryProvider::factory()->create();
        $providerShipment = Shipment::factory()->create();

        $this->linkProviderToShipment(
            $provider,
            $providerShipment
        );

        $providerRequest = $this->requestFor(
            new UpdateShipmentStatusRequest(),
            $provider->user,
            $providerShipment
        );

        $this->assertTrue(
            $providerRequest->authorize()
        );

        $courier = Courier::factory()->create();
        $courierShipment = Shipment::factory()->create();

        $this->assignCourierToShipment(
            $courier,
            $courierShipment
        );

        $courierRequest = $this->requestFor(
            new UpdateShipmentStatusRequest(),
            $courier->user,
            $courierShipment
        );

        $this->assertTrue(
            $courierRequest->authorize()
        );

        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $administratorRequest = $this->requestFor(
            new UpdateShipmentStatusRequest(),
            $administrator,
            Shipment::factory()->create()
        );

        $this->assertTrue(
            $administratorRequest->authorize()
        );
    }

    public function test_unrelated_users_cannot_submit_status_updates(): void
    {
        $shipment = Shipment::factory()->create();

        $customer = $shipment->customer->user;

        $supportAgent = $this->userWithRole(
            'SUPPORT_AGENT'
        );

        $unrelatedProvider =
            DeliveryProvider::factory()->create();

        $unassignedCourier =
            Courier::factory()->create();

        $users = [
            $customer,
            $supportAgent,
            $unrelatedProvider->user,
            $unassignedCourier->user,
        ];

        foreach ($users as $user) {
            $request = $this->requestFor(
                new UpdateShipmentStatusRequest(),
                $user,
                $shipment
            );

            $this->assertFalse(
                $request->authorize()
            );
        }
    }

    public function test_only_the_owner_or_administrator_can_cancel(): void
    {
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();

        $shipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $ownerRequest = $this->requestFor(
            new CancelShipmentRequest(),
            $customer->user,
            $shipment
        );

        $this->assertTrue(
            $ownerRequest->authorize()
        );

        $otherCustomerRequest = $this->requestFor(
            new CancelShipmentRequest(),
            $otherCustomer->user,
            $shipment
        );

        $this->assertFalse(
            $otherCustomerRequest->authorize()
        );

        $provider = DeliveryProvider::factory()->create();

        $providerRequest = $this->requestFor(
            new CancelShipmentRequest(),
            $provider->user,
            $shipment
        );

        $this->assertFalse(
            $providerRequest->authorize()
        );

        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $administratorRequest = $this->requestFor(
            new CancelShipmentRequest(),
            $administrator,
            $shipment
        );

        $this->assertTrue(
            $administratorRequest->authorize()
        );
    }

    public function test_an_inactive_user_cannot_submit_shipment_actions(): void
    {
        $customer = Customer::factory()->create();

        $shipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $suspendedStatus = AccountStatus::query()
            ->where('status_name', 'SUSPENDED')
            ->firstOrFail();

        $customer->user->update([
            'account_status_id' => $suspendedStatus->id,
        ]);

        $cancelRequest = $this->requestFor(
            new CancelShipmentRequest(),
            $customer->user->fresh(),
            $shipment
        );

        $this->assertFalse(
            $cancelRequest->authorize()
        );

        $administrator = $this->userWithRole(
            'ADMINISTRATOR'
        );

        $administrator->update([
            'account_status_id' => $suspendedStatus->id,
        ]);

        $statusRequest = $this->requestFor(
            new UpdateShipmentStatusRequest(),
            $administrator->fresh(),
            $shipment
        );

        $this->assertFalse(
            $statusRequest->authorize()
        );
    }

    public function test_status_update_data_is_validated(): void
    {
        $pickedUpStatus = ShipmentStatus::query()
            ->where('status_name', 'PICKED_UP')
            ->firstOrFail();

        $validValidator = $this->validator(
            new UpdateShipmentStatusRequest(),
            [
                'shipment_status_id' =>
                    $pickedUpStatus->id,

                'comment' =>
                    'Package collected successfully.',
            ]
        );

        $this->assertFalse(
            $validValidator->fails(),
            implode(
                ', ',
                $validValidator->errors()->all()
            )
        );

        $missingValidator = $this->validator(
            new UpdateShipmentStatusRequest(),
            []
        );

        $this->assertTrue(
            $missingValidator->fails()
        );

        $this->assertArrayHasKey(
            'shipment_status_id',
            $missingValidator->errors()->toArray()
        );

        $invalidValidator = $this->validator(
            new UpdateShipmentStatusRequest(),
            [
                'shipment_status_id' => 999999,
            ]
        );

        $this->assertTrue(
            $invalidValidator->fails()
        );

        $this->assertArrayHasKey(
            'shipment_status_id',
            $invalidValidator->errors()->toArray()
        );
    }

    public function test_action_comments_cannot_exceed_the_limit(): void
    {
        $pickedUpStatus = ShipmentStatus::query()
            ->where('status_name', 'PICKED_UP')
            ->firstOrFail();

        $longComment = str_repeat('a', 1001);

        $updateValidator = $this->validator(
            new UpdateShipmentStatusRequest(),
            [
                'shipment_status_id' =>
                    $pickedUpStatus->id,

                'comment' => $longComment,
            ]
        );

        $this->assertTrue(
            $updateValidator->fails()
        );

        $this->assertArrayHasKey(
            'comment',
            $updateValidator->errors()->toArray()
        );

        $cancelValidator = $this->validator(
            new CancelShipmentRequest(),
            [
                'comment' => $longComment,
            ]
        );

        $this->assertTrue(
            $cancelValidator->fails()
        );

        $this->assertArrayHasKey(
            'comment',
            $cancelValidator->errors()->toArray()
        );
    }

    private function requestFor(
        FormRequest $request,
        User $user,
        Shipment $shipment
    ): FormRequest {
        $route = new RoutingRoute(
            ['PATCH'],
            '/shipments/{shipment}',
            []
        );

        $httpRequest = HttpRequest::create(
            "/shipments/{$shipment->id}",
            'PATCH'
        );

        /*
         * Inicializa los parámetros internos de la ruta.
         */
        $route->bind($httpRequest);

        /*
         * Simula el Route Model Binding reemplazando
         * el ID por la instancia de Shipment.
         */
        $route->setParameter(
            'shipment',
            $shipment
        );

        /*
         * Simula al usuario autenticado.
         */
        $request->setUserResolver(
            fn () => $user
        );

        /*
         * Permite usar $request->route('shipment').
         */
        $request->setRouteResolver(
            fn () => $route
        );

        return $request;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function validator(
        FormRequest $request,
        array $data
    ): ValidatorContract {
        return Validator::make(
            $data,
            $request->rules(),
            $request->messages(),
            $request->attributes()
        );
    }

    private function linkProviderToShipment(
        DeliveryProvider $provider,
        Shipment $shipment
    ): void {
        $trip = Trip::factory()->create([
            'delivery_provider_id' => $provider->id,
            'status' => 'USED',
            'used_at' => now(),
        ]);

        DeliveryService::factory()->create([
            'shipment_id' => $shipment->id,
            'trip_id' => $trip->id,
            'trip_type_id' => $trip->trip_type_id,
            'status' => 'ASSIGNED',
            'accepted_at' => now(),
        ]);
    }

    private function assignCourierToShipment(
        Courier $courier,
        Shipment $shipment
    ): void {
        $plannedStatus = RouteStatus::query()
            ->where('status_name', 'PLANNED')
            ->firstOrFail();

        $route = Route::query()->create([
            'courier_id' => $courier->id,
            'route_status_id' => $plannedStatus->id,
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
            ->where('role_name', $roleName)
            ->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
        ]);
    }
}