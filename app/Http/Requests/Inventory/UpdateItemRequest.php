<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
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
            'category_id' => $this->filled('category_id') ? $this->input('category_id') : null,
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
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
