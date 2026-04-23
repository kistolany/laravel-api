<?php

namespace App\Services\Student;

use App\Services\BaseService;

use App\DTOs\PaginatedResult;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\Student\StudentResource;
use App\Models\Students;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class StudentService extends BaseService
{
    private const FINAL_EXAM_ABSENCE_LIMIT = 10;
    private const TUITION_PLANS = [
        'PAY_FULL',
        'SCHOLARSHIP_FULL',
        'SCHOLARSHIP_70',
        'SCHOLARSHIP_50',
        'SCHOLARSHIP_30',
    ];
    private const SCHOLARSHIP_TUITION_PLANS = [
        'SCHOLARSHIP_FULL',
        'SCHOLARSHIP_70',
        'SCHOLARSHIP_50',
        'SCHOLARSHIP_30',
    ];

    /**
     * Get all students with their academic information
     */
    public function index(): PaginatedResult
    {
        return $this->trace(__FUNCTION__, function (): PaginatedResult {
            // 1. Start query with relationships
            $query = Students::with([
                'academicInfo.major.faculty',
                'academicInfo.shift',
                'addresses.province',
                'addresses.district',
                'addresses.commune',
                'parentGuardian',
                'registration',
                'scholarship',
            ]);
            
            // 2. Filter by Major (Inside academicInfo table)
            $query->when(request('major_id'), function ($q, $majorId) {
                $q->whereHas('academicInfo', function ($sub) use ($majorId) {
                    $sub->where('major_id', $majorId);
                });
            });
            
            // 3. Filter by Shift (Inside academicInfo table)
            $query->when(request('shift_id'), function ($q, $shiftId) {
                $q->whereHas('academicInfo', function ($sub) use ($shiftId) {
                    $sub->where('shift_id', $shiftId);
                });
            });
            
            // 4. Filter by Search (Name or Email)
            $query->when(request('search'), function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('full_name_en', 'like', "%{$search}%")
                        ->orWhere('full_name_kh', 'like', "%{$search}%");
            
                });
            });
            
            // 5. Filter by Batch Year
            $query->when(request('batch_year'), function ($q, $year) {
                $q->whereHas('academicInfo', fn($sub) => $sub->where('batch_year', $year));
            });
            
            // 6. Filter by Province (Inside addresses table)
            $query->when(request('province_id'), function ($q, $provinceId) {
                $q->whereHas('addresses', function ($sub) use ($provinceId) {
                    $sub->where('province_id', $provinceId);
                });
            });
            
            // 7. Filter by Status (default: only show active students)
            $query->where('status', 'active');

            // 8. Final Sort and Paginate
            return $this->paginateResponse($query->latest(), StudentResource::class);
            
            
        });
    }

    /**
     * Get only PENDING (scholarship) students with pagination.
     */
    public function pendingStudents(): PaginatedResult
    {
        return $this->studentsByTypes(['PENDING'], __FUNCTION__);
    }

    /**
     * Get only PAY or PASS students with pagination.
     * Optional query params:
     *   major_id, shift_id, faculty_id, stage, batch_year, study_days,
     *   class_id, search, size
     */
    public function payOrPass(): PaginatedResult
    {
        return $this->studentsByTypes(['PAY', 'PASS'], __FUNCTION__);
    }

    /**
     * Get PAY or PASS students eligible for the final exam list.
     * Students with 10 or more absences for the selected class/term context
     * are excluded from this roster.
     */
    public function finalExamList(): PaginatedResult
    {
        return $this->studentsByTypes(['PAY', 'PASS'], __FUNCTION__, excludeOverAbsentLimit: true);
    }

    /**
     * Get only PASS students with pagination.
     * Optional query params match payOrPass():
     *   major_id, shift_id, faculty_id, stage, batch_year, study_days,
     *   class_id, search, size
     */
    public function passStudents(): PaginatedResult
    {
        return $this->studentsByTypes(['PASS'], __FUNCTION__);
    }

    /**
     * Get only FAIL scholarship students with pagination.
     * Optional query params match payOrPass():
     *   major_id, shift_id, faculty_id, stage, batch_year, study_days,
     *   class_id, search, size
     */
    public function failStudents(): PaginatedResult
    {
        return $this->studentsByTypes(['FAIL'], __FUNCTION__);
    }

    private function studentsByTypes(array $studentTypes, string $traceName, bool $excludeOverAbsentLimit = false): PaginatedResult
    {
        return $this->trace($traceName, function () use ($studentTypes, $excludeOverAbsentLimit): PaginatedResult {
            $query = Students::with([
                'academicInfo.major.faculty',
                'academicInfo.shift',
                'addresses.province',
                'addresses.district',
                'addresses.commune',
                'parentGuardian',
                'registration',
                'scholarship',
                'classes',
            ])->whereIn('student_type', $studentTypes)
              ->where('status', 'active');

            $this->applyStudentListFilters($query);

            if ($excludeOverAbsentLimit) {
                $this->excludeStudentsWithTooManyAbsences($query);
            }

            return $this->paginateResponse($query->latest(), StudentResource::class);
        });
    }

    private function applyStudentListFilters(Builder $query): void
    {
        // Filter by major
        $query->when(request('major_id'), function ($q, $majorId) {
            $q->whereHas('academicInfo', fn($sub) => $sub->where('major_id', $majorId));
        });

        // Filter by shift
        $query->when(request('shift_id'), function ($q, $shiftId) {
            $q->whereHas('academicInfo', fn($sub) => $sub->where('shift_id', $shiftId));
        });

        // Filter by faculty (via major)
        $query->when(request('faculty_id'), function ($q, $facultyId) {
            $q->whereHas('academicInfo.major', fn($sub) => $sub->where('faculty_id', $facultyId));
        });

        // Filter by stage (year level e.g. "Year 1")
        $query->when(request('stage'), function ($q, $stage) {
            $q->whereHas('academicInfo', fn($sub) => $sub->where('stage', $stage));
        });

        // Filter by batch year
        $query->when(request('batch_year'), function ($q, $batchYear) {
            $q->whereHas('academicInfo', fn($sub) => $sub->where('batch_year', $batchYear));
        });

        // Filter by academic year (class context)
        $query->when(request('academic_year'), function ($q, $academicYear) {
            $q->whereHas('classes', fn($sub) => $sub->where('classes.academic_year', $academicYear));
        });

        // Filter by semester (class context)
        $query->when(request('semester'), function ($q, $semester) {
            $q->whereHas('classes', fn($sub) => $sub->where('classes.semester', $semester));
        });

        // Filter by study_days (study year / schedule)
        $query->when(request('study_days'), function ($q, $studyDays) {
            $q->whereHas('academicInfo', fn($sub) => $sub->where('study_days', $studyDays));
        });

        // Filter by class: "none" = no class assigned, otherwise filter by class id
        if (request('class_id') === 'none') {
            $query->whereDoesntHave('classes');
        } elseif (request('class_id')) {
            $query->whereHas('classes', fn($sub) => $sub->where('classes.id', request('class_id')));
        }

        // Search by name or barcode
        $query->when(request('search'), function ($q, $search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('full_name_en', 'like', "%{$search}%")
                    ->orWhere('full_name_kh', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%");
            });
        });
    }

    private function excludeStudentsWithTooManyAbsences(Builder $query): void
    {
        $query->whereHas('attendanceRecords', function (Builder $attendanceQuery) {
            $attendanceQuery->whereIn('status', $this->absentStatuses());

            if ($this->hasAttendanceContextFilters()) {
                $attendanceQuery->whereHas('session', function (Builder $sessionQuery) {
                    $this->applyAttendanceContextFilters($sessionQuery);
                });
            }
        }, '<', self::FINAL_EXAM_ABSENCE_LIMIT);
    }

    private function absentStatuses(): array
    {
        return ['Absent', 'absent', 'A', 'a'];
    }

    private function hasAttendanceContextFilters(): bool
    {
        return request()->filled('class_id')
            || request()->filled('major_id')
            || request()->filled('shift_id')
            || request()->filled('academic_year')
            || request()->filled('stage')
            || request()->filled('year_level')
            || request()->filled('semester');
    }

    private function applyAttendanceContextFilters(Builder $query): void
    {
        if (request('class_id') && request('class_id') !== 'none') {
            $query->where('class_id', request('class_id'));
        }

        $query->when(request('major_id'), fn (Builder $q, $majorId) => $q->where('major_id', $majorId));
        $query->when(request('shift_id'), fn (Builder $q, $shiftId) => $q->where('shift_id', $shiftId));
        $query->when(request('academic_year'), fn (Builder $q, $academicYear) => $q->where('academic_year', $academicYear));
        $query->when(request('semester'), fn (Builder $q, $semester) => $q->where('semester', $semester));

        $yearLevel = $this->attendanceYearLevel(request('year_level', request('stage')));
        if ($yearLevel) {
            $query->where('year_level', $yearLevel);
        }
    }

    private function attendanceYearLevel(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        preg_match('/\d+/', (string) $value, $matches);
        return isset($matches[0]) ? (int) $matches[0] : null;
    }

    /**
     * Find a specific student by ID
     */
    public function findById(int $id): Students
    {
        return $this->trace(__FUNCTION__, function () use ($id): Students {
            $student = Students::with([
                'academicInfo.major.faculty',
                'academicInfo.shift',
                'addresses.province',
                'addresses.district',
                'addresses.commune',
                'parentGuardian',
                'registration',
                'scholarship',
            ])->find($id);
            
            if (!$student) {
                Log::warning('Student not found.', ['id' => $id]);
                throw new ApiException(ResponseStatus::NOT_FOUND, "Student with ID :$id not found.");
            }
            
            return $student;
            
            
        });
    }

    /**
     * Create Student and Academic Info (Transaction)
     */
    public function create(array $data): Students
    {
        return $this->trace(__FUNCTION__, function () use ($data): Students {
            $validatedData = $this->validateExisting($data);
            $shouldGenerateStudentCode = $this->shouldGenerateStudentCode($validatedData['id_card_number'] ?? null);

            if ($shouldGenerateStudentCode) {
                $validatedData['id_card_number'] = $this->temporaryStudentCode();
            }

            $validatedData = $this->normalizeTuitionPlan($validatedData);
            $addresses = $validatedData['addresses'] ?? [];
            $parentGuardian = $validatedData['parent_guardian'] ?? null;
            $registration = $validatedData['registration'] ?? null;
            $scholarship = $validatedData['scholarship'] ?? null;
            unset(
                $validatedData['addresses'],
                $validatedData['parent_guardian'],
                $validatedData['registration'],
                $validatedData['scholarship']
            );
            
            $validatedData = $this->handleImageUpload($validatedData);
            
            return DB::transaction(function () use (
                $validatedData,
                $addresses,
                $parentGuardian,
                $registration,
                $scholarship,
                $shouldGenerateStudentCode
            ) {
                // 1. Create the Student record
                $student = Students::create($validatedData);

                if ($shouldGenerateStudentCode) {
                    $student->forceFill([
                        'id_card_number' => $this->generateStudentCode($student->id),
                    ])->save();
                }
            
                // 2. Create the Academic Info record linked to this student
                $student->academicInfo()->create($validatedData);
            
                // 3. Create Address records linked to this student
                foreach ($addresses as $addressData) {
                    $student->addresses()->create($addressData);
                }
            
                // 4. Create Parent/Guardian record
                if ($parentGuardian) {
                    $student->parentGuardian()->create($parentGuardian);
                }

                // 5. Create Registration details when provided
                if ($this->hasSectionValues($registration)) {
                    $student->registration()->create($registration);
                }

                // 6. Create Scholarship details when provided
                if ($this->hasSectionValues($scholarship)) {
                    $student->scholarship()->create($scholarship);
                }
            
                return $student->load([
                    'academicInfo.major.faculty',
                    'academicInfo.shift',
                    'addresses.province',
                    'addresses.district',
                    'addresses.commune',
                    'parentGuardian',
                    'registration',
                    'scholarship',
                ]);
            });
            
            
        });
    }

    /**
     * Update Student and Academic Info (Transaction)
     */
    public function update(int $id, array $data): Students
    {
        return $this->trace(__FUNCTION__, function () use ($id, $data): Students {
            $student = $this->findById($id);
            $validatedData = $this->validateExisting($data, $student->id);
            $validatedData = $this->normalizeTuitionPlan($validatedData, $student);
            $validatedData['id_card_number'] = $this->resolvedStudentCode(
                $validatedData['id_card_number'] ?? null,
                $student
            );
            $addresses = $validatedData['addresses'] ?? [];
            $parentGuardian = $validatedData['parent_guardian'] ?? null;
            $registration = $validatedData['registration'] ?? null;
            $scholarship = $validatedData['scholarship'] ?? null;
            unset(
                $validatedData['addresses'],
                $validatedData['parent_guardian'],
                $validatedData['registration'],
                $validatedData['scholarship']
            );
            
            $validatedData = $this->handleImageUpload($validatedData, $student->image);
            
            return DB::transaction(function () use (
                $student,
                $validatedData,
                $addresses,
                $parentGuardian,
                $registration,
                $scholarship
            ) {
                // 1. Update Student Table
                $student->update($validatedData);
            
                // 2. Update or Create Academic Info Table
                $student->academicInfo()->updateOrCreate(
                    ['student_id' => $student->id],
                    $validatedData
                );
            
                // 3. Update or Create Address records
                foreach ($addresses as $addressData) {
                    $student->addresses()->updateOrCreate(
                        ['address_type' => $addressData['address_type']],
                        $addressData
                    );
                }
            
                // 4. Update or Create Parent/Guardian record
                if ($parentGuardian) {
                    $student->parentGuardian()->updateOrCreate(
                        ['student_id' => $student->id],
                        $parentGuardian
                    );
                }

                // 5. Update or create registration details
                if ($this->hasSectionValues($registration)) {
                    $student->registration()->updateOrCreate(
                        ['student_id' => $student->id],
                        $registration
                    );
                }

                // 6. Update or create scholarship details
                if ($this->hasSectionValues($scholarship)) {
                    $student->scholarship()->updateOrCreate(
                        ['student_id' => $student->id],
                        $scholarship
                    );
                }
            
                return $student->refresh()->load([
                    'academicInfo.major.faculty',
                    'academicInfo.shift',
                    'addresses.province',
                    'addresses.district',
                    'addresses.commune',
                    'parentGuardian',
                    'registration',
                    'scholarship',
                ]);
            });
            
            
        });
    }

    /**
     * Delete Student (Academic info will delete if cascade is set in migration)
     */
    public function delete(int $id): bool
    {
        return $this->trace(__FUNCTION__, function () use ($id): bool {
            $student = $this->findById($id);
            return $student->delete();
            
            
        });
    }

    private function normalizeTuitionPlan(array $data, ?Students $student = null): array
    {
        $type = strtoupper((string) ($data['student_type'] ?? $student?->student_type ?? ''));
        $incomingPlan = $data['tuition_plan'] ?? null;
        $existingPlan = $student?->tuition_plan;
        $plan = $incomingPlan ?: $existingPlan;

        if ($type === 'PAY') {
            $plan = 'PAY_FULL';
        } elseif ($type === 'PASS') {
            $plan = in_array($plan, self::SCHOLARSHIP_TUITION_PLANS, true) ? $plan : null;
        } else {
            $plan = null;
        }

        $data['tuition_plan'] = $plan;

        if ($plan === null) {
            $data['tuition_plan_assigned_at'] = null;
        } elseif ($plan !== $existingPlan) {
            $data['tuition_plan_assigned_at'] = now();
        }

        return $data;
    }

    private function shouldGenerateStudentCode(mixed $value): bool
    {
        return trim((string) ($value ?? '')) === '';
    }

    private function resolvedStudentCode(mixed $incomingCode, Students $student): string
    {
        $normalizedCode = trim((string) ($incomingCode ?? ''));

        if ($normalizedCode !== '') {
            return $normalizedCode;
        }

        if (!empty($student->id_card_number)) {
            return (string) $student->id_card_number;
        }

        return $this->generateStudentCode($student->id);
    }

    private function temporaryStudentCode(): string
    {
        return 'TMP-STU-' . now()->format('YmdHisv') . '-' . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function generateStudentCode(int $studentId): string
    {
        return 'STU-' . str_pad((string) $studentId, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Validation logic for both tables
     */
    protected function validateExisting(array $data, ?int $ignoreId = null): array
    {
        $validator = Validator::make($data, [
            // Students table fields
            'full_name_kh'   => 'required|string|max:255',
            'full_name_en'   => 'required|string|max:255',
            'gender'         => 'required|in:Male,Female,Other',
            'dob'            => 'required|date',
            'phone'          => 'nullable|string',
            'email'          => [
                'nullable',
                'email',
                Rule::unique('students', 'email')->ignore($ignoreId),
            ],
            'other_notes' => 'nullable|string',
            'id_card_number' => [
                'nullable',
                'string',
                Rule::unique('students', 'id_card_number')->ignore($ignoreId),
            ],
            'short_docs_status' => 'boolean',
            'image'          => 'nullable|string',
            'status'            => 'sometimes|in:enable,disable',
            'student_type'      => 'required|in:PAY,PENDING,PASS,FAIL',
            'tuition_plan'      => ['nullable', 'string', Rule::in(self::TUITION_PLANS)],
            'exam_place'        => 'nullable|string|max:255',
            'bacll_code'        => 'nullable|string|max:255',
            'grade'             => 'required|string|max:50',
            'doc'               => 'nullable|string',

            // Academic Info table fields
            'major_id'       => 'required|exists:majors,id',
            'shift_id'       => 'required|exists:shifts,id',
            'batch_year'     => 'required|integer',
            'stage'       => 'required|string',
            'study_days'     => 'required|string',

            // Address table fields
            'addresses'                 => 'required|array|min:1',
            'addresses.*.address_type'  => 'required|in:Permanent,Current|distinct',
            'addresses.*.house_number'  => 'nullable|string|max:255',
            'addresses.*.street_number' => 'nullable|string|max:255',
            'addresses.*.village'       => 'nullable|string|max:255',
            'addresses.*.province_id'   => 'required|exists:provinces,id',
            'addresses.*.district_id'   => 'required|exists:districts,id',
            'addresses.*.commune_id'    => 'required|exists:communes,id',

            // Parent/Guardian fields
            'parent_guardian'                => 'nullable|array',
            'parent_guardian.father_name'    => 'nullable|string|max:255',
            'parent_guardian.father_job'     => 'nullable|string|max:255',
            'parent_guardian.mother_name'    => 'nullable|string|max:255',
            'parent_guardian.mother_job'     => 'nullable|string|max:255',
            'parent_guardian.guardian_name'  => 'nullable|string|max:255',
            'parent_guardian.guardian_job'   => 'nullable|string|max:255',
            'parent_guardian.guardian_phone' => 'nullable|string|max:255',

            // Student registration details
            'registration'                     => 'nullable|array',
            'registration.high_school_name'   => 'nullable|string|max:255',
            'registration.high_school_province' => 'nullable|string|max:255',
            'registration.bacii_exam_year'    => 'nullable|integer|min:2000|max:2100',
            'registration.bacii_grade'        => 'nullable|string|max:10',
            'registration.target_degree'      => 'nullable|string|max:255',
            'registration.diploma_attached'   => 'nullable|boolean',

            // Scholarship-specific details
            'scholarship' => [
                Rule::requiredIf(fn () => in_array(
                    strtoupper((string) ($data['student_type'] ?? '')),
                    ['PENDING', 'PASS', 'FAIL'],
                    true
                )),
                'nullable',
                'array',
            ],
            'scholarship.nationality' => 'nullable|string|max:255',
            'scholarship.ethnicity' => 'nullable|string|max:255',
            'scholarship.emergency_name' => [
                Rule::requiredIf(fn () => in_array(
                    strtoupper((string) ($data['student_type'] ?? '')),
                    ['PENDING', 'PASS', 'FAIL'],
                    true
                )),
                'nullable',
                'string',
                'max:255',
            ],
            'scholarship.emergency_relation' => [
                Rule::requiredIf(fn () => in_array(
                    strtoupper((string) ($data['student_type'] ?? '')),
                    ['PENDING', 'PASS', 'FAIL'],
                    true
                )),
                'nullable',
                'string',
                'max:255',
            ],
            'scholarship.emergency_phone' => [
                Rule::requiredIf(fn () => in_array(
                    strtoupper((string) ($data['student_type'] ?? '')),
                    ['PENDING', 'PASS', 'FAIL'],
                    true
                )),
                'nullable',
                'string',
                'max:255',
            ],
            'scholarship.emergency_address' => 'nullable|string',
            'scholarship.grade' => 'nullable|string|max:10',
            'scholarship.exam_year' => 'nullable|integer|min:2000|max:2100',
            'scholarship.guardians_address' => 'nullable|string',
            'scholarship.guardians_phone_number' => 'nullable|string|max:255',
            'scholarship.guardians_email' => 'nullable|email|max:255',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();
            $message = $validator->errors()->first('email')
                ?: $validator->errors()->first('id_card_number')
                ?: $validator->errors()->first('major_id')
                ?: $validator->errors()->first('shift_id')
                ?: 'Validation failed for student data.';

            Log::warning('Student validation failed.', [
                'ignore_id' => $ignoreId,
                'data' => [
                    'email' => $data['email'] ?? null,
                    'id_card_number' => $data['id_card_number'] ?? null,
                    'major_id' => $data['major_id'] ?? null,
                    'shift_id' => $data['shift_id'] ?? null,
                    'batch_year' => $data['batch_year'] ?? null,
                    'student_type' => $data['student_type'] ?? null,
                ],
                'errors' => $errors,
            ]);

            throw new ApiException(
                ResponseStatus::EXISTING_DATA,
                $message,
                data: ['errors' => $validator->errors()]
            );
        }

        return $validator->validated();
    }

    /**
     * Update only student status
     */
    public function setStatus(int $id, string $status): Students
    {
        return $this->trace(__FUNCTION__, function () use ($id, $status): Students {
            $student = $this->findById($id);
            $student->update(['status' => $status]);
            
            return $student->refresh()->load([
                'academicInfo.major.faculty',
                'academicInfo.shift',
                'addresses.province',
                'addresses.district',
                'addresses.commune',
                'parentGuardian',
                'registration',
                'scholarship',
            ]);
            
            
        });
    }

    /**
     * Update only student type
     */
    public function setStudentType(int $id, string $studentType, ?string $tuitionPlan = null): Students
    {
        return $this->trace(__FUNCTION__, function () use ($id, $studentType, $tuitionPlan): Students {
            $student = $this->findById($id);
            $student->update($this->normalizeTuitionPlan([
                'student_type' => $studentType,
                'tuition_plan' => $tuitionPlan,
            ], $student));
            
            return $student->refresh()->load([
                'academicInfo.major.faculty',
                'academicInfo.shift',
                'addresses.province',
                'addresses.district',
                'addresses.commune',
                'parentGuardian',
                'registration',
                'scholarship',
            ]);
            
            
        });
    }

    /**
     * Update student image only
     */
    public function updateImage(int $id, UploadedFile $image): Students
    {
        return $this->trace(__FUNCTION__, function () use ($id, $image): Students {
            $student = $this->findById($id);
            $validatedData = $this->handleImageUpload(['image' => $image], $student->image);
            $student->update($validatedData);
            
            return $student->refresh()->load([
                'academicInfo.major.faculty',
                'academicInfo.shift',
                'addresses.province',
                'addresses.district',
                'addresses.commune',
                'parentGuardian',
                'registration',
                'scholarship',
            ]);
            
            
        });
    }

    /**
     * Get classes for a student
     */
    public function classes(int $id): Students
    {
        return $this->trace(__FUNCTION__, function () use ($id): Students {
            $student = $this->findById($id);
            $student->load('classes');
            
            return $student;
            
            
        });
    }

    private function handleImageUpload(array $validatedData, ?string $oldPath = null): array
    {
        $file = $validatedData['image'] ?? null;

        if ($file instanceof UploadedFile) {
            if ($oldPath && !str_contains($oldPath, 'res.cloudinary.com')) {
                Storage::disk('public')->delete($oldPath);
            }

            $this->ensureCloudinaryConfigured();
            $uploadOptions = $this->buildCloudinaryUploadOptions('students');

            try {
                $result = \CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary::uploadApi()->upload(
                    $file->getRealPath(),
                    $uploadOptions
                );
                $uploadedFileUrl = $result['secure_url'] ?? null;
            } catch (\Throwable $e) {
                Log::error(
                    'Student image upload failed on Cloudinary.',
                    $this->buildCloudinaryExceptionContext($e, $file, $uploadOptions)
                );

                throw new ApiException(ResponseStatus::INTERNAL_SERVER_ERROR, 'Failed to upload image.');
            }

            if (!$uploadedFileUrl) {
                Log::error('Student image upload failed: Cloudinary returned empty URL.');
                throw new ApiException(ResponseStatus::INTERNAL_SERVER_ERROR, 'Failed to upload image.');
            }
            
            $validatedData['image'] = $uploadedFileUrl;
            return $validatedData;
        }

        unset($validatedData['image']);
        return $validatedData;
    }

    private function ensureCloudinaryConfigured(): void
    {
        $cloudUrl = (string) config('cloudinary.cloud_url', '');

        if ($cloudUrl === '' || str_contains($cloudUrl, 'API_KEY:API_SECRET@CLOUD_NAME')) {
            Log::error('Cloudinary is not configured for student uploads. Set CLOUDINARY_URL in .env and clear config cache.');
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

    private function hasSectionValues(?array $data): bool
    {
        if (!$data) {
            return false;
        }

        foreach ($data as $value) {
            if (is_bool($value)) {
                if ($value) {
                    return true;
                }

                continue;
            }

            if ($value !== null && $value !== '') {
                return true;
            }
        }

        return false;
    }
}




