<?php

namespace App\Services;

use App\DTOs\PaginatedResult;
use App\Http\Resources\UserResource;
use App\Models\RefreshToken;
use App\Models\Role;
use App\Models\User;
use App\Traits\Paginatable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    use Paginatable;

    public function __construct(private JwtService $jwt)
    {
    }

    public function register(array $data, string $ip, ?string $userAgent): array
    {
        $roleId = $data['role_id'] ?? $this->defaultRoleId();

        $user = User::create([
            'username' => $data['username'],
            'password_hash' => Hash::make($data['password']),
            'role_id' => $roleId,
            'status' => $data['status'] ?? 'Active',
        ]);

        return $this->issueTokens($user, $ip, $userAgent);
    }

    public function createUserByAdmin(array $data): User
    {
        $roleId = (int) $data['role_id'];

        return User::create([
            'username' => $data['username'],
            'password_hash' => Hash::make($data['password']),
            'role_id' => $roleId,
            'status' => $data['status'] ?? 'Active',
        ]);
    }

    public function listUsers(): PaginatedResult
    {
        $query = User::with('role')->latest();

        return $this->paginateResponse($query, UserResource::class);
    }

    public function updateStatus(int $id, string $status): User
    {
        $user = $this->findUserById($id);

        $user->update([
            'status' => $status,
        ]);

        return $user->load('role');
    }

    public function deleteUser(int $id, ?User $actor = null): void
    {
        $user = $this->findUserById($id);

        if ($actor && $actor->id === $user->id) {
            throw new ApiException(ResponseStatus::BAD_REQUEST, 'You cannot delete your own account.');
        }

        $user->delete();
    }

    public function login(string $username, string $password, string $ip, ?string $userAgent): array
    {
        $user = User::where('username', $username)->first();

        if (!$user || !Hash::check($password, (string) $user->password_hash)) {
            throw new AuthenticationException('Invalid credentials.');
        }

        if ($user->status !== 'Active') {
            throw new AuthorizationException('Account is inactive.');
        }

        return $this->issueTokens($user, $ip, $userAgent);
    }

    public function refresh(string $refreshToken, string $ip, ?string $userAgent): array
    {
        $token = $this->getRefreshTokenRecord($refreshToken);

        if (!$token || $token->isExpired() || $token->isRevoked()) {
            throw new AuthenticationException('Invalid refresh token.');
        }

        $user = $token->user;

        if (!$user || $user->status !== 'Active') {
            throw new AuthorizationException('Account is inactive.');
        }

        return DB::transaction(function () use ($token, $user, $ip, $userAgent) {
            // Rotate refresh tokens: invalidate the one used.
            $token->revoked_at = now();
            $token->last_used_at = now();
            $token->save();

            return $this->issueTokens($user, $ip, $userAgent);
        });
    }

    public function logout(User $user, ?string $refreshToken): void
    {
        if ($refreshToken) {
            $this->revokeRefreshToken($user, $refreshToken);
        }
    }

    public function logoutAll(User $user): int
    {
        return RefreshToken::where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
                'last_used_at' => now(),
            ]);
    }

    public function revokeRefreshToken(User $user, string $refreshToken): bool
    {
        $token = $this->getRefreshTokenRecord($refreshToken, $user->id);

        if (!$token || $token->isRevoked()) {
            return false;
        }

        $token->revoked_at = now();
        $token->last_used_at = now();
        $token->save();

        return true;
    }

    private function issueTokens(User $user, string $ip, ?string $userAgent): array
    {
        return DB::transaction(function () use ($user, $ip, $userAgent) {
            if (config('jwt.single_refresh_token', true)) {
                RefreshToken::where('user_id', $user->id)
                    ->whereNull('revoked_at')
                    ->update([
                        'revoked_at' => now(),
                        'last_used_at' => now(),
                    ]);
            }

            $access = $this->jwt->issueAccessToken($user);
            $refreshPlain = bin2hex(random_bytes(64));

            RefreshToken::create([
                'user_id' => $user->id,
                // Store only a hash in DB for security.
                'token_hash' => hash('sha256', $refreshPlain),
                'expires_at' => now()->addSeconds((int) config('jwt.refresh_ttl', 604800)),
                'last_used_at' => now(),
                'ip_address' => $ip,
                'user_agent' => $userAgent,
            ]);

            return [
                'access_token' => $access['token'],
                'refresh_token' => $refreshPlain,
                'token_type' => 'Bearer',
                'expires_in' => $access['expires_in'],
            ];
        });
    }

    private function getRefreshTokenRecord(string $refreshToken, ?int $userId = null): ?RefreshToken
    {
        $hash = hash('sha256', $refreshToken);
        $query = RefreshToken::where('token_hash', $hash)->with('user');

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->first();
    }

    private function defaultRoleId(): ?int
    {
        $roleId = Role::where('name', 'Viewer')->value('id');

        if (!$roleId) {
            throw new \RuntimeException('Default role "Viewer" is not seeded.');
        }

        return $roleId;
    }

    private function findUserById(int $id): User
    {
        $user = User::with('role')->find($id);

        if (!$user) {
            throw new ApiException(ResponseStatus::NOT_FOUND, 'User not found.');
        }

        return $user;
    }
}
