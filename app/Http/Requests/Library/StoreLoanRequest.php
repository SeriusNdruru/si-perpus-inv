<?php

namespace App\Http\Requests\Library;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $assetIds = collect($this->input('asset_ids', []))
            ->filter(static fn ($value): bool => $value !== null && $value !== '')
            ->map(static fn ($value): int => (int) $value)
            ->unique()
            ->values()
            ->all();

        $this->merge([
            'member_id' => $this->filled('member_id') ? (int) $this->input('member_id') : null,
            'asset_ids' => $assetIds,
            'notes' => $this->filled('notes') ? trim((string) $this->input('notes')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'member_id' => [
                'required',
                'integer',
                Rule::exists('members', 'id'),
            ],
            'due_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:today',
                'before_or_equal:'.today()->addYear()->format('Y-m-d'),
            ],
            'asset_ids' => ['required', 'array', 'min:1', 'max:50'],
            'asset_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('assets', 'id'),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'member_id.required' => 'Anggota wajib dipilih.',
            'member_id.exists' => 'Anggota yang dipilih tidak ditemukan.',
            'due_date.required' => 'Tanggal jatuh tempo wajib diisi.',
            'due_date.after_or_equal' => 'Tanggal jatuh tempo tidak boleh sebelum hari ini.',
            'due_date.before_or_equal' => 'Tanggal jatuh tempo maksimal satu tahun dari hari ini.',
            'asset_ids.required' => 'Pilih minimal satu eksemplar buku.',
            'asset_ids.min' => 'Pilih minimal satu eksemplar buku.',
            'asset_ids.max' => 'Maksimal 50 eksemplar dalam satu transaksi.',
            'asset_ids.*.exists' => 'Salah satu eksemplar yang dipilih tidak ditemukan.',
            'asset_ids.*.distinct' => 'Eksemplar yang sama tidak boleh dipilih dua kali.',
        ];
    }
}
