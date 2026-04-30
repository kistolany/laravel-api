<?php

namespace App\Http\Controllers\ApiController\Auth;

use App\Enums\ResponseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AuthRequest;
use App\Http\Resources\Auth\AuthUserResource;
use App\Services\Auth\AuthService;
use App\Traits\ApiResponseTrait;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private AuthService $service
    ) {}

    public function index(): JsonResponse
    {
        return $this->success($this->service->listUsers(), 'Users retrieved successfully.');
    }

    public function register(AuthRequest $request): JsonResponse
    {
        return $this->success(
            $this->service->register($request->validated(), $request->ip(), $request->userAgent()),
            'Registered successfully.'
        );
    }

    public function createUser(AuthRequest $request): JsonResponse
    {
        $data = $this->service->normalizeCreateUserData($request->validated(), $request->file('image'));

        return $this->success(
            AuthUserResource::user($this->service->createUserByAdmin($data)),
            'User created successfully.'
        );
    }

    public function updateUser(AuthRequest $request, int $id): JsonResponse
    {
        $data = $this->service->normalizeUpdateUserData($request->validated(), $request->file('image'));

        return $this->success(
            AuthUserResource::user($this->service->updateUserByAdmin($id, $data)),
            'User updated successfully.'
        );
    }

    public function updateProfile(AuthRequest $request): JsonResponse
    {
        $data = $this->service->normalizeUpdateProfileData($request->validated(), $request->file('image'));

        return $this->success(
            AuthUserResource::profile($this->service->updateOwnProfile($request->user(), $data)),
            'Profile updated successfully.'
        );
    }

    public function updateStatus(AuthRequest $request, int $id): JsonResponse
    {
        return $this->success(
            AuthUserResource::status($this->service->updateStatus($id, $request->validated('status'))),
            'User status updated successfully.'
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->service->deleteUser($id, $request->user());

        return $this->success(null, 'User deleted successfully.');
    }

    public function login(AuthRequest $request): JsonResponse
    {
        try {
            $tokens = $this->service->login(
                $request->validated('username'),
                $request->validated('password'),
                $request->ip(),
                $request->userAgent()
            );
        } catch (AuthenticationException $e) {
            return $this->error($e->getMessage(), ResponseStatus::UNAUTHORIZED);
        } catch (AuthorizationException) {
            return $this->error('Account is inactive.', ResponseStatus::FORBIDDEN);
        }

        return $this->success($tokens, 'Login successful.');
    }

    public function refresh(AuthRequest $request): JsonResponse
    {
        try {
            $tokens = $this->service->refresh(
                $request->validated('refresh_token'),
                $request->ip(),
                $request->userAgent()
            );
        } catch (AuthenticationException) {
            return $this->error('Invalid refresh token.', ResponseStatus::UNAUTHORIZED);
        } catch (AuthorizationException) {
            return $this->error('Account is inactive.', ResponseStatus::FORBIDDEN);
        }

        return $this->success($tokens, 'Token refreshed.');
    }

    public function logout(AuthRequest $request): JsonResponse
    {
        $this->service->logout($request->user(), $request->validated('refresh_token'));

        return $this->success(null, 'Logged out.');
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $this->service->logoutAll($request->user());

        return $this->success(null, 'Logged out from all devices.');
    }

    public function revoke(AuthRequest $request): JsonResponse
    {
        $this->service->revokeRefreshTokenOrFail($request->user(), $request->validated('refresh_token'));

        return $this->success(null, 'Refresh token revoked.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success(
            AuthUserResource::profile($this->service->profile($request->user())),
            'User retrieved successfully.'
        );
    }
}
