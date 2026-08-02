<?php

namespace App\Http\Requests\Master;

use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $code = Str::of((string) $this->input('supplier_code'))
            ->trim()
            ->replaceMatches('/\s+/', '-')
            ->upper()
            ->toString();

        $this->merge([
            'supplier_code' => $code,
            'supplier_name' => trim((string) $this->input('supplier_name')),
            'contact_person' => $this->nullableString('contact_person'),
            'phone' => $this->nullableString('phone'),
            'email' => $this->nullableLowerString('email'),
            'address' => $this->nullableString('address'),
        ]);
    }

    public function rules(): array
    {
        /** @var Supplier|null $supplier */
        $supplier = $this->route('supplier');

        return [
            'supplier_code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('suppliers', 'supplier_code')->ignore($supplier?->id),
            ],
            'supplier_name' => ['required', 'string', 'max:150'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\.\s]+$/'],
            'email' => ['nullable', 'email:rfc', 'max:150'],
            'address' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_code.required' => 'Kode supplier wajib diisi.',
            'supplier_code.regex' => 'Kode supplier hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah.',
            'supplier_code.unique' => 'Kode supplier sudah digunakan.',
            'supplier_name.required' => 'Nama supplier wajib diisi.',
            'supplier_name.max' => 'Nama supplier maksimal 150 karakter.',
            'contact_person.max' => 'Nama kontak maksimal 150 karakter.',
            'phone.max' => 'Nomor telepon maksimal 30 karakter.',
            'phone.regex' => 'Format nomor telepon tidak valid.',
            'email.email' => 'Format email tidak valid.',
            'email.max' => 'Email maksimal 150 karakter.',
            'address.max' => 'Alamat maksimal 2.000 karakter.',
            'status.required' => 'Status supplier wajib dipilih.',
            'status.in' => 'Status supplier tidak valid.',
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
