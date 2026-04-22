<?php

namespace App\Services\Lookup;

use App\Services\BaseService;

use App\Models\AcademicInfo;
use App\Models\Classes;
use App\Models\ClassProgram;
use App\Models\ClassSchedule;
use App\Models\Commune;
use App\Models\District;
use App\Models\Faculty;
use App\Models\Major;
use App\Models\MajorSubject;
use App\Models\Province;
use App\Models\Shift;
use App\Models\StudentScore;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
class LookupService extends BaseService
{
    /**
     * Get all faculties for the top-level dropdown.
     */
    public function getFaculties()
    {
        $fn = __FUNCTION__;
        return $this->trace($fn, function () use ($fn) {
            return $this->rememberLookup($fn, [], fn () => Faculty::select('id', 'name')
                ->orderBy('name')
                ->get());
        });
    }

    /**
     * Get majors for a specific faculty, or all majors when no faculty is selected.
     */
    public function getMajorsByFaculty(mixed $facultyId = null)
    {
        $fn = __FUNCTION__;
        return $this->trace($fn, function () use ($fn, $facultyId) {
            $facultyId = $this->toNullableInt($facultyId);
            return $this->rememberLookup($fn, ['faculty_id' => $facultyId], fn () => Major::query()
                ->when(!is_null($facultyId), fn ($query) => $query->where('faculty_id', $facultyId))
                ->select('id', 'name')
                ->orderBy('name')
                ->get());
        });
    }

    public function getProvinces()
    {
        $fn = __FUNCTION__;
        return $this->trace($fn, function () use ($fn) {
            return $this->rememberLookup($fn, [], fn () => Province::select('id', 'name')
                ->orderBy('name')
                ->get());
        });
    }

    public function getDistrictsByProvince(mixed $provinceId = null)
    {
        $fn = __FUNCTION__;
        return $this->trace($fn, function () use ($fn, $provinceId) {
            $provinceId = $this->toNullableInt($provinceId);
            if ($provinceId === null) {
                return collect();
            }
            return $this->rememberLookup($fn, ['province_id' => $provinceId], fn () => District::where('province_id', $provinceId)
                ->select('id', 'name', 'province_id')
                ->orderBy('name')
                ->get());
        });
    }

    public function getCommunesByDistrict(mixed $districtId = null)
    {
        $fn = __FUNCTION__;
        return $this->trace($fn, function () use ($fn, $districtId) {
            $districtId = $this->toNullableInt($districtId);
            if ($districtId === null) {
                return collect();
            }
            return $this->rememberLookup($fn, ['district_id' => $districtId], fn () => Commune::where('district_id', $districtId)
                ->select('id', 'name', 'district_id')
                ->orderBy('name')
                ->get());
        });
    }

    public function getSubjectsByMajor(mixed $majorId = null, mixed $yearLevel = null, mixed $semester = null)
    {
        $fn = __FUNCTION__;
        return $this->trace($fn, function () use ($fn, $majorId, $yearLevel, $semester) {
            $majorId   = $this->toNullableInt($majorId);
            $yearLevel = $this->toNullableInt($yearLevel);
            $semester  = $this->toNullableInt($semester);

            return $this->rememberLookup($fn, [
                'major_id'   => $majorId,
                'year_level' => $yearLevel,
                'semester'   => $semester,
            ], fn () => Subject::query()
                ->when(!is_null($majorId), function ($query) use ($majorId, $yearLevel, $semester) {
                    $query->whereIn(
                        'id',
                        MajorSubject::query()
                            ->where('major_id', $majorId)
                            ->when($yearLevel, fn ($q) => $q->where('year_level', $yearLevel))
                            ->when($semester,  fn ($q) => $q->where('semester',   $semester))
                            ->select('subject_id')
                    );
                })
                ->select('id', 'subject_Code', 'name')
                ->orderBy('name')
                ->get());
        });
    }

    public function getShifts()
    {
        $fn = __FUNCTION__;
        return $this->trace($fn, function () use ($fn) {
            return $this->rememberLookup($fn, [], fn () => Shift::query()
                ->select('id', 'name', 'time_range')
                ->orderBy('name')
                ->get());
        });
    }

    public function getClasses(mixed $majorId = null, mixed $shiftId = null, mixed $yearLevel = null, mixed $semester = null)
    {
        $fn = __FUNCTION__;
        return $this->trace($fn, function () use ($fn, $majorId, $shiftId, $yearLevel, $semester) {
            // All four params must be integers to match the DB column types (unsignedTinyInteger / unsignedBigInteger)
            $majorId   = $this->toNullableInt($majorId);
            $shiftId   = $this->toNullableInt($shiftId);
            $yearLevel = $this->toNullableInt($yearLevel);
            $semester  = $this->toNullableInt($semester);

            return $this->rememberLookup($fn, [
                'major_id'   => $majorId,
                'shift_id'   => $shiftId,
                'year_level' => $yearLevel,
                'semester'   => $semester,
            ], fn () => Classes::query()
                // A class can serve multiple majors via class_programs — filter through that relationship
                ->when($majorId || $shiftId || $yearLevel || $semester, function ($q) use ($majorId, $shiftId, $yearLevel, $semester) {
                    $q->whereHas('programs', function ($pq) use ($majorId, $shiftId, $yearLevel, $semester) {
                        $pq->when($majorId,   fn ($q) => $q->where('major_id',   $majorId))
                           ->when($shiftId,   fn ($q) => $q->where('shift_id',   $shiftId))
                           ->when($yearLevel, fn ($q) => $q->where('year_level', $yearLevel))
                           ->when($semester,  fn ($q) => $q->where('semester',   $semester));
                    });
                })
                ->select('id', 'name')
                ->orderBy('name')
                ->get());
        });
    }

    public function getStages(): array
    {
        $fn = __FUNCTION__;
        return $this->trace($fn, function () use ($fn): array {
            return $this->rememberLookup($fn, [], fn () =>
                AcademicInfo::select('stage')
                    ->distinct()
                    ->whereNotNull('stage')
                    ->orderBy('stage')
                    ->pluck('stage')
                    ->map(fn ($v) => ['value' => $v, 'label' => $v])
                    ->toArray()
            );
        });
    }

    public function getBatchYears(): array
    {
        $fn = __FUNCTION__;
        return $this->trace($fn, function () use ($fn): array {
            return $this->rememberLookup($fn, [], fn () =>
                AcademicInfo::select('batch_year')
                    ->distinct()
                    ->whereNotNull('batch_year')
                    ->orderByDesc('batch_year')
                    ->pluck('batch_year')
                    ->map(fn ($v) => ['value' => (string) $v, 'label' => (string) $v])
                    ->toArray()
            );
        });
    }

    public function getAcademicYears(): array
    {
        $fn = __FUNCTION__;
        return $this->trace($fn, function () use ($fn): array {
            return $this->rememberLookup($fn, [], function (): array {
                $values = collect();

                if (Schema::hasColumn((new Classes())->getTable(), 'academic_year')) {
                    $values = $values->merge(Classes::query()->whereNotNull('academic_year')->distinct()->pluck('academic_year'));
                }

                if (Schema::hasColumn((new StudentScore())->getTable(), 'academic_year')) {
                    $values = $values->merge(StudentScore::query()->whereNotNull('academic_year')->distinct()->pluck('academic_year'));
                }

                return $values
                    ->filter()
                    ->unique()
                    ->sortDesc()
                    ->values()
                    ->map(fn ($value) => ['value' => (string) $value, 'label' => (string) $value])
                    ->toArray();
            });
        });
    }

    public function getSemesters(): array
    {
        $fn = __FUNCTION__;
        return $this->trace($fn, function () use ($fn): array {
            return $this->rememberLookup($fn, [], function (): array {
                $values = collect();

                if (Schema::hasColumn((new Classes())->getTable(), 'semester')) {
                    $values = $values->merge(Classes::query()->whereNotNull('semester')->distinct()->pluck('semester'));
                }

                if (Schema::hasColumn((new ClassProgram())->getTable(), 'semester')) {
                    $values = $values->merge(ClassProgram::query()->whereNotNull('semester')->distinct()->pluck('semester'));
                }

                if (Schema::hasColumn((new StudentScore())->getTable(), 'semester')) {
                    $values = $values->merge(StudentScore::query()->whereNotNull('semester')->distinct()->pluck('semester'));
                }

                $values = $values
                    ->filter(fn ($value) => $value !== null && $value !== '')
                    ->map(fn ($value) => (string) $value)
                    ->unique()
                    ->sortBy(fn ($value) => is_numeric($value) ? (int) $value : $value)
                    ->values();

                if ($values->isEmpty()) {
                    $values = collect(['1', '2']);
                }

                return $values
                    ->map(fn ($value) => ['value' => $value, 'label' => $value])
                    ->toArray();
            });
        });
    }

    public function getStudyDays(): array
    {
        $fn = __FUNCTION__;
        return $this->trace($fn, function () use ($fn): array {
            return $this->rememberLookup($fn, [], fn () =>
                AcademicInfo::select('study_days')
                    ->distinct()
                    ->whereNotNull('study_days')
                    ->orderBy('study_days')
                    ->pluck('study_days')
                    ->map(fn ($v) => ['value' => $v, 'label' => $v])
                    ->toArray()
            );
        });
    }

    public function getScoreFilters(): array
    {
        $fn = __FUNCTION__;
        return $this->trace($fn, function () use ($fn): array {
            return $this->rememberLookup($fn, [], fn (): array => [
                'stages' => $this->getStages(),
                'batch-years' => $this->getBatchYears(),
                'semesters' => $this->getSemesters(),
                'academic-years' => $this->getAcademicYears(),
                'faculties' => Faculty::query()
                    ->select('id', 'name')
                    ->orderBy('name')
                    ->get()
                    ->map(fn (Faculty $faculty) => [
                        'id' => $faculty->id,
                        'name' => $faculty->name,
                    ])
                    ->all(),
                'majors' => Major::query()
                    ->select('id', 'faculty_id', 'name')
                    ->orderBy('name')
                    ->get()
                    ->map(fn (Major $major) => [
                        'id' => $major->id,
                        'faculty_id' => $major->faculty_id,
                        'name' => $major->name,
                    ])
                    ->all(),
                'shifts' => Shift::query()
                    ->select('id', 'name', 'time_range')
                    ->orderBy('name')
                    ->get()
                    ->map(fn (Shift $shift) => [
                        'id' => $shift->id,
                        'name' => $shift->name,
                        'time_range' => $shift->time_range,
                    ])
                    ->all(),
                'classes' => Classes::query()
                    ->select('id', 'name', 'major_id', 'shift_id')
                    ->orderBy('name')
                    ->get()
                    ->map(fn (Classes $class) => [
                        'id' => $class->id,
                        'name' => $class->name,
                        'major_id' => $class->major_id,
                        'shift_id' => $class->shift_id,
                    ])
                    ->all(),
            ]);
        });
    }

    public function getAttendanceFilters(): array
    {
        $fn = __FUNCTION__;
        return $this->trace($fn, function () use ($fn): array {
            return $this->rememberLookup($fn, [], fn (): array => [
                'stages' => $this->getStages(),
                'batch-years' => $this->getBatchYears(),
                'semesters' => $this->getSemesters(),
                'academic-years' => $this->getAcademicYears(),
                'study-days' => $this->getStudyDays(),
                'faculties' => Faculty::query()
                    ->select('id', 'name')
                    ->orderBy('name')
                    ->get()
                    ->map(fn (Faculty $faculty) => [
                        'id' => $faculty->id,
                        'name' => $faculty->name,
                    ])
                    ->all(),
                'majors' => Major::query()
                    ->select('id', 'faculty_id', 'name')
                    ->orderBy('name')
                    ->get()
                    ->map(fn (Major $major) => [
                        'id' => $major->id,
                        'faculty_id' => $major->faculty_id,
                        'name' => $major->name,
                    ])
                    ->all(),
                'shifts' => Shift::query()
                    ->select('id', 'name', 'time_range')
                    ->orderBy('name')
                    ->get()
                    ->map(fn (Shift $shift) => [
                        'id' => $shift->id,
                        'name' => $shift->name,
                        'time_range' => $shift->time_range,
                    ])
                    ->all(),
            ]);
        });
    }

    public function getAttendanceClasses(array $rawFilters = []): array
    {
        $fn = __FUNCTION__;
        return $this->trace($fn, function () use ($fn, $rawFilters): array {
            $filters = $this->normalizeAttendanceLookupFilters($rawFilters);

            return $this->rememberLookup($fn, $filters, function () use ($filters): array {
                $query = Classes::query()
                    ->select('classes.id', 'classes.name')
                    ->when($filters['academic_year'], fn ($q, $value) => $q->where('classes.academic_year', $value));

                if ($this->hasAttendanceClassContextFilters($filters)) {
                    $query->where(function ($contextQuery) use ($filters) {
                        $this->applyAttendanceClassContextFilters($contextQuery, $filters);
                    });
                }

                $query->whereExists(function ($studentQuery) use ($filters) {
                    $studentQuery
                        ->selectRaw('1')
                        ->from('class_students')
                        ->join('academic_info', 'academic_info.student_id', '=', 'class_students.student_id')
                        ->leftJoin('majors as student_majors', 'student_majors.id', '=', 'academic_info.major_id')
                        ->whereColumn('class_students.class_id', 'classes.id')
                        ->where('class_students.status', 'Active');

                    $this->applyAttendanceAcademicFilters($studentQuery, $filters, 'academic_info', 'student_majors');
                });

                return $query
                    ->orderBy('classes.name')
                    ->get()
                    ->map(fn (Classes $class) => [
                        'id' => $class->id,
                        'name' => $class->name,
                    ])
                    ->all();
            });
        });
    }

    public function getAttendanceSubjects(array $rawFilters = []): array
    {
        $fn = __FUNCTION__;
        return $this->trace($fn, function () use ($fn, $rawFilters): array {
            $filters = $this->normalizeAttendanceLookupFilters($rawFilters);

            return $this->rememberLookup($fn, $filters, function () use ($filters): array {
                $contexts = $this->attendanceSubjectContexts($filters);
                $needsCurriculumFilter = $filters['class_id']
                    || $filters['major_id']
                    || $filters['year_level']
                    || $filters['semester'];

                if ($filters['class_id'] && empty($contexts)) {
                    return [];
                }

                if ($filters['class_id']) {
                    $scheduled = $this->getScheduledAttendanceSubjects($filters);

                    if (!empty($scheduled)) {
                        return $scheduled;
                    }
                }

                $query = Subject::query()->select('id', 'name');

                if (!empty($contexts)) {
                    $query->whereIn('id', MajorSubject::query()
                        ->select('subject_id')
                        ->where(function ($contextQuery) use ($contexts) {
                            foreach ($contexts as $context) {
                                $contextQuery->orWhere(function ($slot) use ($context) {
                                    $this->applyMajorSubjectContext($slot, $context);
                                });
                            }
                        }));
                } elseif ($needsCurriculumFilter) {
                    $query->whereIn('id', MajorSubject::query()
                        ->select('subject_id')
                        ->when($filters['major_id'], fn ($q, $value) => $q->where('major_id', $value))
                        ->when($filters['year_level'], fn ($q, $value) => $q->where('year_level', $value))
                        ->when($filters['semester'], fn ($q, $value) => $q->where('semester', $value)));
                }

                return $query
                    ->orderBy('name')
                    ->get()
                    ->map(fn (Subject $subject) => [
                        'id' => $subject->id,
                        'name' => $subject->name,
                    ])
                    ->all();
            });
        });
    }

    public function getStudentTypes(): array
    {
        $fn = __FUNCTION__;
        return $this->trace($fn, function () use ($fn): array {
            return [
                ['value' => 'PAY',     'label' => 'PAY'],
                ['value' => 'PENDING', 'label' => 'PENDING'],
                ['value' => 'PASS',    'label' => 'PASS'],
                ['value' => 'FAIL',    'label' => 'FAIL'],
            ];
        });
    }

    public function getTeachers()
    {
        $fn = __FUNCTION__;
        return $this->trace($fn, function () use ($fn) {
            return $this->rememberLookup($fn, [], fn () => Teacher::query()
                ->select('id', 'first_name', 'last_name')
                ->orderBy('first_name')
                ->get()
                ->map(fn ($t) => [
                    'id'   => $t->id,
                    'name' => trim($t->first_name . ' ' . $t->last_name),
                ]));
        });
    }

    public function getFullLookup(
        mixed $facultyId = null,
        mixed $majorId = null,
        mixed $provinceId = null,
        mixed $districtId = null,
        mixed $shiftId = null
    ): array {
        $fn = __FUNCTION__;
        return $this->trace($fn, function () use ($fn, $facultyId, $majorId, $provinceId, $districtId, $shiftId): array {
            $facultyId  = $this->toNullableInt($facultyId);
            $majorId    = $this->toNullableInt($majorId);
            $provinceId = $this->toNullableInt($provinceId);
            $districtId = $this->toNullableInt($districtId);
            $shiftId    = $this->toNullableInt($shiftId);
            return $this->rememberLookup($fn, [
                'faculty_id'  => $facultyId,
                'major_id'    => $majorId,
                'province_id' => $provinceId,
                'district_id' => $districtId,
                'shift_id'    => $shiftId,
            ], fn (): array => [
                'faculties'     => $this->getFaculties(),
                'majors'        => $this->getMajorsByFaculty($facultyId),
                'subjects'      => $this->getSubjectsByMajor($majorId),
                'provinces'     => $this->getProvinces(),
                'districts'     => $this->getDistrictsByProvince($provinceId),
                'communes'      => $this->getCommunesByDistrict($districtId),
                'shifts'        => $this->getShifts(),
                'academic_years' => $this->getAcademicYears(),
                'classes'       => $this->getClasses($majorId, $shiftId),
                'student_types' => $this->getStudentTypes(),
            ]);
        });
    }

    private function normalizeAttendanceLookupFilters(array $filters): array
    {
        return [
            'class_id' => $this->toNullableInt($filters['class_id'] ?? null),
            'faculty_id' => $this->toNullableInt($filters['faculty_id'] ?? null),
            'major_id' => $this->toNullableInt($filters['major_id'] ?? null),
            'shift_id' => $this->toNullableInt($filters['shift_id'] ?? null),
            'year_level' => $this->toNullableInt($filters['year_level'] ?? $filters['stage'] ?? null),
            'semester' => $this->toNullableInt($filters['semester'] ?? null),
            'batch_year' => $this->toNullableString($filters['batch_year'] ?? $filters['batch'] ?? null),
            'academic_year' => $this->toNullableString($filters['academic_year'] ?? null),
            'study_day' => $this->toNullableString($filters['study_day'] ?? $filters['study_days'] ?? null),
        ];
    }

    private function hasAttendanceClassContextFilters(array $filters): bool
    {
        return (bool) (
            $filters['faculty_id']
            || $filters['major_id']
            || $filters['shift_id']
            || $filters['year_level']
            || $filters['semester']
        );
    }

    private function applyAttendanceClassContextFilters($query, array $filters): void
    {
        $query
            ->where(function ($direct) use ($filters) {
                $direct
                    ->when($filters['major_id'], fn ($q, $value) => $q->where('classes.major_id', $value))
                    ->when($filters['shift_id'], fn ($q, $value) => $q->where('classes.shift_id', $value))
                    ->when($filters['year_level'], fn ($q, $value) => $q->where('classes.year_level', $value))
                    ->when($filters['semester'], fn ($q, $value) => $q->where('classes.semester', $value));

                if ($filters['faculty_id'] && !$filters['major_id']) {
                    $direct->whereIn('classes.major_id', Major::query()
                        ->select('id')
                        ->where('faculty_id', $filters['faculty_id']));
                }
            })
            ->orWhereExists(function ($program) use ($filters) {
                $program
                    ->selectRaw('1')
                    ->from('class_programs')
                    ->leftJoin('majors as program_majors', 'program_majors.id', '=', 'class_programs.major_id')
                    ->whereColumn('class_programs.class_id', 'classes.id')
                    ->when($filters['major_id'], fn ($q, $value) => $q->where('class_programs.major_id', $value))
                    ->when($filters['shift_id'], fn ($q, $value) => $q->where('class_programs.shift_id', $value))
                    ->when($filters['year_level'], fn ($q, $value) => $q->where('class_programs.year_level', $value))
                    ->when($filters['semester'], fn ($q, $value) => $q->where('class_programs.semester', $value));

                if ($filters['faculty_id'] && !$filters['major_id']) {
                    $program->where('program_majors.faculty_id', $filters['faculty_id']);
                }
            });
    }

    private function applyAttendanceAcademicFilters($query, array $filters, string $academicTable, string $majorTable): void
    {
        $query
            ->when($filters['major_id'], fn ($q, $value) => $q->where("{$academicTable}.major_id", $value))
            ->when($filters['shift_id'], fn ($q, $value) => $q->where("{$academicTable}.shift_id", $value))
            ->when($filters['batch_year'], fn ($q, $value) => $q->where("{$academicTable}.batch_year", $value))
            ->when($filters['study_day'], fn ($q, $value) => $q->where("{$academicTable}.study_days", $value));

        if ($filters['faculty_id']) {
            $query->where("{$majorTable}.faculty_id", $filters['faculty_id']);
        }

        if ($filters['year_level']) {
            $query->where(function ($stageQuery) use ($academicTable, $filters) {
                $stageQuery
                    ->where("{$academicTable}.stage", (string) $filters['year_level'])
                    ->orWhere("{$academicTable}.stage", 'Year ' . $filters['year_level']);
            });
        }
    }

    private function attendanceSubjectContexts(array $filters): array
    {
        if (!$filters['class_id']) {
            return $filters['major_id'] || $filters['year_level'] || $filters['semester']
                ? [$this->onlyContextValues($filters)]
                : [];
        }

        $class = Classes::query()
            ->with('programs')
            ->find($filters['class_id']);

        if (!$class) {
            return [];
        }

        $contexts = $class->programs
            ->map(fn (ClassProgram $program) => [
                'major_id' => $program->major_id,
                'year_level' => $program->year_level,
                'semester' => $program->semester,
            ])
            ->filter(fn (array $context) => $context['major_id'])
            ->values()
            ->all();

        if (empty($contexts) && $class->major_id) {
            $contexts[] = [
                'major_id' => $class->major_id,
                'year_level' => $class->year_level,
                'semester' => $class->semester,
            ];
        }

        return array_values(array_filter(array_map(
            fn (array $context) => $this->mergeRequestedContext($context, $filters),
            $contexts
        )));
    }

    private function getScheduledAttendanceSubjects(array $filters): array
    {
        $query = ClassSchedule::query()
            ->join('subjects', 'subjects.id', '=', 'class_schedules.subject_id')
            ->where('class_schedules.class_id', $filters['class_id'])
            ->when($filters['shift_id'], fn ($q, $value) => $q->where('class_schedules.shift_id', $value))
            ->when($filters['academic_year'], fn ($q, $value) => $q->where('class_schedules.academic_year', $value))
            ->when($filters['year_level'], fn ($q, $value) => $q->where('class_schedules.year_level', $value))
            ->when($filters['semester'], fn ($q, $value) => $q->where('class_schedules.semester', $value));

        $dayOfWeek = $this->toScheduleDay($filters['study_day']);
        if ($dayOfWeek) {
            $query->where('class_schedules.day_of_week', $dayOfWeek);
        }

        return $query
            ->select('subjects.id', 'subjects.name')
            ->distinct()
            ->orderBy('subjects.name')
            ->get()
            ->map(fn ($subject) => [
                'id' => $subject->id,
                'name' => $subject->name,
            ])
            ->all();
    }

    private function mergeRequestedContext(array $context, array $filters): ?array
    {
        foreach (['major_id', 'year_level', 'semester'] as $key) {
            if (!$filters[$key]) {
                continue;
            }

            if (!empty($context[$key]) && (int) $context[$key] !== (int) $filters[$key]) {
                return null;
            }

            $context[$key] = $filters[$key];
        }

        return $context;
    }

    private function onlyContextValues(array $filters): array
    {
        return [
            'major_id' => $filters['major_id'],
            'year_level' => $filters['year_level'],
            'semester' => $filters['semester'],
        ];
    }

    private function applyMajorSubjectContext($query, array $context): void
    {
        $query
            ->when($context['major_id'] ?? null, fn ($q, $value) => $q->where('major_id', $value))
            ->when($context['year_level'] ?? null, fn ($q, $value) => $q->where('year_level', $value))
            ->when($context['semester'] ?? null, fn ($q, $value) => $q->where('semester', $value));
    }

    private function rememberLookup(string $name, array $params, callable $callback): mixed
    {
        if (!config('cache.lookup.enabled', true)) {
            return $callback();
        }

        $ttlSeconds = max(30, (int) config('cache.lookup.ttl_seconds', 300));
        $key = $this->lookupCacheKey($name, $params);

        return Cache::remember($key, now()->addSeconds($ttlSeconds), $callback);
    }

    private function lookupCacheKey(string $name, array $params): string
    {
        ksort($params);
        $payload = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return 'lookup:' . $name . ':' . sha1((string) $payload);
    }

    private function toNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        // Handle "Year 1", "Year 2" etc. from AcademicInfo.stage
        $extracted = (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);
        return $extracted > 0 ? $extracted : null;
    }

    private function toNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function toScheduleDay(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $normalized = strtolower(trim($value));
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        foreach ($days as $day) {
            if ($normalized === $day || str_starts_with($normalized, substr($day, 0, 3))) {
                return ucfirst($day);
            }
        }

        return null;
    }
}



