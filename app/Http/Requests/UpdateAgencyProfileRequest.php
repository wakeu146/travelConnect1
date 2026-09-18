<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgencyProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAgencyOwner() === true && $this->user()->agency?->user_id === $this->user()->id;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'company_name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'license_number' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'agency_phone' => ['nullable', 'string', 'max:30'],
            'agency_email' => ['nullable', 'email', 'max:191'],
            'website' => ['nullable', 'url', 'max:255'],
            'countries' => ['array'],
            'countries.*' => ['integer', 'exists:countries,id'],
            'services' => ['array'],
            'services.*' => ['integer', 'exists:services,id'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
