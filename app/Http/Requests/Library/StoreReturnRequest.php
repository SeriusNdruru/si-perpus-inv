<?php

namespace App\Http\Requests\Library;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'return_status' => ['required', Rule::in(['returned', 'damaged', 'lost'])],
            'condition_in' => [
                'nullable',
                'required_if:return_status,returned',
                Rule::in(['good', 'fair']),
            ],
            'return_notes' => [
                'nullable',
                'required_if:return_status,damaged,lost',
                'string',
                'max:2000',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'return_status.required' => 'Status pengembalian wajib dipilih.',
            'return_status.in' => 'Status pengembalian tidak valid.',
            'condition_in.required_if' => 'Kondisi buku saat kembali wajib dipilih.',
            'condition_in.in' => 'Kondisi buku saat kembali tidak valid.',
            'return_notes.required_if' => 'Catatan wajib diisi untuk buku rusak atau hilang.',
            'return_notes.max' => 'Catatan pengembalian maksimal 2.000 karakter.',
        ];
    }
}
