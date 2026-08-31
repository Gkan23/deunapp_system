<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListVehiclesRequest;
use App\Http\Requests\StoreVehicleRequest;
use App\Http\Requests\UpdateVehicleStatusRequest;
use App\Models\Courier;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Vehicle\CreateVehicleService;
use App\Services\Vehicle\UpdateVehicleStatusService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class VehicleController extends Controller
{
    /**
     * Relaciones utilizadas para presentar vehículos.
     *
     * @var array<int, string>
     */
    private const RELATIONS = [
        'courier.user',
        'courier.deliveryProvider',
        'vehicleType',
        'vehicleStatus',
    ];

    /**
     * Lista los vehículos autorizados.
     */
    public function index(
        ListVehiclesRequest $request
    ): JsonResponse {
        $validated = $request->validated();

        /** @var User $user */
        $user = $request->user();

        $user->loadMissing([
            'role',
            'deliveryProvider',
            'courier',
        ]);

        $query = Vehicle::query()
            ->with(self::RELATIONS);

        $this->applyUserScope(
            $query,
            $user
        );

        if (
            isset($validated['search'])
            && $validated['search'] !== ''
        ) {
            $search = $validated['search'];

            $query->where(function (
                Builder $query
            ) use ($search): void {
                $query
                    ->where(
                        'plate_number',
                        'like',
                        '%'.$search.'%'
                    )
                    ->orWhere(
                        'brand',
                        'like',
                        '%'.$search.'%'
                    )
                    ->orWhere(
                        'model',
                        'like',
                        '%'.$search.'%'
                    )
                    ->orWhereHas(
                        'courier.user',
                        fn (
                            Builder $userQuery
                        ): Builder =>
                            $userQuery->where(
                                'name',
                                'like',
                                '%'.$search.'%'
                            )
                    );
            });
        }

        if (isset(
            $validated['vehicle_type']
        )) {
            $query->whereHas(
                'vehicleType',
                fn (
                    Builder $typeQuery
                ): Builder =>
                    $typeQuery->where(
                        'type_name',
                        $validated[
                            'vehicle_type'
                        ]
                    )
            );
        }

        if (isset(
            $validated['vehicle_status']
        )) {
            $query->whereHas(
                'vehicleStatus',
                fn (
                    Builder $statusQuery
                ): Builder =>
                    $statusQuery->where(
                        'status_name',
                        $validated[
                            'vehicle_status'
                        ]
                    )
            );
        }

        if (isset(
            $validated['courier_id']
        )) {
            $query->where(
                'courier_id',
                $validated['courier_id']
            );
        }

        $vehicles = $query
            ->orderBy('id')
            ->paginate(
                (int) (
                    $validated['per_page']
                    ?? 15
                )
            );

        return response()->json([
            'data' => collect(
                $vehicles->items()
            )
                ->map(
                    fn (Vehicle $vehicle): array =>
                        $this->vehicleData(
                            $vehicle
                        )
                )
                ->values()
                ->all(),
            'meta' => [
                'current_page' =>
                    $vehicles->currentPage(),
                'last_page' =>
                    $vehicles->lastPage(),
                'per_page' =>
                    $vehicles->perPage(),
                'total' =>
                    $vehicles->total(),
            ],
        ]);
    }

    /**
     * Registra un vehículo para un repartidor.
     */
    public function store(
        StoreVehicleRequest $request,
        CreateVehicleService $service
    ): JsonResponse {
        $validated = $request->validated();

        $courier = Courier::query()
            ->findOrFail(
                $validated['courier_id']
            );

        try {
            $vehicle = $service->execute(
                performedBy: $request->user(),
                courier: $courier,
                vehicleTypeName:
                    $validated['vehicle_type'],
                plateNumber:
                    $validated['plate_number'],
                brand:
                    $validated['brand'] ?? null,
                model:
                    $validated['model'] ?? null,
                color:
                    $validated['color'] ?? null
            );
        } catch (DomainException $exception) {
            return response()->json([
                'message' =>
                    $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'message' =>
                'Vehicle created successfully.',
            'data' =>
                $this->vehicleData(
                    $vehicle
                ),
        ], Response::HTTP_CREATED);
    }

    /**
     * Cambia el estado de un vehículo.
     */
    public function updateStatus(
        UpdateVehicleStatusRequest $request,
        Vehicle $vehicle,
        UpdateVehicleStatusService $service
    ): JsonResponse {
        $validated = $request->validated();

        try {
            $updatedVehicle = $service->execute(
                vehicle: $vehicle,
                performedBy: $request->user(),
                targetStatusName:
                    $validated['status'],
                comment:
                    $validated['comment']
            );
        } catch (DomainException $exception) {
            return response()->json([
                'message' =>
                    $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'message' =>
                'Vehicle status updated successfully.',
            'data' =>
                $this->vehicleData(
                    $updatedVehicle
                ),
        ]);
    }

    /**
     * Muestra un vehículo autorizado.
     */
    public function show(
        Request $request,
        Vehicle $vehicle
    ): JsonResponse {
        Gate::forUser(
            $request->user()
        )->authorize(
            'view',
            $vehicle
        );

        $vehicle->load(self::RELATIONS);

        return response()->json([
            'data' =>
                $this->vehicleData(
                    $vehicle
                ),
        ]);
    }

    /**
     * Limita la consulta según el rol del usuario.
     */
    private function applyUserScope(
        Builder $query,
        User $user
    ): void {
        $roleName = $user
            ->role
            ?->role_name;

        if ($roleName === 'DELIVERY_PROVIDER') {
            $query->whereHas(
                'courier',
                fn (
                    Builder $courierQuery
                ): Builder =>
                    $courierQuery->where(
                        'delivery_provider_id',
                        $user
                            ->deliveryProvider
                            ->id
                    )
            );

            return;
        }

        if ($roleName === 'COURIER') {
            $query->where(
                'courier_id',
                $user->courier->id
            );

            return;
        }

        if (! in_array(
            $roleName,
            [
                'SUPPORT_AGENT',
                'ADMINISTRATOR',
            ],
            true
        )) {
            $query->whereRaw('1 = 0');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function vehicleData(
        Vehicle $vehicle
    ): array {
        return [
            'id' => $vehicle->id,
            'plate_number' =>
                $vehicle->plate_number,
            'brand' => $vehicle->brand,
            'model' => $vehicle->model,
            'color' => $vehicle->color,
            'vehicle_type' =>
                $vehicle
                    ->vehicleType
                    ->type_name,
            'vehicle_status' =>
                $vehicle
                    ->vehicleStatus
                    ->status_name,
            'courier' => [
                'id' =>
                    $vehicle->courier->id,
                'user_id' =>
                    $vehicle
                        ->courier
                        ->user_id,
                'name' =>
                    $vehicle
                        ->courier
                        ->user
                        ->name,
                'delivery_provider_id' =>
                    $vehicle
                        ->courier
                        ->delivery_provider_id,
            ],
        ];
    }
}