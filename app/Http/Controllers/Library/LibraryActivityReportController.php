<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Models\LibraryVisit;
use App\Models\Member;
use App\Models\SystemSetting;
use App\Services\Reports\SimpleExcelReportService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LibraryActivityReportController extends Controller
{
    public function __construct(private readonly SimpleExcelReportService $excel)
    {
    }

    public function visits(Request $request): View
    {
        $filters = $this->visitFilters($request);
        $query = $this->visitQuery($filters);

        $summary = [
            'visits' => (clone $query)->count(),
            'students' => (clone $query)->distinct('library_visits.member_id')->count('library_visits.member_id'),
        ];

        $visits = $query
            ->orderByDesc('library_visits.visit_date')
            ->orderByDesc('library_visits.visit_time')
            ->paginate(20)
            ->withQueryString();

        return view('reports.library-visits', [
            'visits' => $visits,
            'summary' => $summary,
            'classes' => $this->classes(),
            'filters' => $filters,
        ]);
    }

    public function frequentVisitors(Request $request): View
    {
        $filters = $this->visitFilters($request);
        $query = $this->rankingQuery($filters);
        $ranking = $query->paginate(20)->withQueryString();

        return view('reports.frequent-visitors', [
            'ranking' => $ranking,
            'classes' => $this->classes(),
            'filters' => $filters,
        ]);
    }

    public function loanRecords(Request $request): View
    {
        $filters = $this->loanFilters($request);
        $query = $this->studentLoanHistoryQuery($filters);
        $summaryQuery = DB::query()->fromSub(clone $query, 'student_loan_history');

        $students = (int) (clone $summaryQuery)->count();
        $totalLoans = (int) (clone $summaryQuery)->sum('loan_count');

        $summary = [
            'students' => $students,
            'loans' => $totalLoans,
            'highest' => (int) ((clone $summaryQuery)->max('loan_count') ?? 0),
            'average' => $students > 0 ? round($totalLoans / $students, 1) : 0,
        ];

        $records = $query
            ->orderByDesc('loan_count')
            ->orderBy('members.member_name')
            ->paginate(20)
            ->withQueryString();

        return view('reports.loan-records', [
            'records' => $records,
            'summary' => $summary,
            'classes' => $this->classes(),
            'filters' => $filters,
        ]);
    }

    public function loanRecordDetail(Request $request, Member $member): View
    {
        abort_unless($member->member_type === 'student', 404);

        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $detailQuery = $this->studentLoanDetailQuery($member->id, $filters);
        $summaryQuery = DB::query()->fromSub(clone $detailQuery, 'student_loan_detail');

        $summary = [
            'loans' => (int) (clone $summaryQuery)->distinct()->count('loan_id'),
            'books' => (int) (clone $summaryQuery)->count(),
            'active' => (int) (clone $summaryQuery)->where('return_status', 'borrowed')->count(),
            'fines' => (float) ((clone $summaryQuery)->sum('fine_amount') ?? 0),
        ];

        $records = $detailQuery
            ->orderByDesc('loan_items.borrowed_at')
            ->orderBy('items.item_name')
            ->paginate(20)
            ->withQueryString();

        return view('reports.loan-record-detail', [
            'member' => $member,
            'records' => $records,
            'summary' => $summary,
            'filters' => $filters,
        ]);
    }

    public function visitsExcel(Request $request): BinaryFileResponse
    {
        $filters = $this->visitFilters($request);
        $rows = $this->visitQuery($filters)
            ->orderByDesc('library_visits.visit_date')
            ->orderByDesc('library_visits.visit_time')
            ->get();

        return $this->excel->download(
            'laporan-kunjungan-'.now()->format('Ymd-His').'.xlsx',
            $this->institutionName(),
            'Laporan Kunjungan Siswa ke Perpustakaan',
            $this->excelMeta($filters),
            ['No.', 'Tanggal', 'Waktu', 'Kode anggota', 'Nama siswa', 'NIS/NISN', 'Kelas', 'Kegiatan', 'Petugas', 'Catatan'],
            $rows,
            fn ($row, int $number): array => [
                $number,
                $row->visit_date,
                substr((string) $row->visit_time, 0, 5),
                $row->member_code,
                $row->member_name,
                $row->identity_number ?: '-',
                $row->department ?: '-',
                $row->activity,
                $row->recorder_name ?: '-',
                $row->notes ?: '-',
            ],
            'Kunjungan Siswa',
        );
    }

    public function frequentVisitorsExcel(Request $request): BinaryFileResponse
    {
        $filters = $this->visitFilters($request);
        $rows = $this->rankingQuery($filters)->get();

        return $this->excel->download(
            'laporan-siswa-sering-berkunjung-'.now()->format('Ymd-His').'.xlsx',
            $this->institutionName(),
            'Peringkat Siswa yang Sering ke Perpustakaan',
            $this->excelMeta($filters),
            ['Peringkat', 'Kode anggota', 'Nama siswa', 'NIS/NISN', 'Kelas', 'Jumlah kunjungan', 'Kunjungan terakhir'],
            $rows,
            fn ($row, int $number): array => [
                $number,
                $row->member_code,
                $row->member_name,
                $row->identity_number ?: '-',
                $row->department ?: '-',
                (int) $row->visit_count,
                $row->last_visit ?: '-',
            ],
            'Siswa Sering Berkunjung',
        );
    }

    public function loanRecordsExcel(Request $request): BinaryFileResponse
    {
        $filters = $this->loanFilters($request);
        $rows = $this->studentLoanHistoryQuery($filters)
            ->orderByDesc('loan_count')
            ->orderBy('members.member_name')
            ->get();

        return $this->excel->download(
            'riwayat-peminjaman-siswa-'.now()->format('Ymd-His').'.xlsx',
            $this->institutionName(),
            'Riwayat Peminjaman Siswa',
            $this->excelMeta($filters),
            ['No.', 'NIS/NISN', 'Nama siswa', 'Kelas', 'Jumlah peminjaman'],
            $rows,
            fn ($row, int $number): array => [
                $number,
                $row->identity_number ?: '-',
                $row->member_name,
                $row->department ?: '-',
                (int) $row->loan_count,
            ],
            'Riwayat Peminjaman',
        );
    }

    public function loanRecordDetailExcel(Request $request, Member $member): BinaryFileResponse
    {
        abort_unless($member->member_type === 'student', 404);

        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $rows = $this->studentLoanDetailQuery($member->id, $filters)
            ->orderByDesc('loan_items.borrowed_at')
            ->orderBy('items.item_name')
            ->get();

        $meta = $this->excelMeta($filters);
        array_splice($meta, 0, 0, [
            'Nama siswa: '.$member->member_name,
            'NIS/NISN: '.($member->identity_number ?: '-').' | Kelas: '.($member->department ?: '-'),
        ]);

        return $this->excel->download(
            'detail-riwayat-peminjaman-'.preg_replace('/[^A-Za-z0-9_-]+/', '-', $member->member_code).'-'.now()->format('Ymd-His').'.xlsx',
            $this->institutionName(),
            'Detail Riwayat Peminjaman Siswa',
            $meta,
            ['No.', 'Kode transaksi', 'Judul buku', 'Kode buku', 'Kode eksemplar', 'Hari peminjaman', 'Tanggal peminjaman', 'Jatuh tempo', 'Tanggal pengembalian', 'Denda', 'Sudah dibayar', 'Sisa denda', 'Status'],
            $rows,
            function ($row, int $number): array {
                $fine = (float) $row->fine_amount;
                $paid = (float) $row->paid_amount;

                return [
                    $number,
                    $row->loan_code,
                    $row->item_name,
                    $row->book_code,
                    $row->asset_code,
                    \Illuminate\Support\Carbon::parse($row->borrowed_at)->translatedFormat('l'),
                    \Illuminate\Support\Carbon::parse($row->borrowed_at)->format('Y-m-d H:i:s'),
                    \Illuminate\Support\Carbon::parse($row->due_date)->format('Y-m-d'),
                    $row->returned_at ? \Illuminate\Support\Carbon::parse($row->returned_at)->format('Y-m-d H:i:s') : '-',
                    $fine,
                    $paid,
                    max($fine - $paid, 0),
                    match ($row->return_status) {
                        'borrowed' => 'Masih dipinjam',
                        'returned' => 'Dikembalikan',
                        'damaged' => 'Rusak',
                        'lost' => 'Hilang',
                        default => ucfirst((string) $row->return_status),
                    },
                ];
            },
            'Detail Peminjaman',
        );
    }

    /** @return array<string, mixed> */
    private function visitFilters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'class' => ['nullable', 'string', 'max:150'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
    }

    /** @return array<string, mixed> */
    private function loanFilters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'class' => ['nullable', 'string', 'max:150'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
    }

    /** @param array<string, mixed> $filters */
    private function visitQuery(array $filters): Builder
    {
        return DB::table('library_visits')
            ->join('members', 'members.id', '=', 'library_visits.member_id')
            ->leftJoin('users', 'users.id', '=', 'library_visits.recorded_by')
            ->where('members.member_type', 'student')
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery->where('members.member_name', 'like', '%'.$search.'%')
                        ->orWhere('members.member_code', 'like', '%'.$search.'%')
                        ->orWhere('members.identity_number', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['class'] ?? null, fn (Builder $query, string $class) => $query->where('members.department', $class))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('library_visits.visit_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('library_visits.visit_date', '<=', $date))
            ->select([
                'library_visits.id',
                'library_visits.visit_date',
                'library_visits.visit_time',
                'library_visits.activity',
                'library_visits.notes',
                'members.member_code',
                'members.member_name',
                'members.identity_number',
                'members.department',
                'users.full_name as recorder_name',
            ]);
    }

    /** @param array<string, mixed> $filters */
    private function rankingQuery(array $filters): Builder
    {
        return DB::table('library_visits')
            ->join('members', 'members.id', '=', 'library_visits.member_id')
            ->where('members.member_type', 'student')
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery->where('members.member_name', 'like', '%'.$search.'%')
                        ->orWhere('members.member_code', 'like', '%'.$search.'%')
                        ->orWhere('members.identity_number', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['class'] ?? null, fn (Builder $query, string $class) => $query->where('members.department', $class))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('library_visits.visit_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('library_visits.visit_date', '<=', $date))
            ->groupBy([
                'members.id',
                'members.member_code',
                'members.member_name',
                'members.identity_number',
                'members.department',
            ])
            ->select([
                'members.id',
                'members.member_code',
                'members.member_name',
                'members.identity_number',
                'members.department',
            ])
            ->selectRaw('COUNT(library_visits.id) as visit_count')
            ->selectRaw('MAX(library_visits.visit_date) as last_visit')
            ->orderByDesc('visit_count')
            ->orderBy('members.member_name');
    }

    /** @param array<string, mixed> $filters */
    private function studentLoanHistoryQuery(array $filters): Builder
    {
        return DB::table('loans')
            ->join('loan_items', 'loan_items.loan_id', '=', 'loans.id')
            ->join('members', 'members.id', '=', 'loans.member_id')
            ->where('members.member_type', 'student')
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery->where('members.member_name', 'like', '%'.$search.'%')
                        ->orWhere('members.member_code', 'like', '%'.$search.'%')
                        ->orWhere('members.identity_number', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['class'] ?? null, fn (Builder $query, string $class) => $query->where('members.department', $class))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('loan_items.borrowed_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('loan_items.borrowed_at', '<=', $date))
            ->groupBy([
                'members.id',
                'members.member_code',
                'members.member_name',
                'members.identity_number',
                'members.department',
            ])
            ->select([
                'members.id',
                'members.member_code',
                'members.member_name',
                'members.identity_number',
                'members.department',
            ])
            ->selectRaw('COUNT(DISTINCT loans.id) as loan_count');
    }

    /** @param array<string, mixed> $filters */
    private function studentLoanDetailQuery(int $memberId, array $filters): Builder
    {
        $finePayments = DB::table('fine_payments')
            ->select('loan_item_id')
            ->selectRaw('SUM(amount) as paid_amount')
            ->groupBy('loan_item_id');

        return DB::table('loans')
            ->join('loan_items', 'loan_items.loan_id', '=', 'loans.id')
            ->join('assets', 'assets.id', '=', 'loan_items.asset_id')
            ->join('items', 'items.id', '=', 'assets.item_id')
            ->leftJoinSub($finePayments, 'fine_totals', function ($join): void {
                $join->on('fine_totals.loan_item_id', '=', 'loan_items.id');
            })
            ->where('loans.member_id', $memberId)
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('loan_items.borrowed_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('loan_items.borrowed_at', '<=', $date))
            ->select([
                'loans.id as loan_id',
                'loans.loan_code',
                'loan_items.id as loan_item_id',
                'loan_items.borrowed_at',
                'loan_items.due_date',
                'loan_items.returned_at',
                'loan_items.return_status',
                'loan_items.fine_amount',
                'assets.asset_code',
                'items.item_code as book_code',
                'items.item_name',
            ])
            ->selectRaw('COALESCE(fine_totals.paid_amount, 0) as paid_amount');
    }

    private function classes()
    {
        return Member::query()
            ->where('member_type', 'student')
            ->whereNotNull('department')
            ->where('department', '<>', '')
            ->distinct()
            ->orderBy('department')
            ->pluck('department');
    }

    /** @param array<string, mixed> $filters @return array<int, string> */
    private function excelMeta(array $filters): array
    {
        $period = 'Semua tanggal';
        if (! empty($filters['date_from']) || ! empty($filters['date_to'])) {
            $period = ($filters['date_from'] ?? 'awal').' sampai '.($filters['date_to'] ?? 'sekarang');
        }

        return [
            'Periode: '.$period,
            'Kelas: '.($filters['class'] ?? 'Semua kelas'),
            'Diekspor: '.now()->format('d-m-Y H:i').' oleh '.(auth()->user()?->full_name ?? 'Sistem'),
        ];
    }

    private function institutionName(): string
    {
        return (string) (SystemSetting::query()
            ->where('setting_key', 'institution.name')
            ->value('setting_value') ?: 'SDN Mekarsari 08');
    }


}
