<?php

namespace App\Http\Requests\Library;

use App\Services\SchoolClassOptionsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $memberCode = Str::of((string) $this->input('member_code'))
            ->trim()
            ->replaceMatches('/\s+/', '-')
            ->upper()
            ->toString();

        $this->merge([
            'member_code' => $memberCode !== '' ? $memberCode : null,
            'member_name' => trim((string) $this->input('member_name')),
            'identity_number' => $this->nullableString('identity_number'),
            'department' => $this->nullableString('department'),
            'phone' => $this->nullableString('phone'),
            'email' => $this->nullableLowerString('email'),
            'address' => $this->nullableString('address'),
            'expiry_date' => $this->filled('expiry_date') ? $this->input('expiry_date') : null,
            'account_username' => Str::lower(trim((string) $this->input('account_username'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'member_code' => [
                'nullable',
                'string',
                'max:60',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('members', 'member_code'),
            ],
            'member_name' => ['required', 'string', 'max:180'],
            'member_type' => ['required', Rule::in(['student', 'teacher', 'staff', 'public'])],
            'identity_number' => ['nullable', 'string', 'max:80', Rule::unique('members', 'identity_number')],
            'department' => [
                'nullable',
                'string',
                'max:150',
                Rule::in(app(SchoolClassOptionsService::class)->options()),
            ],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-.\s]+$/'],
            'email' => [
                'required',
                'email:rfc',
                'max:150',
                Rule::unique('members', 'email'),
                Rule::unique('users', 'email'),
            ],
            'address' => ['nullable', 'string', 'max:2000'],
            'join_date' => ['required', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:join_date'],
            'status' => ['required', Rule::in(['active', 'suspended', 'inactive', 'expired'])],
            'account_username' => [
                'required',
                'string',
                'min:4',
                'max:60',
                'regex:/^[a-z0-9._-]+$/',
                Rule::unique('users', 'username'),
            ],
            'account_password' => [
                'required',
                'confirmed',
                Password::min(8)->letters()->numbers(),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (
                $this->input('status') === 'active'
                && $this->filled('expiry_date')
                && now()->startOfDay()->greaterThan($this->date('expiry_date'))
            ) {
                $validator->errors()->add('status', 'Anggota dengan masa berlaku yang sudah lewat tidak dapat berstatus aktif.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'member_code.regex' => 'Kode anggota hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah.',
            'member_code.unique' => 'Kode anggota sudah digunakan.',
            'member_name.required' => 'Nama anggota wajib diisi.',
            'member_name.max' => 'Nama anggota maksimal 180 karakter.',
            'member_type.required' => 'Jenis anggota wajib dipilih.',
            'member_type.in' => 'Jenis anggota tidak valid.',
            'identity_number.unique' => 'Nomor identitas sudah digunakan oleh anggota lain.',
            'identity_number.max' => 'Nomor identitas maksimal 80 karakter.',
            'department.max' => 'Nama kelas maksimal 150 karakter.',
            'department.in' => 'Pilihan kelas tidak valid. Pilih Kelas 1 sampai Kelas 6.',
            'phone.regex' => 'Format nomor telepon tidak valid.',
            'phone.max' => 'Nomor telepon maksimal 30 karakter.',
            'email.required' => 'Email login siswa wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah digunakan oleh anggota atau pengguna lain.',
            'email.max' => 'Email maksimal 150 karakter.',
            'address.max' => 'Alamat maksimal 2.000 karakter.',
            'join_date.required' => 'Tanggal bergabung wajib diisi.',
            'join_date.date' => 'Tanggal bergabung tidak valid.',
            'expiry_date.date' => 'Tanggal berakhir tidak valid.',
            'expiry_date.after_or_equal' => 'Tanggal berakhir tidak boleh lebih awal dari tanggal bergabung.',
            'status.required' => 'Status anggota wajib dipilih.',
            'status.in' => 'Status anggota tidak valid.',
            'account_username.required' => 'Username akun wajib diisi.',
            'account_username.min' => 'Username minimal 4 karakter.',
            'account_username.regex' => 'Username hanya boleh berisi huruf kecil, angka, titik, garis bawah, dan tanda hubung.',
            'account_username.unique' => 'Username sudah digunakan.',
            'account_password.required' => 'Password awal wajib diisi.',
            'account_password.confirmed' => 'Konfirmasi password tidak sama.',
            'account_password.min' => 'Password minimal 8 karakter.',
        ];
    }

    private function nullableString(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value !== '' ? $value : null;
    }

    private function nullableLowerString(string $key): ?string
    {
        $value = $this->nullableString($key);

        return $value !== null ? Str::lower($value) : null;
    }
}
