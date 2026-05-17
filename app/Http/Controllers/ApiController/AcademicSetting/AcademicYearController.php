<?php

namespace App\Http\Controllers\ApiController\AcademicSetting;

use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AcademicSetting\AcademicYearRequest;
use App\Models\AcademicYear;
use App\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\Cache;

class AcademicYearController extends Controller
{
    use ApiResponseTrait;

    public function index()
    {
        return $this->success(AcademicYear::ordered()->get());
    }

    public function show(int $id)
    {
        return $this->success($this->find($id));
    }

    public function store(AcademicYearRequest $request)
    {
        $year = AcademicYear::create($this->payload($request->validated()));
        Cache::flush();

        return $this->success($year, 'Academic year created successfully.');
    }

    public function update(AcademicYearRequest $request, int $id)
    {
        $year = $this->find($id);
        $year->update($this->payload($request->validated()));
        Cache::flush();

        return $this->success($year->refresh(), 'Academic year updated successfully.');
    }

    public function destroy(int $id)
    {
        $this->find($id)->delete();
        Cache::flush();

        return $this->success(null, 'Academic year deleted successfully.');
    }

    private function find(int $id): AcademicYear
    {
        $year = AcademicYear::find($id);

        if (!$year) {
            throw new ApiException(ResponseStatus::NOT_FOUND, "Academic year id: {$id} not found.");
        }

        return $year;
    }

    private function payload(array $data): array
    {
        return [
            'name'       => $data['name'],
            'status'     => $data['status'] ?? 'active',
            'start_date' => $data['start_date'] ?? null,
            'end_date'   => $data['end_date'] ?? null,
        ];
    }
}
