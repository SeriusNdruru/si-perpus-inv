<?php

namespace App\Http\Requests\PublicSite;

use Illuminate\Foundation\Http\FormRequest;

class StoreContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sender_name' => trim((string) $this->input('sender_name')),
            'sender_email' => strtolower(trim((string) $this->input('sender_email'))),
            'sender_phone' => trim((string) $this->input('sender_phone')),
            'subject' => trim((string) $this->input('subject')),
            'message' => trim((string) $this->input('message')),
        ]);
    }

    public function rules(): array
    {
        return [
            'sender_name' => ['required', 'string', 'max:180'],
            'sender_email' => ['nullable', 'email:rfc', 'max:150'],
            'sender_phone' => ['nullable', 'string', 'max:50'],
            'subject' => ['required', 'string', 'max:220'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'website' => ['prohibited'],
        ];
    }
}
