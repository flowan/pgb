<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OpenSwapRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'schedule_id' => ['nullable', 'required_without:schedule_exception_id', 'exists:schedules,id'],
            'schedule_exception_id' => ['nullable', 'required_without:schedule_id', 'exists:schedule_exceptions,id'],
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
