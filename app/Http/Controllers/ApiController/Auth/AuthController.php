<?php

namespace App\Http\Controllers\ApiController\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\Auth\AuthUserResource;
use App\Http\Requests\Auth\AuthRequest;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Services\Auth\AuthService;
use App\Traits\ApiResponseTrait;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private AuthService $service)
    {
    }

    public function index(): JsonResponse
    {
        $users = $this->service->listUsers();

        return $this->success($users, 'Users retrieved successfully.');
    }

    public function register(AuthRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            $tokens = $this->service->register($data, $request->ip(), $request->userAgent());
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), ResponseStatus::INTERNAL_SERVER_ERROR);
        } catch (ApiException $e) {
            return $e->render($request);
        }

        return $this->success($tokens, 'Registered successfully.');
    }

    public function createUser(AuthRequest $request): JsonResponse
    {
        try {
            $data = $this->service->normalizeCreateUserData(
                $request->validated(),
                $request->file('image')
            );

            $user = $this->service->createUserByAdmin($data);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), ResponseStatus::BAD_REQUEST);
        } catch (ApiException $e) {
            return $e->render($request);
        }

        return $this->success(AuthUserResource::user($user), 'User created successfully.');
    }

    public function updateUser(AuthRequest $request, int $id): JsonResponse
    {
        try {
            $data = $this->service->normalizeUpdateUserData(
                $request->validated(),
                $request->file('image')
            );

            $user = $this->service->updateUserByAdmin($id, $data);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), ResponseStatus::BAD_REQUEST);
        } catch (ApiException $e) {
            return $e->render($request);
        }

        return $this->success(AuthUserResource::user($user), 'User updated successfully.');
    }

    public function updateProfile(AuthRequest $request): JsonResponse
    {
        try {
            $data = $this->service->normalizeUpdateProfileData(
                $request->validated(),
                $request->file('image')
            );

            $user = $this->service->updateOwnProfile($request->user(), $data);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), ResponseStatus::BAD_REQUEST);
        } catch (ApiException $e) {
            return $e->render($request);
        }

        return $this->success(AuthUserResource::profile($user), 'Profile updated successfully.');
    }

    public function updateStatus(AuthRequest $request, int $id): JsonResponse
    {
        try {
            $data = $request->validated();

            $user = $this->service->updateStatus($id, $data['status']);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), ResponseStatus::BAD_REQUEST);
        } catch (ApiException $e) {
            return $e->render($request);
        }

        return $this->success(AuthUserResource::status($user), 'User status updated successfully.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->service->deleteUser($id, $request->user());
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), ResponseStatus::BAD_REQUEST);
        } catch (ApiException $e) {
            return $e->render($request);
        }

        return $this->success(null, 'User deleted successfully.');
    }

    public function login(AuthRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            $tokens = $this->service->login(
                $data['username'],
                $data['password'],
                $request->ip(),
                $request->userAgent()
            );
        } catch (AuthenticationException $e) {
            return $this->error($e->getMessage(), ResponseStatus::UNAUTHORIZED);
        } catch (AuthorizationException) {
            return $this->error('Account is inactive.', ResponseStatus::FORBIDDEN);
        } catch (ApiException $e) {
            return $e->render($request);
        }

        return $this->success($tokens, 'Login successful.');
    }

    public function refresh(AuthRequest $request): JsonResponse
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
        } catch (AuthorizationException) {
            return $this->error('Account is inactive.', ResponseStatus::FORBIDDEN);
        } catch (ApiException $e) {
            return $e->render($request);
        }

        return $this->success($tokens, 'Token refreshed.');
    }

    public function logout(AuthRequest $request): JsonResponse
    {
        $data = $request->validated();

        $this->service->logout($request->user(), $data['refresh_token'] ?? null);

        return $this->success(null, 'Logged out.');
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $this->service->logoutAll($request->user());

        return $this->success(null, 'Logged out from all devices.');
    }

    public function revoke(AuthRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $this->service->revokeRefreshTokenOrFail($request->user(), $data['refresh_token']);
        } catch (ApiException $e) {
            return $e->render($request);
        }

        return $this->success(null, 'Refresh token revoked.');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing('role.permissions');

        if (!$user->teacher_id && $user->hasRole('Teacher')) {
            $this->resolveTeacherForUser($user);
        }

        return $this->success(AuthUserResource::profile($user), 'User retrieved successfully.');
    }

    private function resolveTeacherForUser(\App\Models\User $user): ?\App\Models\Teacher
    {
        if ($user->teacher_id) {
            return \App\Models\Teacher::find($user->teacher_id);
        }

        $username = trim((string) $user->username);
        if ($username !== '') {
            $teacher = \App\Models\Teacher::where('username', $username)
                ->orWhere('email', $username)
                ->first();

            if ($teacher) {
                return $this->linkTeacherToUser($user, $teacher);
            }
        }

        $phone = trim((string) $user->phone);
        if ($phone !== '') {
            $teacher = \App\Models\Teacher::where('phone_number', $phone)->first();

            if ($teacher) {
                return $this->linkTeacherToUser($user, $teacher);
            }
        }

        $fullName = strtolower(preg_replace('/\s+/', ' ', trim((string) $user->full_name)));
        if ($fullName !== '') {
            $teacher = \App\Models\Teacher::whereRaw(
                "LOWER(TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')))) = ?",
                [$fullName]
            )->first();

            if ($teacher) {
                return $this->linkTeacherToUser($user, $teacher);
            }
        }

        return null;
    }

    private function linkTeacherToUser(\App\Models\User $user, \App\Models\Teacher $teacher): \App\Models\Teacher
    {
        $user->forceFill(['teacher_id' => $teacher->id])->save();

        return $teacher;
    }
}
