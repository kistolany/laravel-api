<?php

namespace App\Http\Controllers\ApiController\AcademicSetting;

use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AcademicSetting\AcademicTermRequest;
use App\Http\Resources\AcademicSetting\AcademicTermResource;
use App\Models\AcademicTerm;
use App\Models\ClassProgram;
use App\Models\ClassSchedule;
use App\Models\StudentScore;
use App\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class AcademicTermController extends Controller
{
    use ApiResponseTrait;

    public function index()
    {
        $items = AcademicTerm::query()->ordered()->get();

        return $this->success(AcademicTermResource::collection($items)->resolve());
    }

    public function show(int $id)
    {
        return $this->success(new AcademicTermResource($this->findTerm($id)));
    }

    public function store(AcademicTermRequest $request)
    {
        $term = AcademicTerm::create($this->payload($request->validated()));
        $this->clearLookupCache();

        return $this->success(new AcademicTermResource($term), 'Academic term created successfully.');
    }

    public function update(AcademicTermRequest $request, int $id)
    {
        $term = $this->findTerm($id);
        $term->update($this->payload($request->validated()));
        $this->clearLookupCache();

        return $this->success(new AcademicTermResource($term->refresh()), 'Academic term updated successfully.');
    }

    public function destroy(int $id)
    {
        $term = $this->findTerm($id);

        if ($this->isUsed($term)) {
            throw new ApiException(
                ResponseStatus::BAD_REQUEST,
                'This academic term is already used by programs, schedules, or scores. Disable it instead of deleting it.'
            );
        }

        $term->delete();
        $this->clearLookupCache();

        return $this->success('Academic term deleted successfully.');
    }

    private function findTerm(int $id): AcademicTerm
    {
        $term = AcademicTerm::find($id);

        if (!$term) {
            throw new ApiException(ResponseStatus::NOT_FOUND, "Academic term id: {$id} not found.");
        }

        return $term;
    }

    private function payload(array $data): array
    {
        return [
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'number' => $data['number'] ?? null,
            'sort_order' => $data['sort_order'] ?? ($data['number'] ?? 0),
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
        ];
    }

    private function isUsed(AcademicTerm $term): bool
    {
        if (!$term->number) {
            return false;
        }

        return $this->columnHasValue(ClassProgram::class, 'semester', $term->number)
            || $this->columnHasValue(ClassSchedule::class, 'semester', $term->number)
            || $this->columnHasValue(StudentScore::class, 'semester', $term->number);
    }

    private function columnHasValue(string $modelClass, string $column, mixed $value): bool
    {
        $model = new $modelClass();

        if (!Schema::hasColumn($model->getTable(), $column)) {
            return false;
        }

        return $modelClass::query()->where($column, $value)->exists();
    }

    private function clearLookupCache(): void
    {
        Cache::flush();
    }
}
