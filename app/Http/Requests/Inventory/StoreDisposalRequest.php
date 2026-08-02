<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreDisposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['SUPER_ADMIN', 'INVENTORY_ADMIN']) ?? false;
    }

    public function rules(): array
    {
        return [
            'asset_id' => ['required', 'integer', 'exists:assets,id'],
            'proposed_at' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ];
    }

    public function messages(): array
    {
        return [
            'asset_id.required' => 'Aset yang akan dihapuskan wajib dipilih.',
            'asset_id.exists' => 'Aset yang dipilih tidak tersedia.',
            'proposed_at.required' => 'Tanggal usulan wajib diisi.',
            'proposed_at.date' => 'Format tanggal usulan tidak valid.',
            'reason.required' => 'Alasan penghapusan wajib dijelaskan.',
            'reason.max' => 'Alasan penghapusan maksimal 5.000 karakter.',
            'notes.max' => 'Catatan maksimal 3.000 karakter.',
        ];
    }
}
