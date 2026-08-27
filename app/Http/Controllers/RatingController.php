<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRatingRequest;
use App\Models\DeliveryService;
use App\Models\Rating;
use App\Models\User;
use App\Services\Rating\CreateRatingService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class RatingController extends Controller
{
    /**
     * Relaciones utilizadas en las respuestas JSON.
     *
     * @var array<int, string>
     */
    private const RELATIONS = [
        'customer',
        'deliveryService.shipment',
        'deliveryService.trip.deliveryProvider',
    ];

    /**
     * Mostrar las evaluaciones visibles para el usuario.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::forUser($request->user())->authorize(
            'viewAny',
            Rating::class
        );

        $ratings = $this->visibleRatingsFor(
            $request->user()
        )
            ->with(self::RELATIONS)
            ->latest('id')
            ->get();

        return response()->json([
            'data' => $ratings,
        ]);
    }

    /**
     * Mostrar una evaluación específica.
     */
    public function show(
        Request $request,
        Rating $rating
    ): JsonResponse {
        Gate::forUser($request->user())->authorize(
            'view',
            $rating
        );

        $rating->load(self::RELATIONS);

        return response()->json([
            'rating' => $rating,
        ]);
    }

    /**
     * Crear una evaluación para un servicio completado.
     */
    public function store(
        StoreRatingRequest $request,
        DeliveryService $deliveryService,
        CreateRatingService $service
    ): JsonResponse {
        $customer = $request->user()
            ->customer()
            ->firstOrFail();

        try {
            $rating = $service->execute(
                deliveryService: $deliveryService,
                customer: $customer,
                punctualityScore: (int) $request->validated(
                    'punctuality_score'
                ),
                customerServiceScore: (int) $request->validated(
                    'customer_service_score'
                ),
                packageConditionScore: (int) $request->validated(
                    'package_condition_score'
                ),
                comment: $request->validated('comment')
            );

            return response()->json([
                'message' => 'Rating created successfully.',
                'rating' => $rating,
            ], Response::HTTP_CREATED);
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Construir la consulta según el rol autenticado.
     */
    private function visibleRatingsFor(User $user): Builder
    {
        $query = Rating::query();

        if ($this->hasAnyRole($user, [
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ])) {
            return $query;
        }

        if ($this->hasRole($user, 'CUSTOMER')) {
            return $query->whereHas(
                'customer',
                fn (Builder $customerQuery): Builder =>
                    $customerQuery->where(
                        'user_id',
                        $user->id
                    )
            );
        }

        if ($this->hasRole($user, 'DELIVERY_PROVIDER')) {
            return $query->whereHas(
                'deliveryService.trip.deliveryProvider',
                fn (Builder $providerQuery): Builder =>
                    $providerQuery->where(
                        'user_id',
                        $user->id
                    )
            );
        }

        return $query->whereRaw('1 = 0');
    }

    private function hasRole(
        User $user,
        string $role
    ): bool {
        return $user->role()
            ->where('role_name', $role)
            ->exists();
    }

    /**
     * @param array<int, string> $roles
     */
    private function hasAnyRole(
        User $user,
        array $roles
    ): bool {
        return $user->role()
            ->whereIn('role_name', $roles)
            ->exists();
    }
}