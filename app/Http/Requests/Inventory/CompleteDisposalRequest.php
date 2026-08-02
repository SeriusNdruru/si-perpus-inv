<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteDisposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['SUPER_ADMIN', 'INVENTORY_ADMIN']) ?? false;
    }

    public function rules(): array
    {
        return [
            'disposed_at' => ['required', 'date'],
            'disposal_method' => [
                'required',
                Rule::in(['destroyed', 'sold', 'donated', 'returned', 'other']),
            ],
            'completion_notes' => ['nullable', 'string', 'max:3000'],
        ];
    }

    public function messages(): array
    {
        return [
            'disposed_at.required' => 'Tanggal pelaksanaan penghapusan wajib diisi.',
            'disposed_at.date' => 'Format tanggal pelaksanaan tidak valid.',
            'disposal_method.required' => 'Metode penghapusan wajib dipilih.',
            'disposal_method.in' => 'Metode penghapusan tidak valid.',
            'completion_notes.max' => 'Catatan pelaksanaan maksimal 3.000 karakter.',
        ];
    }
}
