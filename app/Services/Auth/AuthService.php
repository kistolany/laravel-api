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
                Log::info("User registration with image upload, image path: {$imagePath}");
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
                ...$this->identityFieldsForRole($roleId, $data),
                'account_purpose' => $data['account_purpose'] ?? 'Main Account',
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
                $updates = [
                    ...$updates,
                    ...$this->identityFieldsForRole((int) $data['role_id'], $data),
                ];
            }
            
            if (isset($data['status'])) {
                $updates['status'] = $data['status'];
            }
            
            if (isset($data['username'])) {
                $updates['username'] = $data['username'];
            }

            if (isset($data['account_purpose'])) {
                $updates['account_purpose'] = $data['account_purpose'];
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
            $query = User::with(['role', 'teacher', 'student'])->latest();
            Log::info('Listing users with filters', request()->only(['search', 'role_id', 'status']));
            return $this->paginateResponse($query, UserResource::class);
            
            
        });
    }

    public function updateStatus(int $id, string $status): User
    {
        return $this->trace(__FUNCTION__, function () use ($id, $status): User {
            $user = $this->findUserById($id);
            Log::info("Updating user ID {$id} status to {$status}");
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
                Log::warning("User ID {$actor->id} attempted to delete their own account.");
                throw new ApiException(ResponseStatus::BAD_REQUEST, 'You cannot delete your own account.');
            }
            
            $user->delete();
            
            
        });
    }

    public function login(string $username, string $password, string $ip, ?string $userAgent): array
    {
        return $this->trace(__FUNCTION__, function () use ($username, $password, $ip, $userAgent): array {
            $user = User::where('username', $username)->first();
            
            if (!$user) {
                Log::warning('Failed login attempt: account not found.', [
                    'username' => $username,
                    'ip' => $ip,
                ]);
                throw new AuthenticationException('account not exist');
            }

            if (!Hash::check($password, (string) $user->password_hash)) {
                Log::warning('Failed login attempt: account is not correct.', [
                    'username' => $username,
                    'ip' => $ip,
                ]);
                throw new AuthenticationException('account is not correct');
            }
            
            if ($user->status !== 'Active') {
                Log::warning("Login attempt for inactive user: {$username}");
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
                Log::warning("Invalid refresh token attempt from IP {$ip}");
                throw new AuthenticationException('Invalid refresh token.');
            }
            
            $user = $token->user;
            
            if (!$user || $user->status !== 'Active') {
                Log::warning("Refresh token used for inactive user: {$user->username}");
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
                Log::info("User ID {$user->id} logged out with refresh token revoked.");
            }
            
            
        });
    }

    public function logoutAll(User $user): int
    {
        return $this->trace(__FUNCTION__, function () use ($user): int {
            $updated = RefreshToken::where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => now(),
                    'last_used_at' => now(),
                ]);

            Log::info("User ID {$user->id} logged out from all sessions, all refresh tokens revoked.", [
                'revoked_tokens' => $updated,
            ]);

            return $updated;
            
            
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
                Log::warning('User revoke refresh token failed: token not found.', [
                    'user_id' => $user->id,
                ]);
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

    private function identityFieldsForRole(int $roleId, array $data): array
    {
        $roleName = Role::whereKey($roleId)->value('name');
        $identityType = $this->identityTypeForRole($roleName);

        return [
            'student_id' => $identityType === 'student' ? ($data['student_id'] ?? null) : null,
            'teacher_id' => $identityType === 'teacher' ? ($data['teacher_id'] ?? null) : null,
            'staff_id' => $identityType === 'staff' ? ($data['staff_id'] ?? null) : null,
        ];
    }

    private function identityTypeForRole(?string $roleName): ?string
    {
        $normalized = strtolower(preg_replace('/\s+/', '', trim((string) $roleName)));

        return match ($normalized) {
            'teacher' => 'teacher',
            'student' => 'student',
            'staff', 'assistant', 'assistance', 'orderstaff' => 'staff',
            default => null,
        };
    }

    private function findUserById(int $id): User
    {
        $user = User::with('role')->find($id);

        if (!$user) {
            Log::warning('User not found.', ['id' => $id]);
            throw new ApiException(ResponseStatus::NOT_FOUND, 'User not found.');
        }

        return $user;
    }

    /**
     * Upload a user image to Cloudinary only.
     */
    private function uploadImage(\Illuminate\Http\UploadedFile $file): string
    {
        $this->ensureCloudinaryConfigured();
        $uploadOptions = $this->buildCloudinaryUploadOptions('users');

        try {
            $result = \CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary::uploadApi()->upload(
                $file->getRealPath(),
                $uploadOptions
            );
            $url = $result['secure_url'] ?? null;
        } catch (\Throwable $e) {
            Log::error(
                'User image upload failed on Cloudinary.',
                $this->buildCloudinaryExceptionContext($e, $file, $uploadOptions)
            );

            throw new ApiException(ResponseStatus::INTERNAL_SERVER_ERROR, 'Failed to upload image.');
        }

        if (!$url) {
            Log::error('User image upload failed: Cloudinary returned empty URL.');
            throw new ApiException(ResponseStatus::INTERNAL_SERVER_ERROR, 'Failed to upload image.');
        }

        return $url;
    }

    private function ensureCloudinaryConfigured(): void
    {
        $cloudUrl = (string) config('cloudinary.cloud_url', '');

        if ($cloudUrl === '' || str_contains($cloudUrl, 'API_KEY:API_SECRET@CLOUD_NAME')) {
            Log::error('Cloudinary is not configured for user uploads. Set CLOUDINARY_URL in .env and clear config cache.');
            throw new ApiException(ResponseStatus::BAD_REQUEST, 'Cloudinary is not configured. Set CLOUDINARY_URL in .env.');
        }
    }

    private function buildCloudinaryUploadOptions(string $folder): array
    {
        $options = [
            'folder' => $folder,
            'overwrite' => true,
            'use_filename' => false,
            'unique_filename' => false,
            'use_filename_as_display_name' => true,
            'resource_type' => 'auto',
        ];
        
        $preset = trim((string) config('cloudinary.upload_preset', ''));

        if ($preset !== '') {
            $options['upload_preset'] = $preset;
        }

        return $options;
    }

    private function buildCloudinaryExceptionContext(
        \Throwable $e,
        \Illuminate\Http\UploadedFile $file,
        array $uploadOptions
    ): array {
        $cloudUrl = (string) config('cloudinary.cloud_url', '');
        $context = [
            'exception_class' => $e::class,
            'exception_message' => $e->getMessage(),
            'exception_code' => $e->getCode(),
            'exception_file' => $e->getFile(),
            'exception_line' => $e->getLine(),
            'upload_original_name' => $file->getClientOriginalName(),
            'upload_mime_type' => $file->getClientMimeType(),
            'upload_size_bytes' => $file->getSize(),
            'cloudinary_upload_options' => $uploadOptions,
            'cloudinary_config_host' => parse_url($cloudUrl, PHP_URL_HOST) ?: null,
            'cloudinary_config_has_url' => $cloudUrl !== '',
            'cloudinary_config_has_upload_preset' => array_key_exists('upload_preset', $uploadOptions),
        ];

        if ($e->getPrevious() !== null) {
            $context['previous_exception_class'] = $e->getPrevious()::class;
            $context['previous_exception_message'] = $e->getPrevious()->getMessage();
        }

        $response = null;

        if (method_exists($e, 'getResponse')) {
            try {
                $response = call_user_func([$e, 'getResponse']);
            } catch (\Throwable $responseError) {
                $context['cloudinary_response_inspect_error'] = $responseError->getMessage();
            }
        }

        if ($response !== null) {
            if (method_exists($response, 'getStatusCode')) {
                $context['cloudinary_response_status'] = $response->getStatusCode();
            }

            if (method_exists($response, 'getHeaderLine')) {
                $context['cloudinary_response_content_type'] = $response->getHeaderLine('Content-Type');
            }

            if (method_exists($response, 'getBody')) {
                $body = (string) $response->getBody();

                if ($body !== '') {
                    $context['cloudinary_response_body_excerpt'] = substr($body, 0, 500);
                }
            }
        }

        if (!array_key_exists('cloudinary_response_status', $context) && method_exists($e, 'getHttpCode')) {
            try {
                $context['cloudinary_response_status'] = call_user_func([$e, 'getHttpCode']);
            } catch (\Throwable) {
                // Ignore optional status extraction failures.
            }
        }

        if (!array_key_exists('cloudinary_response_body_excerpt', $context) && method_exists($e, 'getHttpBody')) {
            try {
                $body = (string) call_user_func([$e, 'getHttpBody']);
                if ($body !== '') {
                    $context['cloudinary_response_body_excerpt'] = substr($body, 0, 500);
                }
            } catch (\Throwable) {
                // Ignore optional body extraction failures.
            }
        }

        return $context;
    }
}



