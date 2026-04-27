<?php

namespace App\Http\Controllers\ApiController\Notification;

use App\Enums\ResponseStatus;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    use ApiResponseTrait;

    public function index(): JsonResponse
    {
        $notifications = DB::table('push_notifications as n')
            ->leftJoin('users as u', 'u.id', '=', 'n.sent_by')
            ->select(
                'n.id',
                'n.title',
                'n.body',
                'n.audience',
                'n.priority',
                'n.created_at',
                'u.full_name as sent_by_name',
            )
            ->orderBy('n.created_at', 'desc')
            ->limit(200)
            ->get();

        return $this->success($notifications->values()->all(), 'Notifications retrieved successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'    => 'required|string|max:120',
            'body'     => 'required|string|max:1000',
            'audience' => 'required|string|in:all,admin,teacher,staff',
            'priority' => 'required|string|in:normal,info,warning,urgent',
        ]);

        $id = DB::table('push_notifications')->insertGetId([
            'title'      => $data['title'],
            'body'       => $data['body'],
            'audience'   => $data['audience'],
            'priority'   => $data['priority'],
            'sent_by'    => $request->user()?->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $notification = DB::table('push_notifications as n')
            ->leftJoin('users as u', 'u.id', '=', 'n.sent_by')
            ->select('n.*', 'u.full_name as sent_by_name')
            ->where('n.id', $id)
            ->first();

        return $this->success($notification, 'Notification sent successfully.', 201);
    }

    /**
     * Public feed — any authenticated user can poll this to receive
     * broadcast notifications (audience = all).  No permission gate.
     */
    public function feed(Request $request): JsonResponse
    {
        $since = $request->query('since'); // optional: ISO timestamp or ID

        $query = DB::table('push_notifications as n')
            ->leftJoin('users as u', 'u.id', '=', 'n.sent_by')
            ->where('n.audience', 'all')
            ->select(
                'n.id',
                'n.title',
                'n.body',
                'n.priority',
                'n.created_at',
                'u.full_name as sent_by_name',
            )
            ->orderBy('n.id', 'desc')
            ->limit(50);

        if ($since) {
            $query->where('n.id', '>', intval($since));
        }

        return $this->success($query->get()->values()->all(), 'Feed retrieved successfully.');
    }

    public function destroy(int $id): JsonResponse
    {
        $deleted = DB::table('push_notifications')->where('id', $id)->delete();

        if (! $deleted) {
            return $this->error('Notification not found.', ResponseStatus::NOT_FOUND);
        }

        return $this->success(null, 'Notification deleted successfully.');
    }
}
