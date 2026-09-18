<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAgencyExperienceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAgencyOwner() === true && $this->user()->agency?->user_id === $this->user()->id;
    }

    public function rules(): array
    {
        return ['image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'], 'destination' => ['required', 'string', 'max:191'], 'caption' => ['required', 'string', 'max:255'], 'alt_text' => ['required', 'string', 'max:255']];
    }
}
