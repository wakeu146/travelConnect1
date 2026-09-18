<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAgencyServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAgencyOwner() === true && $this->user()->agency?->user_id === $this->user()->id;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:120']];
    }
}
