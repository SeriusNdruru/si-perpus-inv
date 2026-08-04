<?php

namespace App\Http\Requests\Library;

use App\Models\Member;
use App\Services\SchoolClassOptionsService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateMemberRequest extends StoreMemberRequest
{
    public function rules(): array
    {
        /** @var Member $member */
        $member = $this->route('member');
        $userId = $member->user_id;

        return [
            'member_code' => [
                'required',
                'string',
                'max:60',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('members', 'member_code')->ignore($member->id),
            ],
            'member_name' => ['required', 'string', 'max:180'],
            'member_type' => ['required', Rule::in(['student', 'teacher', 'staff', 'public'])],
            'identity_number' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique('members', 'identity_number')->ignore($member->id),
            ],
            'department' => [
                'nullable',
                'string',
                'max:150',
                Rule::in(app(SchoolClassOptionsService::class)->options()),
            ],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-.\s]+$/'],
            'email' => [
                'required',
                'email:rfc',
                'max:150',
                Rule::unique('members', 'email')->ignore($member->id),
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'address' => ['nullable', 'string', 'max:2000'],
            'join_date' => ['required', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:join_date'],
            'status' => ['required', Rule::in(['active', 'suspended', 'inactive', 'expired'])],
            'account_username' => [
                'required',
                'string',
                'min:4',
                'max:60',
                'regex:/^[a-z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($userId),
            ],
            'account_password' => $userId === null
                ? [
                    'required',
                    'confirmed',
                    Password::min(8)->letters()->numbers(),
                ]
                : [
                    'nullable',
                    'confirmed',
                    Password::min(8)->letters()->numbers(),
                ],
        ];
    }
}
