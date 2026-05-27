<?php

namespace App\Http\Requests;

use App\Enums\ScheduleExceptionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScheduleExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'schedule_id' => ['nullable', 'exists:schedules,id'],
            'caregiver_id' => ['required', 'exists:caregivers,id'],
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'type' => ['required', Rule::enum(ScheduleExceptionType::class)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
