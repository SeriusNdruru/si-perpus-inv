<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreItemRequest extends FormRequest
{
    /**
     * @var array<string, string>
     */
    private const TRACKING_TYPE_BY_ITEM_TYPE = [
        'book' => 'asset',
        'equipment' => 'asset',
        'electronic' => 'asset',
        'furniture' => 'asset',
        'consumable' => 'quantity',
        'other' => 'asset',
    ];

    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['SUPER_ADMIN', 'INVENTORY_ADMIN']) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $itemType = (string) $this->input('item_type');

        $this->merge([
            'item_name' => trim((string) $this->input('item_name')),
            'contract_number' => $this->filled('contract_number')
                ? trim((string) $this->input('contract_number'))
                : null,
            'asset_type_code' => $this->filled('asset_type_code')
                ? trim((string) $this->input('asset_type_code'))
                : null,
            'skpd_name' => trim((string) $this->input('skpd_name', 'SDN MEKARSARI 08')),
            'tracking_type' => self::TRACKING_TYPE_BY_ITEM_TYPE[$itemType] ?? null,
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
            'contract_number' => ['nullable', 'string', 'max:180'],
            'contract_date' => ['nullable', 'date'],
            'contract_start_date' => ['nullable', 'date'],
            'contract_end_date' => ['nullable', 'date'],
            'asset_type_code' => ['nullable', 'string', 'max:80'],
            'skpd_name' => ['required', 'string', 'max:160'],
            'item_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
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
            'quantity.gt' => 'Jumlah awal harus lebih besar dari nol.',
            'item_image.required' => 'Foto barang atau cover buku wajib ditambahkan.',
            'item_image.image' => 'File foto harus berupa gambar.',
            'item_image.mimes' => 'Foto hanya mendukung JPG, JPEG, PNG, atau WEBP.',
            'item_image.max' => 'Ukuran foto maksimal 3 MB.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $trackingType = (string) $this->input('tracking_type');
            $quantity = (float) $this->input('quantity');

            if ($trackingType === 'asset' && floor($quantity) !== $quantity) {
                $validator->errors()->add('quantity', 'Jumlah barang berbasis aset harus berupa bilangan bulat.');
            }

            $startDate = strtotime((string) $this->input('contract_start_date'));
            $endDate = strtotime((string) $this->input('contract_end_date'));

            if ($startDate !== false && $endDate !== false && $endDate < $startDate) {
                $validator->errors()->add(
                    'contract_end_date',
                    'Tanggal akhir kontrak tidak boleh lebih awal dari tanggal mulai.'
                );
            }
        });
    }
}
