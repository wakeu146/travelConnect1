<?php

namespace App\Http\Requests;

use App\Enums\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgencyDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAgencyOwner() === true && $this->user()->agency?->user_id === $this->user()->id;
    }

    public function rules(): array
    {
        return ['type' => ['required', Rule::enum(DocumentType::class)], 'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240']];
    }
}
