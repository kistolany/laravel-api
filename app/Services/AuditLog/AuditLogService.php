<?php

namespace App\Services\AuditLog;

use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Models\AuditLog;
use Illuminate\Contracts\Auth\Authenticatable;
use App\Services\Concerns\ServiceTraceable;
class AuditLogService
{
    use ServiceTraceable;

    public function list(array $filters): array
    {
        return $this->trace(__FUNCTION__, function () use ($filters): array {
            $page = (int) ($filters['page'] ?? 1);
            $perPage = (int) ($filters['per_page'] ?? 20);
            $search = trim((string) ($filters['search'] ?? ''));

            $baseQuery = $this->buildBaseQuery($search);

            $creates = (clone $baseQuery)->whereRaw('LOWER(`action`) = ?', ['create'])->count();
            $updates = (clone $baseQuery)->whereRaw('LOWER(`action`) = ?', ['update'])->count();
            $deletes = (clone $baseQuery)->whereRaw('LOWER(`action`) = ?', ['delete'])->count();

            $paginator = (clone $baseQuery)
                ->latest('created_at')
                ->paginate($perPage, ['*'], 'page', $page);

            $items = collect($paginator->items())
                ->map(fn (AuditLog $log) => $this->transform($log))
                ->values();

            return [
                'items' => $items,
                'pagination' => [
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'total_pages' => $paginator->lastPage(),
                ],
                'summary' => [
                    'total' => $paginator->total(),
                    'creates' => $creates,
                    'updates' => $updates,
                    'deletes' => $deletes,
                ],
            ];
        });
    }

    public function create(array $data, ?Authenticatable $user, ?string $ip): array
    {
        return $this->trace(__FUNCTION__, function () use ($data, $user, $ip): array {
            $who = trim((string) ($user?->username ?? 'System'));
            if ($who === '') {
                $who = 'System';
            }
            
            $log = AuditLog::create([
                'user_id' => $user?->id,
                'who' => $who,
                'action' => (string) $data['action'],
                'module' => (string) $data['module'],
                'description' => (string) $data['description'],
                'ip' => $ip,
                'before' => array_key_exists('before', $data) ? $data['before'] : null,
                'after' => array_key_exists('after', $data) ? $data['after'] : null,
            ]);
            
            return $this->transform($log);
            
            
        });
    }

    public function clear(array $ids, bool $clearAll): array
    {
        return $this->trace(__FUNCTION__, function () use ($ids, $clearAll): array {
            if ($ids === [] && !$clearAll) {
                throw new ApiException(ResponseStatus::BAD_REQUEST, 'Provide ids or clear_all=true.');
            }
            
            $query = AuditLog::query();
            
            if ($ids !== []) {
                $query->whereIn('id', $ids);
            }
            
            $deleted = (int) $query->delete();
            
            return [
                'deleted' => $deleted,
                'mode' => $ids !== [] ? 'selected' : 'all',
            ];
            
            
        });
    }

    private function buildBaseQuery(string $search)
    {
        $query = AuditLog::query();

        if ($search === '') {
            return $query;
        }

        $needle = '%' . strtolower($search) . '%';

        $query->where(function ($q) use ($needle) {
            $q->whereRaw('LOWER(`who`) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(`action`) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(`module`) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(`description`) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(COALESCE(`ip`, "")) LIKE ?', [$needle]);
        });

        return $query;
    }

    private function transform(AuditLog $log): array
    {
        return [
            'id' => $log->id,
            'when' => $log->created_at?->format('M j, Y g:i A'),
            'who' => $log->who,
            'action' => $log->action,
            'module' => $log->module,
            'description' => $log->description,
            'ip' => $log->ip,
            'before' => $log->before,
            'after' => $log->after,
        ];
    }
}

