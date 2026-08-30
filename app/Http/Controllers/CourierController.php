<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateCourierRequest;
use App\Http\Requests\ListCouriersRequest;
use App\Models\Courier;
use App\Services\Courier\CreateCourierService;
use DomainException;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;

class CourierController extends Controller
{
    /**
     * Lista los repartidores autorizados.
     */
    public function index(
        ListCouriersRequest $request
    ): JsonResponse {
        $validated = $request->validated();

        $authenticatedUser = $request
            ->user()
            ->loadMissing([
                'role',
                'deliveryProvider',
            ]);

        $query = Courier::query()
            ->with($this->relations());

        if (
            $authenticatedUser
                ->role
                ?->role_name
            === 'DELIVERY_PROVIDER'
        ) {
            $query->where(
                'delivery_provider_id',
                $authenticatedUser
                    ->deliveryProvider
                    ->id
            );
        }

        if (
            isset($validated['search'])
            && $validated['search'] !== ''
        ) {
            $search = $validated['search'];

            $query->where(function (
                $query
            ) use ($search): void {
                $query
                    ->where(
                        'license_number',
                        'like',
                        '%'.$search.'%'
                    )
                    ->orWhereHas(
                        'user',
                        function (
                            $query
                        ) use ($search): void {
                            $query
                                ->where(
                                    'name',
                                    'like',
                                    '%'.$search.'%'
                                )
                                ->orWhere(
                                    'email',
                                    'like',
                                    '%'.$search.'%'
                                );
                        }
                    );
            });
        }

        if (
            array_key_exists(
                'is_active',
                $validated
            )
        ) {
            $query->where(
                'is_active',
                (bool) $validated['is_active']
            );
        }

        if (
            array_key_exists(
                'is_available',
                $validated
            )
        ) {
            $query->where(
                'is_available',
                (bool) $validated[
                    'is_available'
                ]
            );
        }

        $couriers = $query
            ->orderBy('id')
            ->paginate(
                (int) (
                    $validated['per_page']
                    ?? 15
                )
            );

        return response()->json([
            'data' => collect(
                $couriers->items()
            )
                ->map(
                    fn (Courier $courier): array =>
                        $this->courierData(
                            $courier
                        )
                )
                ->values()
                ->all(),
            'meta' => [
                'current_page' =>
                    $couriers->currentPage(),
                'last_page' =>
                    $couriers->lastPage(),
                'per_page' =>
                    $couriers->perPage(),
                'total' =>
                    $couriers->total(),
            ],
        ]);
    }

    /**
     * Crea un repartidor para el proveedor.
     */
    public function store(
        CreateCourierRequest $request,
        CreateCourierService $service
    ): JsonResponse {
        $validated = $request->validated();

        try {
            $courierUser = $service->execute(
                performedBy: $request->user(),
                name: $validated['name'],
                email: $validated['email'],
                licenseNumber:
                    $validated['license_number']
                    ?? null,
                comment: $validated['comment']
            );
        } catch (DomainException $exception) {
            return response()->json([
                'message' =>
                    $exception->getMessage(),
            ], 422);
        }

        event(new Registered($courierUser));

        $passwordResetStatus = Password::broker()
            ->sendResetLink([
                'email' => $courierUser->email,
            ]);

        $courier = $courierUser->courier;

        return response()->json([
            'message' =>
                'Courier created successfully.',
            'data' => [
                'user' => [
                    'id' => $courierUser->id,
                    'name' => $courierUser->name,
                    'email' =>
                        $courierUser->email,
                    'email_verified_at' =>
                        $courierUser
                            ->email_verified_at,
                    'email_verified' =>
                        $courierUser
                            ->hasVerifiedEmail(),
                    'role' =>
                        $courierUser
                            ->role
                            ->role_name,
                    'account_status' =>
                        $courierUser
                            ->accountStatus
                            ->status_name,
                ],
                'courier' => [
                    'id' => $courier->id,
                    'delivery_provider_id' =>
                        $courier
                            ->delivery_provider_id,
                    'license_number' =>
                        $courier->license_number,
                    'is_available' =>
                        $courier->is_available,
                    'is_active' =>
                        $courier->is_active,
                ],
            ],
            'invitation' => [
                'verification_email_sent' => true,
                'password_setup_email_sent' =>
                    $passwordResetStatus
                    === Password::RESET_LINK_SENT,
            ],
        ], 201);
    }

    /**
     * Muestra un repartidor específico.
     */
    public function show(
        Request $request,
        Courier $courier
    ): JsonResponse {
        Gate::forUser($request->user())
            ->authorize('view', $courier);

        $courier->load(
            $this->relations()
        );

        return response()->json([
            'data' => $this->courierData(
                $courier
            ),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function relations(): array
    {
        return [
            'user.role',
            'user.accountStatus',
            'deliveryProvider.providerType',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function courierData(
        Courier $courier
    ): array {
        return [
            'id' => $courier->id,
            'license_number' =>
                $courier->license_number,
            'is_available' =>
                (bool) $courier->is_available,
            'is_active' =>
                (bool) $courier->is_active,
            'user' => [
                'id' => $courier->user->id,
                'name' => $courier->user->name,
                'email' => $courier->user->email,
                'email_verified_at' =>
                    $courier
                        ->user
                        ->email_verified_at,
                'email_verified' =>
                    $courier
                        ->user
                        ->hasVerifiedEmail(),
                'role' =>
                    $courier
                        ->user
                        ->role
                        ?->role_name,
                'account_status' =>
                    $courier
                        ->user
                        ->accountStatus
                        ?->status_name,
            ],
            'delivery_provider' => [
                'id' =>
                    $courier
                        ->deliveryProvider
                        ->id,
                'business_name' =>
                    $courier
                        ->deliveryProvider
                        ->business_name,
                'provider_type' =>
                    $courier
                        ->deliveryProvider
                        ->providerType
                        ?->type_name,
                'is_active' =>
                    (bool) $courier
                        ->deliveryProvider
                        ->is_active,
            ],
            'created_at' =>
                $courier->created_at,
            'updated_at' =>
                $courier->updated_at,
        ];
    }
}