<?php
namespace App\Services;

use App\Models\AcademicInfo;

use App\DTOs\PaginatedResult;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\AcademicInfoResource;
use Illuminate\Validation\Rule;

class AcademicInfoService extends BaseService
{
    /*
   public function index():PaginatedResult
   {
       $query = AcademicInfo::query()->latest();

       // optional search by student_id
       $query->when(request('student_id'), fn($q, $term) =>
           $q->where('student_id', 'like', "%{$term}%")
       );

       return $this->paginateResponse($query, AcademicInfoResource::class);
   }

   public function findById(int $id): AcademicInfo

    {
        $academicInfo = AcademicInfo::find($id);

        if (!$academicInfo) {
            throw new ApiException(
                ResponseStatus::NOT_FOUND,
                "AcademicInfo not found."
            );
        }
        return $academicInfo;
    }

    public function create(array $data): AcademicInfo
    {

    // validate existing name 
        $validatedData = $this->validateExisting($data);

        return AcademicInfo::create($validatedData);
    }

    public function update(int $id, array $data): AcademicInfo
    {
        $academicInfo = $this->findById($id);

        $validatedData = $this->validateExisting($data, $academicInfo->id);

        $academicInfo->update($validatedData);

        return $academicInfo;
    }

    public function delete(int $id): bool
    {
        $academicInfo = $this->findById($id);

        return $academicInfo->delete();
    }

    protected function validateExisting(array $data, ?int $ignoreId = null): array
    {
        $validator = \Illuminate\Support\Facades\Validator::make($data, [
            'student_id' => [
            'required',
            'string',
            Rule::unique('academic_info', 'student_id')->ignore($ignoreId),
        ],

        'major_id' => [
            'required',
            'exists:majors,id',
        ],

        'shift_id' => [
            'required',
            'exists:shifts,id',
        ],

        'batch_year' => [
            'required',
            'integer',
        ],

        'stage' => [
            'required',
        ],

        'study_days' => [
            'required',
            'string',
        ],
    ]);

        if ($validator->fails()) {
            throw new ApiException(
                ResponseStatus::EXISTING_DATA,
                "AcademicInfo already exists.",
                data: ['errors' => $validator->errors()]
            );
        }

        return $validator->validated();
    }

        * Get All Academic Info
        */
  
}
