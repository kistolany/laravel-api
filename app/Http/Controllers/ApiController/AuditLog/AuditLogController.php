<?php

namespace App\Http\Controllers\ApiController\AuditLog;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuditLog\AuditLogRequest;
use App\Services\AuditLog\AuditLogService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private AuditLogService $service)
    {
    }

    public function index(AuditLogRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->service->list($validated);

        return $this->success([
            'items' => $result['items'],
            'pagination' => $result['pagination'],
            'summary' => $result['summary'],
        ], 'Audit logs retrieved successfully.');
    }

    public function store(AuditLogRequest $request): JsonResponse
    {
        $data = $request->validated();

        $log = $this->service->create($data, $request->user(), $request->ip());

        return $this->success($log, 'Audit log created successfully.');
    }

    public function destroy(AuditLogRequest $request): JsonResponse
    {
        $data = $request->validated();

        $ids = array_values(array_unique(array_map('intval', $data['ids'] ?? [])));
        $clearAll = (bool) ($data['clear_all'] ?? false);

        $result = $this->service->clear($ids, $clearAll);

        return $this->success([
            'deleted' => $result['deleted'],
            'mode' => $result['mode'],
        ], 'Audit logs cleared successfully.');
    }
}

