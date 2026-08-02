<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateSystemUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(User::ROLE_SUPER_ADMIN) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $email = Str::lower(trim((string) $this->input('email')));
        $phone = trim((string) $this->input('phone'));

        $this->merge([
            'full_name' => trim((string) $this->input('full_name')),
            'username' => Str::lower(trim((string) $this->input('username'))),
            'email' => $email !== '' ? $email : null,
            'phone' => $phone !== '' ? $phone : null,
            'role_code' => Str::upper(trim((string) $this->input('role_code'))),
            'status' => trim((string) $this->input('status')),
        ]);
    }

    public function rules(): array
    {
        /** @var User $managedUser */
        $managedUser = $this->route('user');

        return [
            'full_name' => ['required', 'string', 'max:150'],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:60',
                'regex:/^[a-z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($managedUser->id),
            ],
            'email' => [
                'nullable',
                'email:rfc',
                'max:150',
                Rule::unique('users', 'email')->ignore($managedUser->id),
            ],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s.]+$/'],
            'role_code' => [
                'required',
                Rule::in([
                    User::ROLE_SUPER_ADMIN,
                    User::ROLE_INVENTORY_ADMIN,
                    User::ROLE_LIBRARY_ADMIN,
                    User::ROLE_MANAGER,
                ]),
                Rule::exists('roles', 'role_code'),
            ],
            'status' => ['required', Rule::in(['active', 'inactive', 'locked'])],
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Nama lengkap wajib diisi.',
            'username.required' => 'Username wajib diisi.',
            'username.min' => 'Username minimal 3 karakter.',
            'username.regex' => 'Username hanya boleh berisi huruf kecil, angka, titik, garis bawah, dan tanda hubung.',
            'username.unique' => 'Username sudah digunakan.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah digunakan.',
            'phone.regex' => 'Format nomor telepon tidak valid.',
            'role_code.required' => 'Peran pengguna wajib dipilih.',
            'role_code.in' => 'Peran pengguna tidak valid.',
            'role_code.exists' => 'Peran pengguna belum tersedia pada database.',
            'status.required' => 'Status pengguna wajib dipilih.',
            'status.in' => 'Status pengguna tidak valid.',
        ];
    }
}
