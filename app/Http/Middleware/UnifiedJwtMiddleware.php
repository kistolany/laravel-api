<?php

namespace App\Http\Middleware;

use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Models\Teacher;
use App\Services\Auth\JwtService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UnifiedJwtMiddleware
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

        $type = $payload['type'] ?? 'user';
        $id = $payload['sub'] ?? null;

        if (!$id) {
            return (new ApiException(ResponseStatus::UNAUTHORIZED, 'Invalid token subject.'))->render($request);
        }

        if ($type === 'teacher') {
            $teacher = Teacher::find($id);
            if (!$teacher || !$teacher->is_verified) {
                return (new ApiException(ResponseStatus::UNAUTHORIZED, 'Unauthorized.'))->render($request);
            }
            Auth::setUser($teacher);
            $request->setUserResolver(fn () => $teacher);
        } else {
            $user = User::find($id);
            if (!$user || $user->status !== 'Active') {
                return (new ApiException(ResponseStatus::UNAUTHORIZED, 'Unauthorized.'))->render($request);
            }
            Auth::setUser($user);
            $request->setUserResolver(fn () => $user);
        }

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
