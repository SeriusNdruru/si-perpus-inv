<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockOpnameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'opname_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'location_id.required' => 'Lokasi stock opname wajib dipilih.',
            'location_id.exists' => 'Lokasi yang dipilih tidak tersedia.',
            'opname_date.required' => 'Tanggal stock opname wajib diisi.',
            'opname_date.date' => 'Format tanggal stock opname tidak valid.',
            'notes.max' => 'Catatan maksimal 2.000 karakter.',
        ];
    }
}
