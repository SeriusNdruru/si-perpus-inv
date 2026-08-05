<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Services\Library\MemberAccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MemberLibraryActivityController extends Controller
{
    public function __construct(private readonly MemberAccountService $memberAccounts)
    {
    }

    public function index(Request $request): View
    {
        $member = $this->memberAccounts->memberFor($request->user());

        $statistics = [
            'visits' => DB::table('library_visits')->where('member_id', $member->id)->count(),
            'loan_transactions' => DB::table('loans')->where('member_id', $member->id)->count(),
            'borrowed_books' => DB::table('loan_items')
                ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
                ->where('loans.member_id', $member->id)
                ->count(),
            'active_books' => DB::table('loan_items')
                ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
                ->where('loans.member_id', $member->id)
                ->where('loan_items.return_status', 'borrowed')
                ->count(),
        ];

        $visits = DB::table('library_visits')
            ->where('member_id', $member->id)
            ->orderByDesc('visit_date')
            ->orderByDesc('visit_time')
            ->paginate(12, ['*'], 'kunjungan')
            ->withQueryString();

        $borrowedBooks = DB::table('loan_items')
            ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
            ->join('assets', 'assets.id', '=', 'loan_items.asset_id')
            ->join('items', 'items.id', '=', 'assets.item_id')
            ->leftJoin('book_details', 'book_details.item_id', '=', 'items.id')
            ->where('loans.member_id', $member->id)
            ->orderByDesc('loan_items.borrowed_at')
            ->paginate(12, [
                'loan_items.id',
                'loan_items.borrowed_at',
                'loan_items.due_date',
                'loan_items.returned_at',
                'loan_items.return_status',
                'loans.loan_code',
                'assets.asset_code',
                'items.item_name',
                'book_details.cover_path',
            ], 'buku')
            ->withQueryString();

        return view('member.activity.index', compact('member', 'statistics', 'visits', 'borrowedBooks'));
    }
}
