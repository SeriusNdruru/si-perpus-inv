<?php

namespace App\Http\Requests\Master;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $code = Str::of((string) $this->input('category_code'))
            ->trim()
            ->replaceMatches('/\s+/', '-')
            ->upper()
            ->toString();

        $description = trim((string) $this->input('description'));

        $this->merge([
            'category_code' => $code,
            'category_name' => trim((string) $this->input('category_name')),
            'description' => $description !== '' ? $description : null,
        ]);
    }

    public function rules(): array
    {
        /** @var Category|null $category */
        $category = $this->route('category');

        return [
            'parent_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
                Rule::notIn(array_filter([$category?->id])),
            ],
            'category_code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('categories', 'category_code')->ignore($category?->id),
            ],
            'category_name' => ['required', 'string', 'max:150'],
            'scope' => ['required', Rule::in(['inventory', 'library', 'both'])],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    public function messages(): array
    {
        return [
            'parent_id.exists' => 'Kategori induk tidak ditemukan.',
            'parent_id.not_in' => 'Kategori tidak dapat menjadi induk bagi dirinya sendiri.',
            'category_code.required' => 'Kode kategori wajib diisi.',
            'category_code.regex' => 'Kode kategori hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah.',
            'category_code.unique' => 'Kode kategori sudah digunakan.',
            'category_name.required' => 'Nama kategori wajib diisi.',
            'scope.required' => 'Cakupan kategori wajib dipilih.',
            'scope.in' => 'Cakupan kategori tidak valid.',
            'description.max' => 'Deskripsi maksimal 255 karakter.',
            'status.required' => 'Status kategori wajib dipilih.',
            'status.in' => 'Status kategori tidak valid.',
        ];
    }
}
