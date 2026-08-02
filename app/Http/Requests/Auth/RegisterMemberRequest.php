<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterMemberRequest extends FormRequest
{
    private const ELEMENTARY_CLASSES = [
        'Kelas 1',
        'Kelas 2',
        'Kelas 3',
        'Kelas 4',
        'Kelas 5',
        'Kelas 6',
    ];

    public function authorize(): bool
    {
        return $this->user() === null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'full_name' => trim((string) $this->input('full_name')),
            'username' => strtolower(trim((string) $this->input('username'))),
            'email' => strtolower(trim((string) $this->input('email'))),
            'phone' => $this->nullableTrimmed('phone'),
            'identity_number' => trim((string) $this->input('identity_number')),
            'department' => trim((string) $this->input('department')),
            'address' => $this->nullableTrimmed('address'),
        ]);
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:150'],
            'username' => [
                'required',
                'string',
                'min:4',
                'max:60',
                'regex:/^[a-z0-9._-]+$/',
                Rule::unique('users', 'username'),
            ],
            'email' => [
                'required',
                'email:rfc',
                'max:150',
                Rule::unique('users', 'email'),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'identity_number' => [
                'required',
                'string',
                'max:80',
                Rule::unique('members', 'identity_number'),
            ],
            'department' => [
                'required',
                'string',
                Rule::in(self::ELEMENTARY_CLASSES),
            ],
            'address' => ['nullable', 'string', 'max:1000'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->letters()->numbers(),
            ],
            'terms' => ['accepted'],
            'website' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => 'Username hanya boleh berisi huruf kecil, angka, titik, garis bawah, dan tanda hubung.',
            'username.unique' => 'Username sudah digunakan.',
            'email.unique' => 'Email sudah terdaftar.',
            'identity_number.required' => 'NIS wajib diisi.',
            'identity_number.unique' => 'NIS sudah terdaftar.',
            'department.required' => 'Kelas wajib dipilih.',
            'department.in' => 'Kelas harus dipilih dari Kelas 1 sampai Kelas 6.',
            'password.confirmed' => 'Konfirmasi password tidak sama.',
            'terms.accepted' => 'Anda harus menyetujui ketentuan penggunaan.',
        ];
    }

    private function nullableTrimmed(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }
}
