<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmPaymentRequest;
use App\Http\Requests\RefundPaymentRequest;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payment\ConfirmPaymentService;
use App\Services\Payment\RefundPaymentService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends Controller
{
    /**
     * Relaciones necesarias para presentar un pago.
     *
     * @var array<int, string>
     */
    private const RELATIONS = [
        'paymentMethod',
        'paymentStatus',
        'deliveryService.customer',
        'deliveryService.trip.deliveryProvider',
    ];

    /**
     * Mostrar los pagos visibles para el usuario autenticado.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::forUser($request->user())->authorize(
            'viewAny',
            Payment::class
        );

        $payments = $this->visiblePaymentsFor(
            $request->user()
        )
            ->with(self::RELATIONS)
            ->latest('id')
            ->get();

        return response()->json([
            'data' => $payments,
        ]);
    }

    /**
     * Mostrar un pago específico.
     */
    public function show(
        Request $request,
        Payment $payment
    ): JsonResponse {
        Gate::forUser($request->user())->authorize(
            'view',
            $payment
        );

        $payment->load(self::RELATIONS);

        return response()->json([
            'payment' => $payment,
        ]);
    }

    /**
     * Confirmar un pago pendiente.
     */
    public function confirm(
        ConfirmPaymentRequest $request,
        Payment $payment,
        ConfirmPaymentService $service
    ): JsonResponse {
        try {
            $confirmedPayment = $service->execute(
                $payment,
                $request->user(),
                $request->validated('payment_reference')
            );

            return response()->json([
                'message' => 'Payment confirmed successfully.',
                'payment' => $confirmedPayment,
            ]);
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Reembolsar un pago confirmado.
     */
    public function refund(
        RefundPaymentRequest $request,
        Payment $payment,
        RefundPaymentService $service
    ): JsonResponse {
        try {
            $refundedPayment = $service->execute(
                $payment,
                $request->user(),
                $request->validated('reason'),
                $request->validated('refund_reference')
            );

            return response()->json([
                'message' => 'Payment refunded successfully.',
                'payment' => $refundedPayment,
            ]);
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * Construir la consulta según el rol del usuario.
     */
    private function visiblePaymentsFor(User $user): Builder
    {
        $query = Payment::query();

        if ($this->hasAnyRole($user, [
            'SUPPORT_AGENT',
            'ADMINISTRATOR',
        ])) {
            return $query;
        }

        if ($this->hasRole($user, 'CUSTOMER')) {
            return $query->whereHas(
                'deliveryService.customer',
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