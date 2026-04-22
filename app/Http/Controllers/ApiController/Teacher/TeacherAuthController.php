<?php

namespace App\Http\Controllers\ApiController\Teacher;

use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\TeacherRequest;
use App\Http\Resources\Teacher\TeacherAuthResource;
use App\Http\Resources\Teacher\TeacherResource;
use App\Services\Teacher\TeacherAuthService;
use App\Traits\ApiResponseTrait;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherAuthController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private TeacherAuthService $service)
    {
    }

    public function index(): JsonResponse
    {
        $teachers = \App\Models\Teacher::with(['major', 'subject'])
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success(TeacherResource::collection($teachers), 'Teachers retrieved successfully.');
    }

    public function destroy(int $id): JsonResponse
    {
        $teacher = \App\Models\Teacher::find($id);

        if (!$teacher) {
            return $this->error('Teacher not found.', \App\Enums\ResponseStatus::NOT_FOUND);
        }

        $teacher->delete();

        return $this->success(null, 'Teacher deleted successfully.');
    }

    public function register(TeacherRequest $request): JsonResponse
    {
        try {
            $teacher = $this->service->register($request->validated());
        } catch (ApiException $e) {
            return $e->render($request);
        }

        return $this->success(TeacherAuthResource::teacher($teacher), 'Teacher registered successfully.');
    }

    public function update(TeacherRequest $request, int $id): JsonResponse
    {
        try {
            $teacher = $this->service->update($id, $request->validated());
        } catch (ApiException $e) {
            return $e->render($request);
        }

        return $this->success(TeacherResource::collection([\App\Models\Teacher::find($id)]), 'Teacher updated successfully.');
    }

    public function uploadImage(TeacherRequest $request): JsonResponse
    {
        try {
            $url = $this->service->uploadImageOrFail($request->file('image'));
        } catch (ApiException $e) {
            return $e->render($request);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseStatus::INTERNAL_SERVER_ERROR);
        }

        return $this->success(TeacherAuthResource::uploadImage($url), 'Image uploaded successfully.');
    }

    public function verifyOtp(TeacherRequest $request): JsonResponse
    {
        try {
            $teacher = $this->service->verifyOtp(
                $request->validated('email'),
                $request->validated('otp_code')
            );
        } catch (ApiException $e) {
            return $e->render($request);
        }

        return $this->success(TeacherAuthResource::teacher($teacher), 'Teacher email verified successfully.');
    }

    public function resendOtp(TeacherRequest $request): JsonResponse
    {
        try {
            $teacher = $this->service->resendOtp($request->validated('email'));
        } catch (ApiException $e) {
            return $e->render($request);
        }

        return $this->success(TeacherAuthResource::teacher($teacher), 'OTP has been resent successfully.');
    }

    public function login(TeacherRequest $request): JsonResponse
    {
        try {
            $tokens = $this->service->login(
                $request->validated('login'),
                $request->validated('password'),
                $request->ip(),
                $request->userAgent()
            );
        } catch (AuthenticationException) {
            return $this->error('account not exist', ResponseStatus::UNAUTHORIZED);
        } catch (AuthorizationException $e) {
            return $this->error($e->getMessage(), ResponseStatus::FORBIDDEN);
        } catch (ApiException $e) {
            return $e->render($request);
        }

        return $this->success(TeacherAuthResource::tokens($tokens), 'Teacher login successful.');
    }

    public function refresh(TeacherRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

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

        return $this->success(TeacherAuthResource::tokens($tokens), 'Teacher token refreshed.');
    }

    public function me(Request $request): JsonResponse
    {
        $teacher = $request->user()->loadMissing(['major', 'subject']);

        return $this->success(new TeacherResource($teacher), 'Teacher retrieved successfully.');
    }

    public function logout(TeacherRequest $request): JsonResponse
    {
        $data = $request->validated();

        $this->service->logout($request->user(), $data['refresh_token'] ?? null);

        return $this->success(null, 'Teacher logged out.');
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $this->service->logoutAll($request->user());

        return $this->success(null, 'Teacher logged out from all devices.');
    }

    public function revoke(TeacherRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $this->service->revokeRefreshTokenOrFail($request->user(), $data['refresh_token']);
        } catch (ApiException $e) {
            return $e->render($request);
        }

        return $this->success(null, 'Teacher refresh token revoked.');
    }
}

