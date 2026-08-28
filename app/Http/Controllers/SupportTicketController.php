<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignSupportTicketRequest;
use App\Http\Requests\StoreSupportMessageRequest;
use App\Http\Requests\StoreSupportTicketRequest;
use App\Http\Requests\UpdateSupportTicketStatusRequest;
use App\Models\Shipment;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Support\AddSupportMessageService;
use App\Services\Support\AssignSupportTicketService;
use App\Services\Support\CreateSupportTicketService;
use App\Services\Support\UpdateSupportTicketStatusService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class SupportTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize(
            'viewAny',
            SupportTicket::class
        );

        $user = $request->user();

        $roleName = $user->role()
            ->value('role_name');

        $query = SupportTicket::query()
            ->with([
                'customer.user',
                'shipment',
                'category',
                'status',
                'priority',
                'assignedTo.role',
            ])
            ->orderByDesc('id');

        if ($roleName === 'CUSTOMER') {
            $query->whereHas(
                'customer',
                fn ($customerQuery) => $customerQuery
                    ->where('user_id', $user->id)
            );
        }

        if (! in_array(
            $roleName,
            [
                'CUSTOMER',
                'SUPPORT_AGENT',
                'ADMINISTRATOR',
            ],
            true
        )) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    public function store(
        StoreSupportTicketRequest $request,
        CreateSupportTicketService $service
    ): JsonResponse {
        $customer = $request->user()
            ->customer()
            ->firstOrFail();

        $shipment = null;

        if ($request->validated('shipment_id') !== null) {
            $shipment = Shipment::query()->findOrFail(
                $request->integer('shipment_id')
            );
        }

        try {
            $ticket = $service->execute(
                customer: $customer,
                categoryName: $request->string(
                    'category'
                )->toString(),
                subject: $request->string(
                    'subject'
                )->toString(),
                message: $request->string(
                    'message'
                )->toString(),
                shipment: $shipment,
                attachmentUrl: $request->validated(
                    'attachment_url'
                )
            );
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'message' => 'Support ticket created successfully.',
            'data' => $ticket,
        ], Response::HTTP_CREATED);
    }

    public function show(
        SupportTicket $supportTicket
    ): JsonResponse {
        Gate::authorize(
            'view',
            $supportTicket
        );

        $supportTicket->load([
            'customer.user',
            'shipment',
            'category',
            'status',
            'priority',
            'assignedTo.role',
            'messages.user',
        ]);

        return response()->json([
            'data' => $supportTicket,
        ]);
    }

    public function assign(
        AssignSupportTicketRequest $request,
        SupportTicket $supportTicket,
        AssignSupportTicketService $service
    ): JsonResponse {
        $assignee = User::query()->findOrFail(
            $request->integer('assigned_to_user_id')
        );

        try {
            $ticket = $service->execute(
                ticket: $supportTicket,
                assignee: $assignee,
                performedBy: $request->user()
            );
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'message' => 'Support ticket assigned successfully.',
            'data' => $ticket,
        ]);
    }

    public function addMessage(
        StoreSupportMessageRequest $request,
        SupportTicket $supportTicket,
        AddSupportMessageService $service
    ): JsonResponse {
        try {
            $supportMessage = $service->execute(
                ticket: $supportTicket,
                sender: $request->user(),
                message: $request->string(
                    'message'
                )->toString(),
                attachmentUrl: $request->validated(
                    'attachment_url'
                )
            );
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'message' => 'Support message sent successfully.',
            'data' => $supportMessage,
        ], Response::HTTP_CREATED);
    }

    public function updateStatus(
        UpdateSupportTicketStatusRequest $request,
        SupportTicket $supportTicket,
        UpdateSupportTicketStatusService $service
    ): JsonResponse {
        try {
            $ticket = $service->execute(
                ticket: $supportTicket,
                targetStatusName: $request->string(
                    'status'
                )->toString(),
                performedBy: $request->user(),
                comment: $request->validated('comment')
            );
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'message' => 'Support ticket status updated successfully.',
            'data' => $ticket,
        ]);
    }
}