<?php

namespace App\Http\Controllers\ApiController;

use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\TeacherLoginRequest;
use App\Http\Requests\TeacherRegisterRequest;
use App\Http\Requests\TeacherResendOtpRequest;
use App\Http\Requests\TeacherVerifyOtpRequest;
use App\Http\Resources\TeacherResource;
use App\Services\TeacherAuthService;
use App\Traits\ApiResponseTrait;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TeacherAuthController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private TeacherAuthService $service)
    {
    }

    public function register(TeacherRegisterRequest $request): JsonResponse
    {
        try {
            $teacher = $this->service->register($request->validated());
        } catch (ApiException $e) {
            return $e->render($request);
        }

        return $this->success([
            'teacher' => new TeacherResource($teacher),
        ], 'Teacher registered successfully. OTP has been sent to email.');
    }

    public function verifyOtp(TeacherVerifyOtpRequest $request): JsonResponse
    {
        try {
            $teacher = $this->service->verifyOtp(
                $request->validated('email'),
                $request->validated('otp_code')
            );
        } catch (ApiException $e) {
            return $e->render($request);
        }

        return $this->success([
            'teacher' => new TeacherResource($teacher),
        ], 'Teacher email verified successfully.');
    }

    public function resendOtp(TeacherResendOtpRequest $request): JsonResponse
    {
        try {
            $teacher = $this->service->resendOtp($request->validated('email'));
        } catch (ApiException $e) {
            return $e->render($request);
        }

        return $this->success([
            'teacher' => new TeacherResource($teacher),
        ], 'OTP has been resent successfully.');
    }

    public function login(TeacherLoginRequest $request): JsonResponse
    {
        try {
            $tokens = $this->service->login(
                $request->validated('login'),
                $request->validated('password'),
                $request->ip(),
                $request->userAgent()
            );
        } catch (AuthenticationException) {
            return $this->error('Invalid credentials.', ResponseStatus::UNAUTHORIZED);
        } catch (AuthorizationException $e) {
            return $this->error($e->getMessage(), ResponseStatus::FORBIDDEN);
        } catch (ApiException $e) {
            return $e->render($request);
        }

        return $this->success([
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'token_type' => $tokens['token_type'],
            'expires_in' => $tokens['expires_in'],
            'teacher' => new TeacherResource($tokens['teacher']),
        ], 'Teacher login successful.');
    }

    public function refresh(Request $request): JsonResponse
    {
        try {
            $data = $this->validatePayload($request, [
                'refresh_token' => 'required|string',
            ]);

            $tokens = $this->service->refresh(
                $data['refresh_token'],
                $request->ip(),
                $request->userAgent()
            );
        } catch (AuthenticationException) {
            return $this->error('Invalid refresh token.', ResponseStatus::UNAUTHORIZED);
        } catch (AuthorizationException $e) {
            return $this->error($e->getMessage(), ResponseStatus::FORBIDDEN);
        } catch (ApiException $e) {
            return $e->render($request);
        }

        return $this->success([
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'token_type' => $tokens['token_type'],
            'expires_in' => $tokens['expires_in'],
            'teacher' => new TeacherResource($tokens['teacher']),
        ], 'Teacher token refreshed.');
    }

    public function me(Request $request): JsonResponse
    {
        $teacher = $request->user()->loadMissing(['major', 'subject']);

        return $this->success(new TeacherResource($teacher), 'Teacher retrieved successfully.');
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            $data = $this->validatePayload($request, [
                'refresh_token' => 'nullable|string',
            ]);
        } catch (ApiException $e) {
            return $e->render($request);
        }

        $this->service->logout($request->user(), $data['refresh_token'] ?? null);

        return $this->success(null, 'Teacher logged out.');
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $this->service->logoutAll($request->user());

        return $this->success(null, 'Teacher logged out from all devices.');
    }

    public function revoke(Request $request): JsonResponse
    {
        try {
            $data = $this->validatePayload($request, [
                'refresh_token' => 'required|string',
            ]);
        } catch (ApiException $e) {
            return $e->render($request);
        }

        $revoked = $this->service->revokeRefreshToken($request->user(), $data['refresh_token']);

        if (!$revoked) {
            return $this->error('Refresh token not found.', ResponseStatus::NOT_FOUND);
        }

        return $this->success(null, 'Teacher refresh token revoked.');
    }

    private function validatePayload(Request $request, array $rules): array
    {
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            throw new ApiException(
                ResponseStatus::BAD_REQUEST,
                'Validation failed.',
                data: ['errors' => $validator->errors()]
            );
        }

        return $validator->validated();
    }
}
