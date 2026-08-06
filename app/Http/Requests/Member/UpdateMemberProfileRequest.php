<?php

namespace App\Http\Requests\Member;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMemberProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $phone = trim((string) $this->input('phone'));
        $address = trim((string) $this->input('address'));

        $this->merge([
            'phone' => $phone !== '' ? $phone : null,
            'address' => $address !== '' ? $address : null,
            'remove_profile_photo' => $this->boolean('remove_profile_photo'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'profile_photo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
                'dimensions:min_width=120,min_height=120,max_width=4000,max_height=4000',
            ],
            'remove_profile_photo' => ['nullable', 'boolean'],
            'phone' => [
                'nullable',
                'string',
                'max:30',
                'regex:/^[0-9+()\-\.\s]+$/',
            ],
            'address' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'profile_photo.image' => 'Foto profil harus berupa file gambar.',
            'profile_photo.mimes' => 'Foto profil harus berformat JPG, JPEG, PNG, atau WebP.',
            'profile_photo.max' => 'Ukuran foto profil maksimal 2 MB.',
            'profile_photo.dimensions' => 'Ukuran foto profil minimal 120 x 120 piksel dan maksimal 4.000 x 4.000 piksel.',
            'phone.max' => 'Nomor telepon maksimal 30 karakter.',
            'phone.regex' => 'Format nomor telepon tidak valid.',
            'address.max' => 'Alamat maksimal 2.000 karakter.',
        ];
    }
}
