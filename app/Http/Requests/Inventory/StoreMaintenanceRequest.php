<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreMaintenanceRequest extends FormRequest
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
            'asset_id' => ['required', 'integer', 'exists:assets,id'],
            'reported_at' => ['required', 'date'],
            'issue_description' => ['required', 'string', 'max:5000'],
            'vendor_name' => ['nullable', 'string', 'max:180'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'asset_id.required' => 'Aset yang akan dipelihara wajib dipilih.',
            'asset_id.exists' => 'Aset yang dipilih tidak tersedia.',
            'reported_at.required' => 'Tanggal laporan wajib diisi.',
            'reported_at.date' => 'Format tanggal laporan tidak valid.',
            'issue_description.required' => 'Keluhan atau kerusakan wajib dijelaskan.',
            'issue_description.max' => 'Uraian masalah maksimal 5.000 karakter.',
            'vendor_name.max' => 'Nama vendor maksimal 180 karakter.',
            'notes.max' => 'Catatan maksimal 2.000 karakter.',
        ];
    }
}
