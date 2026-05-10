<?php

namespace App\Services\Auth;

use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Support\Str;
use App\Services\Concerns\ServiceTraceable;
class JwtService
{
    use ServiceTraceable;

    public function issueAccessToken(AuthenticatableContract $user, string $type = 'user'): array
    {
        return $this->trace(__FUNCTION__, function () use ($user, $type): array {
            $this->ensureJwtLibrary();
            
            $now = time();
            $ttl = (int) config('jwt.access_ttl', 900);
            
            $payload = [
                'iss' => (string) config('jwt.issuer'),
                'sub' => (string) $user->getAuthIdentifier(),
                'type' => $type,
                'iat' => $now,
                'exp' => $now + $ttl,
                'jti' => (string) Str::uuid(),
            ];
            
            return [
                'token' => JWT::encode($payload, $this->secret(), $this->algo()),
                'expires_in' => $ttl,
            ];
        });
    }

    /**
     * Decodes a JWT token and returns the payload.
     *
     * @param string $token
     * @return array
     */
    public function decode(string $token): array
    {
        // Note: This method will throw exceptions if the token is invalid or expired.
        return $this->trace(__FUNCTION__, function () use ($token): array {
            $this->ensureJwtLibrary();
            JWT::$leeway = (int) config('jwt.leeway', 0);
            
            $decoded = JWT::decode($token, new Key($this->secret(), $this->algo()));
            
            return (array) $decoded;
            
        });
    }


    // Additional helper methods for refresh tokens, token validation, etc. can be added here.
    private function secret(): string
    {

        $secret = (string) config('jwt.secret');

        if ($secret === '') {
            throw new ApiException(ResponseStatus::INTERNAL_SERVER_ERROR, 'JWT_SECRET is not configured.');
        }

        return $secret;
    }

    private function algo(): string
    {
    
        return (string) config('jwt.algo', 'HS256');
    }

    private function ensureJwtLibrary(): void
    {
        if (!class_exists(JWT::class) || !class_exists(Key::class)) {
            throw new ApiException(
                ResponseStatus::INTERNAL_SERVER_ERROR,
                'JWT library is not installed. Run composer require firebase/php-jwt.'
            );
        }
    }
}



