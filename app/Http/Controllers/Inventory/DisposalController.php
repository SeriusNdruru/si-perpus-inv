<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\CompleteDisposalRequest;
use App\Http\Requests\Inventory\StoreDisposalRequest;
use App\Http\Requests\Inventory\UpdateDisposalRequest;
use App\Models\Asset;
use App\Models\Disposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class DisposalController extends Controller
{
    private const STATUSES = [
        'proposed' => 'Menunggu persetujuan',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        'completed' => 'Selesai dihapuskan',
    ];

    private const METHODS = [
        'destroyed' => 'Dimusnahkan',
        'sold' => 'Dijual',
        'donated' => 'Disumbangkan',
        'returned' => 'Dikembalikan ke pemasok',
        'other' => 'Metode lainnya',
    ];

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $status = (string) $request->query('status');
        $method = (string) $request->query('method');
        $dateFrom = (string) $request->query('date_from');
        $dateTo = (string) $request->query('date_to');

        $disposals = Disposal::query()
            ->with([
                'asset:id,item_id,asset_code,barcode,condition_status,asset_status,current_location_id',
                'asset.item:id,item_code,item_name,item_type',
                'asset.location:id,location_code,location_name',
                'proposer:id,full_name',
                'approver:id,full_name',
            ])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $subQuery) use ($search): void {
                    $subQuery
                        ->where('disposal_code', 'like', "%{$search}%")
                        ->orWhere('reason', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('asset', function (Builder $assetQuery) use ($search): void {
                            $assetQuery
                                ->where('asset_code', 'like', "%{$search}%")
                                ->orWhere('barcode', 'like', "%{$search}%")
                                ->orWhereHas('item', function (Builder $itemQuery) use ($search): void {
                                    $itemQuery
                                        ->where('item_code', 'like', "%{$search}%")
                                        ->orWhere('item_name', 'like', "%{$search}%");
                                });
                        });
                });
            })
            ->when(array_key_exists($status, self::STATUSES), fn (Builder $query) => $query->where('status', $status))
            ->when(array_key_exists($method, self::METHODS), fn (Builder $query) => $query->where('disposal_method', $method))
            ->when($this->isDate($dateFrom), fn (Builder $query) => $query->whereDate('proposed_at', '>=', $dateFrom))
            ->when($this->isDate($dateTo), fn (Builder $query) => $query->whereDate('proposed_at', '<=', $dateTo))
            ->orderByDesc('proposed_at')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        $summary = [
            'total' => Disposal::query()->count(),
            'proposed' => Disposal::query()->where('status', 'proposed')->count(),
            'approved' => Disposal::query()->where('status', 'approved')->count(),
            'completed' => Disposal::query()->where('status', 'completed')->count(),
        ];

        return view('inventory.disposals.index', [
            'disposals' => $disposals,
            'summary' => $summary,
            'statuses' => self::STATUSES,
            'methods' => self::METHODS,
        ]);
    }

    public function create(Request $request): View
    {
        return view('inventory.disposals.create', [
            'assets' => $this->eligibleAssets(),
            'selectedAssetId' => (int) $request->query('asset_id'),
        ]);
    }

    public function store(StoreDisposalRequest $request): RedirectResponse
    {
        $data = $request->validated();

        try {
            $disposalId = DB::transaction(function () use ($data, $request): int {
                /** @var Asset $asset */
                $asset = Asset::query()
                    ->with('item:id,item_code,item_name,item_type,status,tracking_type')
                    ->lockForUpdate()
                    ->findOrFail((int) $data['asset_id']);

                $this->assertAssetCanBeProposed($asset);

                if (Disposal::query()->where('asset_id', $asset->id)->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages([
                        'asset_id' => 'Aset tersebut sudah memiliki riwayat usulan penghapusan.',
                    ]);
                }

                $disposal = Disposal::query()->create([
                    'disposal_code' => $this->nextCode(Carbon::parse((string) $data['proposed_at'])->toDateString()),
                    'asset_id' => $asset->id,
                    'reason' => trim((string) $data['reason']),
                    'proposed_at' => Carbon::parse((string) $data['proposed_at']),
                    'status' => 'proposed',
                    'proposed_by' => $request->user()->id,
                    'notes' => $data['notes'] ?? null,
                ]);

                return (int) $disposal->id;
            }, 3);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            return back()
                ->withInput()
                ->withErrors(['database' => $this->databaseMessage($exception)]);
        }

        return redirect()
            ->route('inventory.disposals.show', $disposalId)
            ->with('success', 'Usulan penghapusan berhasil dibuat dan menunggu persetujuan Super Admin.');
    }

    public function show(Disposal $disposal): View
    {
        $disposal->load([
            'asset.item.category:id,category_code,category_name',
            'asset.location:id,location_code,location_name',
            'asset.shelf:id,shelf_code,shelf_name',
            'asset.supplier:id,supplier_code,supplier_name',
            'proposer:id,full_name,username',
            'approver:id,full_name,username',
        ]);

        return view('inventory.disposals.show', [
            'disposal' => $disposal,
            'methods' => self::METHODS,
        ]);
    }

    public function edit(Disposal $disposal): View|RedirectResponse
    {
        if (! in_array($disposal->status, ['proposed', 'rejected'], true)) {
            return redirect()
                ->route('inventory.disposals.show', $disposal)
                ->with('error', 'Hanya usulan yang menunggu persetujuan atau ditolak yang dapat diperbaiki.');
        }

        $disposal->load('asset.item:id,item_code,item_name,item_type');

        return view('inventory.disposals.edit', [
            'disposal' => $disposal,
        ]);
    }

    public function update(UpdateDisposalRequest $request, Disposal $disposal): RedirectResponse
    {
        if (! in_array($disposal->status, ['proposed', 'rejected'], true)) {
            return redirect()
                ->route('inventory.disposals.show', $disposal)
                ->with('error', 'Usulan yang sudah disetujui atau selesai tidak dapat diubah.');
        }

        $data = $request->validated();
        $wasRejected = $disposal->status === 'rejected';

        $disposal->update([
            'proposed_at' => Carbon::parse((string) $data['proposed_at']),
            'reason' => trim((string) $data['reason']),
            'notes' => $data['notes'] ?? null,
            'status' => 'proposed',
            'approved_at' => null,
            'approved_by' => null,
        ]);

        return redirect()
            ->route('inventory.disposals.show', $disposal)
            ->with('success', $wasRejected
                ? 'Usulan berhasil diperbaiki dan diajukan kembali.'
                : 'Usulan penghapusan berhasil diperbarui.');
    }

    public function approve(Request $request, Disposal $disposal): RedirectResponse
    {
        $this->assertSuperAdmin($request);

        try {
            DB::transaction(function () use ($request, $disposal): void {
                /** @var Disposal $lockedDisposal */
                $lockedDisposal = Disposal::query()->lockForUpdate()->findOrFail($disposal->id);

                if ($lockedDisposal->status !== 'proposed') {
                    throw new RuntimeException('Hanya usulan berstatus menunggu persetujuan yang dapat disetujui.');
                }

                /** @var Asset $asset */
                $asset = Asset::query()
                    ->with('item:id,status,tracking_type')
                    ->lockForUpdate()
                    ->findOrFail($lockedDisposal->asset_id);

                $this->assertAssetCanBeProposed($asset);

                $lockedDisposal->update([
                    'status' => 'approved',
                    'approved_at' => now(),
                    'approved_by' => $request->user()->id,
                ]);
            }, 3);
        } catch (Throwable $exception) {
            return back()->with('error', $this->databaseMessage($exception));
        }

        return back()->with('success', 'Usulan penghapusan telah disetujui.');
    }

    public function reject(Request $request, Disposal $disposal): RedirectResponse
    {
        $this->assertSuperAdmin($request);

        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:3000'],
        ], [
            'rejection_reason.required' => 'Alasan penolakan wajib diisi.',
            'rejection_reason.max' => 'Alasan penolakan maksimal 3.000 karakter.',
        ]);

        if ($disposal->status !== 'proposed') {
            return back()->with('error', 'Hanya usulan yang menunggu persetujuan yang dapat ditolak.');
        }

        $oldNotes = trim((string) $disposal->notes);
        $rejectionNote = 'Alasan penolakan '.now()->format('d/m/Y H:i').': '.trim((string) $data['rejection_reason']);

        $disposal->update([
            'status' => 'rejected',
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
            'notes' => $oldNotes !== '' ? $oldNotes."\n\n".$rejectionNote : $rejectionNote,
        ]);

        return back()->with('success', 'Usulan penghapusan telah ditolak.');
    }

    public function completeForm(Disposal $disposal): View|RedirectResponse
    {
        if ($disposal->status !== 'approved') {
            return redirect()
                ->route('inventory.disposals.show', $disposal)
                ->with('error', 'Penghapusan hanya dapat dilaksanakan setelah disetujui.');
        }

        $disposal->load('asset.item:id,item_code,item_name,item_type');

        return view('inventory.disposals.complete', [
            'disposal' => $disposal,
            'methods' => self::METHODS,
        ]);
    }

    public function complete(
        CompleteDisposalRequest $request,
        Disposal $disposal
    ): RedirectResponse {
        $data = $request->validated();

        try {
            DB::transaction(function () use ($data, $request, $disposal): void {
                /** @var Disposal $lockedDisposal */
                $lockedDisposal = Disposal::query()
                    ->lockForUpdate()
                    ->findOrFail($disposal->id);

                if ($lockedDisposal->status !== 'approved') {
                    throw new RuntimeException('Status usulan berubah. Muat ulang halaman lalu coba lagi.');
                }

                /** @var Asset $asset */
                $asset = Asset::query()
                    ->with(['item:id,item_code,item_name,item_type,status,tracking_type', 'shelf'])
                    ->lockForUpdate()
                    ->findOrFail($lockedDisposal->asset_id);

                $this->assertAssetCanBeCompleted($asset);

                $oldShelfId = $asset->current_shelf_id;
                $oldLocationId = $asset->current_location_id;

                if ($oldShelfId !== null) {
                    DB::table('asset_shelf_history')->insert([
                        'asset_id' => $asset->id,
                        'old_shelf_id' => $oldShelfId,
                        'new_shelf_id' => null,
                        'changed_by' => $request->user()->id,
                        'changed_at' => now(),
                        'notes' => 'Penempatan rak dilepas karena aset dihapuskan.',
                    ]);
                }

                $asset->update([
                    'asset_status' => 'disposed',
                    'current_shelf_id' => null,
                    'updated_by' => $request->user()->id,
                ]);

                $completionNotes = trim((string) ($data['completion_notes'] ?? ''));
                $existingNotes = trim((string) $lockedDisposal->notes);

                $lockedDisposal->update([
                    'status' => 'completed',
                    'disposed_at' => Carbon::parse((string) $data['disposed_at']),
                    'disposal_method' => $data['disposal_method'],
                    'notes' => $completionNotes !== ''
                        ? ($existingNotes !== '' ? $existingNotes."\n\nCatatan pelaksanaan: ".$completionNotes : 'Catatan pelaksanaan: '.$completionNotes)
                        : $existingNotes,
                ]);

                DB::table('stock_movements')->insert([
                    'movement_code' => 'MOV-'.str_replace('-', '', (string) Str::uuid()),
                    'item_id' => $asset->item_id,
                    'asset_id' => $asset->id,
                    'movement_type' => 'disposal',
                    'quantity' => 1,
                    'from_location_id' => $oldLocationId,
                    'to_location_id' => null,
                    'reference_type' => 'disposal',
                    'reference_id' => $lockedDisposal->id,
                    'movement_date' => Carbon::parse((string) $data['disposed_at']),
                    'created_by' => $request->user()->id,
                    'notes' => 'Penghapusan aset '.$asset->asset_code.' melalui metode '.self::METHODS[$data['disposal_method']].'.',
                    'created_at' => now(),
                ]);
            }, 3);
        } catch (Throwable $exception) {
            return back()
                ->withInput()
                ->withErrors(['database' => $this->databaseMessage($exception)]);
        }

        return redirect()
            ->route('inventory.disposals.show', $disposal)
            ->with('success', 'Penghapusan aset berhasil diselesaikan dan status aset menjadi disposed.');
    }

    private function eligibleAssets()
    {
        return Asset::query()
            ->with([
                'item:id,item_code,item_name,item_type,status,tracking_type',
                'location:id,location_code,location_name',
            ])
            ->whereHas('item', fn (Builder $query) => $query
                ->where('status', 'active')
                ->where('tracking_type', 'asset'))
            ->whereNotIn('asset_status', ['borrowed', 'reserved', 'maintenance', 'disposed'])
            ->whereDoesntHave('disposal')
            ->orderBy('asset_code')
            ->get();
    }

    private function assertAssetCanBeProposed(Asset $asset): void
    {
        if ($asset->item === null || $asset->item->status !== 'active' || $asset->item->tracking_type !== 'asset') {
            throw ValidationException::withMessages([
                'asset_id' => 'Aset tidak aktif atau bukan aset individual.',
            ]);
        }

        if (in_array($asset->asset_status, ['borrowed', 'reserved', 'maintenance', 'disposed'], true)) {
            throw ValidationException::withMessages([
                'asset_id' => 'Aset dengan status saat ini tidak dapat diajukan untuk penghapusan.',
            ]);
        }
    }

    private function assertAssetCanBeCompleted(Asset $asset): void
    {
        if ($asset->asset_status === 'disposed') {
            throw new RuntimeException('Aset sudah berstatus dihapuskan.');
        }

        if (in_array($asset->asset_status, ['borrowed', 'reserved', 'maintenance'], true)) {
            throw new RuntimeException('Aset sedang dipinjam, direservasi, atau dipelihara sehingga belum dapat dihapuskan.');
        }

        $hasActiveLoan = DB::table('loan_items')
            ->where('asset_id', $asset->id)
            ->where('return_status', 'borrowed')
            ->exists();

        if ($hasActiveLoan) {
            throw new RuntimeException('Aset masih tercatat dalam peminjaman aktif.');
        }

        $hasOpenMaintenance = DB::table('maintenance_records')
            ->where('asset_id', $asset->id)
            ->whereIn('status', ['reported', 'in_progress'])
            ->exists();

        if ($hasOpenMaintenance) {
            throw new RuntimeException('Aset masih memiliki pemeliharaan aktif.');
        }
    }

    private function assertSuperAdmin(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();

        abort_unless($user?->hasRole(User::ROLE_SUPER_ADMIN), 403);
    }

    private function nextCode(string $date): string
    {
        $dateCode = str_replace('-', '', substr($date, 0, 10));
        $prefix = "DSP-{$dateCode}-";

        $lastCode = (string) Disposal::query()
            ->where('disposal_code', 'like', $prefix.'%')
            ->orderByDesc('disposal_code')
            ->lockForUpdate()
            ->value('disposal_code');

        $sequence = $lastCode !== '' ? ((int) substr($lastCode, -4)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function isDate(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
    }

    private function databaseMessage(Throwable $exception): string
    {
        report($exception);

        if ($exception instanceof RuntimeException || $exception instanceof ValidationException) {
            return $exception->getMessage();
        }

        return 'Data penghapusan belum dapat diproses. Periksa kembali data lalu coba lagi.';
    }
}
