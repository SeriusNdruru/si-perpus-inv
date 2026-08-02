<?php

namespace App\Http\Requests\Library;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShelfAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $notes = trim((string) $this->input('notes'));

        $this->merge([
            'shelf_id' => $this->filled('shelf_id') ? (int) $this->input('shelf_id') : null,
            'notes' => $notes !== '' ? $notes : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'shelf_id' => [
                'required',
                'integer',
                Rule::exists('library_shelves', 'id')->where(fn ($query) => $query->where('status', 'active')),
            ],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'shelf_id.required' => 'Rak tujuan wajib dipilih.',
            'shelf_id.exists' => 'Rak tujuan tidak ditemukan atau sedang tidak aktif.',
            'notes.max' => 'Catatan maksimal 255 karakter.',
        ];
    }
}
