<?php

namespace App\Http\Requests\Library;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateMemberStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['active', 'suspended', 'inactive', 'expired'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $member = $this->route('member');

            if (
                $this->input('status') === 'active'
                && $member?->expiry_date !== null
                && $member->expiry_date->isBefore(now()->startOfDay())
            ) {
                $validator->errors()->add('status', 'Perbarui masa berlaku sebelum mengaktifkan anggota ini.');
            }
        });
    }
}
