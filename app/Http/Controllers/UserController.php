<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateStaffUserRequest;
use App\Http\Requests\ListUsersRequest;
use App\Http\Requests\UpdateUserAccountStatusRequest;
use App\Http\Requests\UpdateUserRoleRequest;
use App\Models\User;
use App\Services\User\CreateStaffUserService;
use App\Services\User\UpdateUserAccountStatusService;
use App\Services\User\UpdateUserRoleService;
use DomainException;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;

class UserController extends Controller
{
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
     * Crea una cuenta interna.
     */
    public function storeStaff(
        CreateStaffUserRequest $request,
        CreateStaffUserService $service
    ): JsonResponse {
        $validated = $request->validated();

        try {
            $staffUser = $service->execute(
                performedBy: $request->user(),
                name: $validated['name'],
                email: $validated['email'],
                roleName: $validated['role'],
                comment: $validated['comment']
            );
        } catch (DomainException $exception) {
            return response()->json([
                'message' =>
                    $exception->getMessage(),
            ], 422);
        }

        /*
         * Envía el correo de verificación.
         */
        event(new Registered($staffUser));

        /*
         * Envía un enlace para que el usuario
         * establezca su propia contraseña.
         */
        $passwordResetStatus = Password::broker()
            ->sendResetLink([
                'email' => $staffUser->email,
            ]);

        return response()->json([
            'message' =>
                'Staff user created successfully.',
            'data' => $this->userData(
                $staffUser
            ),
            'invitation' => [
                'verification_email_sent' => true,
                'password_setup_email_sent' =>
                    $passwordResetStatus
                    === Password::RESET_LINK_SENT,
            ],
        ], 201);
    }

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

    public function updateAccountStatus(
        UpdateUserAccountStatusRequest $request,
        User $user,
        UpdateUserAccountStatusService $service
    ): JsonResponse {
        $validated = $request->validated();

        try {
            $updatedUser = $service->execute(
                targetUser: $user,
                performedBy: $request->user(),
                targetStatusName:
                    $validated['status'],
                comment:
                    $validated['comment'] ?? null
            );
        } catch (DomainException $exception) {
            return response()->json([
                'message' =>
                    $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' =>
                'User account status updated successfully.',
            'data' => $this->userData(
                $updatedUser
            ),
        ]);
    }

    public function updateRole(
        UpdateUserRoleRequest $request,
        User $user,
        UpdateUserRoleService $service
    ): JsonResponse {
        $validated = $request->validated();

        try {
            $updatedUser = $service->execute(
                targetUser: $user,
                performedBy: $request->user(),
                targetRoleName:
                    $validated['role'],
                comment:
                    $validated['comment']
            );
        } catch (DomainException $exception) {
            return response()->json([
                'message' =>
                    $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' =>
                'User role updated successfully.',
            'data' => $this->userData(
                $updatedUser
            ),
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