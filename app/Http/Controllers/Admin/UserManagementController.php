<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResetSystemUserPasswordRequest;
use App\Http\Requests\Admin\StoreSystemUserRequest;
use App\Http\Requests\Admin\UpdateSystemUserRequest;
use App\Http\Requests\Admin\UpdateSystemUserStatusRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use RuntimeException;

class UserManagementController extends Controller
{
    /** @var array<int, string> */
    private const MANAGED_ROLES = [
        User::ROLE_SUPER_ADMIN,
        User::ROLE_INVENTORY_ADMIN,
        User::ROLE_LIBRARY_ADMIN,
        User::ROLE_MANAGER,
    ];

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $role = strtoupper(trim((string) $request->query('role')));
        $status = trim((string) $request->query('status'));

        $users = User::query()
            ->with('roles:id,role_code,role_name')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('full_name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when(in_array($role, self::MANAGED_ROLES, true), function ($query) use ($role): void {
                $query->whereHas('roles', fn ($roleQuery) => $roleQuery->where('role_code', $role));
            })
            ->when(in_array($status, ['active', 'inactive', 'locked'], true), function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->whereHas('roles', fn ($query) => $query->whereIn('role_code', self::MANAGED_ROLES))
            ->orderBy('full_name')
            ->paginate(10)
            ->withQueryString();

        $summary = [
            'total' => User::query()
                ->whereHas('roles', fn ($query) => $query->whereIn('role_code', self::MANAGED_ROLES))
                ->count(),
            'active' => User::query()
                ->where('status', 'active')
                ->whereHas('roles', fn ($query) => $query->whereIn('role_code', self::MANAGED_ROLES))
                ->count(),
            'inventory_admins' => User::query()
                ->whereHas('roles', fn ($query) => $query->where('role_code', User::ROLE_INVENTORY_ADMIN))
                ->count(),
            'library_admins' => User::query()
                ->whereHas('roles', fn ($query) => $query->where('role_code', User::ROLE_LIBRARY_ADMIN))
                ->count(),
        ];

        return view('admin.users.index', [
            'users' => $users,
            'summary' => $summary,
            'roleOptions' => $this->roleOptions(),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'roleOptions' => $this->roleOptions(),
        ]);
    }

    public function store(StoreSystemUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $roleCode = $validated['role_code'];
        unset($validated['role_code'], $validated['password_confirmation']);

        DB::transaction(function () use ($validated, $roleCode, $request): void {
            $role = $this->findRole($roleCode);

            $user = User::query()->create([
                'full_name' => $validated['full_name'],
                'username' => $validated['username'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'status' => $validated['status'],
                'password_hash' => Hash::make($validated['password']),
                'password_changed_at' => now(),
            ]);

            $user->roles()->sync([$role->id]);

            $this->writeAuditLog(
                request: $request,
                action: 'insert',
                recordId: $user->id,
                oldData: null,
                newData: [
                    'full_name' => $user->full_name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'status' => $user->status,
                    'role_code' => $roleCode,
                ],
            );
        });

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Pengguna sistem berhasil ditambahkan.');
    }

    public function edit(Request $request, User $user): View
    {
        $this->ensureManagedUser($user);
        $user->load('roles:id,role_code,role_name');

        return view('admin.users.edit', [
            'managedUser' => $user,
            'roleOptions' => $this->roleOptions(),
            'selectedRoleCode' => $this->primaryManagedRoleCode($user),
            'isOwnAccount' => $request->user()->is($user),
        ]);
    }

    public function update(UpdateSystemUserRequest $request, User $user): RedirectResponse
    {
        $this->ensureManagedUser($user);

        $validated = $request->validated();
        $newRoleCode = $validated['role_code'];
        unset($validated['role_code']);

        $isOwnAccount = $request->user()->is($user);
        $oldRoleCodes = $user->roleCodes()->all();

        if ($isOwnAccount && ($newRoleCode !== User::ROLE_SUPER_ADMIN || $validated['status'] !== 'active')) {
            return back()
                ->withInput()
                ->withErrors([
                    'role_code' => 'Super Admin tidak dapat mengubah peran atau menonaktifkan akun yang sedang digunakan.',
                ]);
        }

        if ($this->wouldRemoveLastActiveSuperAdmin($user, $newRoleCode, $validated['status'])) {
            return back()
                ->withInput()
                ->withErrors([
                    'role_code' => 'Sistem harus memiliki minimal satu Super Admin aktif.',
                ]);
        }

        DB::transaction(function () use ($request, $user, $validated, $newRoleCode, $oldRoleCodes): void {
            $role = $this->findRole($newRoleCode);
            $oldData = [
                'full_name' => $user->full_name,
                'username' => $user->username,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status,
                'role_codes' => $oldRoleCodes,
            ];

            $user->update($validated);
            $user->roles()->sync([$role->id]);

            $this->writeAuditLog(
                request: $request,
                action: 'update',
                recordId: $user->id,
                oldData: $oldData,
                newData: [
                    'full_name' => $user->full_name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'status' => $user->status,
                    'role_code' => $newRoleCode,
                ],
            );
        });

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Data pengguna berhasil diperbarui.');
    }

    public function updateStatus(UpdateSystemUserStatusRequest $request, User $user): RedirectResponse
    {
        $this->ensureManagedUser($user);
        $newStatus = $request->validated('status');
        $currentRoleCode = $this->primaryManagedRoleCode($user);

        if ($request->user()->is($user)) {
            return back()->with('error', 'Status akun yang sedang digunakan tidak dapat diubah.');
        }

        if ($this->wouldRemoveLastActiveSuperAdmin($user, $currentRoleCode, $newStatus)) {
            return back()->with('error', 'Sistem harus memiliki minimal satu Super Admin aktif.');
        }

        $oldStatus = $user->status;
        $user->update(['status' => $newStatus]);

        $this->writeAuditLog(
            request: $request,
            action: 'update',
            recordId: $user->id,
            oldData: ['status' => $oldStatus],
            newData: ['status' => $newStatus],
        );

        $statusLabel = match ($newStatus) {
            'active' => 'diaktifkan',
            'inactive' => 'dinonaktifkan',
            default => 'dikunci',
        };

        return back()->with('success', "Akun {$user->username} berhasil {$statusLabel}.");
    }

    public function editPassword(User $user): View
    {
        $this->ensureManagedUser($user);

        return view('admin.users.password', [
            'managedUser' => $user,
        ]);
    }

    public function updatePassword(ResetSystemUserPasswordRequest $request, User $user): RedirectResponse
    {
        $this->ensureManagedUser($user);

        $user->update([
            'password_hash' => Hash::make($request->validated('password')),
            'password_changed_at' => now(),
        ]);

        $this->writeAuditLog(
            request: $request,
            action: 'update',
            recordId: $user->id,
            oldData: ['password' => 'hidden'],
            newData: ['password' => 'reset_by_super_admin'],
        );

        return redirect()
            ->route('admin.users.index')
            ->with('success', "Password akun {$user->username} berhasil diperbarui.");
    }

    /** @return \Illuminate\Support\Collection<int, Role> */
    private function roleOptions()
    {
        $order = array_flip(self::MANAGED_ROLES);

        return Role::query()
            ->whereIn('role_code', self::MANAGED_ROLES)
            ->get(['id', 'role_code', 'role_name', 'description'])
            ->sortBy(fn (Role $role): int => $order[$role->role_code] ?? 999)
            ->values();
    }

    private function findRole(string $roleCode): Role
    {
        $role = Role::query()->where('role_code', $roleCode)->first();

        if (! $role) {
            throw new RuntimeException("Role {$roleCode} tidak ditemukan pada database.");
        }

        return $role;
    }

    private function ensureManagedUser(User $user): void
    {
        if (! $user->roles()->whereIn('role_code', self::MANAGED_ROLES)->exists()) {
            abort(404);
        }
    }

    private function primaryManagedRoleCode(User $user): string
    {
        $roleCode = $user->roles()
            ->whereIn('role_code', self::MANAGED_ROLES)
            ->orderByRaw("CASE role_code
                WHEN 'SUPER_ADMIN' THEN 1
                WHEN 'INVENTORY_ADMIN' THEN 2
                WHEN 'LIBRARY_ADMIN' THEN 3
                WHEN 'MANAGER' THEN 4
                ELSE 99 END")
            ->value('role_code');

        if (! $roleCode) {
            throw new RuntimeException('Peran pengguna tidak ditemukan.');
        }

        return $roleCode;
    }

    private function wouldRemoveLastActiveSuperAdmin(User $user, string $newRoleCode, string $newStatus): bool
    {
        $isCurrentSuperAdmin = $user->hasRole(User::ROLE_SUPER_ADMIN);

        if (! $isCurrentSuperAdmin) {
            return false;
        }

        if ($newRoleCode === User::ROLE_SUPER_ADMIN && $newStatus === 'active') {
            return false;
        }

        $otherActiveSuperAdmins = User::query()
            ->where('id', '<>', $user->id)
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->where('role_code', User::ROLE_SUPER_ADMIN))
            ->count();

        return $otherActiveSuperAdmins === 0;
    }

    /**
     * @param array<string, mixed>|null $oldData
     * @param array<string, mixed>|null $newData
     */
    private function writeAuditLog(
        Request $request,
        string $action,
        int $recordId,
        ?array $oldData,
        ?array $newData,
    ): void {
        DB::table('audit_logs')->insert([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'module_name' => 'user_management',
            'table_name' => 'users',
            'record_id' => $recordId,
            'old_data' => $oldData !== null ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : null,
            'new_data' => $newData !== null ? json_encode($newData, JSON_UNESCAPED_UNICODE) : null,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }
}
