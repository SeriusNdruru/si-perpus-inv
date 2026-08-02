<?php

namespace App\Http\Requests\Library;

use App\Models\LibraryShelf;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateShelfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $code = Str::of((string) $this->input('shelf_code'))
            ->trim()
            ->replaceMatches('/\s+/', '-')
            ->upper()
            ->toString();

        $classificationRange = trim((string) $this->input('classification_range'));
        $description = trim((string) $this->input('description'));

        $this->merge([
            'shelf_code' => $code,
            'shelf_name' => trim((string) $this->input('shelf_name')),
            'location_id' => $this->filled('location_id') ? (int) $this->input('location_id') : null,
            'classification_range' => $classificationRange !== '' ? $classificationRange : null,
            'capacity' => $this->filled('capacity') ? (int) $this->input('capacity') : null,
            'description' => $description !== '' ? $description : null,
        ]);
    }

    public function rules(): array
    {
        /** @var LibraryShelf|null $shelf */
        $shelf = $this->route('shelf');

        return [
            'shelf_code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('library_shelves', 'shelf_code')->ignore($shelf?->id),
            ],
            'shelf_name' => ['required', 'string', 'max:150'],
            'location_id' => [
                'nullable',
                'integer',
                Rule::exists('locations', 'id')->where(function ($query) use ($shelf): void {
                    $query->where('status', 'active');

                    if ($shelf?->location_id !== null) {
                        $query->orWhere('id', $shelf->location_id);
                    }
                }),
            ],
            'classification_range' => ['nullable', 'string', 'max:100'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    public function messages(): array
    {
        return [
            'shelf_code.required' => 'Kode rak wajib diisi.',
            'shelf_code.regex' => 'Kode rak hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah.',
            'shelf_code.unique' => 'Kode rak sudah digunakan.',
            'shelf_name.required' => 'Nama rak wajib diisi.',
            'shelf_name.max' => 'Nama rak maksimal 150 karakter.',
            'location_id.exists' => 'Lokasi tidak ditemukan atau tidak aktif.',
            'classification_range.max' => 'Rentang klasifikasi maksimal 100 karakter.',
            'capacity.integer' => 'Kapasitas harus berupa angka bulat.',
            'capacity.min' => 'Kapasitas minimal satu eksemplar.',
            'capacity.max' => 'Kapasitas maksimal 100.000 eksemplar.',
            'description.max' => 'Deskripsi maksimal 255 karakter.',
            'status.required' => 'Status rak wajib dipilih.',
            'status.in' => 'Status rak tidak valid.',
        ];
    }
}
