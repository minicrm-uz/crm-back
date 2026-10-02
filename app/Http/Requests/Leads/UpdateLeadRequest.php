<?php

namespace App\Http\Requests\Leads;

use App\Enums\LeadSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'email' => ['sometimes', 'nullable', 'email:rfc', 'max:255'],
            'source' => ['sometimes', Rule::enum(LeadSource::class)],
            'note' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
