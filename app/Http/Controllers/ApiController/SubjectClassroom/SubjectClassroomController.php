<?php

namespace App\Http\Controllers\ApiController\SubjectClassroom;

use App\Http\Controllers\Controller;
use App\Models\SubjectLesson;
use App\Models\HomeworkAssignment;
use App\Models\HomeworkSubmission;
use App\Traits\ApiResponseTrait;
use App\DTOs\PaginatedResult;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SubjectClassroomController extends Controller
{
    use ApiResponseTrait;

    // ─── Helpers ──────────────────────────────────────────────────────────

    /**
     * Upload a file to Cloudinary.
     * Returns [ url, original_name, mime, size ].
     */
    private function uploadFile(Request $request, string $field, string $folder): array
    {
        $file = $request->file($field);
        $originalName = $file->getClientOriginalName();
        $mime = $file->getClientMimeType();
        $size = $file->getSize();

        $this->ensureCloudinaryConfigured();
        
        $extension = strtolower($file->getClientOriginalExtension());
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
        $resourceType = in_array($extension, $imageExtensions) ? 'image' : 'raw';

        // Options based on user feedback and best practices for documents
        $uploadOptions = [
            'folder' => $folder,
            'overwrite' => true,
            'use_filename' => true,
            'unique_filename' => true,
            'resource_type' => $resourceType,
            'type' => 'upload',
        ];

        try {
            // SDK v2 requires using the uploadApi() method
            $result = \CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary::uploadApi()->upload(
                $file->getRealPath(),
                $uploadOptions
            );
            $url = $result['secure_url'] ?? null;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Subject Classroom upload failed: " . $e->getMessage(), [
                'folder' => $folder,
                'file' => $originalName,
                'trace' => $e->getTraceAsString()
            ]);
            throw new \App\Exceptions\ApiException(\App\Enums\ResponseStatus::INTERNAL_SERVER_ERROR, 'Failed to upload to Cloudinary: ' . $e->getMessage());
        }

        if (!$url) {
            throw new \App\Exceptions\ApiException(\App\Enums\ResponseStatus::INTERNAL_SERVER_ERROR, 'Cloudinary returned empty URL.');
        }

        return [
            'url'  => $url,
            'name' => $originalName,
            'type' => $mime,
            'size' => $size,
        ];
    }

    private function ensureCloudinaryConfigured(): void
    {
        $cloudUrl = (string) config('cloudinary.cloud_url', '');
        if ($cloudUrl === '' || str_contains($cloudUrl, 'API_KEY:API_SECRET@CLOUD_NAME')) {
            throw new \App\Exceptions\ApiException(\App\Enums\ResponseStatus::BAD_REQUEST, 'Cloudinary is not configured. Set CLOUDINARY_URL in .env.');
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  LESSONS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * GET /subject-classroom/lessons
     * Query: class_id, subject_id, search, page, per_page
     */
    public function lessons(Request $request)
    {
        $query = SubjectLesson::with(['teacher:id,first_name,last_name', 'subject:id,name', 'classroom:id,name']);

        if ($request->filled('class_id')) {
            $query->where('class_id', $request->class_id);
        }
        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $perPage = $request->input('per_page', 12);
        $paginated = $query->latest()->paginate($perPage);

        return $this->success(PaginatedResult::fromPaginator($paginated), 'Lessons retrieved successfully.');
    }

    /**
     * POST /subject-classroom/lessons
     * Multipart: file (required), title, description, class_id, subject_id, lesson_date
     */
    public function storeLesson(Request $request)
    {
        $request->validate([
            'class_id'    => 'required|integer|exists:classes,id',
            'subject_id'  => 'required|integer|exists:subjects,id',
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'lesson_date' => 'nullable|date',
            'file'        => 'required|file|max:10240', // 10 MB
        ]);

        // Resolve teacher_id from authenticated user
        $teacherId = $this->resolveTeacherId($request);
        if (!$teacherId) {
            return $this->error('Could not resolve teacher identity.', \App\Enums\ResponseStatus::FORBIDDEN);
        }

        $uploaded = $this->uploadFile($request, 'file', 'lessons');

        $lesson = SubjectLesson::create([
            'class_id'    => $request->class_id,
            'subject_id'  => $request->subject_id,
            'teacher_id'  => $teacherId,
            'title'       => $request->title,
            'description' => $request->description,
            'file_url'    => $uploaded['url'],
            'file_name'   => $uploaded['name'],
            'file_type'   => $uploaded['type'],
            'file_size'   => $uploaded['size'],
            'lesson_date' => $request->lesson_date,
        ]);

        $lesson->load(['teacher:id,first_name,last_name', 'subject:id,name']);

        return $this->success($lesson, 'Lesson uploaded successfully.');
    }

    /**
     * DELETE /subject-classroom/lessons/{id}
     */
    public function destroyLesson($id)
    {
        $lesson = SubjectLesson::findOrFail($id);
        $lesson->delete();

        return $this->success(null, 'Lesson deleted successfully.');
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  HOMEWORK ASSIGNMENTS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * GET /subject-classroom/homework
     * Query: class_id, subject_id, is_active, search, page, per_page
     */
    public function homework(Request $request)
    {
        $query = HomeworkAssignment::with(['teacher:id,first_name,last_name', 'subject:id,name', 'classroom:id,name'])
            ->withCount('submissions');

        if ($request->filled('class_id')) {
            $query->where('class_id', $request->class_id);
        }
        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $perPage = $request->input('per_page', 12);
        $paginated = $query->latest()->paginate($perPage);

        // Append computed field
        $paginated->getCollection()->transform(function ($hw) {
            $hw->is_overdue = $hw->is_overdue;
            return $hw;
        });

        return $this->success(PaginatedResult::fromPaginator($paginated), 'Homework assignments retrieved successfully.');
    }

    /**
     * POST /subject-classroom/homework
     * Multipart: title, description, class_id, subject_id, due_date, max_score, attachment (optional)
     */
    public function storeHomework(Request $request)
    {
        $request->validate([
            'class_id'    => 'required|integer|exists:classes,id',
            'subject_id'  => 'required|integer|exists:subjects,id',
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date'    => 'required|date',
            'max_score'   => 'nullable|integer|min:0',
            'attachment'  => 'nullable|file|max:10240',
        ]);

        $teacherId = $this->resolveTeacherId($request);
        if (!$teacherId) {
            return $this->error('Could not resolve teacher identity.', \App\Enums\ResponseStatus::FORBIDDEN);
        }

        $data = [
            'class_id'    => $request->class_id,
            'subject_id'  => $request->subject_id,
            'teacher_id'  => $teacherId,
            'title'       => $request->title,
            'description' => $request->description,
            'due_date'    => $request->due_date,
            'max_score'   => $request->max_score,
            'is_active'   => true,
        ];

        if ($request->hasFile('attachment')) {
            $uploaded = $this->uploadFile($request, 'attachment', 'homework');
            $data['attachment_url']  = $uploaded['url'];
            $data['attachment_name'] = $uploaded['name'];
        }

        $homework = HomeworkAssignment::create($data);
        $homework->load(['teacher:id,first_name,last_name', 'subject:id,name']);

        return $this->success($homework, 'Homework assignment created successfully.');
    }

    /**
     * PUT /subject-classroom/homework/{id}
     */
    public function updateHomework(Request $request, $id)
    {
        $homework = HomeworkAssignment::findOrFail($id);

        $request->validate([
            'title'       => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'due_date'    => 'sometimes|date',
            'max_score'   => 'nullable|integer|min:0',
            'is_active'   => 'sometimes|boolean',
            'attachment'  => 'nullable|file|max:10240',
        ]);

        $homework->fill($request->only(['title', 'description', 'due_date', 'max_score', 'is_active']));

        if ($request->hasFile('attachment')) {
            $uploaded = $this->uploadFile($request, 'attachment', 'homework');
            $homework->attachment_url  = $uploaded['url'];
            $homework->attachment_name = $uploaded['name'];
        }

        $homework->save();
        $homework->load(['teacher:id,first_name,last_name', 'subject:id,name']);

        return $this->success($homework, 'Homework assignment updated successfully.');
    }

    /**
     * DELETE /subject-classroom/homework/{id}
     */
    public function destroyHomework($id)
    {
        $homework = HomeworkAssignment::findOrFail($id);
        $homework->delete();

        return $this->success(null, 'Homework assignment deleted successfully.');
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  SUBMISSIONS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * GET /subject-classroom/homework/{id}/submissions
     */
    public function submissions($id)
    {
        $homework = HomeworkAssignment::findOrFail($id);
        $subs = $homework->submissions()
            ->with('student:id,full_name_en,full_name_kh,id_card_number')
            ->latest('submitted_at')
            ->get();

        return $this->success([
            'homework' => $homework->only(['id', 'title', 'due_date', 'max_score']),
            'submissions' => $subs,
        ], 'Submissions retrieved successfully.');
    }

    /**
     * POST /subject-classroom/homework/{id}/submit
     * Student submits their homework file.
     */
    public function submitHomework(Request $request, $id)
    {
        $homework = HomeworkAssignment::findOrFail($id);

        $request->validate([
            'file' => 'required|file|max:10240',
            'note' => 'nullable|string',
        ]);

        // Resolve student_id from the authenticated user
        $studentId = $this->resolveStudentId($request);
        if (!$studentId) {
            return $this->error('Could not resolve student identity.', \App\Enums\ResponseStatus::FORBIDDEN);
        }

        // Check if already submitted (allow re-submission / update)
        $existing = HomeworkSubmission::where('homework_id', $id)
            ->where('student_id', $studentId)
            ->first();

        $uploaded = $this->uploadFile($request, 'file', 'submissions');
        $now = Carbon::now();
        $isLate = $now->greaterThan($homework->due_date);

        $submissionData = [
            'homework_id'  => $id,
            'student_id'   => $studentId,
            'file_url'     => $uploaded['url'],
            'file_name'    => $uploaded['name'],
            'file_type'    => $uploaded['type'],
            'file_size'    => $uploaded['size'],
            'note'         => $request->note,
            'submitted_at' => $now,
            'is_late'      => $isLate,
        ];

        if ($existing) {
            $existing->update($submissionData);
            $submission = $existing;
        } else {
            $submission = HomeworkSubmission::create($submissionData);
        }

        $submission->load('student:id,full_name_en,full_name_kh');

        return $this->success($submission, $existing ? 'Homework re-submitted successfully.' : 'Homework submitted successfully.');
    }

    /**
     * PATCH /subject-classroom/submissions/{id}/grade
     */
    public function gradeSubmission(Request $request, $id)
    {
        $submission = HomeworkSubmission::findOrFail($id);

        $request->validate([
            'score'    => 'required|numeric|min:0',
            'feedback' => 'nullable|string',
        ]);

        $submission->update([
            'score'    => $request->score,
            'feedback' => $request->feedback,
        ]);

        $submission->load('student:id,full_name_en,full_name_kh');

        return $this->success($submission, 'Submission graded successfully.');
    }

    // ─── Identity Resolvers ───────────────────────────────────────────────

    private function resolveTeacherId(Request $request): ?int
    {
        // Use $request->user() as it is set by the UnifiedJwtMiddleware
        $user = $request->user();

        if (!$user) {
            return null;
        }

        // 1. Directly logged in as Teacher model (teacher guard)
        if ($user instanceof \App\Models\Teacher) {
            return $user->id;
        }

        // 2. Logged in as User model (api guard)
        if ($user instanceof \App\Models\User) {
            // A. Direct teacher_id linkage on User record (e.g. Teacher using User account)
            if ($user->teacher_id) {
                return $user->teacher_id;
            }

            // B. Explicitly provided teacher_id in request (e.g. from Admin panel)
            if ($request->filled('teacher_id')) {
                return (int) $request->teacher_id;
            }

            // C. Automatic fallback to the teacher assigned to this subject in this class
            $classId = $request->class_id ?? $request->input('class_id');
            $subjectId = $request->subject_id ?? $request->input('subject_id');

            if ($classId && $subjectId) {
                $schedule = \App\Models\ClassSchedule::where('class_id', $classId)
                    ->where('subject_id', $subjectId)
                    ->first();
                
                if ($schedule && $schedule->teacher_id) {
                    return $schedule->teacher_id;
                }

                // D. Broader fallback: Any teacher assigned to this subject in ANY class
                $anySchedule = \App\Models\ClassSchedule::where('subject_id', $subjectId)
                    ->whereNotNull('teacher_id')
                    ->first();
                
                if ($anySchedule) {
                    return $anySchedule->teacher_id;
                }

                // E. Emergency fallback for Admin/Staff: First available teacher in the system
                // This ensures that Admins can always upload even if no schedule exists yet.
                $firstTeacher = \App\Models\Teacher::where('is_verified', true)->first() 
                             ?? \App\Models\Teacher::first();
                
                if ($firstTeacher) {
                    return $firstTeacher->id;
                }
            }
        }

        return null;
    }

    private function resolveStudentId(Request $request): ?int
    {
        $user = auth()->user();

        if ($user instanceof \App\Models\User) {
            if ($user->student_id) {
                return $user->student_id;
            }

            // Fallback: find student by phone
            $student = \App\Models\Students::where('phone', $user->phone)->first();
            if ($student) {
                return $student->id;
            }

            // Admin can pass student_id manually
            if ($user->hasRole('Admin') || $user->hasRole('Staff')) {
                return $request->input('student_id');
            }
        }

        return null;
    }
}
