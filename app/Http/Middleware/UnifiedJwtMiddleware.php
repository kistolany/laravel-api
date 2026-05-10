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
            if (!$teacher) {
                return (new ApiException(ResponseStatus::UNAUTHORIZED, 'Unauthorized.'))->render($request);
            }
            Auth::setUser($teacher);
            $request->setUserResolver(fn () => $teacher);

            // 🟢 Track Online Status (Teacher)
            \Illuminate\Support\Facades\Cache::put('user-online-teacher-' . $id, true, now()->addMinutes(2));
        } else {
            $user = User::find($id);
            if (!$user || $user->status !== 'Active') {
                return (new ApiException(ResponseStatus::UNAUTHORIZED, 'Unauthorized.'))->render($request);
            }
            Auth::setUser($user);
            $request->setUserResolver(fn () => $user);

            // 🟢 Track Online Status (System User)
            \Illuminate\Support\Facades\Cache::put('user-online-user-' . $id, true, now()->addMinutes(2));
        }

        return $next($request);
    }

    private function extractBearerToken(Request $request): ?string
    {
        $header = (string) $request->header('Authorization', '');

        if (str_starts_with($header, 'Bearer ')) {
            $token = trim(substr($header, 7));
            return $token !== '' ? $token : null;
        }

        // Allow token via query string for browser-opened file URLs
        $token = trim((string) $request->query('token', ''));
        return $token !== '' ? $token : null;
    }
}
