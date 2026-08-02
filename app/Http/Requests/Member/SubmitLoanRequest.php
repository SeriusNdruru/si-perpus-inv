<?php

namespace App\Http\Requests\Member;

use Illuminate\Foundation\Http\FormRequest;

class SubmitLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('MEMBER') ?? false;
    }

    public function rules(): array
    {
        return [
            'member_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
