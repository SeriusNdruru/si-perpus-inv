<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\LoanRequest;
use App\Services\Library\DueReminderService;
use App\Services\Library\LoanRequestService;
use App\Services\Library\MemberAccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MemberDashboardController extends Controller
{
    public function __construct(
        private readonly MemberAccountService $memberAccounts,
        private readonly DueReminderService $reminders,
        private readonly LoanRequestService $loanRequests,
    ) {
    }

    public function index(Request $request): View
    {
        $member = $this->memberAccounts->memberFor($request->user());
        $this->loanRequests->syncExpired($member->id);
        $this->reminders->generateForMember($member);

        $statistics = [
            'active_books' => DB::table('loan_items')
                ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
                ->where('loans.member_id', $member->id)
                ->where('loan_items.return_status', 'borrowed')
                ->count(),
            'due_tomorrow' => DB::table('loan_items')
                ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
                ->where('loans.member_id', $member->id)
                ->where('loan_items.return_status', 'borrowed')
                ->whereDate('loan_items.due_date', today()->addDay())
                ->count(),
            'active_requests' => LoanRequest::query()
                ->where('member_id', $member->id)
                ->whereIn('status', ['submitted', 'approved', 'ready'])
                ->count(),
            'unread_notifications' => DB::table('member_notifications')
                ->where('member_id', $member->id)
                ->where('is_read', 0)
                ->count(),
        ];

        $activeLoans = DB::table('loan_items')
            ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
            ->join('assets', 'assets.id', '=', 'loan_items.asset_id')
            ->join('items', 'items.id', '=', 'assets.item_id')
            ->leftJoin('book_details', 'book_details.item_id', '=', 'items.id')
            ->where('loans.member_id', $member->id)
            ->where('loan_items.return_status', 'borrowed')
            ->orderBy('loan_items.due_date')
            ->limit(8)
            ->get([
                'loan_items.id',
                'loan_items.due_date',
                'loans.loan_code',
                'items.item_name',
                'book_details.cover_path',
            ]);

        $recentRequests = LoanRequest::query()
            ->withCount('items')
            ->where('member_id', $member->id)
            ->latest('requested_at')
            ->limit(5)
            ->get();

        $notifications = DB::table('member_notifications')
            ->where('member_id', $member->id)
            ->latest('created_at')
            ->limit(5)
            ->get();

        $recommendedBooks = DB::table('items')
            ->join('book_details', 'book_details.item_id', '=', 'items.id')
            ->where('items.item_type', 'book')
            ->where('items.status', 'active')
            ->whereIn('book_details.completion_status', ['complete', 'verified'])
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('assets')
                    ->whereColumn('assets.item_id', 'items.id')
                    ->where('assets.asset_status', 'available');
            })
            ->orderByDesc('items.created_at')
            ->limit(6)
            ->get([
                'items.id',
                'items.item_name',
                'book_details.cover_path',
                'book_details.publication_year',
            ]);

        return view('member.dashboard', compact(
            'member',
            'statistics',
            'activeLoans',
            'recentRequests',
            'notifications',
            'recommendedBooks',
        ));
    }
}
