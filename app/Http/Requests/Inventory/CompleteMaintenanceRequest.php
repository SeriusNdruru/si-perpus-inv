<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteMaintenanceRequest extends FormRequest
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
            'completed_at' => ['required', 'date'],
            'action_taken' => ['required', 'string', 'max:5000'],
            'result_condition' => ['required', Rule::in(['good', 'fair', 'damaged'])],
            'vendor_name' => ['nullable', 'string', 'max:180'],
            'cost' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'completed_at.required' => 'Tanggal selesai wajib diisi.',
            'completed_at.date' => 'Format tanggal selesai tidak valid.',
            'action_taken.required' => 'Tindakan perbaikan wajib dijelaskan.',
            'action_taken.max' => 'Tindakan perbaikan maksimal 5.000 karakter.',
            'result_condition.required' => 'Kondisi akhir aset wajib dipilih.',
            'result_condition.in' => 'Kondisi akhir aset tidak valid.',
            'vendor_name.max' => 'Nama vendor maksimal 180 karakter.',
            'cost.required' => 'Biaya perbaikan wajib diisi. Gunakan 0 jika tidak ada biaya.',
            'cost.numeric' => 'Biaya perbaikan harus berupa angka.',
            'cost.min' => 'Biaya perbaikan tidak boleh kurang dari nol.',
        ];
    }
}
