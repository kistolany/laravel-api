<?php

namespace App\Services\Teacher;

use App\Services\Auth\JwtService;

use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Mail\TeacherOtpMail;
use App\Models\Teacher;
use App\Models\TeacherRefreshToken;
use App\Models\User;
use App\Models\Role;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Services\Concerns\ServiceTraceable;
class TeacherAuthService
{
    use ServiceTraceable;

    public function __construct(private JwtService $jwt)
    {
                            
                    
    }

    public function register(array $data): Teacher
    {
        return $this->trace(__FUNCTION__, function () use ($data): Teacher {
            $imagePath = $this->storeImage($data['image'] ?? null);
            
            // Create Teacher record
            $teacher = Teacher::create([
                // core
                'teacher_id'      => $data['teacher_id']      ?? null,
                'first_name'      => $data['first_name'],
                'last_name'       => $data['last_name'],
                'gender'          => $data['gender'],
                'major_id'        => (int) $data['major_id'],
                'subject_id'      => (int) $data['subject_id'],
                'email'           => strtolower($data['email']),
                'username'        => $data['username'],
                'password'        => Hash::make($data['password']),
                'address'         => $data['address'],
                // personal
                'dob'             => $data['dob']             ?? null,
                'nationality'     => $data['nationality']     ?? null,
                'religion'        => $data['religion']        ?? null,
                'marital_status'  => $data['marital_status']  ?? null,
                'national_id'     => $data['national_id']     ?? null,
                'phone_number'    => $data['phone_number']    ?? null,
                'telegram'        => $data['telegram']        ?? null,
                'image'           => $imagePath,
                // emergency
                'emergency_name'  => $data['emergency_name']  ?? null,
                'emergency_phone' => $data['emergency_phone'] ?? null,
                // professional
                'position'        => $data['position']        ?? null,
                'degree'          => $data['degree']          ?? null,
                'specialization'  => $data['specialization']  ?? null,
                'contract_type'   => $data['contract_type']   ?? null,
                'salary_type'     => $data['salary_type']     ?? null,
                'salary'          => $data['salary']          ?? null,
                'experience'      => $data['experience']      ?? null,
                'join_date'       => $data['join_date']       ?? null,
                'note'            => $data['note']            ?? null,
                // auth
                'role'            => 'Teacher',
                'otp_code'        => null,
                'otp_expires_at'  => null,
                'is_verified'     => true,
                'verified_at'     => now(),
            ]);
            
            // Also create a User account so the teacher appears in User Management
            $teacherRole = Role::where('name', 'Teacher')->first();
            $roleId = $teacherRole?->id ?? 3; // Default to 3 if Teacher role not found
            
            User::create([
                'username'      => $data['username'],
                'password_hash' => Hash::make($data['password']),
                'role_id'       => $roleId,
                'teacher_id'    => $teacher->id,
                'status'        => 'Active',
                'full_name'     => $data['first_name'] . ' ' . $data['last_name'],
                'phone'         => $data['phone_number'] ?? null,
                'image'         => $imagePath,
            ]);
            
            return $teacher->load(['major', 'subject']);
            
            
        });
    }

    public function update(int $id, array $data): Teacher
    {
        return $this->trace(__FUNCTION__, function () use ($id, $data): Teacher {
            $teacher = Teacher::findOrFail($id);
            $oldUsername = $teacher->username;

            // Handle image upload if provided as file
            if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
                $data['image'] = $this->uploadImage($data['image']);
            }

            // Hash password if provided
            if (!empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }

            // Sync with User table if username or name changed
            $user = User::where('username', $oldUsername)->first();
            
            $teacher->update($data);

            if ($user) {
                $userUpdates = ['teacher_id' => $teacher->id];
                if (isset($data['username'])) $userUpdates['username'] = $data['username'];
                if (!empty($data['password'])) $userUpdates['password_hash'] = $data['password'];
                if (isset($data['first_name']) || isset($data['last_name'])) {
                    $userUpdates['full_name'] = ($data['first_name'] ?? $teacher->first_name) . ' ' . ($data['last_name'] ?? $teacher->last_name);
                }
                if (isset($data['phone_number'])) $userUpdates['phone'] = $data['phone_number'];
                if (isset($data['image'])) $userUpdates['image'] = $data['image'];

                if (!empty($userUpdates)) {
                    $user->update($userUpdates);
                }
            }

            return $teacher->fresh(['major', 'subject']);
        });
    }

    public function verifyOtp(string $email, string $otpCode): Teacher
    {
        return $this->trace(__FUNCTION__, function () use ($email, $otpCode): Teacher {
            $teacher = $this->findTeacherByEmail($email);
            
            if ($teacher->is_verified) {
                return $teacher->load(['major', 'subject']);
            }
            
            if ($teacher->otp_code !== $otpCode) {
                Log::warning('Teacher OTP verification failed: invalid code.', [
                    'email' => strtolower($email),
                ]);
                throw new ApiException(ResponseStatus::BAD_REQUEST, 'Invalid OTP code.');
            }
            
            if (!$teacher->otp_expires_at || $teacher->otp_expires_at->isPast()) {
                Log::warning('Teacher OTP verification failed: code expired.', [
                    'email' => strtolower($email),
                ]);
                throw new ApiException(ResponseStatus::BAD_REQUEST, 'OTP code has expired.');
            }
            
            $teacher->update([
                'is_verified' => true,
                'verified_at' => now(),
                'otp_code' => null,
                'otp_expires_at' => null,
            ]);
            
            return $teacher->refresh()->load(['major', 'subject']);
            
            
        });
    }

    public function resendOtp(string $email): Teacher
    {
        return $this->trace(__FUNCTION__, function () use ($email): Teacher {
            $teacher = $this->findTeacherByEmail($email);
            
            if ($teacher->is_verified) {
                Log::warning('Teacher resend OTP rejected: account already verified.', [
                    'email' => strtolower($email),
                ]);
                throw new ApiException(ResponseStatus::BAD_REQUEST, 'Teacher account is already verified.');
            }
            
            $otpCode = $this->generateOtpCode();
            
            $teacher->update([
                'otp_code' => $otpCode,
                'otp_expires_at' => now()->addSeconds($this->otpTtl()),
            ]);
            
            $this->sendOtp($teacher, $otpCode);
            
            return $teacher->refresh()->load(['major', 'subject']);
            
            
        });
    }

    public function login(string $login, string $password, string $ip, ?string $userAgent): array
    {
        return $this->trace(__FUNCTION__, function () use ($login, $password, $ip, $userAgent): array {
            $teacher = Teacher::query()
                ->where('email', strtolower($login))
                ->orWhere('username', $login)
                ->first();
            
            if (!$teacher || !Hash::check($password, (string) $teacher->password)) {
                Log::warning('Teacher login failed: account not found or password invalid.', [
                    'login' => $login,
                    'ip' => $ip,
                ]);
                throw new AuthenticationException('account not exist');
            }
            
            return $this->issueTokens($teacher, $ip, $userAgent);
            
            
        });
    }

    public function refresh(string $refreshToken, string $ip, ?string $userAgent): array
    {
        return $this->trace(__FUNCTION__, function () use ($refreshToken, $ip, $userAgent): array {
            $token = $this->getRefreshTokenRecord($refreshToken);
            
            if (!$token || $token->isExpired() || $token->isRevoked()) {
                Log::warning('Teacher refresh failed: invalid refresh token.', [
                    'ip' => $ip,
                ]);
                throw new AuthenticationException('Invalid refresh token.');
            }
            
            $teacher = $token->teacher;
            
            if (!$teacher) {
                Log::warning('Teacher refresh failed: teacher not found.', [
                    'ip' => $ip,
                ]);
                throw new AuthorizationException('Teacher account not found.');
            }
            
            return DB::transaction(function () use ($token, $teacher, $ip, $userAgent) {
                $token->update([
                    'revoked_at' => now(),
                    'last_used_at' => now(),
                ]);
            
                return $this->issueTokens($teacher, $ip, $userAgent);
            });
            
            
        });
    }

    public function logout(Teacher $teacher, ?string $refreshToken): void
    {
        $this->trace(__FUNCTION__, function () use ($teacher, $refreshToken) {
            if ($refreshToken) {
                $this->revokeRefreshToken($teacher, $refreshToken);
            }
            
            
        });
    }

    public function logoutAll(Teacher $teacher): int
    {
        return $this->trace(__FUNCTION__, function () use ($teacher): int {
            return TeacherRefreshToken::where('teacher_id', $teacher->id)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => now(),
                    'last_used_at' => now(),
                ]);
            
            
        });
    }

    public function revokeRefreshToken(Teacher $teacher, string $refreshToken): bool
    {
        return $this->trace(__FUNCTION__, function () use ($teacher, $refreshToken): bool {
            $token = $this->getRefreshTokenRecord($refreshToken, $teacher->id);
            
            if (!$token || $token->isRevoked()) {
                return false;
            }
            
            $token->update([
                'revoked_at' => now(),
                'last_used_at' => now(),
            ]);
            
            return true;
            
            
        });
    }

    public function revokeRefreshTokenOrFail(Teacher $teacher, string $refreshToken): void
    {
        $this->trace(__FUNCTION__, function () use ($teacher, $refreshToken) {
            $revoked = $this->revokeRefreshToken($teacher, $refreshToken);
            
            if (!$revoked) {
                Log::warning('Teacher revoke refresh token failed: token not found.', [
                    'teacher_id' => $teacher->id,
                ]);
                throw new ApiException(ResponseStatus::NOT_FOUND, 'Refresh token not found.');
            }
            
            
        });
    }

    private function issueTokens(Teacher $teacher, string $ip, ?string $userAgent): array
    {
        return DB::transaction(function () use ($teacher, $ip, $userAgent) {
            TeacherRefreshToken::where('teacher_id', $teacher->id)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => now(),
                    'last_used_at' => now(),
                ]);

            $access = $this->jwt->issueAccessToken($teacher, 'teacher');
            $refreshPlain = bin2hex(random_bytes(64));

            TeacherRefreshToken::create([
                'teacher_id' => $teacher->id,
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
                'teacher' => $teacher->load(['major', 'subject']),
            ];
        });
    }

    private function getRefreshTokenRecord(string $refreshToken, ?int $teacherId = null): ?TeacherRefreshToken
    {
        $hash = hash('sha256', $refreshToken);
        $query = TeacherRefreshToken::where('token_hash', $hash)->with('teacher');

        if ($teacherId !== null) {
            $query->where('teacher_id', $teacherId);
        }

        return $query->first();
    }

    private function findTeacherByEmail(string $email): Teacher
    {
        $teacher = Teacher::where('email', strtolower($email))->first();

        if (!$teacher) {
            Log::warning('Teacher lookup by email failed: not found.', [
                'email' => strtolower($email),
            ]);
            throw new ApiException(ResponseStatus::NOT_FOUND, 'Teacher not found.');
        }

        return $teacher;
    }

    private function sendOtp(Teacher $teacher, string $otpCode): void
    {
        $minutes = (int) ceil($this->otpTtl() / 60);

        Mail::to($teacher->email)->send(new TeacherOtpMail($teacher, $otpCode, $minutes));
    }

    private function generateOtpCode(): string
    {
        return (string) random_int(100000, 999999);
    }

    private function otpTtl(): int
    {
        return max((int) config('teacher_auth.otp_ttl', 600), 300);
    }

    public function uploadImage(UploadedFile $file): string
    {
        return $this->trace(__FUNCTION__, function () use ($file): string {
            $this->ensureCloudinaryConfigured();
            $uploadOptions = $this->buildCloudinaryUploadOptions('teachers');

            try {
                $result = \CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary::uploadApi()->upload(
                    $file->getRealPath(),
                    $uploadOptions
                );
                $url = $result['secure_url'] ?? null;
            } catch (\Throwable $e) {
                Log::error(
                    'Teacher image upload failed on Cloudinary.',
                    $this->buildCloudinaryExceptionContext($e, $file, $uploadOptions)
                );

                throw new ApiException(ResponseStatus::INTERNAL_SERVER_ERROR, 'Failed to upload image.');
            }

            if (!$url) {
                Log::error('Teacher image upload failed: Cloudinary returned empty URL.');
                throw new ApiException(ResponseStatus::INTERNAL_SERVER_ERROR, 'Failed to upload image.');
            }

            return $url;
            
            
        });
    }

    public function uploadImageOrFail(UploadedFile $file): string
    {
        return $this->uploadImage($file);
    }

    private function storeImage(mixed $image): ?string
    {
        if (is_string($image)) {
            return $image;
        }

        if (!$image instanceof UploadedFile) {
            return null;
        }

        return $this->uploadImage($image);
    }

    private function ensureCloudinaryConfigured(): void
    {
        $cloudUrl = (string) config('cloudinary.cloud_url', '');

        if ($cloudUrl === '' || str_contains($cloudUrl, 'API_KEY:API_SECRET@CLOUD_NAME')) {
            Log::error('Cloudinary is not configured for teacher uploads. Set CLOUDINARY_URL in .env and clear config cache.');
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
        UploadedFile $file,
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



