<?php

namespace App\Http\Requests\Master;

use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $code = Str::of((string) $this->input('unit_code'))
            ->trim()
            ->replaceMatches('/\s+/', '-')
            ->upper()
            ->toString();

        $description = trim((string) $this->input('description'));

        $this->merge([
            'unit_code' => $code,
            'unit_name' => trim((string) $this->input('unit_name')),
            'description' => $description !== '' ? $description : null,
        ]);
    }

    public function rules(): array
    {
        /** @var Unit|null $unit */
        $unit = $this->route('unit');

        return [
            'unit_code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('units', 'unit_code')->ignore($unit?->id),
            ],
            'unit_name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    public function messages(): array
    {
        return [
            'unit_code.required' => 'Kode satuan wajib diisi.',
            'unit_code.regex' => 'Kode satuan hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah.',
            'unit_code.unique' => 'Kode satuan sudah digunakan.',
            'unit_name.required' => 'Nama satuan wajib diisi.',
            'unit_name.max' => 'Nama satuan maksimal 100 karakter.',
            'description.max' => 'Deskripsi maksimal 255 karakter.',
            'status.required' => 'Status satuan wajib dipilih.',
            'status.in' => 'Status satuan tidak valid.',
        ];
    }
}
