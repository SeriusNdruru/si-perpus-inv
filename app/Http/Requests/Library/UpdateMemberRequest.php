<?php

namespace App\Http\Requests\Library;

use App\Services\SchoolClassOptionsService;
use App\Models\Member;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends StoreMemberRequest
{
    public function rules(): array
    {
        /** @var Member $member */
        $member = $this->route('member');

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
                'nullable',
                'email:rfc',
                'max:150',
                Rule::unique('members', 'email')->ignore($member->id),
            ],
            'address' => ['nullable', 'string', 'max:2000'],
            'join_date' => ['required', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:join_date'],
            'status' => ['required', Rule::in(['active', 'suspended', 'inactive', 'expired'])],
        ];
    }
}
