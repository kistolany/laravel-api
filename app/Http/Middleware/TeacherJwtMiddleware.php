<?php

namespace App\Http\Middleware;

use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Models\Teacher;
use App\Services\Auth\JwtService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TeacherJwtMiddleware
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

        if (($payload['type'] ?? 'user') !== 'teacher') {
            return (new ApiException(ResponseStatus::UNAUTHORIZED, 'Unauthorized.'))->render($request);
        }

        $teacherId = $payload['sub'] ?? null;

        if (!$teacherId) {
            return (new ApiException(ResponseStatus::UNAUTHORIZED, 'Invalid token subject.'))->render($request);
        }

        $teacher = Teacher::find($teacherId);

        if (!$teacher || !$teacher->is_verified) {
            return (new ApiException(ResponseStatus::UNAUTHORIZED, 'Unauthorized.'))->render($request);
        }

        Auth::setUser($teacher);
        $request->setUserResolver(fn () => $teacher);

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
