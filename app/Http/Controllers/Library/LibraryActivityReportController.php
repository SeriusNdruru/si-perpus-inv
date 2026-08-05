<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Models\LibraryVisit;
use App\Models\Member;
use App\Models\SystemSetting;
use App\Services\Reports\SimplePdfReportService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LibraryActivityReportController extends Controller
{
    public function __construct(private readonly SimplePdfReportService $pdf)
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
        $query = $this->loanRecordQuery($filters);

        $summary = [
            'records' => (clone $query)->count(),
            'students' => (clone $query)->distinct('loans.member_id')->count('loans.member_id'),
            'active' => (clone $query)->where('loan_items.return_status', 'borrowed')->count(),
            'returned' => (clone $query)->whereIn('loan_items.return_status', ['returned', 'damaged', 'lost'])->count(),
        ];

        $records = $query
            ->orderByDesc('loan_items.borrowed_at')
            ->paginate(20)
            ->withQueryString();

        return view('reports.loan-records', [
            'records' => $records,
            'summary' => $summary,
            'classes' => $this->classes(),
            'filters' => $filters,
        ]);
    }

    public function visitsPdf(Request $request): Response
    {
        $filters = $this->visitFilters($request);
        $rows = $this->visitQuery($filters)
            ->orderByDesc('library_visits.visit_date')
            ->orderByDesc('library_visits.visit_time')
            ->get()
            ->values()
            ->map(fn ($row, int $index): array => [
                $index + 1,
                $row->visit_date.' '.substr((string) $row->visit_time, 0, 5),
                $row->member_code,
                $row->member_name,
                $row->identity_number ?: '-',
                $row->department ?: '-',
                $row->activity,
                $row->recorder_name ?: '-',
                $row->notes ?: '-',
            ]);

        return $this->pdf->download(
            'laporan-kunjungan-'.now()->format('Ymd-His').'.pdf',
            $this->institutionName(),
            'Laporan Kunjungan Siswa ke Perpustakaan',
            $this->pdfMeta($filters),
            [
                ['label' => 'No.', 'width' => 30],
                ['label' => 'Tanggal / Waktu', 'width' => 88],
                ['label' => 'Kode', 'width' => 70],
                ['label' => 'Nama Siswa', 'width' => 120],
                ['label' => 'NIS/NISN', 'width' => 75],
                ['label' => 'Kelas', 'width' => 70],
                ['label' => 'Kegiatan', 'width' => 90],
                ['label' => 'Petugas', 'width' => 90],
                ['label' => 'Catatan', 'width' => 120],
            ],
            $rows,
        );
    }

    public function frequentVisitorsPdf(Request $request): Response
    {
        $filters = $this->visitFilters($request);
        $rows = $this->rankingQuery($filters)
            ->get()
            ->values()
            ->map(fn ($row, int $index): array => [
                $index + 1,
                $row->member_code,
                $row->member_name,
                $row->identity_number ?: '-',
                $row->department ?: '-',
                $row->visit_count.' kali',
                $row->last_visit ?: '-',
            ]);

        return $this->pdf->download(
            'laporan-siswa-sering-berkunjung-'.now()->format('Ymd-His').'.pdf',
            $this->institutionName(),
            'Peringkat Siswa yang Sering ke Perpustakaan',
            $this->pdfMeta($filters),
            [
                ['label' => 'Peringkat', 'width' => 55],
                ['label' => 'Kode', 'width' => 85],
                ['label' => 'Nama Siswa', 'width' => 170],
                ['label' => 'NIS/NISN', 'width' => 100],
                ['label' => 'Kelas', 'width' => 95],
                ['label' => 'Jumlah Kunjungan', 'width' => 110],
                ['label' => 'Kunjungan Terakhir', 'width' => 115],
            ],
            $rows,
        );
    }

    public function loanRecordsPdf(Request $request): Response
    {
        $filters = $this->loanFilters($request);
        $rows = $this->loanRecordQuery($filters)
            ->orderByDesc('loan_items.borrowed_at')
            ->get()
            ->values()
            ->map(fn ($row, int $index): array => [
                $index + 1,
                $row->loan_code,
                $row->member_name,
                $row->department ?: '-',
                $row->item_name,
                $row->asset_code,
                $row->borrowed_at,
                $row->due_date,
                $row->returned_at ?: '-',
                $this->returnStatusLabel($row->return_status),
            ]);

        return $this->pdf->download(
            'catatan-peminjaman-siswa-'.now()->format('Ymd-His').'.pdf',
            $this->institutionName(),
            'Catatan Peminjaman Siswa',
            $this->pdfMeta($filters),
            [
                ['label' => 'No.', 'width' => 28],
                ['label' => 'Transaksi', 'width' => 82],
                ['label' => 'Nama Siswa', 'width' => 110],
                ['label' => 'Kelas', 'width' => 60],
                ['label' => 'Judul Buku', 'width' => 145],
                ['label' => 'Kode Buku', 'width' => 75],
                ['label' => 'Tanggal Pinjam', 'width' => 80],
                ['label' => 'Jatuh Tempo', 'width' => 70],
                ['label' => 'Tanggal Kembali', 'width' => 80],
                ['label' => 'Status', 'width' => 70],
            ],
            $rows,
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
            'status' => ['nullable', 'in:borrowed,returned,damaged,lost'],
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
    private function loanRecordQuery(array $filters): Builder
    {
        return DB::table('loan_items')
            ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
            ->join('members', 'members.id', '=', 'loans.member_id')
            ->join('assets', 'assets.id', '=', 'loan_items.asset_id')
            ->join('items', 'items.id', '=', 'assets.item_id')
            ->where('members.member_type', 'student')
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery->where('members.member_name', 'like', '%'.$search.'%')
                        ->orWhere('members.member_code', 'like', '%'.$search.'%')
                        ->orWhere('members.identity_number', 'like', '%'.$search.'%')
                        ->orWhere('loans.loan_code', 'like', '%'.$search.'%')
                        ->orWhere('items.item_name', 'like', '%'.$search.'%')
                        ->orWhere('assets.asset_code', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['class'] ?? null, fn (Builder $query, string $class) => $query->where('members.department', $class))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('loan_items.return_status', $status))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('loan_items.borrowed_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('loan_items.borrowed_at', '<=', $date))
            ->select([
                'loan_items.id',
                'loan_items.borrowed_at',
                'loan_items.due_date',
                'loan_items.returned_at',
                'loan_items.return_status',
                'loans.loan_code',
                'members.member_code',
                'members.member_name',
                'members.identity_number',
                'members.department',
                'assets.asset_code',
                'items.item_name',
            ]);
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
    private function pdfMeta(array $filters): array
    {
        $period = 'Semua tanggal';
        if (! empty($filters['date_from']) || ! empty($filters['date_to'])) {
            $period = ($filters['date_from'] ?? 'awal').' sampai '.($filters['date_to'] ?? 'sekarang');
        }

        return [
            'Periode: '.$period,
            'Kelas: '.($filters['class'] ?? 'Semua kelas'),
            'Dicetak: '.now()->format('d-m-Y H:i').' oleh '.(auth()->user()?->full_name ?? 'Sistem'),
        ];
    }

    private function institutionName(): string
    {
        return (string) (SystemSetting::query()
            ->where('setting_key', 'institution.name')
            ->value('setting_value') ?: 'SDN Mekarsari 08');
    }

    private function returnStatusLabel(string $status): string
    {
        return match ($status) {
            'borrowed' => 'Masih dipinjam',
            'returned' => 'Dikembalikan',
            'damaged' => 'Rusak',
            'lost' => 'Hilang',
            default => ucfirst($status),
        };
    }
}
