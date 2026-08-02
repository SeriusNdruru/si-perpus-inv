<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PasswordResetService
{
    public const TARGET_STUDENT = 'student';

    public const TARGET_INTERNAL = 'internal';

    private const INTERNAL_ROLES = [
        User::ROLE_SUPER_ADMIN,
        User::ROLE_INVENTORY_ADMIN,
        User::ROLE_LIBRARY_ADMIN,
        User::ROLE_LIBRARY_OFFICER_LEGACY,
        User::ROLE_MANAGER,
    ];

    public function __construct(
        private readonly StudentEmailService $emails,
    ) {
    }

    public function sendResetLink(
        string $email,
        string $target,
        Request $request,
    ): void {
        $user = $this->eligibleUser($email, $target);

        if ($user === null) {
            return;
        }

        try {
            $token = Password::broker()->createToken($user);
            $routeName = $target === self::TARGET_STUDENT
                ? 'student.password.reset'
                : 'admin.password.reset';

            $resetUrl = route($routeName, [
                'token' => $token,
                'email' => $user->email,
            ]);

            $accountLabel = $target === self::TARGET_STUDENT
                ? 'akun siswa'
                : 'akun pengguna internal';

            $this->emails->sendPasswordReset(
                user: $user,
                resetUrl: $resetUrl,
                accountLabel: $accountLabel,
                mailType: $target === self::TARGET_STUDENT
                    ? 'password_reset_student'
                    : 'password_reset_internal',
            );

            $this->writeAuditLog(
                request: $request,
                user: $user,
                action: 'password_reset_requested',
                target: $target,
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function resetPassword(
        string $email,
        string $token,
        string $password,
        string $target,
        Request $request,
    ): bool {
        $user = $this->eligibleUser($email, $target);

        if (
            $user === null
            || ! Password::broker()->tokenExists($user, $token)
        ) {
            return false;
        }

        try {
            DB::transaction(function () use ($user, $password, $request, $target): void {
                $user->forceFill([
                    'password_hash' => Hash::make($password),
                    'password_changed_at' => now(),
                ])->save();

                Password::broker()->deleteToken($user);
                $this->deleteDatabaseSessions($user);

                $this->writeAuditLog(
                    request: $request,
                    user: $user,
                    action: 'password_reset_completed',
                    target: $target,
                );
            }, 3);

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    private function eligibleUser(string $email, string $target): ?User
    {
        $user = User::query()
            ->where('email', strtolower(trim($email)))
            ->with('roles')
            ->first();

        if ($user === null || $user->status !== 'active') {
            return null;
        }

        if ($target === self::TARGET_STUDENT) {
            return $user->hasRole(User::ROLE_MEMBER)
                && $user->hasVerifiedEmail()
                ? $user
                : null;
        }

        return $user->hasAnyRole(self::INTERNAL_ROLES)
            ? $user
            : null;
    }

    private function deleteDatabaseSessions(User $user): void
    {
        try {
            $table = (string) config('session.table', 'sessions');

            if (
                Schema::hasTable($table)
                && Schema::hasColumn($table, 'user_id')
            ) {
                DB::table($table)
                    ->where('user_id', $user->id)
                    ->delete();
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function writeAuditLog(
        Request $request,
        User $user,
        string $action,
        string $target,
    ): void {
        try {
            DB::table('audit_logs')->insert([
                'user_id' => $user->id,
                'action' => $action,
                'module_name' => 'password_security',
                'table_name' => 'users',
                'record_id' => $user->id,
                'old_data' => null,
                'new_data' => json_encode([
                    'target' => $target,
                    'email' => $user->email,
                    'password' => 'hidden',
                ], JSON_UNESCAPED_UNICODE),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
