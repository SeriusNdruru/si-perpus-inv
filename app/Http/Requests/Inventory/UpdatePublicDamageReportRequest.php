<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePublicDamageReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['SUPER_ADMIN', 'INVENTORY_ADMIN']) ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in(['submitted', 'reviewed', 'in_progress', 'resolved', 'rejected']),
            ],
            'admin_notes' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
