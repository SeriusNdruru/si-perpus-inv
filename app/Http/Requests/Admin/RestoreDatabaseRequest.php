<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RestoreDatabaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('SUPER_ADMIN') ?? false;
    }

    public function rules(): array
    {
        return [
            'backup_file' => ['required', 'file', 'max:51200'],
            'current_password' => ['required', 'current_password:web'],
            'confirmation' => [
                'required',
                Rule::in(['PULIHKAN DATABASE']),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'backup_file.required' => 'File backup wajib dipilih.',
            'backup_file.file' => 'File backup tidak valid.',
            'backup_file.max' => 'Ukuran file backup maksimal 50 MB.',
            'current_password.required' => 'Password akun saat ini wajib diisi.',
            'current_password.current_password' => 'Password akun saat ini tidak benar.',
            'confirmation.required' => 'Teks konfirmasi wajib diisi.',
            'confirmation.in' => 'Ketik tepat: PULIHKAN DATABASE',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'confirmation' => trim((string) $this->input('confirmation')),
        ]);
    }
}
