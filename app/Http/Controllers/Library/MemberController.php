<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Requests\Library\StoreMemberRequest;
use App\Http\Requests\Library\UpdateMemberRequest;
use App\Http\Requests\Library\UpdateMemberStatusRequest;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use RuntimeException;

class MemberController extends Controller
{
    public function index(Request $request): View
    {
        $this->synchronizeExpiredMembers();

        $search = trim((string) $request->query('search'));
        $memberType = (string) $request->query('member_type');
        $status = (string) $request->query('status');

        $members = Member::query()
            ->select('members.*')
            ->selectSub(function ($query): void {
                $query->from('loans')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('loans.member_id', 'members.id');
            }, 'total_loans')
            ->selectSub(function ($query): void {
                $query->from('loans')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('loans.member_id', 'members.id')
                    ->whereIn('loans.status', ['active', 'overdue']);
            }, 'active_loans')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('member_code', 'like', "%{$search}%")
                        ->orWhere('member_name', 'like', "%{$search}%")
                        ->orWhere('identity_number', 'like', "%{$search}%")
                        ->orWhere('department', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when(
                in_array($memberType, ['student', 'teacher', 'staff', 'public'], true),
                fn ($query) => $query->where('member_type', $memberType)
            )
            ->when(
                in_array($status, ['active', 'suspended', 'inactive', 'expired'], true),
                fn ($query) => $query->where('status', $status)
            )
            ->orderBy('member_name')
            ->paginate(10)
            ->withQueryString();

        $summary = [
            'total' => Member::query()->count(),
            'active' => Member::query()->where('status', 'active')->count(),
            'suspended' => Member::query()->where('status', 'suspended')->count(),
            'expired' => Member::query()->where('status', 'expired')->count(),
        ];

        return view('library.members.index', compact('members', 'summary'));
    }

    public function create(): View
    {
        return view('library.members.create');
    }

    public function store(StoreMemberRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $member = DB::transaction(function () use ($request, $data): Member {
            $memberStatus = $this->normalizedStatus($data['status'], $data['expiry_date'] ?? null);
            $user = $this->createMemberUser(
                data: $data,
                memberStatus: $memberStatus,
            );

            $memberData = $this->memberData($data);
            $memberData['member_code'] = $memberData['member_code'] ?: $this->generateMemberCode();
            $memberData['created_by'] = $request->user()?->id;
            $memberData['status'] = $memberStatus;
            $memberData['user_id'] = $user->id;

            return Member::query()->create($memberData);
        }, 3);

        return redirect()
            ->route('library.members.show', $member)
            ->with('success', 'Anggota dan akun login anggota berhasil ditambahkan.');
    }

    public function show(Member $member): View
    {
        $this->synchronizeMemberExpiry($member);
        $member->load(['creator:id,full_name', 'user:id,full_name,username,email,status,email_verified_at']);

        $loans = $member->loans()
            ->select('loans.*')
            ->selectSub(function ($query): void {
                $query->from('loan_items')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('loan_items.loan_id', 'loans.id');
            }, 'items_count')
            ->selectSub(function ($query): void {
                $query->from('loan_items')
                    ->selectRaw('COALESCE(SUM(fine_amount), 0)')
                    ->whereColumn('loan_items.loan_id', 'loans.id');
            }, 'fine_total')
            ->orderByDesc('loan_date')
            ->paginate(10)
            ->withQueryString();

        $loanSummary = [
            'total' => $member->loans()->count(),
            'active' => $member->loans()->whereIn('status', ['active', 'overdue'])->count(),
            'completed' => $member->loans()->where('status', 'completed')->count(),
            'fine_total' => (float) DB::table('loan_items')
                ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
                ->where('loans.member_id', $member->id)
                ->sum('loan_items.fine_amount'),
        ];

        return view('library.members.show', compact('member', 'loans', 'loanSummary'));
    }

    public function edit(Member $member): View
    {
        $this->synchronizeMemberExpiry($member);
        $member->loadMissing('user');

        return view('library.members.edit', compact('member'));
    }

    public function update(UpdateMemberRequest $request, Member $member): RedirectResponse
    {
        $data = $request->validated();
        $memberStatus = $this->normalizedStatus($data['status'], $data['expiry_date'] ?? null);

        if (
            $memberStatus === 'inactive'
            && $member->loans()->whereIn('status', ['active', 'overdue'])->exists()
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'status' => 'Anggota belum dapat dinonaktifkan karena masih memiliki peminjaman aktif. Gunakan status ditangguhkan jika akses peminjaman baru perlu diblokir.',
                ]);
        }

        DB::transaction(function () use ($member, $data, $memberStatus): void {
            $member->loadMissing('user');

            $memberData = $this->memberData($data);
            $memberData['status'] = $memberStatus;
            $member->update($memberData);

            $user = $member->user;
            if ($user === null) {
                $user = $this->createMemberUser(
                    data: $data,
                    memberStatus: $memberStatus,
                );
                $member->update(['user_id' => $user->id]);

                return;
            }

            $userData = [
                'full_name' => $member->member_name,
                'username' => $data['account_username'],
                'email' => $member->email,
                'phone' => $member->phone,
                'status' => $this->userStatus($memberStatus),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ];

            if (! empty($data['account_password'])) {
                $userData['password_hash'] = Hash::make($data['account_password']);
                $userData['password_changed_at'] = now();
            }

            $user->update($userData);
        }, 3);

        return redirect()
            ->route('library.members.show', $member)
            ->with('success', 'Data anggota dan akun login berhasil diperbarui.');
    }

    public function updateStatus(UpdateMemberStatusRequest $request, Member $member): RedirectResponse
    {
        $newStatus = (string) $request->validated('status');

        if (
            $newStatus === 'inactive'
            && $member->loans()->whereIn('status', ['active', 'overdue'])->exists()
        ) {
            return back()->with('error', 'Anggota belum dapat dinonaktifkan karena masih memiliki peminjaman aktif. Gunakan status ditangguhkan jika akses peminjaman baru perlu diblokir.');
        }

        DB::transaction(function () use ($member, $newStatus): void {
            $member->update(['status' => $newStatus]);

            if ($member->user_id !== null) {
                User::query()
                    ->whereKey($member->user_id)
                    ->update(['status' => $this->userStatus($newStatus)]);
            }
        }, 3);

        return back()->with('success', 'Status anggota dan akses akun berhasil diperbarui.');
    }

    /** @param array<string, mixed> $data */
    private function createMemberUser(array $data, string $memberStatus): User
    {
        $roleId = DB::table('roles')
            ->where('role_code', User::ROLE_MEMBER)
            ->value('id');

        if ($roleId === null) {
            throw new RuntimeException('Role MEMBER belum tersedia.');
        }

        $user = User::query()->create([
            'full_name' => $data['member_name'],
            'username' => $data['account_username'],
            'email' => $data['email'],
            'email_verified_at' => now(),
            'password_hash' => Hash::make($data['account_password']),
            'password_changed_at' => now(),
            'phone' => $data['phone'] ?? null,
            'status' => $this->userStatus($memberStatus),
        ]);

        DB::table('user_roles')->insert([
            'user_id' => $user->id,
            'role_id' => $roleId,
            'assigned_at' => now(),
        ]);

        return $user;
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function memberData(array $data): array
    {
        return Arr::only($data, [
            'member_code',
            'member_name',
            'member_type',
            'identity_number',
            'department',
            'phone',
            'email',
            'address',
            'join_date',
            'expiry_date',
            'status',
        ]);
    }

    private function userStatus(string $memberStatus): string
    {
        return $memberStatus === 'active' ? 'active' : 'inactive';
    }

    private function generateMemberCode(): string
    {
        $year = now()->format('Y');
        $prefix = "AGT-{$year}-";

        $lastCode = Member::query()
            ->where('member_code', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('member_code')
            ->value('member_code');

        $lastNumber = $lastCode !== null
            ? (int) substr((string) $lastCode, strlen($prefix))
            : 0;

        for ($number = $lastNumber + 1; $number <= $lastNumber + 100; $number++) {
            $candidate = $prefix.str_pad((string) $number, 5, '0', STR_PAD_LEFT);

            if (! Member::query()->where('member_code', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new RuntimeException('Kode anggota otomatis tidak dapat dibuat.');
    }

    private function normalizedStatus(string $status, mixed $expiryDate): string
    {
        if ($status === 'active' && $expiryDate !== null && now()->startOfDay()->greaterThan(\Illuminate\Support\Carbon::parse($expiryDate))) {
            return 'expired';
        }

        return $status;
    }

    private function synchronizeExpiredMembers(): void
    {
        $expiredUserIds = Member::query()
            ->where('status', 'active')
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', now()->toDateString())
            ->whereNotNull('user_id')
            ->pluck('user_id');

        Member::query()
            ->where('status', 'active')
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', now()->toDateString())
            ->update(['status' => 'expired']);

        if ($expiredUserIds->isNotEmpty()) {
            User::query()->whereIn('id', $expiredUserIds)->update(['status' => 'inactive']);
        }
    }

    private function synchronizeMemberExpiry(Member $member): void
    {
        if (
            $member->status === 'active'
            && $member->expiry_date !== null
            && $member->expiry_date->isBefore(now()->startOfDay())
        ) {
            DB::transaction(function () use ($member): void {
                $member->update(['status' => 'expired']);

                if ($member->user_id !== null) {
                    User::query()->whereKey($member->user_id)->update(['status' => 'inactive']);
                }
            }, 3);

            $member->refresh();
        }
    }
}
