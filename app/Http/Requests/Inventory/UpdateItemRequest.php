<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Item;
use Illuminate\Validation\Rule;

class UpdateItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['SUPER_ADMIN', 'INVENTORY_ADMIN']) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'item_name' => trim((string) $this->input('item_name')),
            'contract_number' => $this->filled('contract_number')
                ? trim((string) $this->input('contract_number'))
                : null,
            'asset_type_code' => $this->filled('asset_type_code')
                ? trim((string) $this->input('asset_type_code'))
                : null,
            'skpd_name' => trim((string) $this->input('skpd_name', 'SDN MEKARSARI 08')),
            'category_id' => $this->filled('category_id') ? $this->input('category_id') : null,
            'minimum_stock' => $this->filled('minimum_stock') ? $this->input('minimum_stock') : 0,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $item = $this->route('item');
        $hasStoredImage = $item instanceof Item
            && ($item->image_path !== null || $item->bookDetail()->whereNotNull('cover_path')->exists());

        $stockRules = $item instanceof Item && $item->tracking_type === 'asset'
            ? ['required', 'integer', 'min:0', 'max:100000']
            : ['required', 'numeric', 'min:0', 'max:9999999999999.99'];

        return [
            'item_name' => ['required', 'string', 'max:220'],
            'stock_quantity' => $stockRules,
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
            'item_image' => [Rule::requiredIf(! $hasStoredImage), 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'minimum_stock' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'stock_quantity.required' => 'Jumlah stok wajib diisi.',
            'stock_quantity.integer' => 'Stok barang per aset harus berupa bilangan bulat.',
            'stock_quantity.numeric' => 'Jumlah stok harus berupa angka.',
            'stock_quantity.min' => 'Jumlah stok tidak boleh kurang dari 0.',
            'item_image.required' => 'Foto barang atau cover buku wajib ditambahkan.',
            'item_image.image' => 'File foto harus berupa gambar.',
            'item_image.mimes' => 'Foto hanya mendukung JPG, JPEG, PNG, atau WEBP.',
            'item_image.max' => 'Ukuran foto maksimal 3 MB.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $startDate = strtotime((string) $this->input('contract_start_date'));
            $endDate = strtotime((string) $this->input('contract_end_date'));

            if ($startDate !== false && $endDate !== false && $endDate < $startDate) {
                $validator->errors()->add(
                    'contract_end_date',
                    'Tanggal akhir kontrak tidak boleh lebih awal dari tanggal mulai.'
                );
            }

            $item = $this->route('item');

            if ($item instanceof Item && $item->tracking_type === 'asset' && $this->filled('stock_quantity')) {
                $protectedStock = $item->assets()
                    ->whereIn('asset_status', ['borrowed', 'reserved', 'maintenance'])
                    ->count();

                if ((int) $this->input('stock_quantity') < $protectedStock) {
                    $validator->errors()->add(
                        'stock_quantity',
                        "Stok tidak dapat dikurangi di bawah {$protectedStock} unit karena masih ada unit yang dipinjam, dipesan, atau dalam pemeliharaan."
                    );
                }
            }
        });
    }

}
