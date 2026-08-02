<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $code = Str::of((string) $this->input('location_code'))
            ->trim()
            ->replaceMatches('/\s+/', '-')
            ->upper()
            ->toString();

        $description = trim((string) $this->input('description'));

        $this->merge([
            'parent_id' => $this->filled('parent_id') ? (int) $this->input('parent_id') : null,
            'location_code' => $code,
            'location_name' => trim((string) $this->input('location_name')),
            'description' => $description !== '' ? $description : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'integer', 'exists:locations,id'],
            'location_code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('locations', 'location_code'),
            ],
            'location_name' => ['required', 'string', 'max:150'],
            'location_type' => [
                'required',
                Rule::in(['building', 'floor', 'room', 'warehouse', 'cabinet', 'other']),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    public function messages(): array
    {
        return [
            'parent_id.exists' => 'Lokasi induk tidak ditemukan.',
            'location_code.required' => 'Kode lokasi wajib diisi.',
            'location_code.regex' => 'Kode lokasi hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah.',
            'location_code.unique' => 'Kode lokasi sudah digunakan.',
            'location_name.required' => 'Nama lokasi wajib diisi.',
            'location_name.max' => 'Nama lokasi maksimal 150 karakter.',
            'location_type.required' => 'Jenis lokasi wajib dipilih.',
            'location_type.in' => 'Jenis lokasi tidak valid.',
            'description.max' => 'Deskripsi maksimal 255 karakter.',
            'status.required' => 'Status lokasi wajib dipilih.',
            'status.in' => 'Status lokasi tidak valid.',
        ];
    }
}
