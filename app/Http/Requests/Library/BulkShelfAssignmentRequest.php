<?php

namespace App\Http\Requests\Library;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkShelfAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $notes = trim((string) $this->input('notes'));
        $assetIds = collect($this->input('asset_ids', []))
            ->filter(fn ($value) => filter_var($value, FILTER_VALIDATE_INT) !== false)
            ->map(fn ($value) => (int) $value)
            ->values()
            ->all();

        $this->merge([
            'asset_ids' => $assetIds,
            'shelf_id' => $this->filled('shelf_id') ? (int) $this->input('shelf_id') : null,
            'notes' => $notes !== '' ? $notes : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'asset_ids' => ['required', 'array', 'min:1', 'max:100'],
            'asset_ids.*' => ['required', 'integer', 'distinct', Rule::exists('assets', 'id')],
            'shelf_id' => [
                'required',
                'integer',
                Rule::exists('library_shelves', 'id')->where(fn ($query) => $query->where('status', 'active')),
            ],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'asset_ids.required' => 'Pilih minimal satu eksemplar buku.',
            'asset_ids.array' => 'Daftar eksemplar tidak valid.',
            'asset_ids.min' => 'Pilih minimal satu eksemplar buku.',
            'asset_ids.max' => 'Penempatan massal dibatasi maksimal 100 eksemplar sekali proses.',
            'asset_ids.*.distinct' => 'Terdapat eksemplar yang dipilih lebih dari satu kali.',
            'asset_ids.*.exists' => 'Salah satu eksemplar tidak ditemukan.',
            'shelf_id.required' => 'Rak tujuan wajib dipilih.',
            'shelf_id.exists' => 'Rak tujuan tidak ditemukan atau sedang tidak aktif.',
            'notes.max' => 'Catatan maksimal 255 karakter.',
        ];
    }
}
