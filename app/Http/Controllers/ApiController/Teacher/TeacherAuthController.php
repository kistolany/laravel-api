<?php

namespace App\Http\Controllers\ApiController\Teacher;

use App\Enums\ResponseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\TeacherArchiveRequest;
use App\Http\Requests\Teacher\TeacherIndexRequest;
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

    public function __construct(
        private TeacherAuthService $service
    ) {}

    public function index(TeacherIndexRequest $request): JsonResponse
    {
        return $this->success(
            TeacherResource::collection($this->service->index($request->validated())),
            'Teachers retrieved successfully.'
        );
    }

    public function archived(): JsonResponse
    {
        return $this->success(
            TeacherResource::collection($this->service->archived()),
            'Archived teachers retrieved successfully.'
        );
    }

    public function destroy(TeacherArchiveRequest $request, int $id): JsonResponse
    {
        $this->service->archive($id, $request->user()?->id, $request->validated('delete_reason'));

        return $this->success(null, 'Teacher archived successfully.');
    }

    public function restore(int $id): JsonResponse
    {
        return $this->success(
            new TeacherResource($this->service->restore($id)),
            'Teacher restored successfully.'
        );
    }

    public function register(TeacherRequest $request): JsonResponse
    {
        return $this->success(
            TeacherAuthResource::teacher($this->service->register($request->validated())),
            'Teacher registered successfully.'
        );
    }

    public function update(TeacherRequest $request, int $id): JsonResponse
    {
        return $this->success(
            new TeacherResource($this->service->updateForUser($id, $request->validated(), $request->user())),
            'Teacher updated successfully.'
        );
    }

    public function uploadImage(TeacherRequest $request): JsonResponse
    {
        return $this->success(
            TeacherAuthResource::uploadImage($this->service->uploadImageOrFail($request->file('image'))),
            'Image uploaded successfully.'
        );
    }

    public function verifyOtp(TeacherRequest $request): JsonResponse
    {
        return $this->success(
            TeacherAuthResource::teacher(
                $this->service->verifyOtp($request->validated('email'), $request->validated('otp_code'))
            ),
            'Teacher email verified successfully.'
        );
    }

    public function resendOtp(TeacherRequest $request): JsonResponse
    {
        return $this->success(
            TeacherAuthResource::teacher($this->service->resendOtp($request->validated('email'))),
            'OTP has been resent successfully.'
        );
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
        }

        return $this->success(TeacherAuthResource::tokens($tokens), 'Teacher login successful.');
    }

    public function refresh(TeacherRequest $request): JsonResponse
    {
        try {
            $tokens = $this->service->refresh(
                $request->validated('refresh_token'),
                $request->ip(),
                $request->userAgent()
            );
        } catch (AuthenticationException) {
            return $this->error('Invalid refresh token.', ResponseStatus::UNAUTHORIZED);
        } catch (AuthorizationException $e) {
            return $this->error($e->getMessage(), ResponseStatus::FORBIDDEN);
        }

        return $this->success(TeacherAuthResource::tokens($tokens), 'Teacher token refreshed.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success(
            new TeacherResource($request->user()->loadMissing(['major', 'subject'])),
            'Teacher retrieved successfully.'
        );
    }

    public function logout(TeacherRequest $request): JsonResponse
    {
        $this->service->logout($request->user(), $request->validated('refresh_token'));

        return $this->success(null, 'Teacher logged out.');
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $this->service->logoutAll($request->user());

        return $this->success(null, 'Teacher logged out from all devices.');
    }

    public function revoke(TeacherRequest $request): JsonResponse
    {
        $this->service->revokeRefreshTokenOrFail($request->user(), $request->validated('refresh_token'));

        return $this->success(null, 'Teacher refresh token revoked.');
    }
}
