<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAgencyExperienceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $experience = $this->route('experience');

        return $this->user()?->isAgencyOwner() === true
            && $experience?->agency?->user_id === $this->user()->id;
    }

    public function rules(): array
    {
        return [
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'destination' => ['required', 'string', 'max:191'],
            'caption' => ['required', 'string', 'max:255'],
            'alt_text' => ['required', 'string', 'max:255'],
        ];
    }
}
