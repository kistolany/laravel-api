<?php

namespace App\Http\Controllers\ApiController;

use App\Http\Controllers\Controller;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Services\AuthService;
use App\Traits\ApiResponseTrait;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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

    public function register(Request $request): JsonResponse
    {
        try {
            $data = $this->validatePayload($request, [
                'username' => 'required|string|max:255|unique:users,username',
                'password' => 'required|string|min:6|max:255',
            ]);

            $tokens = $this->service->register($data, $request->ip(), $request->userAgent());
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), ResponseStatus::INTERNAL_SERVER_ERROR);
        } catch (ApiException $e) {
            return $e->render($request);
        }

        return $this->success($tokens, 'Registered successfully.');
    }

    public function createUser(Request $request): JsonResponse
    {
        try {
            $data = $this->validatePayload($request, [
                'username' => 'required|string|max:255|unique:users,username',
                'password' => 'required|string|min:6|max:255',
                'role_id' => 'required|integer|exists:roles,id',
                'status' => 'nullable|in:Active,Inactive',
            ]);

            $user = $this->service->createUserByAdmin($data);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), ResponseStatus::BAD_REQUEST);
        } catch (ApiException $e) {
            return $e->render($request);
        }

        return $this->success([
            'id' => $user->id,
            'username' => $user->username,
            'status' => $user->status,
            'role' => $user->role?->name,
        ], 'User created successfully.');
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        try {
            $data = $this->validatePayload($request, [
                'status' => 'required|in:Active,Inactive',
            ]);

            $user = $this->service->updateStatus($id, $data['status']);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), ResponseStatus::BAD_REQUEST);
        } catch (ApiException $e) {
            return $e->render($request);
        }

        return $this->success([
            'id' => $user->id,
            'username' => $user->username,
            'status' => $user->status,
            'role' => $user->role?->name,
        ], 'User status updated successfully.');
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

    public function login(Request $request): JsonResponse
    {
        try {
            $data = $this->validatePayload($request, [
                'username' => 'required|string',
                'password' => 'required|string',
            ]);

            $tokens = $this->service->login(
                $data['username'],
                $data['password'],
                $request->ip(),
                $request->userAgent()
            );
        } catch (AuthenticationException) {
            return $this->error('Invalid credentials.', ResponseStatus::UNAUTHORIZED);
        } catch (AuthorizationException) {
            return $this->error('Account is inactive.', ResponseStatus::FORBIDDEN);
        } catch (ApiException $e) {
            return $e->render($request);
        }

        return $this->success($tokens, 'Login successful.');
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
        } catch (AuthorizationException) {
            return $this->error('Account is inactive.', ResponseStatus::FORBIDDEN);
        } catch (ApiException $e) {
            return $e->render($request);
        }

        return $this->success($tokens, 'Token refreshed.');
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

        return $this->success(null, 'Logged out.');
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $this->service->logoutAll($request->user());

        return $this->success(null, 'Logged out from all devices.');
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

        return $this->success(null, 'Refresh token revoked.');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing('role.permissions');

        return $this->success([
            'id' => $user->id,
            'username' => $user->username,
            'status' => $user->status,
            'role' => $user->role?->name,
            'permissions' => $user->role?->permissions->pluck('name')->values(),
        ], 'User retrieved successfully.');
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
