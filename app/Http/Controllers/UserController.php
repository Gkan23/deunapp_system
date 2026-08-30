<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListUsersRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    /**
     * Lista las cuentas autorizadas.
     */
    public function index(
        ListUsersRequest $request
    ): JsonResponse {
        $validated = $request->validated();

        $users = User::query()
            ->with($this->profileRelations())
            ->when(
                $validated['search'] ?? null,
                function (
                    $query,
                    string $search
                ): void {
                    $query->where(function (
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
                    });
                }
            )
            ->when(
                $validated['role'] ?? null,
                fn ($query, string $role) =>
                    $query->whereHas(
                        'role',
                        fn ($query) =>
                            $query->where(
                                'role_name',
                                $role
                            )
                    )
            )
            ->when(
                $validated['account_status'] ?? null,
                fn ($query, string $status) =>
                    $query->whereHas(
                        'accountStatus',
                        fn ($query) =>
                            $query->where(
                                'status_name',
                                $status
                            )
                    )
            )
            ->orderBy('id')
            ->paginate(
                (int) (
                    $validated['per_page']
                    ?? 15
                )
            );

        return response()->json([
            'data' => collect($users->items())
                ->map(
                    fn (User $user): array =>
                        $this->userData($user)
                )
                ->values()
                ->all(),
            'meta' => [
                'current_page' =>
                    $users->currentPage(),
                'last_page' =>
                    $users->lastPage(),
                'per_page' =>
                    $users->perPage(),
                'total' =>
                    $users->total(),
            ],
        ]);
    }

    /**
     * Muestra una cuenta específica.
     */
    public function show(
        Request $request,
        User $user
    ): JsonResponse {
        Gate::forUser($request->user())
            ->authorize('view', $user);

        $user->load(
            $this->profileRelations()
        );

        return response()->json([
            'data' => $this->userData($user),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function profileRelations(): array
    {
        return [
            'role',
            'accountStatus',
            'customer.customerType',
            'deliveryProvider.providerType',
            'courier.deliveryProvider.providerType',
        ];
    }

    /**
     * Convierte el usuario en una respuesta segura.
     *
     * @return array<string, mixed>
     */
    private function userData(User $user): array
    {
        $roleName = $user->role?->role_name;

        $profileType = match ($roleName) {
            'CUSTOMER' => 'CUSTOMER',
            'DELIVERY_PROVIDER' =>
                'DELIVERY_PROVIDER',
            'COURIER' => 'COURIER',
            default => null,
        };

        $profile = match ($roleName) {
            'CUSTOMER' => $user->customer,
            'DELIVERY_PROVIDER' =>
                $user->deliveryProvider,
            'COURIER' => $user->courier,
            default => null,
        };

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' =>
                $user->email_verified_at,
            'email_verified' =>
                $user->hasVerifiedEmail(),
            'role' => $user->role,
            'account_status' =>
                $user->accountStatus,
            'account_active' =>
                $user
                    ->accountStatus
                    ?->status_name === 'ACTIVE',
            'profile_type' => $profileType,
            'profile' => $profile,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }
}