<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Requests\Member\UpdateMemberProfileRequest;
use App\Services\Library\MemberAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class MemberProfileController extends Controller
{
    public function __construct(
        private readonly MemberAccountService $memberAccounts,
    ) {
    }

    public function show(Request $request): View
    {
        $user = $request->user();
        $member = $this->memberAccounts->memberFor($user);

        $totalFine = (float) DB::table('loan_items')
            ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
            ->where('loans.member_id', $member->id)
            ->sum('loan_items.fine_amount');

        $totalPaid = (float) DB::table('fine_payments')
            ->join('loan_items', 'loan_items.id', '=', 'fine_payments.loan_item_id')
            ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
            ->where('loans.member_id', $member->id)
            ->sum('fine_payments.amount');

        $statistics = [
            'loan_transactions' => DB::table('loans')
                ->where('member_id', $member->id)
                ->count(),

            'active_books' => DB::table('loan_items')
                ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
                ->where('loans.member_id', $member->id)
                ->where('loan_items.return_status', 'borrowed')
                ->count(),

            'returned_books' => DB::table('loan_items')
                ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
                ->where('loans.member_id', $member->id)
                ->whereIn('loan_items.return_status', ['returned', 'damaged', 'lost'])
                ->count(),

            'outstanding_fine' => max($totalFine - $totalPaid, 0),
        ];

        return view('member.profile.show', compact(
            'user',
            'member',
            'statistics',
        ));
    }

    public function edit(Request $request): View
    {
        $user = $request->user();
        $member = $this->memberAccounts->memberFor($user);

        return view('member.profile.edit', compact('user', 'member'));
    }

    public function update(UpdateMemberProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $member = $this->memberAccounts->memberFor($user);
        $oldPhotoPath = $member->profile_photo_path;
        $newPhotoPath = null;

        if ($request->hasFile('profile_photo')) {
            $newPhotoPath = $request->file('profile_photo')->store('profile-photos', 'public');
        }

        $removePhoto = $request->boolean('remove_profile_photo') && $newPhotoPath === null;
        $selectedPhotoPath = $newPhotoPath ?? ($removePhoto ? null : $oldPhotoPath);

        try {
            DB::transaction(function () use ($request, $user, $member, $selectedPhotoPath): void {
                $phone = $request->validated('phone');

                $member->update([
                    'phone' => $phone,
                    'address' => $request->validated('address'),
                    'profile_photo_path' => $selectedPhotoPath,
                ]);

                $user->update([
                    'phone' => $phone,
                ]);
            });
        } catch (Throwable $exception) {
            if ($newPhotoPath !== null) {
                Storage::disk('public')->delete($newPhotoPath);
            }

            report($exception);

            return back()
                ->withInput()
                ->with('error', 'Profil belum dapat diperbarui. Silakan coba kembali.');
        }

        if (
            $oldPhotoPath !== null
            && $oldPhotoPath !== $selectedPhotoPath
            && str_starts_with($oldPhotoPath, 'profile-photos/')
        ) {
            Storage::disk('public')->delete($oldPhotoPath);
        }

        return redirect()
            ->route('member.profile.show')
            ->with('success', 'Profil siswa berhasil diperbarui.');
    }
}
