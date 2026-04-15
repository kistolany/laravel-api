<?php

namespace App\Services\AuditLog;

use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Models\AuditLog;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\Concerns\ServiceTraceable;
class AuditLogService
{
    use ServiceTraceable;

    public function list(array $filters): array
    {
        return $this->trace(__FUNCTION__, function () use ($filters): array {
            $page = max((int) ($filters['page'] ?? 1), 1);
            $perPage = max(1, min((int) ($filters['per_page'] ?? 20), 100));
            $search = trim((string) ($filters['search'] ?? ''));

            $ttlSeconds = max(0, (int) config('cache.audit_log_list.ttl_seconds', 8));

            if ($ttlSeconds === 0) {
                return $this->buildListResult($page, $perPage, $search);
            }

            $version = (int) Cache::get('audit_log:list:version', 1);
            $cacheKey = 'audit_log:list:v' . $version . ':' . sha1(json_encode([
                'page' => $page,
                'per_page' => $perPage,
                'search' => $search,
            ]));

            return Cache::remember(
                $cacheKey,
                now()->addSeconds($ttlSeconds),
                fn () => $this->buildListResult($page, $perPage, $search)
            );
        });
    }

    private function buildListResult(int $page, int $perPage, string $search): array
    {
        $requestStartedAt = microtime(true);

        $buildStartedAt = microtime(true);
        $baseQuery = $this->buildBaseQuery($search);
        $buildDurationMs = $this->durationMs($buildStartedAt);

        $summaryStartedAt = microtime(true);
        $summary = $this->summarizeActions(clone $baseQuery);
        $summaryDurationMs = $this->durationMs($summaryStartedAt);

        $pageStartedAt = microtime(true);
        $paginator = (clone $baseQuery)
            ->latest('created_at')
            ->simplePaginate($perPage, ['*'], 'page', $page);

        $items = collect($paginator->items())
            ->map(fn (AuditLog $log) => $this->transform($log))
            ->values();
        $pageDurationMs = $this->durationMs($pageStartedAt);

        $totalItems = $summary['total'];
        $totalPages = $totalItems > 0
            ? (int) ceil($totalItems / $perPage)
            : 1;

        if (config('logging.audit_log_diagnostics.enabled', true)) {
            $this->logListDiagnostics([
                'duration_ms' => [
                    'total' => $this->durationMs($requestStartedAt),
                    'build_query' => $buildDurationMs,
                    'summary_counts' => $summaryDurationMs,
                    'paginate_and_transform' => $pageDurationMs,
                ],
                'filters' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'search' => Str::limit($search, 120),
                ],
                'result' => [
                    'returned_items' => $items->count(),
                    'total_items' => $totalItems,
                    'creates' => $summary['creates'],
                    'updates' => $summary['updates'],
                    'deletes' => $summary['deletes'],
                ],
                'query' => [
                    'sql' => $baseQuery->toBase()->toSql(),
                    'bindings' => $baseQuery->toBase()->getBindings(),
                ],
                'request' => [
                    'method' => request()->method(),
                    'path' => request()->path(),
                    'ip' => request()->ip(),
                ],
            ]);
        }

        return [
            'items' => $items,
            'pagination' => [
                'total' => $totalItems,
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'total_pages' => $totalPages,
            ],
            'summary' => [
                'total' => $totalItems,
                'creates' => $summary['creates'],
                'updates' => $summary['updates'],
                'deletes' => $summary['deletes'],
            ],
        ];
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

            $this->bumpListCacheVersion();
            
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

            if ($deleted > 0) {
                $this->bumpListCacheVersion();
            }
            
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

    private function bumpListCacheVersion(): void
    {
        $key = 'audit_log:list:version';
        $version = (int) Cache::get($key, 1);
        Cache::forever($key, $version + 1);
    }

    private function summarizeActions(Builder $query): array
    {
        $summaryRow = $query
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN action = 'create' THEN 1 ELSE 0 END) as creates")
            ->selectRaw("SUM(CASE WHEN action = 'update' THEN 1 ELSE 0 END) as updates")
            ->selectRaw("SUM(CASE WHEN action = 'delete' THEN 1 ELSE 0 END) as deletes")
            ->first();

        return [
            'total' => (int) ($summaryRow?->total ?? 0),
            'creates' => (int) ($summaryRow?->creates ?? 0),
            'updates' => (int) ($summaryRow?->updates ?? 0),
            'deletes' => (int) ($summaryRow?->deletes ?? 0),
        ];
    }

    private function logListDiagnostics(array $context): void
    {
        if (!config('logging.audit_log_diagnostics.enabled', true)) {
            return;
        }

        $slowThresholdMs = max(1, (int) config('logging.audit_log_diagnostics.slow_threshold_ms', 400));
        $sampleRate = max(1, (int) config('logging.audit_log_diagnostics.sample_rate', 1));
        $totalDurationMs = (float) ($context['duration_ms']['total'] ?? 0.0);

        if ($sampleRate > 1 && random_int(1, $sampleRate) !== 1) {
            return;
        }

        if ($totalDurationMs >= $slowThresholdMs) {
            Log::warning('AuditLog list is slow', $context);
            return;
        }

        if (config('logging.audit_log_diagnostics.log_all', false)) {
            Log::info('AuditLog list diagnostics', $context);
        }
    }

    private function durationMs(float $startedAt): float
    {
        return round((microtime(true) - $startedAt) * 1000, 2);
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

