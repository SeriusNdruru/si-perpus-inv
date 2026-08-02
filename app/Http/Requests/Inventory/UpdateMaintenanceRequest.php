<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMaintenanceRequest extends FormRequest
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
            'reported_at' => ['required', 'date'],
            'issue_description' => ['required', 'string', 'max:5000'],
            'action_taken' => ['nullable', 'string', 'max:5000'],
            'vendor_name' => ['nullable', 'string', 'max:180'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reported_at.required' => 'Tanggal laporan wajib diisi.',
            'reported_at.date' => 'Format tanggal laporan tidak valid.',
            'issue_description.required' => 'Keluhan atau kerusakan wajib dijelaskan.',
            'issue_description.max' => 'Uraian masalah maksimal 5.000 karakter.',
            'action_taken.max' => 'Tindakan perbaikan maksimal 5.000 karakter.',
            'vendor_name.max' => 'Nama vendor maksimal 180 karakter.',
            'cost.numeric' => 'Biaya perbaikan harus berupa angka.',
            'cost.min' => 'Biaya perbaikan tidak boleh kurang dari nol.',
        ];
    }
}
