<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Http\Middleware\EnsurePasswordSessionIsCurrent;
use App\Services\StudentEmailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Throwable;

class StudentEmailVerificationController extends Controller
{
    public function __construct(
        private readonly StudentEmailService $emails,
    ) {
    }

    public function notice(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user !== null && $user->hasRole(User::ROLE_MEMBER) && $user->hasVerifiedEmail()) {
            return redirect()->route('dashboard.member');
        }

        return view('auth.verify-email', [
            'verificationEmail' => session('verification_email', old('email')),
        ]);
    }

    public function verify(Request $request, User $user, string $hash): RedirectResponse
    {
        if (! hash_equals(sha1(strtolower((string) $user->email)), $hash)) {
            abort(403, 'Tautan verifikasi email tidak valid.');
        }

        $user->loadMissing(['roles', 'member']);

        if (! $user->hasRole(User::ROLE_MEMBER)) {
            abort(403, 'Tautan ini bukan untuk akun siswa.');
        }

        DB::transaction(function () use ($user, $request): void {
            if (! $user->hasVerifiedEmail()) {
                $user->forceFill([
                    'email_verified_at' => now(),
                    'status' => 'active',
                ])->save();
            } elseif ($user->status !== 'active') {
                $user->forceFill(['status' => 'active'])->save();
            }

            if ($user->member !== null) {
                $user->member->update([
                    'email' => $user->email,
                    'status' => 'active',
                ]);
            }

            try {
                DB::table('audit_logs')->insert([
                    'user_id' => $user->id,
                    'action' => 'update',
                    'module_name' => 'student_email_verification',
                    'table_name' => 'users',
                    'record_id' => $user->id,
                    'old_data' => null,
                    'new_data' => json_encode([
                        'email' => $user->email,
                        'email_verified_at' => now()->toDateTimeString(),
                        'status' => 'active',
                    ], JSON_UNESCAPED_UNICODE),
                    'ip_address' => $request->ip(),
                    'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                    'created_at' => now(),
                ]);
            } catch (Throwable $exception) {
                report($exception);
            }
        }, 3);

        $verifiedUser = $user->fresh(['roles', 'member']);
        Auth::login($verifiedUser);
        $request->session()->regenerate();
        EnsurePasswordSessionIsCurrent::remember($request, $verifiedUser);
        $request->session()->forget('verification_email');

        return redirect()
            ->route('dashboard.member')
            ->with('success', 'Email berhasil diverifikasi. Akun siswa sudah aktif.');
    }

    public function resend(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:150'],
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
        ]);

        $email = strtolower(trim((string) $validated['email']));
        $user = User::query()
            ->where('email', $email)
            ->with(['roles', 'member'])
            ->first();

        if (
            $user === null
            || ! $user->hasRole(User::ROLE_MEMBER)
            || $user->hasVerifiedEmail()
        ) {
            return back()
                ->withInput(['email' => $email])
                ->with('status', 'Apabila akun masih menunggu verifikasi, tautan baru akan dikirim ke email tersebut.');
        }

        $sent = $this->emails->sendVerification($user);

        return back()
            ->withInput(['email' => $email])
            ->with(
                $sent ? 'status' : 'error',
                $sent
                    ? 'Tautan verifikasi baru sudah dikirim. Periksa kotak masuk dan folder spam.'
                    : 'Email belum dapat dikirim. Periksa konfigurasi SMTP atau coba beberapa saat lagi.'
            );
    }
}
