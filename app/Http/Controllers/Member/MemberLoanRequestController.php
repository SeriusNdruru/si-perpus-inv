<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Requests\Member\SubmitLoanRequest;
use App\Models\LoanRequest;
use App\Services\Library\LoanRequestService;
use App\Services\Library\MemberAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class MemberLoanRequestController extends Controller
{
    public function __construct(
        private readonly MemberAccountService $memberAccounts,
        private readonly LoanRequestService $loanRequests,
    ) {
    }

    public function store(SubmitLoanRequest $request): RedirectResponse
    {
        $member = $this->memberAccounts->memberFor($request->user());
        $itemIds = $request->session()->get('member.loan_request_cart', []);

        try {
            $loanRequest = $this->loanRequests->submit(
                $member,
                $itemIds,
                $request->validated()['member_notes'] ?? null,
            );
        } catch (Throwable $exception) {
            if ($exception instanceof \Illuminate\Validation\ValidationException) {
                throw $exception;
            }

            report($exception);

            return back()->withErrors([
                'cart' => 'Pengajuan belum dapat disimpan. Muat ulang halaman lalu coba kembali.',
            ]);
        }

        $request->session()->forget('member.loan_request_cart');

        return redirect()
            ->route('member.loan-requests.show', $loanRequest)
            ->with('success', 'Pengajuan peminjaman berhasil dikirim.');
    }

    public function index(Request $request): View
    {
        $member = $this->memberAccounts->memberFor($request->user());
        $this->loanRequests->syncExpired($member->id);

        $requests = LoanRequest::query()
            ->withCount('items')
            ->where('member_id', $member->id)
            ->latest('requested_at')
            ->paginate(12);

        return view('member.loan-requests.index', compact('member', 'requests'));
    }

    public function show(Request $request, LoanRequest $loanRequest): View
    {
        $member = $this->memberAccounts->memberFor($request->user());

        abort_unless($loanRequest->member_id === $member->id, 404);

        $this->loanRequests->syncExpired($member->id);
        $loanRequest->refresh();
        $loanRequest->load([
            'items.item.bookDetail',
            'items.item.authors',
            'items.asset.shelf',
            'processor:id,full_name',
        ]);

        return view('member.loan-requests.show', compact('member', 'loanRequest'));
    }

    public function cancel(Request $request, LoanRequest $loanRequest): RedirectResponse
    {
        $member = $this->memberAccounts->memberFor($request->user());

        try {
            $this->loanRequests->cancelByMember($loanRequest, $member);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Pengajuan berhasil dibatalkan.');
    }
}
