<?php

namespace App\Http\Requests\Library;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole([
            'SUPER_ADMIN',
            'LIBRARY_ADMIN',
            'LIBRARY_OFFICER',
        ]) ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['unread', 'read', 'replied', 'closed'])],
        ];
    }
}
