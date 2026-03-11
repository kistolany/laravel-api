<?php

namespace App\Http\Middleware;

use App\Enums\ResponseStatus;
use App\Traits\ApiResponseTrait;
use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    use ApiResponseTrait;

    public function handle(Request $request, Closure $next, string $roles)
    {
        $user = $request->user();

        if (!$user) {
            return $this->error('Unauthorized.', ResponseStatus::UNAUTHORIZED);
        }

        $allowed = array_filter(array_map('trim', preg_split('/[|,]/', $roles)));

        foreach ($allowed as $role) {
            if ($user->hasRole($role)) {
                return $next($request);
            }
        }

        return $this->error('Forbidden.', ResponseStatus::FORBIDDEN);
    }
}
