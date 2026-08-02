<?php

namespace App\Http\Requests\Library;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'member_id' => $this->filled('member_id') ? (int) $this->input('member_id') : null,
            'item_id' => $this->filled('item_id') ? (int) $this->input('item_id') : null,
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
            'item_id' => [
                'required',
                'integer',
                Rule::exists('book_details', 'item_id'),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'member_id.required' => 'Anggota wajib dipilih.',
            'member_id.exists' => 'Anggota yang dipilih tidak ditemukan.',
            'item_id.required' => 'Judul buku wajib dipilih.',
            'item_id.exists' => 'Judul buku yang dipilih tidak ditemukan.',
            'notes.max' => 'Catatan maksimal 2.000 karakter.',
        ];
    }
}
