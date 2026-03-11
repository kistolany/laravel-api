<?php

namespace App\Services;

use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Mail\TeacherOtpMail;
use App\Models\Teacher;
use App\Models\TeacherRefreshToken;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class TeacherAuthService
{
    public function __construct(private JwtService $jwt)
    {
    }

    public function register(array $data): Teacher
    {
        $imagePath = $this->storeImage($data['image'] ?? null);
        $otpCode = $this->generateOtpCode();

        $teacher = Teacher::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'gender' => $data['gender'],
            'major_id' => (int) $data['major_id'],
            'subject_id' => (int) $data['subject_id'],
            'email' => strtolower($data['email']),
            'username' => $data['username'],
            'password' => Hash::make($data['password']),
            'phone_number' => $data['phone_number'] ?? null,
            'telegram' => $data['telegram'] ?? null,
            'image' => $imagePath,
            'address' => $data['address'],
            'role' => 'Teacher',
            'otp_code' => $otpCode,
            'otp_expires_at' => now()->addSeconds($this->otpTtl()),
            'is_verified' => false,
        ]);

        $this->sendOtp($teacher, $otpCode);

        return $teacher->load(['major', 'subject']);
    }

    public function verifyOtp(string $email, string $otpCode): Teacher
    {
        $teacher = $this->findTeacherByEmail($email);

        if ($teacher->is_verified) {
            return $teacher->load(['major', 'subject']);
        }

        if ($teacher->otp_code !== $otpCode) {
            throw new ApiException(ResponseStatus::BAD_REQUEST, 'Invalid OTP code.');
        }

        if (!$teacher->otp_expires_at || $teacher->otp_expires_at->isPast()) {
            throw new ApiException(ResponseStatus::BAD_REQUEST, 'OTP code has expired.');
        }

        $teacher->update([
            'is_verified' => true,
            'verified_at' => now(),
            'otp_code' => null,
            'otp_expires_at' => null,
        ]);

        return $teacher->refresh()->load(['major', 'subject']);
    }

    public function resendOtp(string $email): Teacher
    {
        $teacher = $this->findTeacherByEmail($email);

        if ($teacher->is_verified) {
            throw new ApiException(ResponseStatus::BAD_REQUEST, 'Teacher account is already verified.');
        }

        $otpCode = $this->generateOtpCode();

        $teacher->update([
            'otp_code' => $otpCode,
            'otp_expires_at' => now()->addSeconds($this->otpTtl()),
        ]);

        $this->sendOtp($teacher, $otpCode);

        return $teacher->refresh()->load(['major', 'subject']);
    }

    public function login(string $login, string $password, string $ip, ?string $userAgent): array
    {
        $teacher = Teacher::query()
            ->where('email', strtolower($login))
            ->orWhere('username', $login)
            ->first();

        if (!$teacher || !Hash::check($password, (string) $teacher->password)) {
            throw new AuthenticationException('Invalid credentials.');
        }

        if (!$teacher->is_verified) {
            throw new AuthorizationException('Teacher email is not verified.');
        }

        return $this->issueTokens($teacher, $ip, $userAgent);
    }

    public function refresh(string $refreshToken, string $ip, ?string $userAgent): array
    {
        $token = $this->getRefreshTokenRecord($refreshToken);

        if (!$token || $token->isExpired() || $token->isRevoked()) {
            throw new AuthenticationException('Invalid refresh token.');
        }

        $teacher = $token->teacher;

        if (!$teacher || !$teacher->is_verified) {
            throw new AuthorizationException('Teacher account is not verified.');
        }

        return DB::transaction(function () use ($token, $teacher, $ip, $userAgent) {
            $token->update([
                'revoked_at' => now(),
                'last_used_at' => now(),
            ]);

            return $this->issueTokens($teacher, $ip, $userAgent);
        });
    }

    public function logout(Teacher $teacher, ?string $refreshToken): void
    {
        if ($refreshToken) {
            $this->revokeRefreshToken($teacher, $refreshToken);
        }
    }

    public function logoutAll(Teacher $teacher): int
    {
        return TeacherRefreshToken::where('teacher_id', $teacher->id)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
                'last_used_at' => now(),
            ]);
    }

    public function revokeRefreshToken(Teacher $teacher, string $refreshToken): bool
    {
        $token = $this->getRefreshTokenRecord($refreshToken, $teacher->id);

        if (!$token || $token->isRevoked()) {
            return false;
        }

        $token->update([
            'revoked_at' => now(),
            'last_used_at' => now(),
        ]);

        return true;
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

    private function storeImage(mixed $image): ?string
    {
        if (!$image instanceof UploadedFile) {
            return null;
        }

        return $image->store('teacher-images', 'public');
    }
}
