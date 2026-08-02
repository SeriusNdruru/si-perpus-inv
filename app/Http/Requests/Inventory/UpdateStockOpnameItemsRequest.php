<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStockOpnameItemsRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'distinct', 'exists:stock_opname_items,id'],
            'items.*.actual_quantity' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'items.*.finding_status' => [
                'nullable',
                Rule::in(['matched', 'damaged', 'missing']),
            ],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Data hasil pemeriksaan tidak ditemukan.',
            'items.array' => 'Format data pemeriksaan tidak valid.',
            'items.*.id.exists' => 'Salah satu baris stock opname tidak tersedia.',
            'items.*.actual_quantity.required' => 'Jumlah fisik wajib diisi.',
            'items.*.actual_quantity.numeric' => 'Jumlah fisik harus berupa angka.',
            'items.*.actual_quantity.min' => 'Jumlah fisik tidak boleh kurang dari nol.',
            'items.*.finding_status.in' => 'Status temuan aset tidak valid.',
            'items.*.notes.max' => 'Catatan temuan maksimal 1.000 karakter.',
        ];
    }
}
