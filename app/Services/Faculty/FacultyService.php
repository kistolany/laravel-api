<?php

namespace App\Services\Faculty;

use App\Services\BaseService;

use App\DTOs\PaginatedResult;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\Faculty\FacultyResource;
use App\Models\Faculty;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
class FacultyService extends BaseService
{
    public function index(): PaginatedResult
    {
        return $this->trace(__FUNCTION__, function (): PaginatedResult {
            //  find snd sort by lstest 
            $query = Faculty::query()->latest()
            
                // handle on search by name
                ->when(request('name'), fn($q, $term) => $q->search($term));
            
            return $this->paginateResponse($query, FacultyResource::class);
            
            
        });
    }

    /**
     * Return the full faculty → major → subject hierarchy for the Academic tree UI.
     */
    public function tree(): array
    {
        $faculties = Faculty::with([
            'majors.majorSubjects.subject',
        ])->orderBy('name')->get();

        return $faculties->map(function (Faculty $faculty) {
            return [
                'key'      => 'f-' . $faculty->id,
                'id'       => $faculty->id,
                'name'     => $faculty->name,
                'level'    => 'faculty',
                'children' => $faculty->majors->map(function ($major) {
                    return [
                        'key'      => 'm-' . $major->id,
                        'id'       => $major->id,
                        'name'     => $major->name,
                        'level'    => 'major',
                        'faculty'  => $major->faculty?->name,
                        'faculty_id' => $major->faculty_id,
                        'children' => $major->majorSubjects->map(function ($ms) {
                            return [
                                'key'        => 'ms-' . $ms->id,
                                'id'         => $ms->id,
                                'name'       => $ms->subject?->name ?? '',
                                'subject_id' => $ms->subject_id,
                                'level'      => 'subject',
                                'year'       => $ms->year_level,
                                'semester'   => $ms->semester,
                                'major_id'   => $ms->major_id,
                            ];
                        })->values()->all(),
                    ];
                })->values()->all(),
            ];
        })->values()->all();
    }

    public function findById(int $id): Faculty
    {
        return $this->trace(__FUNCTION__, function () use ($id): Faculty {
            // find id method
            $faculty = Faculty::find($id);
            
            // validate if id null throw error
            if (!$faculty) {
                Log::warning('Faculty not found.', ['id' => $id]);
                throw new ApiException(ResponseStatus::NOT_FOUND, "Faculty not found.");
            }
            
            return $faculty;
            
            
        });
    }

    public function create(array $data): Faculty
    {
        return $this->trace(__FUNCTION__, function () use ($data): Faculty {
            // call method and validate exist name 
            $validatedData = $this->validateExisting($data);
            
            //create and return
            return Faculty::create($validatedData);
            
            
        });
    }

    public function update(int $id, array $data): Faculty
    {
        return $this->trace(__FUNCTION__, function () use ($id, $data): Faculty {
            // 1. Check if ID exists (Throws ApiException if not)
            $faculty = $this->findById($id);
            
            // 2. Validate data (Ignoring the current ID for unique checks)
            $validatedData = $this->validateExisting($data, $faculty->id);
            
            // 3. Perform update
            $faculty->update($validatedData);
            return $faculty;
            
            
        });
    }

    public function delete($id): bool
    {
        return $this->trace(__FUNCTION__, function () use ($id): bool {
            $faculty = $this->findById($id);

            // delete major_subjects → majors first to satisfy FK constraints
            foreach ($faculty->majors as $major) {
                $major->majorSubjects()->delete();
            }
            $faculty->majors()->delete();

            return $faculty->delete();
        });
    }

    protected function validateExisting(array $data, ?int $ignoreId = null): array
    {
        $validator = \Illuminate\Support\Facades\Validator::make($data, [
            'name' => [
                'required',
                'string',
                Rule::unique('faculties', 'name')->ignore($ignoreId),
            ],
        ]);

        if ($validator->fails()) {
            $message = $validator->errors()->first('name') ?: 'Validation failed for faculty data.';

            Log::warning('Faculty validation failed.', [
                'ignore_id' => $ignoreId,
                'name'      => $data['name'] ?? null,
                'errors'    => $validator->errors()->toArray(),
            ]);

            throw new ApiException(
                ResponseStatus::EXISTING_DATA,
                $message,
                data: ['errors' => $validator->errors()]
            );
        }

        return $validator->validated();
    }
}




