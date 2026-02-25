<?php

namespace App\Services;

use App\DTOs\PaginatedResult;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\StudentResource;
use App\Models\Students;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class StudentService extends BaseService
{
    /**
     * Get all students with their academic information
     */
    public function index(): PaginatedResult
    {
        // 1. Start query with relationships
        $query = Students::with([
            'academicInfo.major',
            'academicInfo.shift',
            'addresses.province',
            'addresses.district',
            'addresses.commune',
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

        // 7. Final Sort and Paginate
        return $this->paginateResponse($query->latest(), StudentResource::class);
    }

    /**
     * Find a specific student by ID
     */
    public function findById(int $id): Students
    {
        $student = Students::with([
            'academicInfo.major',
            'academicInfo.shift',
            'addresses.province',
            'addresses.district',
            'addresses.commune',
        ])->find($id);

        if (!$student) {
            throw new ApiException(ResponseStatus::NOT_FOUND, "Student with ID :$id not found.");
        }

        return $student;
    }

    /**
     * Create Student and Academic Info (Transaction)
     */
    public function create(array $data): Students
    {
        $validatedData = $this->validateExisting($data);
        $addresses = $validatedData['addresses'] ?? [];
        unset($validatedData['addresses']);

        $validatedData = $this->handleImageUpload($validatedData);

        return DB::transaction(function () use ($validatedData, $addresses) {
            // 1. Create the Student record
            $student = Students::create($validatedData);

            // 2. Create the Academic Info record linked to this student
            $student->academicInfo()->create($validatedData);

            // 3. Create Address records linked to this student
            foreach ($addresses as $addressData) {
                $student->addresses()->create($addressData);
            }

            return $student->load([
                'academicInfo.major',
                'academicInfo.shift',
                'addresses.province',
                'addresses.district',
                'addresses.commune',
            ]);
        });
    }

    /**
     * Update Student and Academic Info (Transaction)
     */
    public function update(int $id, array $data): Students
    {
        $student = $this->findById($id);
        $validatedData = $this->validateExisting($data, $student->id);
        $addresses = $validatedData['addresses'] ?? [];
        unset($validatedData['addresses']);

        $validatedData = $this->handleImageUpload($validatedData, $student->image);

        return DB::transaction(function () use ($student, $validatedData, $addresses) {
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

            return $student->refresh()->load([
                'academicInfo.major',
                'academicInfo.shift',
                'addresses.province',
                'addresses.district',
                'addresses.commune',
            ]);
        });
    }

    /**
     * Delete Student (Academic info will delete if cascade is set in migration)
     */
    public function delete(int $id): bool
    {
        $student = $this->findById($id);
        return $student->delete();
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
            'status'            => 'sometimes|in:active,inactive',

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
        ]);

        if ($validator->fails()) {
            throw new ApiException(
                ResponseStatus::EXISTING_DATA,
                "Validation failed for student data.",
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
        $student = $this->findById($id);
        $student->update(['status' => $status]);

        return $student->refresh()->load([
            'academicInfo.major',
            'academicInfo.shift',
            'addresses.province',
            'addresses.district',
            'addresses.commune',
        ]);
    }

    /**
     * Update student image only
     */
    public function updateImage(int $id, UploadedFile $image): Students
    {
        $student = $this->findById($id);
        $validatedData = $this->handleImageUpload(['image' => $image], $student->image);
        $student->update($validatedData);

        return $student->refresh()->load([
            'academicInfo.major',
            'academicInfo.shift',
            'addresses.province',
            'addresses.district',
            'addresses.commune',
        ]);
    }

    /**
     * Get classes for a student
     */
    public function classes(int $id): Students
    {
        $student = $this->findById($id);
        $student->load('classes');

        return $student;
    }

    private function handleImageUpload(array $validatedData, ?string $oldPath = null): array
    {
        $file = $validatedData['image'] ?? null;

        if ($file instanceof UploadedFile) {
            if ($oldPath) {
                Storage::disk('public')->delete($oldPath);
            }
            $validatedData['image'] = $file->store('student-Image', 'public');
            return $validatedData;
        }

        unset($validatedData['image']);
        return $validatedData;
    }
}
