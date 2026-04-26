<?php

namespace App\Http\Controllers\ApiController\SubjectClassroom;

use App\Http\Controllers\Controller;
use App\DTOs\PaginatedResult;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Models\ClassSchedule;
use App\Models\HomeworkAssignment;
use App\Models\HomeworkSubmission;
use App\Models\StudentScore;
use App\Models\SubjectLesson;
use App\Models\Teacher;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class SubjectClassroomController extends Controller
{
    use ApiResponseTrait;

    private function supportsHomeworkReviewState(): bool
    {
        return Schema::hasColumn('homework_submissions', 'reviewed_at');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    private function normalizeAssessmentType(?string $value): string
    {
        $type = strtolower(trim((string) ($value ?: 'homework')));

        return in_array($type, ['homework', 'assignment', 'midterm', 'final'], true)
            ? $type
            : 'homework';
    }

    private function assessmentConfig(?string $type = null): array
    {
        return match ($this->normalizeAssessmentType($type)) {
            'assignment' => [
                'type' => 'assignment',
                'label' => 'Assignment',
                'upload_folder' => 'assignments',
                'default_max_score' => 10,
                'score_field' => 'assignment_score',
            ],
            'midterm' => [
                'type' => 'midterm',
                'label' => 'Midterm',
                'upload_folder' => 'midterms',
                'default_max_score' => 20,
                'score_field' => 'midterm_score',
            ],
            'final' => [
                'type' => 'final',
                'label' => 'Final',
                'upload_folder' => 'finals',
                'default_max_score' => 50,
                'score_field' => 'final_score',
            ],
            default => [
                'type' => 'homework',
                'label' => 'Homework',
                'upload_folder' => 'homework',
                'default_max_score' => 100,
                'score_field' => null,
            ],
        };
    }

    private function ensureOfficialAssessmentSlotAvailable(string $type, int $classId, int $subjectId, ?int $ignoreId = null): void
    {
        $config = $this->assessmentConfig($type);

        if (!$config['score_field']) {
            return;
        }

        $exists = HomeworkAssignment::query()
            ->where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->where('assessment_type', $config['type'])
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw new ApiException(
                ResponseStatus::EXISTING_DATA,
                "An official {$config['label']} already exists for this class and subject. Delete it first if you need to replace it."
            );
        }
    }

    private function syncAssessmentScore(HomeworkSubmission $submission, ?float $score): void
    {
        $submission->loadMissing('assignment.classroom');

        $assignment = $submission->assignment;
        $config = $this->assessmentConfig($assignment?->assessment_type);
        $scoreField = $config['score_field'];

        if (!$assignment || !$scoreField) {
            return;
        }

        $classroom = $assignment->classroom;
        $context = [
            'student_id' => $submission->student_id,
            'class_id' => $assignment->class_id,
            'subject_id' => $assignment->subject_id,
            'academic_year' => $classroom?->academic_year,
            'year_level' => $classroom?->year_level !== null ? (string) $classroom->year_level : null,
            'semester' => $classroom?->semester !== null ? (string) $classroom->semester : null,
        ];

        $scoreRecord = StudentScore::query()->where($context)->first();

        if (!$scoreRecord && $score === null) {
            return;
        }

        $scoreRecord ??= new StudentScore($context);
        $scoreRecord->{$scoreField} = $score;
        $scoreRecord->{$scoreField . '_source'} = $score !== null ? 'classroom' : null;
        $scoreRecord->save();
    }

    private function clearAssessmentScoresForAssignment(HomeworkAssignment $assignment): void
    {
        $assignment->loadMissing('submissions');
        $config = $this->assessmentConfig($assignment->assessment_type);

        if (!$config['score_field']) {
            return;
        }

        foreach ($assignment->submissions as $submission) {
            $this->syncAssessmentScore($submission, null);
        }
    }

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

    /**
     * GET /subject-classroom/options
     *
     * Returns the class and subject contexts the current account may use.
     * Class schedules are the source of truth for teacher, subject, major,
     * year, semester, shift, and academic year.
     */
    public function options(Request $request)
    {
        $actor = $this->resolveActor($request);
        $query = $this->scheduleOptionQuery();

        if ($actor['scope'] === 'teacher') {
            $query->where('class_schedules.teacher_id', $actor['teacher_id']);
        } elseif ($actor['scope'] === 'student') {
            $this->applyStudentScheduleScope($query, $actor['student_id']);
        }

        $schedules = $query
            ->orderBy('class_schedules.academic_year')
            ->orderBy('class_schedules.year_level')
            ->orderBy('class_schedules.semester')
            ->orderBy('class_schedules.class_id')
            ->orderBy('class_schedules.subject_id')
            ->get();

        $classes = $schedules
            ->groupBy('class_id')
            ->map(function ($items) {
                $schedule = $items->first();
                $class = $schedule->classroom;

                return [
                    'id' => $schedule->class_id,
                    'name' => $class?->name ?? "Class {$schedule->class_id}",
                    'major_id' => $class?->major_id,
                    'major_name' => $class?->major?->name,
                    'shift_id' => $class?->shift_id ?? $schedule->shift_id,
                    'shift_name' => $class?->shift?->name ?? $schedule->shift?->name,
                    'academic_year' => $class?->academic_year ?? $schedule->academic_year,
                    'year_level' => $class?->year_level ?? $schedule->year_level,
                    'semester' => $class?->semester ?? $schedule->semester,
                    'subjects' => $items
                        ->unique('subject_id')
                        ->map(fn (ClassSchedule $item) => $this->scheduleSubjectOption($item))
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        return $this->success([
            'scope' => $actor['scope'],
            'can_manage' => $actor['can_manage'],
            'can_submit' => $actor['can_submit'],
            'teacher_id' => $actor['teacher_id'],
            'student_id' => $actor['student_id'],
            'classes' => $classes,
            'subjects' => $schedules
                ->unique('subject_id')
                ->map(fn (ClassSchedule $schedule) => $this->scheduleSubjectOption($schedule))
                ->values()
                ->all(),
            'schedules' => $schedules
                ->map(fn (ClassSchedule $schedule) => $this->scheduleOption($schedule))
                ->values()
                ->all(),
        ], 'Subject classroom options retrieved successfully.');
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
        $actor = $this->resolveActor($request);
        $query = SubjectLesson::with([
            'teacher:id,first_name,last_name',
            'subject:id,name',
            'classroom:id,name,major_id,shift_id,academic_year,year_level,semester',
            'classroom.major:id,name',
            'classroom.shift:id,name',
        ]);

        $this->applyVisibleContentScope($query, $actor, 'subject_lessons');

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

        $schedule = $this->resolveWritableSchedule($request, (int) $request->class_id, (int) $request->subject_id);

        $uploaded = $this->uploadFile($request, 'file', 'lessons');

        $lesson = SubjectLesson::create([
            'class_id'    => $request->class_id,
            'subject_id'  => $request->subject_id,
            'teacher_id'  => $schedule->teacher_id,
            'title'       => $request->title,
            'description' => $request->description,
            'file_url'    => $uploaded['url'],
            'file_name'   => $uploaded['name'],
            'file_type'   => $uploaded['type'],
            'file_size'   => $uploaded['size'],
            'lesson_date' => $request->lesson_date,
        ]);

        $lesson->load(['teacher:id,first_name,last_name', 'subject:id,name', 'classroom:id,name']);

        return $this->success($lesson, 'Lesson uploaded successfully.');
    }

    /**
     * DELETE /subject-classroom/lessons/{id}
     */
    public function destroyLesson($id)
    {
        $lesson = SubjectLesson::findOrFail($id);
        $this->guardCanManageClassSubject(request(), $lesson->class_id, $lesson->subject_id);
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
        $actor = $this->resolveActor($request);
        $assessmentConfig = $this->assessmentConfig($request->input('type'));
        $supportsReviewState = $this->supportsHomeworkReviewState();
        $query = HomeworkAssignment::with([
                'teacher:id,first_name,last_name',
                'subject:id,name',
                'classroom:id,name,major_id,shift_id,academic_year,year_level,semester',
                'classroom.major:id,name',
                'classroom.shift:id,name',
            ])
            ->withCount('submissions');

        if ($supportsReviewState) {
            $query->withCount([
                'submissions as unreviewed_submissions_count' => fn ($submissionQuery) => $submissionQuery->whereNull('reviewed_at'),
            ]);
        }

        if ($actor['scope'] === 'student' && $actor['student_id']) {
            $query->with([
                'submissions' => function ($submissionQuery) use ($actor) {
                    $submissionQuery
                        ->where('student_id', $actor['student_id'])
                        ->latest('submitted_at');
                },
            ]);
        }

        $this->applyVisibleContentScope($query, $actor, 'homework_assignments');
        $query->where('assessment_type', $assessmentConfig['type']);

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
        $paginated->getCollection()->transform(function ($hw) use ($actor, $supportsReviewState) {
            $hw->is_overdue = $hw->is_overdue;
            $hw->unreviewed_submissions_count = $supportsReviewState
                ? ($hw->unreviewed_submissions_count ?? 0)
                : ($hw->submissions_count ?? 0);
            $hw->assessment_type = $this->normalizeAssessmentType($hw->assessment_type);
            $hw->score_field = $this->assessmentConfig($hw->assessment_type)['score_field'];

            if ($actor['scope'] === 'student' && $actor['student_id']) {
                $submission = $hw->relationLoaded('submissions') ? $hw->submissions->first() : null;
                $hw->setRelation('my_submission', $submission);
                $hw->submission_state = $submission
                    ? ($submission->score !== null ? 'graded' : ($submission->is_late ? 'submitted_late' : 'submitted'))
                    : ($hw->is_overdue ? 'missing_overdue' : 'missing');
                $hw->can_submit_now = (bool) $hw->is_active;
                $hw->unsetRelation('submissions');
            }

            return $hw;
        });

        return $this->success(
            PaginatedResult::fromPaginator($paginated),
            "{$assessmentConfig['label']} items retrieved successfully."
        );
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
            'assessment_type' => 'nullable|in:homework,assignment,midterm,final',
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date'    => 'required|date',
            'max_score'   => 'nullable|integer|min:0',
            'attachment'  => 'nullable|file|max:10240',
        ]);

        $assessmentConfig = $this->assessmentConfig($request->input('assessment_type'));
        $this->ensureOfficialAssessmentSlotAvailable($assessmentConfig['type'], (int) $request->class_id, (int) $request->subject_id);

        $schedule = $this->resolveWritableSchedule($request, (int) $request->class_id, (int) $request->subject_id);

        $data = [
            'class_id'    => $request->class_id,
            'subject_id'  => $request->subject_id,
            'teacher_id'  => $schedule->teacher_id,
            'assessment_type' => $assessmentConfig['type'],
            'title'       => $request->title,
            'description' => $request->description,
            'due_date'    => $request->due_date,
            'max_score'   => $request->max_score ?? $assessmentConfig['default_max_score'],
            'is_active'   => true,
        ];

        if ($request->hasFile('attachment')) {
            $uploaded = $this->uploadFile($request, 'attachment', $assessmentConfig['upload_folder']);
            $data['attachment_url']  = $uploaded['url'];
            $data['attachment_name'] = $uploaded['name'];
        }

        $homework = HomeworkAssignment::create($data);
        $homework->load(['teacher:id,first_name,last_name', 'subject:id,name', 'classroom:id,name']);

        return $this->success($homework, "{$assessmentConfig['label']} created successfully.");
    }

    /**
     * PUT /subject-classroom/homework/{id}
     */
    public function updateHomework(Request $request, $id)
    {
        $homework = HomeworkAssignment::findOrFail($id);
        $assessmentConfig = $this->assessmentConfig($homework->assessment_type);
        $this->guardCanManageClassSubject($request, $homework->class_id, $homework->subject_id);

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
            $uploaded = $this->uploadFile($request, 'attachment', $assessmentConfig['upload_folder']);
            $homework->attachment_url  = $uploaded['url'];
            $homework->attachment_name = $uploaded['name'];
        }

        $homework->save();
        $homework->load(['teacher:id,first_name,last_name', 'subject:id,name']);

        return $this->success($homework, "{$assessmentConfig['label']} updated successfully.");
    }

    /**
     * DELETE /subject-classroom/homework/{id}
     */
    public function destroyHomework($id)
    {
        $homework = HomeworkAssignment::findOrFail($id);
        $assessmentConfig = $this->assessmentConfig($homework->assessment_type);
        $this->guardCanManageClassSubject(request(), $homework->class_id, $homework->subject_id);
        $this->clearAssessmentScoresForAssignment($homework);
        $homework->delete();

        return $this->success(null, "{$assessmentConfig['label']} deleted successfully.");
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
        $this->guardCanManageClassSubject(request(), $homework->class_id, $homework->subject_id);

        $subsQuery = $homework->submissions()
            ->with('student:id,full_name_en,full_name_kh,id_card_number');

        if ($this->supportsHomeworkReviewState()) {
            $subsQuery->orderByRaw('CASE WHEN reviewed_at IS NULL THEN 0 ELSE 1 END');
        }

        $subs = $subsQuery
            ->latest('submitted_at')
            ->get();

        return $this->success([
            'homework' => $homework->only(['id', 'title', 'due_date', 'max_score', 'assessment_type']),
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
        $assessmentConfig = $this->assessmentConfig($homework->assessment_type);

        if (!$homework->is_active) {
            throw new ApiException(ResponseStatus::FORBIDDEN, "This {$assessmentConfig['type']} is closed for submissions.");
        }

        $request->validate([
            'file' => 'required|file|max:10240',
            'note' => 'nullable|string',
        ]);

        // Resolve student_id from the authenticated user
        $studentId = $this->resolveStudentId($request);
        if (!$studentId) {
            return $this->error('Could not resolve student identity.', \App\Enums\ResponseStatus::FORBIDDEN);
        }

        $this->guardStudentCanAccessHomework($studentId, $homework);

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
            'score'        => null,
            'feedback'     => null,
        ];

        if ($this->supportsHomeworkReviewState()) {
            $submissionData['reviewed_at'] = null;
        }

        if ($existing) {
            if ($existing->score !== null || $existing->feedback !== null) {
                $this->syncAssessmentScore($existing, null);
            }
            $existing->update($submissionData);
            $submission = $existing;
        } else {
            $submission = HomeworkSubmission::create($submissionData);
        }

        $submission->load('student:id,full_name_en,full_name_kh');

        return $this->success(
            $submission,
            $existing
                ? "{$assessmentConfig['label']} re-submitted successfully."
                : "{$assessmentConfig['label']} submitted successfully."
        );
    }

    /**
     * PATCH /subject-classroom/submissions/{id}/grade
     */
    public function gradeSubmission(Request $request, $id)
    {
        $submission = HomeworkSubmission::findOrFail($id);
        $submission->loadMissing('assignment');
        $assessmentConfig = $this->assessmentConfig($submission->assignment?->assessment_type);
        $this->guardCanManageClassSubject($request, $submission->assignment->class_id, $submission->assignment->subject_id);

        $request->validate([
            'score'    => 'required|numeric|min:0',
            'feedback' => 'nullable|string',
        ]);

        $payload = [
            'score'    => $request->score,
            'feedback' => $request->feedback,
        ];

        if ($this->supportsHomeworkReviewState()) {
            $payload['reviewed_at'] = Carbon::now();
        }

        $submission->update($payload);
        $this->syncAssessmentScore($submission, (float) $request->score);

        $submission->load('student:id,full_name_en,full_name_kh');

        return $this->success($submission, "{$assessmentConfig['label']} submission graded successfully.");
    }

    // ─── Identity Resolvers ───────────────────────────────────────────────

    /**
     * PATCH /subject-classroom/submissions/{id}/review
     */
    public function reviewSubmission(Request $request, $id)
    {
        $submission = HomeworkSubmission::findOrFail($id);
        $submission->loadMissing('assignment');
        $this->guardCanManageClassSubject($request, $submission->assignment->class_id, $submission->assignment->subject_id);

        if (!$this->supportsHomeworkReviewState()) {
            $submission->load('student:id,full_name_en,full_name_kh');
            return $this->success($submission, 'Submission review tracking is not available until the database migration is applied.');
        }

        if (!$submission->reviewed_at) {
            $submission->reviewed_at = Carbon::now();
            $submission->save();
        }

        $submission->load('student:id,full_name_en,full_name_kh');

        return $this->success($submission, 'Submission marked as reviewed.');
    }

    private function resolveActor(Request $request): array
    {
        $user = $request->user();

        if ($user instanceof Teacher) {
            return [
                'scope' => 'teacher',
                'teacher_id' => $user->id,
                'student_id' => null,
                'can_manage' => true,
                'can_submit' => false,
            ];
        }

        if ($user instanceof User) {
            $role = strtolower((string) $user->role?->name);
            $isPrivileged = in_array($role, ['admin', 'staff', 'assistant', 'orderstaff'], true);

            if ($isPrivileged) {
                return [
                    'scope' => 'all',
                    'teacher_id' => null,
                    'student_id' => null,
                    'can_manage' => true,
                    'can_submit' => false,
                ];
            }

            if ($user->teacher_id) {
                return [
                    'scope' => 'teacher',
                    'teacher_id' => (int) $user->teacher_id,
                    'student_id' => null,
                    'can_manage' => true,
                    'can_submit' => false,
                ];
            }

            if ($user->student_id) {
                return [
                    'scope' => 'student',
                    'teacher_id' => null,
                    'student_id' => (int) $user->student_id,
                    'can_manage' => false,
                    'can_submit' => true,
                ];
            }
        }

        return [
            'scope' => 'none',
            'teacher_id' => null,
            'student_id' => null,
            'can_manage' => false,
            'can_submit' => false,
        ];
    }

    private function scheduleOptionQuery()
    {
        return ClassSchedule::query()
            ->select([
                'class_schedules.id',
                'class_schedules.class_id',
                'class_schedules.subject_id',
                'class_schedules.teacher_id',
                'class_schedules.shift_id',
                'class_schedules.day_of_week',
                'class_schedules.academic_year',
                'class_schedules.year_level',
                'class_schedules.semester',
                'class_schedules.room',
            ])
            ->with([
                'classroom:id,name,major_id,shift_id,academic_year,year_level,semester,section',
                'classroom.major:id,name',
                'classroom.shift:id,name',
                'subject:id,name,subject_Code',
                'teacher:id,first_name,last_name',
                'shift:id,name',
            ]);
    }

    private function scheduleOption(ClassSchedule $schedule): array
    {
        $class = $schedule->classroom;

        return [
            'id' => $schedule->id,
            'class_id' => $schedule->class_id,
            'class_name' => $class?->name ?? "Class {$schedule->class_id}",
            'subject_id' => $schedule->subject_id,
            'subject_name' => $schedule->subject?->name,
            'teacher_id' => $schedule->teacher_id,
            'teacher_name' => $schedule->teacher ? trim($schedule->teacher->first_name . ' ' . $schedule->teacher->last_name) : null,
            'major_id' => $class?->major_id,
            'major_name' => $class?->major?->name,
            'shift_id' => $class?->shift_id ?? $schedule->shift_id,
            'shift_name' => $class?->shift?->name ?? $schedule->shift?->name,
            'academic_year' => $class?->academic_year ?? $schedule->academic_year,
            'year_level' => $class?->year_level ?? $schedule->year_level,
            'semester' => $class?->semester ?? $schedule->semester,
            'day_of_week' => $schedule->day_of_week,
            'room' => $schedule->room,
        ];
    }

    private function scheduleSubjectOption(ClassSchedule $schedule): array
    {
        $class = $schedule->classroom;

        return [
            'id' => $schedule->subject_id,
            'name' => $schedule->subject?->name,
            'subject_code' => $schedule->subject?->subject_Code,
            'class_id' => $schedule->class_id,
            'schedule_id' => $schedule->id,
            'teacher_id' => $schedule->teacher_id,
            'major_id' => $class?->major_id,
            'major_name' => $class?->major?->name,
            'academic_year' => $class?->academic_year ?? $schedule->academic_year,
            'year_level' => $class?->year_level ?? $schedule->year_level,
            'semester' => $class?->semester ?? $schedule->semester,
        ];
    }

    private function applyStudentScheduleScope($query, int $studentId): void
    {
        $query->whereExists(function ($classStudent) use ($studentId) {
            $classStudent
                ->selectRaw('1')
                ->from('class_students')
                ->whereColumn('class_students.class_id', 'class_schedules.class_id')
                ->where('class_students.student_id', $studentId)
                ->where('class_students.status', 'Active');
        });
    }

    private function applyVisibleContentScope($query, array $actor, string $table): void
    {
        if ($actor['scope'] === 'all') {
            return;
        }

        if ($actor['scope'] === 'teacher') {
            $query->whereExists(function ($schedule) use ($actor, $table) {
                $schedule
                    ->selectRaw('1')
                    ->from('class_schedules')
                    ->whereColumn('class_schedules.class_id', "{$table}.class_id")
                    ->whereColumn('class_schedules.subject_id', "{$table}.subject_id")
                    ->where('class_schedules.teacher_id', $actor['teacher_id']);
            });

            return;
        }

        if ($actor['scope'] === 'student') {
            $query->whereExists(function ($classStudent) use ($actor, $table) {
                $classStudent
                    ->selectRaw('1')
                    ->from('class_students')
                    ->whereColumn('class_students.class_id', "{$table}.class_id")
                    ->where('class_students.student_id', $actor['student_id'])
                    ->where('class_students.status', 'Active');
            });

            return;
        }

        $query->whereRaw('1 = 0');
    }

    private function resolveWritableSchedule(Request $request, int $classId, int $subjectId): ClassSchedule
    {
        $actor = $this->resolveActor($request);

        if (!$actor['can_manage']) {
            throw new ApiException(ResponseStatus::FORBIDDEN, 'Only assigned teachers or staff can manage subject classroom materials.');
        }

        $query = $this->scheduleOptionQuery()
            ->where('class_schedules.class_id', $classId)
            ->where('class_schedules.subject_id', $subjectId);

        if ($actor['scope'] === 'teacher') {
            $query->where('class_schedules.teacher_id', $actor['teacher_id']);
        } elseif ($request->filled('teacher_id')) {
            $query->where('class_schedules.teacher_id', (int) $request->teacher_id);
        }

        $schedule = $query->first();

        if (!$schedule) {
            throw new ApiException(ResponseStatus::FORBIDDEN, 'This class and subject are not assigned to this teacher schedule.');
        }

        return $schedule;
    }

    private function guardCanManageClassSubject(Request $request, int $classId, int $subjectId): void
    {
        $actor = $this->resolveActor($request);

        if (!$actor['can_manage']) {
            throw new ApiException(ResponseStatus::FORBIDDEN, 'Only assigned teachers or staff can manage this material.');
        }

        if ($actor['scope'] !== 'teacher') {
            return;
        }

        $allowed = ClassSchedule::query()
            ->where('teacher_id', $actor['teacher_id'])
            ->where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->exists();

        if (!$allowed) {
            throw new ApiException(ResponseStatus::FORBIDDEN, 'This material is outside your assigned teaching schedule.');
        }
    }

    private function guardStudentCanAccessHomework(int $studentId, HomeworkAssignment $homework): void
    {
        $allowed = \App\Models\ClassStudent::query()
            ->where('student_id', $studentId)
            ->where('class_id', $homework->class_id)
            ->where('status', 'Active')
            ->exists();

        if (!$allowed) {
            throw new ApiException(ResponseStatus::FORBIDDEN, 'This homework is outside this student class.');
        }
    }

    private function resolveTeacherId(Request $request): ?int
    {
        // Use $request->user() as it is set by the UnifiedJwtMiddleware
        $user = $request->user();

        if (!$user) {
            return null;
        }

        // 1. Directly logged in as Teacher model (teacher guard)
        if ($user instanceof Teacher) {
            return $user->id;
        }

        // 2. Logged in as User model (api guard)
        if ($user instanceof User) {
            // A. Direct teacher_id linkage on User record (e.g. Teacher using User account)
            if ($user->teacher_id) {
                return $user->teacher_id;
            }

            // B. Explicitly provided teacher_id in request from a privileged staff account.
            if ($request->filled('teacher_id') && $this->resolveActor($request)['scope'] === 'all') {
                return (int) $request->teacher_id;
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
