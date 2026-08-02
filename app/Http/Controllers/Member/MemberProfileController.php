<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Services\Library\MemberAccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

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
}
