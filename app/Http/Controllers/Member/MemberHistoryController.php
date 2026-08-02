<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Services\Library\MemberAccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MemberHistoryController extends Controller
{
    public function __construct(private readonly MemberAccountService $memberAccounts)
    {
    }

    public function loans(Request $request): View
    {
        $member = $this->memberAccounts->memberFor($request->user());

        $loans = DB::table('loans')
            ->where('member_id', $member->id)
            ->select('loans.*')
            ->selectSub(function ($query): void {
                $query->from('loan_items')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('loan_items.loan_id', 'loans.id');
            }, 'items_count')
            ->selectSub(function ($query): void {
                $query->from('loan_items')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('loan_items.loan_id', 'loans.id')
                    ->where('return_status', 'borrowed');
            }, 'active_items_count')
            ->latest('loan_date')
            ->paginate(12);

        return view('member.history.loans', compact('member', 'loans'));
    }

    public function loanDetail(Request $request, int $loan): View
    {
        $member = $this->memberAccounts->memberFor($request->user());

        $loanRow = DB::table('loans')
            ->where('id', $loan)
            ->where('member_id', $member->id)
            ->first();

        abort_unless($loanRow !== null, 404);

        $items = DB::table('loan_items')
            ->join('assets', 'assets.id', '=', 'loan_items.asset_id')
            ->join('items', 'items.id', '=', 'assets.item_id')
            ->leftJoin('book_details', 'book_details.item_id', '=', 'items.id')
            ->where('loan_items.loan_id', $loan)
            ->orderBy('items.item_name')
            ->get([
                'loan_items.*',
                'assets.asset_code',
                'items.item_name',
                'book_details.cover_path',
            ]);

        return view('member.history.loan-detail', compact('member', 'loanRow', 'items'));
    }

    public function fines(Request $request): View
    {
        $member = $this->memberAccounts->memberFor($request->user());

        $fines = DB::table('loan_items')
            ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
            ->join('assets', 'assets.id', '=', 'loan_items.asset_id')
            ->join('items', 'items.id', '=', 'assets.item_id')
            ->where('loans.member_id', $member->id)
            ->where('loan_items.fine_amount', '>', 0)
            ->select([
                'loan_items.id',
                'loan_items.fine_amount',
                'loan_items.returned_at',
                'loans.loan_code',
                'items.item_name',
            ])
            ->selectSub(function ($query): void {
                $query->from('fine_payments')
                    ->selectRaw('COALESCE(SUM(amount), 0)')
                    ->whereColumn('fine_payments.loan_item_id', 'loan_items.id');
            }, 'paid_amount')
            ->latest('loan_items.updated_at')
            ->paginate(12);

        return view('member.history.fines', compact('member', 'fines'));
    }
}
