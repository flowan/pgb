<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OpenSwapOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'offered_schedule_id' => ['nullable', 'required_without:offered_schedule_exception_id', 'exists:schedules,id'],
            'offered_schedule_exception_id' => ['nullable', 'required_without:offered_schedule_id', 'exists:schedule_exceptions,id'],
            'offered_date' => ['required', 'date'],
        ];
    }
}
