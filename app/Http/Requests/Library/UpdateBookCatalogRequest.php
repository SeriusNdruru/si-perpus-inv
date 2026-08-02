<?php

namespace App\Http\Requests\Library;

use App\Models\Item;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $isbn10 = $this->normalizeIsbn($this->input('isbn_10'));
        $isbn13 = $this->normalizeIsbn($this->input('isbn_13'));

        $authors = preg_split('/\r\n|\r|\n/', (string) $this->input('authors_text', '')) ?: [];
        $authors = array_values(array_unique(array_filter(array_map(
            static fn (string $name): string => trim($name),
            $authors
        ))));

        $this->merge([
            'isbn_10' => $isbn10,
            'isbn_13' => $isbn13,
            'authors' => $authors,
            'new_publisher_name' => $this->filled('new_publisher_name')
                ? trim((string) $this->input('new_publisher_name'))
                : null,
            'language' => $this->filled('language')
                ? trim((string) $this->input('language'))
                : 'Indonesia',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $book = $this->route('book');
        $itemId = $book instanceof Item ? $book->id : $book;

        return [
            'isbn_10' => [
                'nullable',
                'regex:/^\d{9}[\dX]$/',
                Rule::unique('book_details', 'isbn_10')->ignore($itemId, 'item_id'),
            ],
            'isbn_13' => [
                'nullable',
                'digits:13',
                Rule::unique('book_details', 'isbn_13')->ignore($itemId, 'item_id'),
            ],
            'publisher_id' => ['nullable', 'integer', 'exists:publishers,id'],
            'new_publisher_name' => ['nullable', 'string', 'max:180'],
            'publication_year' => ['nullable', 'integer', 'between:1000,2200'],
            'edition' => ['nullable', 'string', 'max:80'],
            'language' => ['required', 'string', 'max:50'],
            'page_count' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'classification_code' => ['nullable', 'string', 'max:50'],
            'call_number' => ['nullable', 'string', 'max:80'],
            'catalog_notes' => ['nullable', 'string', 'max:5000'],
            'authors_text' => ['nullable', 'string', 'max:4000'],
            'authors' => ['array', 'max:20'],
            'authors.*' => ['string', 'max:180', 'distinct:ignore_case'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'remove_cover' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'isbn_10.regex' => 'ISBN-10 harus terdiri dari 10 karakter angka. Karakter terakhir boleh X.',
            'isbn_13.digits' => 'ISBN-13 harus terdiri dari 13 angka.',
            'isbn_10.unique' => 'ISBN-10 sudah digunakan oleh buku lain.',
            'isbn_13.unique' => 'ISBN-13 sudah digunakan oleh buku lain.',
            'publisher_id.exists' => 'Penerbit yang dipilih tidak ditemukan.',
            'publication_year.between' => 'Tahun terbit harus berada antara 1000 dan 2200.',
            'authors.max' => 'Maksimal 20 penulis dapat disimpan dalam satu buku.',
            'authors.*.distinct' => 'Nama penulis tidak boleh ditulis lebih dari satu kali.',
            'cover_image.image' => 'File cover harus berupa gambar.',
            'cover_image.mimes' => 'Cover hanya mendukung JPG, PNG, atau WEBP.',
            'cover_image.max' => 'Ukuran cover maksimal 3 MB.',
        ];
    }

    private function normalizeIsbn(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return strtoupper((string) preg_replace('/[\s-]+/', '', (string) $value));
    }
}
