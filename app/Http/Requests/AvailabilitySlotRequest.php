<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AvailabilitySlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:recurring,one_time'],
            'day_of_week' => ['required_if:type,recurring', 'nullable', 'integer', 'min:0', 'max:6'],
            'date' => ['required_if:type,one_time', 'nullable', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
