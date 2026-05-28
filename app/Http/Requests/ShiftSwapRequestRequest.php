<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShiftSwapRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'requester_schedule_id' => ['nullable', 'required_without:requester_schedule_exception_id', 'exists:schedules,id'],
            'requester_schedule_exception_id' => ['nullable', 'required_without:requester_schedule_id', 'exists:schedule_exceptions,id'],
            'requester_date' => ['required', 'date'],
            'target_caregiver_id' => ['required', 'exists:caregivers,id'],
            'target_schedule_id' => ['nullable', 'required_without:target_schedule_exception_id', 'exists:schedules,id'],
            'target_schedule_exception_id' => ['nullable', 'required_without:target_schedule_id', 'exists:schedule_exceptions,id'],
            'target_date' => ['required', 'date'],
        ];
    }
}
