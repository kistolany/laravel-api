<?php

namespace App\Http\Controllers\ApiController\AcademicSetting;

use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AcademicSetting\YearLevelRequest;
use App\Http\Resources\AcademicSetting\YearLevelResource;
use App\Models\ClassProgram;
use App\Models\ClassSchedule;
use App\Models\StudentScore;
use App\Models\YearLevel;
use App\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class YearLevelController extends Controller
{
    use ApiResponseTrait;

    public function index()
    {
        $items = YearLevel::query()->ordered()->get();

        return $this->success(YearLevelResource::collection($items)->resolve());
    }

    public function show(int $id)
    {
        return $this->success(new YearLevelResource($this->findYearLevel($id)));
    }

    public function store(YearLevelRequest $request)
    {
        $level = YearLevel::create($this->payload($request->validated()));
        $this->clearLookupCache();

        return $this->success(new YearLevelResource($level), 'Year level created successfully.');
    }

    public function update(YearLevelRequest $request, int $id)
    {
        $level = $this->findYearLevel($id);
        $level->update($this->payload($request->validated()));
        $this->clearLookupCache();

        return $this->success(new YearLevelResource($level->refresh()), 'Year level updated successfully.');
    }

    public function destroy(int $id)
    {
        $level = $this->findYearLevel($id);

        if ($this->isUsed($level)) {
            throw new ApiException(
                ResponseStatus::BAD_REQUEST,
                'This year level is already used by programs, schedules, or scores. Disable it instead of deleting it.'
            );
        }

        $level->delete();
        $this->clearLookupCache();

        return $this->success('Year level deleted successfully.');
    }

    private function findYearLevel(int $id): YearLevel
    {
        $level = YearLevel::find($id);

        if (!$level) {
            throw new ApiException(ResponseStatus::NOT_FOUND, "Year level id: {$id} not found.");
        }

        return $level;
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

    private function isUsed(YearLevel $level): bool
    {
        if (!$level->number) {
            return false;
        }

        return $this->columnHasValue(ClassProgram::class, 'year_level', $level->number)
            || $this->columnHasValue(ClassSchedule::class, 'year_level', $level->number)
            || $this->columnHasValue(StudentScore::class, 'year_level', $level->number);
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
