<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Models\LibraryVisit;
use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LibraryVisitController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'class' => ['nullable', 'string', 'max:150'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $baseQuery = $this->filteredQuery($filters);

        $summary = [
            'today' => LibraryVisit::query()->whereDate('visit_date', today())->count(),
            'month' => LibraryVisit::query()
                ->whereYear('visit_date', today()->year)
                ->whereMonth('visit_date', today()->month)
                ->count(),
            'filtered' => (clone $baseQuery)->count(),
            'students' => (clone $baseQuery)->distinct('member_id')->count('member_id'),
        ];

        $visits = $baseQuery
            ->with([
                'member:id,member_code,member_name,identity_number,department',
                'recorder:id,full_name',
            ])
            ->orderByDesc('visit_date')
            ->orderByDesc('visit_time')
            ->paginate(20)
            ->withQueryString();

        $classes = Member::query()
            ->where('member_type', 'student')
            ->whereNotNull('department')
            ->where('department', '<>', '')
            ->distinct()
            ->orderBy('department')
            ->pluck('department');

        return view('library.visits.index', compact('visits', 'summary', 'classes', 'filters'));
    }

    public function create(): View
    {
        $students = $this->studentOptions();

        return view('library.visits.create', compact('students'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateVisit($request);
        $validated['activity'] = 'Membaca buku';
        $validated['recorded_by'] = $request->user()->id;

        LibraryVisit::query()->create($validated);

        return redirect()->route('library.visits.index')
            ->with('success', 'Kunjungan siswa berhasil dicatat.');
    }

    public function edit(LibraryVisit $visit): View
    {
        $students = $this->studentOptions();

        return view('library.visits.edit', compact('visit', 'students'));
    }

    public function update(Request $request, LibraryVisit $visit): RedirectResponse
    {
        $validated = $this->validateVisit($request);
        $validated['activity'] = 'Membaca buku';

        $visit->update($validated);

        return redirect()->route('library.visits.index')
            ->with('success', 'Catatan kunjungan berhasil diperbarui.');
    }

    public function destroy(LibraryVisit $visit): RedirectResponse
    {
        $visit->delete();

        return redirect()->route('library.visits.index', request()->query())
            ->with('success', 'Catatan kunjungan berhasil dihapus.');
    }

    /** @param array<string, mixed> $filters */
    private function filteredQuery(array $filters): Builder
    {
        return LibraryVisit::query()
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->whereHas('member', function (Builder $memberQuery) use ($search): void {
                    $memberQuery->where(function (Builder $searchQuery) use ($search): void {
                        $searchQuery->where('member_name', 'like', '%'.$search.'%')
                            ->orWhere('member_code', 'like', '%'.$search.'%')
                            ->orWhere('identity_number', 'like', '%'.$search.'%');
                    });
                });
            })
            ->when($filters['class'] ?? null, function (Builder $query, string $class): void {
                $query->whereHas('member', fn (Builder $memberQuery) => $memberQuery->where('department', $class));
            })
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('visit_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('visit_date', '<=', $date));
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Member> */
    private function studentOptions()
    {
        return Member::query()
            ->where('member_type', 'student')
            ->where('status', 'active')
            ->orderBy('member_name')
            ->get(['id', 'member_code', 'member_name', 'identity_number', 'department']);
    }

    /** @return array<string, mixed> */
    private function validateVisit(Request $request): array
    {
        return $request->validate([
            'member_id' => [
                'required',
                'integer',
                Rule::exists('members', 'id')->where(fn ($query) => $query
                    ->where('member_type', 'student')
                    ->where('status', 'active')),
            ],
            'visit_date' => ['required', 'date'],
            'visit_time' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'member_id.required' => 'Siswa wajib dipilih.',
            'member_id.exists' => 'Siswa tidak ditemukan atau statusnya tidak aktif.',
            'visit_date.required' => 'Tanggal kunjungan wajib diisi.',
            'visit_time.required' => 'Waktu kunjungan wajib diisi.',
        ]);
    }
}
