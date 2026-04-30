<?php

namespace App\Services\Notification;

use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Services\Concerns\ServiceTraceable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    use ServiceTraceable;

    public function list(): Collection
    {
        return $this->trace(__FUNCTION__, function (): Collection {
            return $this->baseQuery()
                ->select(
                    'n.id',
                    'n.title',
                    'n.body',
                    'n.audience',
                    'n.priority',
                    'n.target_user_id',
                    'n.created_at',
                    'u.full_name as sent_by_name',
                )
                ->orderBy('n.created_at', 'desc')
                ->limit(200)
                ->get()
                ->values();
        });
    }

    public function create(array $data, ?Authenticatable $user): object
    {
        return $this->trace(__FUNCTION__, function () use ($data, $user): object {
            $id = DB::table('push_notifications')->insertGetId([
                'title' => $data['title'],
                'body' => $data['body'],
                'audience' => $data['audience'],
                'priority' => $data['priority'],
                'sent_by' => $user?->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->findDisplayRow($id);
        });
    }

    public function feed(array $filters, ?Authenticatable $user): Collection
    {
        return $this->trace(__FUNCTION__, function () use ($filters, $user): Collection {
            $userId = $user?->id;
            $roleName = strtolower((string) ($user?->role?->name ?? $user?->role_name ?? ''));

            $query = $this->baseQuery()
                ->where(function ($query) use ($userId, $roleName) {
                    $query->where(function ($broadcastQuery) {
                        $broadcastQuery
                            ->where('n.audience', 'all')
                            ->whereNull('n.target_user_id');
                    });

                    if ($roleName !== '') {
                        $query->orWhereRaw('LOWER(n.audience) = ?', [$roleName]);
                    }

                    if ($userId) {
                        $query->orWhere('n.target_user_id', $userId);
                    }
                })
                ->select(
                    'n.id',
                    'n.title',
                    'n.body',
                    'n.audience',
                    'n.priority',
                    'n.target_user_id',
                    'n.created_at',
                    'u.full_name as sent_by_name',
                )
                ->orderBy('n.id', 'desc')
                ->limit(100);

            if (! empty($filters['since'])) {
                $query->where('n.id', '>', (int) $filters['since']);
            }

            return $query->get()->values();
        });
    }

    public function delete(int $id): void
    {
        $this->trace(__FUNCTION__, function () use ($id): void {
            $deleted = DB::table('push_notifications')->where('id', $id)->delete();

            if (! $deleted) {
                throw new ApiException(ResponseStatus::NOT_FOUND, 'Notification not found.');
            }
        });
    }

    private function baseQuery()
    {
        return DB::table('push_notifications as n')
            ->leftJoin('users as u', 'u.id', '=', 'n.sent_by');
    }

    private function findDisplayRow(int $id): object
    {
        return $this->baseQuery()
            ->select('n.*', 'u.full_name as sent_by_name')
            ->where('n.id', $id)
            ->first();
    }
}
