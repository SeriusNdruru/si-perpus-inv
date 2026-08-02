<?php

namespace App\Services\Library;

use App\Models\Member;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MemberAccountService
{
    public function memberFor(User $user): Member
    {
        $member = Member::query()
            ->where('user_id', $user->id)
            ->first();

        if ($member === null) {
            throw new HttpException(
                403,
                'Akun ini belum terhubung dengan data anggota perpustakaan.'
            );
        }

        if (
            trim((string) $member->member_name) !== ''
            && $user->full_name !== $member->member_name
        ) {
            $user->update([
                'full_name' => $member->member_name,
            ]);
        }

        return $member;
    }
}
