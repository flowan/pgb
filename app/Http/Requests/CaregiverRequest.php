<?php

namespace App\Http\Requests;

use App\Enums\CaregiverType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CaregiverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(CaregiverType::class)],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
