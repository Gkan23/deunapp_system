<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Services\Support\MarkSupportMessagesAsReadService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class SupportTicketMessageReadController extends Controller
{
    public function __invoke(
        Request $request,
        SupportTicket $supportTicket,
        MarkSupportMessagesAsReadService $service
    ): JsonResponse {
        Gate::authorize(
            'readMessages',
            $supportTicket
        );

        try {
            $readCount = $service->execute(
                ticket: $supportTicket,
                reader: $request->user()
            );
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'message' => 'Support messages marked as read successfully.',
            'data' => [
                'read_count' => $readCount,
            ],
        ]);
    }
}