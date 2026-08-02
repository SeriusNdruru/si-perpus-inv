<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateSystemSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(User::ROLE_SUPER_ADMIN) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'application_name' => trim((string) $this->input('application_name')),
            'application_short_name' => Str::upper(trim((string) $this->input('application_short_name'))),
            'institution_name' => trim((string) $this->input('institution_name')),
            'institution_address' => trim((string) $this->input('institution_address')),
            'institution_phone' => $this->nullableTrimmed('institution_phone'),
            'institution_email' => Str::lower((string) ($this->nullableTrimmed('institution_email') ?? '')) ?: null,
            'asset_code_separator' => trim((string) $this->input('asset_code_separator')),
            'portal_hero_title' => trim((string) $this->input('portal_hero_title')),
            'portal_hero_subtitle' => trim((string) $this->input('portal_hero_subtitle')),
            'portal_about_title' => trim((string) $this->input('portal_about_title')),
            'portal_about_content' => trim((string) $this->input('portal_about_content')),
            'portal_about_video_url' => $this->nullableTrimmed('portal_about_video_url'),
            'portal_contact_intro' => trim((string) $this->input('portal_contact_intro')),
            'portal_opening_hours' => trim((string) $this->input('portal_opening_hours')),
        ]);
    }

    public function rules(): array
    {
        return [
            'application_name' => ['required', 'string', 'max:120'],
            'application_short_name' => ['required', 'string', 'min:2', 'max:4', 'regex:/^[A-Z0-9]+$/'],
            'institution_name' => ['required', 'string', 'max:180'],
            'institution_address' => ['required', 'string', 'max:1000'],
            'institution_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s.]+$/'],
            'institution_email' => ['nullable', 'email:rfc', 'max:150'],
            'default_loan_days' => ['required', 'integer', 'min:1', 'max:365'],
            'max_active_loans' => ['required', 'integer', 'min:1', 'max:50'],
            'fine_per_day' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'reservation_hold_days' => ['required', 'integer', 'min:1', 'max:30'],
            'max_active_reservations' => ['required', 'integer', 'min:1', 'max:20'],
            'asset_code_separator' => ['required', Rule::in(['-', '/', '.', '_'])],
            'portal_hero_title' => ['required', 'string', 'max:180'],
            'portal_hero_subtitle' => ['required', 'string', 'max:500'],
            'portal_about_title' => ['required', 'string', 'max:180'],
            'portal_about_content' => ['required', 'string', 'max:5000'],
            'portal_about_video_url' => ['nullable', 'url:http,https', 'max:500'],
            'portal_contact_intro' => ['required', 'string', 'max:1000'],
            'portal_opening_hours' => ['required', 'string', 'max:300'],
            'loan_request_hold_days' => ['required', 'integer', 'min:1', 'max:14'],
        ];
    }

    public function messages(): array
    {
        return [
            'application_name.required' => 'Nama aplikasi wajib diisi.',
            'application_name.max' => 'Nama aplikasi maksimal 120 karakter.',
            'application_short_name.required' => 'Inisial aplikasi wajib diisi.',
            'application_short_name.min' => 'Inisial aplikasi minimal 2 karakter.',
            'application_short_name.max' => 'Inisial aplikasi maksimal 4 karakter.',
            'application_short_name.regex' => 'Inisial aplikasi hanya boleh berisi huruf kapital dan angka.',
            'institution_name.required' => 'Nama instansi wajib diisi.',
            'institution_address.required' => 'Alamat instansi wajib diisi.',
            'institution_phone.regex' => 'Format nomor telepon instansi tidak valid.',
            'institution_email.email' => 'Format email instansi tidak valid.',
            'default_loan_days.required' => 'Lama peminjaman wajib diisi.',
            'default_loan_days.integer' => 'Lama peminjaman harus berupa bilangan bulat.',
            'default_loan_days.min' => 'Lama peminjaman minimal 1 hari.',
            'default_loan_days.max' => 'Lama peminjaman maksimal 365 hari.',
            'max_active_loans.required' => 'Batas pinjaman aktif wajib diisi.',
            'max_active_loans.integer' => 'Batas pinjaman aktif harus berupa bilangan bulat.',
            'max_active_loans.min' => 'Batas pinjaman aktif minimal 1 eksemplar.',
            'max_active_loans.max' => 'Batas pinjaman aktif maksimal 50 eksemplar.',
            'fine_per_day.required' => 'Denda per hari wajib diisi.',
            'fine_per_day.numeric' => 'Denda per hari harus berupa angka.',
            'fine_per_day.min' => 'Denda per hari tidak boleh bernilai negatif.',
            'reservation_hold_days.required' => 'Masa penyimpanan reservasi wajib diisi.',
            'reservation_hold_days.integer' => 'Masa penyimpanan reservasi harus berupa bilangan bulat.',
            'reservation_hold_days.min' => 'Masa penyimpanan reservasi minimal 1 hari.',
            'reservation_hold_days.max' => 'Masa penyimpanan reservasi maksimal 30 hari.',
            'max_active_reservations.required' => 'Batas reservasi aktif wajib diisi.',
            'max_active_reservations.integer' => 'Batas reservasi aktif harus berupa bilangan bulat.',
            'max_active_reservations.min' => 'Batas reservasi aktif minimal 1.',
            'max_active_reservations.max' => 'Batas reservasi aktif maksimal 20.',
            'asset_code_separator.in' => 'Pemisah kode aset tidak valid.',
            'portal_about_video_url.url' => 'Tautan video harus menggunakan alamat HTTP atau HTTPS yang valid.',
            'loan_request_hold_days.min' => 'Masa pengambilan minimal 1 hari.',
            'loan_request_hold_days.max' => 'Masa pengambilan maksimal 14 hari.',
        ];
    }
    private function nullableTrimmed(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value !== '' ? $value : null;
    }
}
