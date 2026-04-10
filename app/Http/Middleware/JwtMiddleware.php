<?php

namespace App\Http\Middleware;

use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\Auth\JwtService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class JwtMiddleware
{
    public function __construct(private JwtService $jwt)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $token = $this->extractBearerToken($request);

        if ($token === null) {
            return (new ApiException(ResponseStatus::UNAUTHORIZED, 'Unauthorized.'))->render($request);
        }

        try {
            $payload = $this->jwt->decode($token);
        } catch (ApiException $e) {
            return $e->render($request);
        } catch (\Throwable $e) {
            return (new ApiException(ResponseStatus::UNAUTHORIZED, 'Invalid or expired token.'))->render($request);
        }

        if (($payload['iss'] ?? null) !== config('jwt.issuer')) {
            return (new ApiException(ResponseStatus::UNAUTHORIZED, 'Invalid token issuer.'))->render($request);
        }

        if (($payload['type'] ?? 'user') !== 'user') {
            return (new ApiException(ResponseStatus::UNAUTHORIZED, 'Unauthorized.'))->render($request);
        }

        $userId = $payload['sub'] ?? null;

        if (!$userId) {
            return (new ApiException(ResponseStatus::UNAUTHORIZED, 'Invalid token subject.'))->render($request);
        }

        $user = User::find($userId);

        if (!$user || $user->status !== 'Active') {
            return (new ApiException(ResponseStatus::UNAUTHORIZED, 'Unauthorized.'))->render($request);
        }

        // Attach the user to the request/auth system.
        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }

    private function extractBearerToken(Request $request): ?string
    {
        $header = (string) $request->header('Authorization', '');

        if (!str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($header, 7));

        return $token !== '' ? $token : null;
    }
}
