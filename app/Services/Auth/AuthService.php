<?php

namespace App\Services\Auth;

use App\DTOs\PaginatedResult;
use App\Http\Resources\Auth\UserResource;
use App\Models\RefreshToken;
use App\Models\Role;
use App\Models\User;
use App\Traits\Paginatable;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

use Illuminate\Support\Facades\Log;
use App\Services\Concerns\ServiceTraceable;
class AuthService
{
    use ServiceTraceable;

    use Paginatable;

    public function __construct(private JwtService $jwt)
    {
                            
                    
    }

    public function register(array $data, string $ip, ?string $userAgent): array
    {
        return $this->trace(__FUNCTION__, function () use ($data, $ip, $userAgent): array {
            $roleId = $data['role_id'] ?? $this->defaultRoleId();
            
            $imagePath = null;
            if (isset($data['image']) && $data['image'] instanceof \Illuminate\Http\UploadedFile) {
                $imagePath = $this->uploadImage($data['image']);
            }
            
            $user = User::create([
                'username' => $data['username'],
                'password_hash' => Hash::make($data['password']),
                'role_id' => $roleId,
                'status' => $data['status'] ?? 'Active',
                'full_name' => $data['full_name'] ?? null,
                'phone' => $data['phone'] ?? null,
                'image' => $imagePath,
            ]);
            
            return $this->issueTokens($user, $ip, $userAgent);
            
            
        });
    }

    public function createUserByAdmin(array $data): User
    {
        return $this->trace(__FUNCTION__, function () use ($data): User {
            $roleId = (int) $data['role_id'];
            
            $imagePath = null;
            if (isset($data['image']) && $data['image'] instanceof \Illuminate\Http\UploadedFile) {
                $imagePath = $this->uploadImage($data['image']);
            }
            
            return User::create([
                'username' => $data['username'],
                'password_hash' => Hash::make($data['password']),
                'role_id' => $roleId,
                'status' => $data['status'] ?? 'Active',
                'full_name' => $data['full_name'] ?? null,
                'phone' => $data['phone'] ?? null,
                'image' => $imagePath,
            ]);
            
            
        });
    }

    public function updateUserByAdmin(int $id, array $data): User
    {
        return $this->trace(__FUNCTION__, function () use ($id, $data): User {
            $user = $this->findUserById($id);
            
            $updates = [];
            
            if (isset($data['full_name'])) {
                $updates['full_name'] = $data['full_name'];
            }
            
            if (isset($data['phone'])) {
                $updates['phone'] = $data['phone'];
            }
            
            if (isset($data['role_id'])) {
                $updates['role_id'] = (int) $data['role_id'];
            }
            
            if (isset($data['status'])) {
                $updates['status'] = $data['status'];
            }
            
            if (isset($data['username'])) {
                $updates['username'] = $data['username'];
            }
            
            // Only update password if a new one is provided
            if (!empty($data['password'])) {
                $updates['password_hash'] = Hash::make($data['password']);
            }
            
            // Handle image upload
            if (isset($data['image']) && $data['image'] instanceof \Illuminate\Http\UploadedFile) {
                $updates['image'] = $this->uploadImage($data['image']);
            }
            
            $user->update($updates);
            
            return $user->load('role');
            
            
        });
    }

    public function updateOwnProfile(User $user, array $data): User
    {
        return $this->trace(__FUNCTION__, function () use ($user, $data): User {
            $updates = [];
            
            if (array_key_exists('full_name', $data)) {
                $updates['full_name'] = $data['full_name'];
            }
            
            if (array_key_exists('phone', $data)) {
                $updates['phone'] = $data['phone'];
            }
            
            if (!empty($data['new_password'])) {
                $updates['password_hash'] = Hash::make($data['new_password']);
            }
            
            if (isset($data['image']) && $data['image'] instanceof \Illuminate\Http\UploadedFile) {
                $updates['image'] = $this->uploadImage($data['image']);
            }
            
            if (!empty($updates)) {
                $user->update($updates);
            }
            
            return $user->load('role.permissions');
            
            
        });
    }

    public function listUsers(): PaginatedResult
    {
        return $this->trace(__FUNCTION__, function (): PaginatedResult {
            $query = User::with('role')->latest();
            
            return $this->paginateResponse($query, UserResource::class);
            
            
        });
    }

    public function updateStatus(int $id, string $status): User
    {
        return $this->trace(__FUNCTION__, function () use ($id, $status): User {
            $user = $this->findUserById($id);
            
            $user->update([
                'status' => $status,
            ]);
            
            return $user->load('role');
            
            
        });
    }

    public function deleteUser(int $id, ?User $actor = null): void
    {
        $this->trace(__FUNCTION__, function () use ($id, $actor) {
            $user = $this->findUserById($id);
            
            if ($actor && $actor->id === $user->id) {
                throw new ApiException(ResponseStatus::BAD_REQUEST, 'You cannot delete your own account.');
            }
            
            $user->delete();
            
            
        });
    }

    public function login(string $username, string $password, string $ip, ?string $userAgent): array
    {
        return $this->trace(__FUNCTION__, function () use ($username, $password, $ip, $userAgent): array {
            $user = User::where('username', $username)->first();
            
            if (!$user || !Hash::check($password, (string) $user->password_hash)) {
                throw new AuthenticationException('account not exist');
            }
            
            if ($user->status !== 'Active') {
                throw new AuthorizationException('Account is inactive.');
            }
            
            return $this->issueTokens($user, $ip, $userAgent);
            
            
        });
    }

    public function refresh(string $refreshToken, string $ip, ?string $userAgent): array
    {
        return $this->trace(__FUNCTION__, function () use ($refreshToken, $ip, $userAgent): array {
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
            
            
        });
    }

    public function logout(User $user, ?string $refreshToken): void
    {
        $this->trace(__FUNCTION__, function () use ($user, $refreshToken) {
            if ($refreshToken) {
                $this->revokeRefreshToken($user, $refreshToken);
            }
            
            
        });
    }

    public function logoutAll(User $user): int
    {
        return $this->trace(__FUNCTION__, function () use ($user): int {
            return RefreshToken::where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => now(),
                    'last_used_at' => now(),
                ]);
            
            
        });
    }

    public function revokeRefreshToken(User $user, string $refreshToken): bool
    {
        return $this->trace(__FUNCTION__, function () use ($user, $refreshToken): bool {
            $token = $this->getRefreshTokenRecord($refreshToken, $user->id);
            
            if (!$token || $token->isRevoked()) {
                return false;
            }
            
            $token->revoked_at = now();
            $token->last_used_at = now();
            $token->save();
            
            return true;
            
            
        });
    }

    public function revokeRefreshTokenOrFail(User $user, string $refreshToken): void
    {
        $this->trace(__FUNCTION__, function () use ($user, $refreshToken) {
            $revoked = $this->revokeRefreshToken($user, $refreshToken);
            
            if (!$revoked) {
                throw new ApiException(ResponseStatus::NOT_FOUND, 'Refresh token not found.');
            }
            
            
        });
    }

    public function normalizeCreateUserData(array $data, ?\Illuminate\Http\UploadedFile $image): array
    {
        return $this->trace(__FUNCTION__, function () use ($data, $image): array {
            $data['image'] = $image;
            
            return $data;
            
            
        });
    }

    public function normalizeUpdateUserData(array $data, ?\Illuminate\Http\UploadedFile $image): array
    {
        return $this->trace(__FUNCTION__, function () use ($data, $image): array {
            $data['image'] = $image;
            
            if (empty($data['password'])) {
                unset($data['password']);
            }
            
            return $data;
            
            
        });
    }

    public function normalizeUpdateProfileData(array $data, ?\Illuminate\Http\UploadedFile $image): array
    {
        return $this->trace(__FUNCTION__, function () use ($data, $image): array {
            $data['image'] = $image;
            
            if (empty($data['new_password'])) {
                unset($data['new_password']);
            }
            
            unset($data['new_password_confirmation']);
            
            return $data;
            
            
        });
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

    /**
     * Upload a user image.
     * Tries Cloudinary first; falls back to local storage if Cloudinary is not configured.
     */
    private function uploadImage(\Illuminate\Http\UploadedFile $file): ?string
    {
        // Try Cloudinary first
        try {
            return \CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary::upload($file->getRealPath(), [
                'folder' => 'users',
                'upload_preset' => 'image'
            ])->getSecurePath();
        } catch (\Throwable $e) {
            // Cloudinary not configured or failed Ã¢â‚¬â€œ fall back to local storage
            Log::warning('Cloudinary upload failed, using local storage: ' . $e->getMessage());
        }

        // Fallback: store locally in storage/app/public/users
        $path = $file->store('users', 'public');

        return $path ? '/storage/' . $path : null;
    }
}



