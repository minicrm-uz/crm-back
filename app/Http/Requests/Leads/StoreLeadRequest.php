<?php

namespace App\Http\Requests\Leads;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'source' => ['required', Rule::enum(LeadSource::class)],
            'status' => ['nullable', Rule::enum(LeadStatus::class)],
            'note' => ['nullable', 'string'],
        ];
    }
}
