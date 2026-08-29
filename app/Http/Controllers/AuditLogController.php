<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexAuditLogRequest;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AuditLogController extends Controller
{
    public function index(
        IndexAuditLogRequest $request
    ): JsonResponse {
        $query = AuditLog::query()
            ->with([
                'performedBy.role',
            ])
            ->orderByDesc('performed_at')
            ->orderByDesc('id');

        if ($request->filled('table_name')) {
            $query->where(
                'table_name',
                $request->validated('table_name')
            );
        }

        if ($request->filled('action_type')) {
            $query->where(
                'action_type',
                $request->validated('action_type')
            );
        }

        if (
            $request->filled(
                'performed_by_user_id'
            )
        ) {
            $query->where(
                'performed_by_user_id',
                $request->integer(
                    'performed_by_user_id'
                )
            );
        }

        if ($request->filled('record_id')) {
            $query->where(
                'record_id',
                $request->integer('record_id')
            );
        }

        if ($request->filled('date_from')) {
            $query->whereDate(
                'performed_at',
                '>=',
                $request->validated('date_from')
            );
        }

        if ($request->filled('date_to')) {
            $query->whereDate(
                'performed_at',
                '<=',
                $request->validated('date_to')
            );
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    public function show(
        AuditLog $auditLog
    ): JsonResponse {
        Gate::authorize(
            'view',
            $auditLog
        );

        $auditLog->load([
            'performedBy.role',
        ]);

        return response()->json([
            'data' => $auditLog,
        ]);
    }
}