<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShiftTakeoverRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_schedule_id' => ['nullable', 'exists:schedules,id', 'required_without:target_schedule_exception_id'],
            'target_schedule_exception_id' => ['nullable', 'exists:schedule_exceptions,id', 'required_without:target_schedule_id'],
            'target_date' => ['required', 'date'],
            'target_caregiver_id' => ['required', 'exists:caregivers,id'],
            'message' => ['nullable', 'string'],
        ];
    }
}
