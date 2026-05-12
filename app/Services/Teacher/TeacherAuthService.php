<?php

namespace App\Services\Teacher;

use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Models\Teacher;
use App\Models\User;
use App\Models\Role;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\Concerns\ServiceTraceable;
class TeacherAuthService
{
    use ServiceTraceable;

    public function register(array $data): Teacher
    {
        return $this->trace(__FUNCTION__, function () use ($data): Teacher {
            return DB::transaction(function () use ($data): Teacher {
                $imagePath      = $this->storeImage($data['image'] ?? null);
                $cvFilePath     = $this->storeFile($data['cv_file']      ?? null, 'teacher_cv');
                $idCardFilePath = $this->storeFile($data['id_card_file'] ?? null, 'teacher_id_cards');
                $createLogin = !empty($data['username']) && !empty($data['password']);
                $internalUsername = $createLogin
                    ? $data['username']
                    : $this->generateInternalUsername($data);
                $status = $this->normalizeStatus($data['status'] ?? 'active');

                // Create Teacher record. Login credentials are optional; a User can be linked later.
                $teacher = Teacher::create([
                    // core
                    'teacher_id'      => $data['teacher_id']      ?? null,
                    'first_name'      => $data['first_name'],
                    'last_name'       => $data['last_name'],
                    'gender'          => $data['gender'],
                    'major_id'        => (int) $data['major_id'],
                    'subject_id'      => isset($data['subject_id']) ? (int) $data['subject_id'] : null,
                    'email'           => strtolower($data['email']),
                    'username'        => $internalUsername,
                    'password'        => $createLogin ? Hash::make($data['password']) : Hash::make(Str::random(48)),
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
                    'cv_file'         => $cvFilePath,
                    'id_card_file'    => $idCardFilePath,
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
                    'status'          => $status,
                ]);

                if ($createLogin) {
                    $teacherRole = Role::where('name', 'Teacher')->first();
                    $roleId = $data['role_id'] ?? $teacherRole?->id ?? 3;

                    User::create([
                        'username'      => $data['username'],
                        'password_hash' => Hash::make($data['password']),
                        'role_id'       => $roleId,
                        'teacher_id'    => $teacher->id,
                        'status'        => $this->userStatusFromTeacherStatus($status),
                        'full_name'     => $data['first_name'] . ' ' . $data['last_name'],
                        'phone'         => $data['phone_number'] ?? null,
                        'image'         => $imagePath,
                    ]);
                }

                return $teacher->load(['major', 'subject']);
            });
        });
    }

    public function index(array $filters = []): Collection
    {
        return $this->trace(__FUNCTION__, function () use ($filters): Collection {
            $archived = filter_var($filters['archived'] ?? false, FILTER_VALIDATE_BOOLEAN);

            return Teacher::with(['major', 'subject'])
                ->when($archived, fn ($query) => $query->onlyTrashed())
                ->when(isset($filters['status']), function ($query) use ($filters) {
                    $status = strtolower((string) $filters['status']);
                    $query->where('status', $status === 'inactive' ? 'inactive' : 'active');
                }, function ($query) use ($archived) {
                    if (!$archived) {
                        $query->where('status', 'active');
                    }
                })
                ->orderBy('created_at', 'desc')
                ->get();
        });
    }

    public function archived(): Collection
    {
        return $this->trace(__FUNCTION__, function (): Collection {
            return Teacher::with(['major', 'subject'])
                ->onlyTrashed()
                ->orderBy('deleted_at', 'desc')
                ->get();
        });
    }

    private function generateInternalUsername(array $data): string
    {
        $base = 'teacher-profile-' . Str::slug((string) ($data['teacher_id'] ?? ''));
        if ($base === 'teacher-profile-') {
            $base .= Str::lower(Str::random(10));
        }

        $candidate = $base;
        $counter = 1;

        while (Teacher::where('username', $candidate)->exists() || User::where('username', $candidate)->exists()) {
            $candidate = $base . '-' . $counter;
            $counter++;
        }

        return $candidate;
    }

    private function normalizeStatus(?string $status): string
    {
        $value = strtolower(trim((string) ($status ?: 'active')));

        return in_array($value, ['inactive', 'disable'], true) ? 'inactive' : 'active';
    }

    private function userStatusFromTeacherStatus(string $status): string
    {
        return $this->normalizeStatus($status) === 'inactive' ? 'Inactive' : 'Active';
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

            // Handle CV and ID card uploads
            if (isset($data['cv_file']) && $data['cv_file'] instanceof UploadedFile) {
                $data['cv_file'] = $this->uploadFile($data['cv_file'], 'teacher_cv');
            } elseif (array_key_exists('cv_file', $data) && !$data['cv_file'] instanceof UploadedFile) {
                unset($data['cv_file']);
            }

            if (isset($data['id_card_file']) && $data['id_card_file'] instanceof UploadedFile) {
                $data['id_card_file'] = $this->uploadFile($data['id_card_file'], 'teacher_id_cards');
            } elseif (array_key_exists('id_card_file', $data) && !$data['id_card_file'] instanceof UploadedFile) {
                unset($data['id_card_file']);
            }

            // Hash password if provided
            if (!empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }

            if (array_key_exists('status', $data)) {
                $data['status'] = $this->normalizeStatus($data['status']);
            }

            // Sync with a linked User account when one exists. Profile-only teachers may not have a username.
            $user = $oldUsername
                ? User::where('username', $oldUsername)->first()
                : User::where('teacher_id', $teacher->id)->first();
            
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
                if (isset($data['status'])) $userUpdates['status'] = $this->userStatusFromTeacherStatus($data['status']);

                if (!empty($userUpdates)) {
                    $user->update($userUpdates);
                }
            }

            return $teacher->fresh(['major', 'subject']);
        });
    }

    public function updateForUser(int $id, array $data, ?Authenticatable $user): Teacher
    {
        return $this->trace(__FUNCTION__, function () use ($id, $data, $user): Teacher {
            if (!$user || !method_exists($user, 'hasPermission') || !$user->hasPermission('teacher.update')) {
                $data = array_intersect_key($data, array_flip(['status']));
            }

            return $this->update($id, $data);
        });
    }

    public function archive(int $id, ?int $deletedBy = null, ?string $reason = null): bool
    {
        return $this->trace(__FUNCTION__, function () use ($id, $deletedBy, $reason): bool {
            return DB::transaction(function () use ($id, $deletedBy, $reason): bool {
                $teacher = Teacher::findOrFail($id);

                $teacher->forceFill([
                    'status' => 'archived',
                    'deleted_by' => $deletedBy,
                    'delete_reason' => $reason,
                ])->save();

                User::where('teacher_id', $teacher->id)->update(['status' => 'Inactive']);

                return $teacher->delete();
            });
        });
    }

    public function restore(int $id): Teacher
    {
        return $this->trace(__FUNCTION__, function () use ($id): Teacher {
            return DB::transaction(function () use ($id): Teacher {
                $teacher = Teacher::onlyTrashed()->findOrFail($id);

                $teacher->restore();
                $teacher->forceFill([
                    'status' => 'active',
                    'deleted_by' => null,
                    'delete_reason' => null,
                ])->save();

                User::where('teacher_id', $teacher->id)->update(['status' => 'Active']);

                return $teacher->fresh(['major', 'subject']);
            });
        });
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

    public function uploadFile(UploadedFile $file, string $folder = 'teacher_files'): string
    {
        return $this->trace(__FUNCTION__, function () use ($file, $folder): string {
            $this->ensureCloudinaryConfigured();
            $uploadOptions = array_merge(
                $this->buildCloudinaryUploadOptions($folder),
                ['resource_type' => 'auto']
            );

            try {
                $result = \CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary::uploadApi()->upload(
                    $file->getRealPath(),
                    $uploadOptions
                );
                $url = $result['secure_url'] ?? null;
                // If Cloudinary strips the extension (raw uploads), append it so the URL is self-describing
                if ($url && isset($result['format']) && $result['format'] !== '') {
                    $ext = strtolower($result['format']);
                    if (!str_ends_with(strtolower($url), ".{$ext}")) {
                        $url .= ".{$ext}";
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Teacher file upload failed on Cloudinary.', ['error' => $e->getMessage()]);
                throw new ApiException(ResponseStatus::INTERNAL_SERVER_ERROR, 'Failed to upload file.');
            }

            if (!$url) {
                throw new ApiException(ResponseStatus::INTERNAL_SERVER_ERROR, 'Failed to upload file.');
            }

            return $url;
        });
    }

    private function storeFile(mixed $file, string $folder): ?string
    {
        if (is_string($file)) return $file;
        if (!$file instanceof UploadedFile) return null;
        return $this->uploadFile($file, $folder);
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
            'access_mode' => 'public',
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

