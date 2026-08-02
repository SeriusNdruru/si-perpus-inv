<?php

namespace App\Http\Requests\Library;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLoanRequestStatus extends FormRequest
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
            'admin_notes' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
