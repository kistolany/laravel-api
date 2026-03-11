<?php

namespace App\Http\Middleware;

use App\Enums\ResponseStatus;
use App\Traits\ApiResponseTrait;
use Closure;
use Illuminate\Http\Request;

class PermissionMiddleware
{
    use ApiResponseTrait;

    public function handle(Request $request, Closure $next, string $permissions)
    {
        $user = $request->user();

        if (!$user) {
            return $this->error('Unauthorized.', ResponseStatus::UNAUTHORIZED);
        }

        $required = array_filter(array_map('trim', preg_split('/[|,]/', $permissions)));

        foreach ($required as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        return $this->error('Forbidden.', ResponseStatus::FORBIDDEN);
    }
}
