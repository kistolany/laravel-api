<?php

namespace App\Services;

use App\DTOs\PaginatedResult;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\Api\V1\AddressResource;
use App\Models\Address;
use Illuminate\Validation\Rule;

class AddressService extends BaseService
{
    // Inject services for validation
    public function __construct(
        //protected StudentService $studentService,
        protected ProvinceService $provinceService,
        protected DistrictService $districtService,
        protected CommuneService $communeService
    ) {}

    public function index(): PaginatedResult
    {
        $query = Address::query()
            ->with(['province', 'district', 'commune'])->latest();
        // search by student_id
        $query->when(request('student_id'), fn($q, $id) =>$q->where('student_id', $id));
        // search by province
        $query->when(request('province_id'), fn($q, $id) =>$q->where('province_id', $id));
        return $this->paginateResponse($query, AddressResource::class);
    }

    public function findById(int $id): Address
    {
        $address = Address::with(['province', 'district', 'commune'])
            ->find($id);

        if (!$address) {
            throw new ApiException(
                ResponseStatus::NOT_FOUND,
                "Address not found."
            );
        }

        return $address;
    }

    public function create(array $data): Address
    {
        // validate foreign keys exist
        $this->studentService->findById($data['student_id']);
        $this->provinceService->findById($data['province_id']);
        $this->districtService->findById($data['district_id']);
        $this->communeService->findById($data['commune_id']);

        $validatedData = $this->validateExisting($data);

        return Address::create($validatedData);
    }

    public function update(int $id, array $data): Address
    {
        $address = $this->findById($id);

        // validate foreign keys if provided
        if (isset($data['student_id'])) {
            $this->studentService->findById($data['student_id']);
        } else {
            $data['student_id'] = $address->student_id;
        }

        if (isset($data['province_id'])) {
            $this->provinceService->findById($data['province_id']);
        } else {
            $data['province_id'] = $address->province_id;
        }

        if (isset($data['district_id'])) {
            $this->districtService->findById($data['district_id']);
        } else {
            $data['district_id'] = $address->district_id;
        }

        if (isset($data['commune_id'])) {
            $this->communeService->findById($data['commune_id']);
        } else {
            $data['commune_id'] = $address->commune_id;
        }

        $validatedData = $this->validateExisting($data, $address->id);

        $address->update($validatedData);

        return $address;
    }

    public function delete(int $id): bool
    {
        $address = $this->findById($id);

        return $address->delete();
    }

    /**
     * Validate duplicate address per student
     */
    protected function validateExisting(array $data, ?int $ignoreId = null): array
    {
        $studentId = $data['student_id'] ?? null;

        $validator = \Illuminate\Support\Facades\Validator::make($data, [
            'student_id' => [
                'required',
                'exists:students,student_id'
            ],
            'address_type' => [
                'required',
                'string',
                Rule::unique('addresses', 'address_type')
                    ->where('student_id', $studentId)
                    ->ignore($ignoreId),
            ],
            'house_number'  => 'nullable|string|max:50',
            'street_number' => 'nullable|string|max:50',
            'village'       => 'nullable|string|max:255',
            'province_id'   => 'required|exists:provinces,id',
            'district_id'   => 'required|exists:districts,id',
            'commune_id'    => 'required|exists:communes,id',
        ]);

        if ($validator->fails()) {
            throw new ApiException(
                ResponseStatus::EXISTING_DATA,
                "This address type already exists for this student.",
                data: ['errors' => $validator->errors()]
            );
        }

        return $validator->validated();
    }
}