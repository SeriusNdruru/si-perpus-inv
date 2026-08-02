<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SendTestEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('SUPER_ADMIN') === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'recipient_email' => strtolower(trim((string) $this->input('recipient_email'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'recipient_email' => ['required', 'email:rfc', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'recipient_email.required' => 'Email tujuan wajib diisi.',
            'recipient_email.email' => 'Format email tujuan tidak valid.',
        ];
    }
}
