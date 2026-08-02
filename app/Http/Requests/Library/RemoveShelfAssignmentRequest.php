<?php

namespace App\Http\Requests\Library;

use Illuminate\Foundation\Http\FormRequest;

class RemoveShelfAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $notes = trim((string) $this->input('remove_notes'));

        $this->merge([
            'remove_notes' => $notes !== '' ? $notes : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'remove_notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'remove_notes.max' => 'Catatan pelepasan rak maksimal 255 karakter.',
        ];
    }
}
