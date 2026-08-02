<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Requests\Library\UpdateLoanRequestStatus;
use App\Models\LoanRequest;
use App\Services\Library\LoanRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class LoanRequestAdminController extends Controller
{
    public function __construct(private readonly LoanRequestService $loanRequests)
    {
    }

    public function index(Request $request): View
    {
        $this->loanRequests->syncExpired();

        $search = trim((string) $request->query('search'));
        $status = (string) $request->query('status');

        $requests = LoanRequest::query()
            ->with(['member:id,member_code,member_name,identity_number'])
            ->withCount('items')
            ->when($search !== '', fn ($query) => $query->where(function ($subQuery) use ($search): void {
                $subQuery
                    ->where('request_code', 'like', "%{$search}%")
                    ->orWhereHas('member', fn ($memberQuery) => $memberQuery
                        ->where('member_code', 'like', "%{$search}%")
                        ->orWhere('member_name', 'like', "%{$search}%")
                        ->orWhere('identity_number', 'like', "%{$search}%"));
            }))
            ->when(
                in_array($status, ['submitted', 'approved', 'ready', 'collected', 'rejected', 'cancelled', 'expired'], true),
                fn ($query) => $query->where('status', $status)
            )
            ->latest('requested_at')
            ->paginate(15)
            ->withQueryString();

        $summary = [
            'submitted' => LoanRequest::query()->where('status', 'submitted')->count(),
            'approved' => LoanRequest::query()->where('status', 'approved')->count(),
            'ready' => LoanRequest::query()->where('status', 'ready')->count(),
            'today_collected' => LoanRequest::query()
                ->where('status', 'collected')
                ->whereDate('collected_at', today())
                ->count(),
        ];

        return view('library.loan-requests.index', compact('requests', 'summary'));
    }

    public function show(LoanRequest $loanRequest): View
    {
        $this->loanRequests->syncExpired();
        $loanRequest->refresh();
        $loanRequest->load([
            'member.user:id,username,email',
            'items.item.bookDetail',
            'items.item.authors',
            'items.asset.shelf',
            'items.asset.location',
            'processor:id,full_name',
        ]);

        return view('library.loan-requests.show', compact('loanRequest'));
    }

    public function approve(
        UpdateLoanRequestStatus $request,
        LoanRequest $loanRequest
    ): RedirectResponse {
        return $this->transition(
            fn () => $this->loanRequests->approve(
                $loanRequest,
                $request->user(),
                $request->validated()['admin_notes'] ?? null,
            ),
            'Pengajuan berhasil disetujui dan eksemplar sudah dipesan.'
        );
    }

    public function ready(
        UpdateLoanRequestStatus $request,
        LoanRequest $loanRequest
    ): RedirectResponse {
        return $this->transition(
            fn () => $this->loanRequests->markReady(
                $loanRequest,
                $request->user(),
                $request->validated()['admin_notes'] ?? null,
            ),
            'Buku ditandai siap diambil dan anggota menerima notifikasi.'
        );
    }

    public function collect(
        UpdateLoanRequestStatus $request,
        LoanRequest $loanRequest
    ): RedirectResponse {
        try {
            $loan = $this->loanRequests->collect(
                $loanRequest,
                $request->user(),
                $request->validated()['admin_notes'] ?? null,
            );
        } catch (Throwable $exception) {
            return back()->with('error', $this->safeMessage($exception));
        }

        return redirect()
            ->route('library.loans.show', $loan)
            ->with('success', 'Pengambilan dikonfirmasi dan transaksi peminjaman berhasil dibuat.');
    }

    public function reject(
        UpdateLoanRequestStatus $request,
        LoanRequest $loanRequest
    ): RedirectResponse {
        return $this->transition(
            fn () => $this->loanRequests->reject(
                $loanRequest,
                $request->user(),
                $request->validated()['admin_notes'] ?? null,
            ),
            'Pengajuan berhasil ditolak.'
        );
    }

    private function transition(callable $callback, string $success): RedirectResponse
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            return back()->with('error', $this->safeMessage($exception));
        }

        return back()->with('success', $success);
    }

    private function safeMessage(Throwable $exception): string
    {
        report($exception);

        return $exception instanceof RuntimeException
            ? $exception->getMessage()
            : 'Proses belum dapat diselesaikan. Muat ulang halaman lalu coba kembali.';
    }
}
