<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['SUPER_ADMIN', 'INVENTORY_ADMIN']) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $itemType = (string) $this->input('item_type');

        $this->merge([
            'item_code' => strtoupper(trim((string) $this->input('item_code'))),
            'item_name' => trim((string) $this->input('item_name')),
            'tracking_type' => $itemType === 'book'
                ? 'asset'
                : (string) $this->input('tracking_type'),
            'category_id' => $this->filled('category_id') ? $this->input('category_id') : null,
            'supplier_id' => $this->filled('supplier_id') ? $this->input('supplier_id') : null,
            'acquisition_price' => $this->filled('acquisition_price') ? $this->input('acquisition_price') : null,
            'minimum_stock' => $this->filled('minimum_stock') ? $this->input('minimum_stock') : 0,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'item_code' => ['required', 'string', 'max:60', 'regex:/^[A-Z0-9][A-Z0-9._\/-]*$/', 'unique:items,item_code'],
            'item_name' => ['required', 'string', 'max:220'],
            'item_type' => ['required', Rule::in(['book', 'equipment', 'electronic', 'furniture', 'consumable', 'other'])],
            'tracking_type' => ['required', Rule::in(['asset', 'quantity'])],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where(fn ($query) => $query->where('status', 'active')),
            ],
            'unit_id' => [
                'required',
                'integer',
                Rule::exists('units', 'id')->where(fn ($query) => $query->where('status', 'active')),
            ],
            'description' => ['nullable', 'string'],
            'minimum_stock' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99'],
            'acquisition_date' => ['nullable', 'date'],
            'acquisition_source' => ['required', Rule::in(['purchase', 'donation', 'grant', 'transfer', 'other'])],
            'acquisition_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'supplier_id' => [
                'nullable',
                'integer',
                Rule::exists('suppliers', 'id')->where(fn ($query) => $query->where('status', 'active')),
            ],
            'location_id' => [
                'required',
                'integer',
                Rule::exists('locations', 'id')->where(fn ($query) => $query->where('status', 'active')),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'item_code.regex' => 'Kode barang hanya boleh berisi huruf kapital, angka, titik, garis miring, garis bawah, atau tanda hubung.',
            'item_code.unique' => 'Kode barang sudah digunakan.',
            'quantity.gt' => 'Jumlah awal harus lebih besar dari nol.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $trackingType = (string) $this->input('tracking_type');
            $quantity = (float) $this->input('quantity');

            if ($this->input('item_type') === 'book' && $trackingType !== 'asset') {
                $validator->errors()->add('tracking_type', 'Buku wajib menggunakan pencatatan per aset atau eksemplar.');
            }

            if ($trackingType === 'asset' && floor($quantity) !== $quantity) {
                $validator->errors()->add('quantity', 'Jumlah barang berbasis aset harus berupa bilangan bulat.');
            }
        });
    }
}
