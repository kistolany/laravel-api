<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class DashboardStatsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $period = strtolower((string) $this->query('period', 'month'));

        $this->merge([
            'period' => in_array($period, ['week', 'month', 'year'], true) ? $period : 'month',
        ]);
    }

    public function rules(): array
    {
        return [
            'period' => 'required|in:week,month,year',
        ];
    }
}
