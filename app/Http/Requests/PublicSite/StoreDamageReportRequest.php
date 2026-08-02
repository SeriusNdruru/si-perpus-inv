<?php

namespace App\Http\Requests\PublicSite;

use Illuminate\Foundation\Http\FormRequest;

class StoreDamageReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reporter_name' => $this->nullableTrimmed('reporter_name'),
            'reporter_contact' => $this->nullableTrimmed('reporter_contact'),
            'issue_description' => trim((string) $this->input('issue_description')),
        ]);
    }

    public function rules(): array
    {
        return [
            'item_id' => ['nullable', 'integer', 'exists:items,id'],
            'asset_id' => ['nullable', 'integer', 'exists:assets,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'reporter_name' => ['nullable', 'string', 'max:180'],
            'reporter_contact' => ['nullable', 'string', 'max:150'],
            'issue_description' => ['required', 'string', 'min:10', 'max:5000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'website' => ['prohibited'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->filled('item_id') && ! $this->filled('asset_id') && ! $this->filled('location_id')) {
                $validator->errors()->add(
                    'item_id',
                    'Pilih minimal barang, kode aset, atau lokasi yang dilaporkan.'
                );
            }
        });
    }

    private function nullableTrimmed(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }
}
