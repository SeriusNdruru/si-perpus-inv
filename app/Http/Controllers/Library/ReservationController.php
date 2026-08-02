<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Requests\Library\StoreReservationRequest;
use App\Models\Item;
use App\Models\LoanItem;
use App\Models\Member;
use App\Models\Reservation;
use App\Services\Library\ReservationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class ReservationController extends Controller
{
    public function __construct(private readonly ReservationService $reservationService)
    {
    }

    public function index(Request $request): View
    {
        $this->reservationService->synchronizeAll();

        $search = trim((string) $request->query('search'));
        $status = (string) $request->query('status');

        $reservations = Reservation::query()
            ->with([
                'member:id,member_code,member_name,member_type,identity_number',
                'item:id,item_code,item_name,item_type,status',
                'item.bookDetail:item_id,isbn_10,isbn_13,call_number,completion_status',
                'item.authors:id,author_name',
                'processor:id,full_name',
            ])
            ->select('reservations.*')
            ->selectSub(function ($query): void {
                $query->from('assets')
                    ->join('library_shelves', 'library_shelves.id', '=', 'assets.current_shelf_id')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('assets.item_id', 'reservations.item_id')
                    ->where('assets.asset_status', 'available')
                    ->whereIn('assets.condition_status', ['good', 'fair'])
                    ->where('library_shelves.status', 'active');
            }, 'available_copies')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('reservation_code', 'like', "%{$search}%")
                        ->orWhereHas('member', function ($memberQuery) use ($search): void {
                            $memberQuery->where('member_code', 'like', "%{$search}%")
                                ->orWhere('member_name', 'like', "%{$search}%")
                                ->orWhere('identity_number', 'like', "%{$search}%");
                        })
                        ->orWhereHas('item', function ($itemQuery) use ($search): void {
                            $itemQuery->where('item_code', 'like', "%{$search}%")
                                ->orWhere('item_name', 'like', "%{$search}%")
                                ->orWhereHas('bookDetail', function ($bookQuery) use ($search): void {
                                    $bookQuery->where('isbn_10', 'like', "%{$search}%")
                                        ->orWhere('isbn_13', 'like', "%{$search}%")
                                        ->orWhere('call_number', 'like', "%{$search}%");
                                })
                                ->orWhereHas('authors', fn ($authorQuery) => $authorQuery->where('author_name', 'like', "%{$search}%"));
                        });
                });
            })
            ->when(
                in_array($status, ['waiting', 'ready', 'completed', 'cancelled', 'expired'], true),
                fn ($query) => $query->where('reservations.status', $status)
            )
            ->orderByRaw("CASE reservations.status WHEN 'ready' THEN 1 WHEN 'waiting' THEN 2 ELSE 3 END")
            ->orderBy('reservations.reservation_date')
            ->orderBy('reservations.id')
            ->paginate(15)
            ->withQueryString();

        $summary = [
            'waiting' => Reservation::query()->where('status', 'waiting')->count(),
            'ready' => Reservation::query()->where('status', 'ready')->count(),
            'expiring_today' => Reservation::query()
                ->where('status', 'ready')
                ->whereDate('expires_at', today())
                ->count(),
            'completed_today' => Reservation::query()
                ->where('status', 'completed')
                ->whereDate('updated_at', today())
                ->count(),
        ];

        return view('library.reservations.index', compact('reservations', 'summary'));
    }

    public function create(Request $request): View
    {
        $this->synchronizeExpiredMembers();
        $this->reservationService->synchronizeAll();

        $settings = $this->reservationService->settings();
        $preselectedMemberId = $request->integer('member_id') ?: null;
        $preselectedItemId = $request->integer('item_id') ?: null;

        $members = Member::query()
            ->select('members.*')
            ->selectSub(function ($query): void {
                $query->from('reservations')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('reservations.member_id', 'members.id')
                    ->whereIn('reservations.status', ['waiting', 'ready']);
            }, 'active_reservation_count')
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->whereNull('expiry_date')
                    ->orWhereDate('expiry_date', '>=', today());
            })
            ->orderBy('member_name')
            ->get();

        $books = Item::query()
            ->with([
                'bookDetail:item_id,isbn_10,isbn_13,call_number,completion_status',
                'authors:id,author_name',
            ])
            ->select('items.*')
            ->selectSub(function ($query): void {
                $query->from('assets')
                    ->join('library_shelves', 'library_shelves.id', '=', 'assets.current_shelf_id')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('assets.item_id', 'items.id')
                    ->where('assets.asset_status', 'available')
                    ->whereIn('assets.condition_status', ['good', 'fair'])
                    ->where('library_shelves.status', 'active');
            }, 'available_copies')
            ->selectSub(function ($query): void {
                $query->from('assets')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('assets.item_id', 'items.id')
                    ->whereIn('assets.asset_status', ['available', 'borrowed', 'reserved'])
                    ->whereIn('assets.condition_status', ['good', 'fair']);
            }, 'circulating_copies')
            ->selectSub(function ($query): void {
                $query->from('reservations')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('reservations.item_id', 'items.id')
                    ->whereIn('reservations.status', ['waiting', 'ready']);
            }, 'active_reservation_count')
            ->where('items.item_type', 'book')
            ->where('items.status', 'active')
            ->whereHas('bookDetail', function ($query): void {
                $query->whereIn('completion_status', ['complete', 'verified']);
            })
            ->whereHas('assets', function ($query): void {
                $query->whereIn('asset_status', ['available', 'borrowed', 'reserved'])
                    ->whereIn('condition_status', ['good', 'fair']);
            })
            ->orderBy('items.item_name')
            ->get();

        return view('library.reservations.create', compact(
            'members',
            'books',
            'settings',
            'preselectedMemberId',
            'preselectedItemId'
        ));
    }

    public function store(StoreReservationRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $reservation = DB::transaction(function () use ($request, $validated): Reservation {
                $member = Member::query()->lockForUpdate()->findOrFail((int) $validated['member_id']);
                $item = Item::query()
                    ->with('bookDetail')
                    ->lockForUpdate()
                    ->findOrFail((int) $validated['item_id']);

                $this->assertMemberCanReserve($member);
                $this->assertBookCanBeReserved($item);

                $settings = $this->reservationService->settings();
                $activeReservations = Reservation::query()
                    ->where('member_id', $member->id)
                    ->whereIn('status', ['waiting', 'ready'])
                    ->lockForUpdate()
                    ->get(['id', 'item_id']);

                if ($activeReservations->count() >= $settings['max_active']) {
                    throw ValidationException::withMessages([
                        'member_id' => "Anggota telah mencapai batas {$settings['max_active']} reservasi aktif.",
                    ]);
                }

                if ($activeReservations->contains('item_id', $item->id)) {
                    throw ValidationException::withMessages([
                        'item_id' => 'Anggota sudah memiliki reservasi aktif untuk judul ini.',
                    ]);
                }

                $alreadyBorrowing = LoanItem::query()
                    ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
                    ->join('assets', 'assets.id', '=', 'loan_items.asset_id')
                    ->where('loans.member_id', $member->id)
                    ->where('assets.item_id', $item->id)
                    ->where('loan_items.return_status', 'borrowed')
                    ->exists();

                if ($alreadyBorrowing) {
                    throw ValidationException::withMessages([
                        'item_id' => 'Anggota masih meminjam judul ini sehingga tidak perlu membuat reservasi baru.',
                    ]);
                }

                $reservation = Reservation::query()->create([
                    'reservation_code' => $this->generateReservationCode(),
                    'member_id' => $member->id,
                    'item_id' => $item->id,
                    'reservation_date' => now(),
                    'status' => 'waiting',
                    'processed_by' => $request->user()?->id,
                    'notes' => $validated['notes'] ?? null,
                ]);

                $this->reservationService->synchronizeItemLocked($item->id);
                $reservation->refresh();

                DB::table('audit_logs')->insert([
                    'user_id' => $request->user()?->id,
                    'action' => 'insert',
                    'module_name' => 'library_reservations',
                    'table_name' => 'reservations',
                    'record_id' => $reservation->id,
                    'new_data' => json_encode([
                        'reservation_code' => $reservation->reservation_code,
                        'member_id' => $member->id,
                        'item_id' => $item->id,
                        'queue_number' => $reservation->queue_number,
                        'status' => $reservation->status,
                        'expires_at' => $reservation->expires_at?->toDateTimeString(),
                    ], JSON_THROW_ON_ERROR),
                    'ip_address' => $request->ip(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
                    'created_at' => now(),
                ]);

                return $reservation;
            }, 3);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (QueryException $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors(['item_id' => 'Reservasi gagal disimpan karena data berubah atau kode reservasi berbenturan. Coba kembali.']);
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors(['item_id' => 'Reservasi belum dapat disimpan. Periksa data lalu coba kembali.']);
        }

        $message = $reservation->status === 'ready'
            ? 'Reservasi berhasil dibuat. Buku tersedia dan siap diambil sebelum batas waktu.'
            : "Reservasi berhasil dibuat pada antrean nomor {$reservation->queue_number}.";

        return redirect()
            ->route('library.reservations.show', $reservation)
            ->with('success', $message);
    }

    public function show(Reservation $reservation): View
    {
        if ($reservation->isActive()) {
            $this->reservationService->synchronizeItem($reservation->item_id);
            $reservation->refresh();
        }

        $reservation->load([
            'member:id,member_code,member_name,member_type,identity_number,department,phone,email,status,expiry_date',
            'item:id,item_code,item_name,item_type,status',
            'item.bookDetail:item_id,isbn_10,isbn_13,call_number,classification_code,completion_status',
            'item.authors:id,author_name',
            'processor:id,full_name,username',
        ]);

        $availableCopies = $this->reservationService
            ->eligibleAvailableAssetsQuery($reservation->item_id)
            ->count();

        $activeQueue = Reservation::query()
            ->where('item_id', $reservation->item_id)
            ->whereIn('status', ['waiting', 'ready'])
            ->orderBy('queue_number')
            ->orderBy('reservation_date')
            ->get(['id', 'reservation_code', 'member_id', 'queue_number', 'status', 'expires_at']);

        return view('library.reservations.show', compact('reservation', 'availableCopies', 'activeQueue'));
    }

    public function cancel(Request $request, Reservation $reservation): RedirectResponse
    {
        if (! $reservation->isActive()) {
            return redirect()
                ->route('library.reservations.show', $reservation)
                ->with('error', 'Hanya reservasi aktif yang dapat dibatalkan.');
        }

        try {
            DB::transaction(function () use ($request, $reservation): void {
                $locked = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);

                if (! $locked->isActive()) {
                    throw ValidationException::withMessages([
                        'status' => 'Reservasi sudah diproses oleh petugas lain.',
                    ]);
                }

                $oldData = [
                    'status' => $locked->status,
                    'queue_number' => $locked->queue_number,
                    'expires_at' => $locked->expires_at?->toDateTimeString(),
                ];

                $locked->update([
                    'status' => 'cancelled',
                    'expires_at' => null,
                    'processed_by' => $request->user()?->id,
                ]);

                $this->reservationService->synchronizeItemLocked($locked->item_id);

                DB::table('audit_logs')->insert([
                    'user_id' => $request->user()?->id,
                    'action' => 'update',
                    'module_name' => 'library_reservations',
                    'table_name' => 'reservations',
                    'record_id' => $locked->id,
                    'old_data' => json_encode($oldData, JSON_THROW_ON_ERROR),
                    'new_data' => json_encode(['status' => 'cancelled'], JSON_THROW_ON_ERROR),
                    'ip_address' => $request->ip(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
                    'created_at' => now(),
                ]);
            }, 3);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Reservasi belum dapat dibatalkan. Coba kembali.');
        }

        return redirect()
            ->route('library.reservations.index')
            ->with('success', 'Reservasi berhasil dibatalkan dan antrean telah diperbarui.');
    }

    private function assertMemberCanReserve(Member $member): void
    {
        if ($member->status !== 'active') {
            throw ValidationException::withMessages([
                'member_id' => 'Anggota tidak aktif dan tidak dapat membuat reservasi.',
            ]);
        }

        if ($member->expiry_date !== null && $member->expiry_date->isBefore(today())) {
            $member->update(['status' => 'expired']);

            throw ValidationException::withMessages([
                'member_id' => 'Masa berlaku keanggotaan sudah berakhir.',
            ]);
        }
    }

    private function assertBookCanBeReserved(Item $item): void
    {
        if ($item->item_type !== 'book' || $item->status !== 'active') {
            throw ValidationException::withMessages([
                'item_id' => 'Judul yang dipilih bukan buku aktif.',
            ]);
        }

        if (! in_array($item->bookDetail?->completion_status, ['complete', 'verified'], true)) {
            throw ValidationException::withMessages([
                'item_id' => 'Katalog buku belum lengkap sehingga belum dapat direservasi.',
            ]);
        }

        $hasCirculatingCopy = $item->assets()
            ->whereIn('asset_status', ['available', 'borrowed', 'reserved'])
            ->whereIn('condition_status', ['good', 'fair'])
            ->exists();

        if (! $hasCirculatingCopy) {
            throw ValidationException::withMessages([
                'item_id' => 'Buku belum memiliki eksemplar yang layak untuk disirkulasikan.',
            ]);
        }
    }

    private function generateReservationCode(): string
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $candidate = sprintf(
                'RSV-%s-%s',
                now()->format('Ymd-His'),
                Str::upper(Str::random(4))
            );

            if (! Reservation::query()->where('reservation_code', $candidate)->exists()) {
                return $candidate;
            }

            usleep(1000);
        }

        throw new \RuntimeException('Kode reservasi tidak dapat dibuat.');
    }

    private function synchronizeExpiredMembers(): void
    {
        Member::query()
            ->where('status', 'active')
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', today())
            ->update(['status' => 'expired']);
    }
}
