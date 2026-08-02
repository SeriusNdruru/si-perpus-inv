<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StudentLoginRequest;
use App\Http\Middleware\EnsurePasswordSessionIsCurrent;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class StudentLoginController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user() !== null) {
            return $request->user()->hasRole(User::ROLE_MEMBER)
                ? redirect()->route('dashboard.member')
                : redirect()->route('dashboard');
        }

        return view('auth.student-login');
    }

    public function store(StudentLoginRequest $request): RedirectResponse
    {
        if ($request->user() !== null) {
            return $request->user()->hasRole(User::ROLE_MEMBER)
                ? redirect()->route('dashboard.member')
                : redirect()->route('dashboard');
        }

        $user = $request->authenticate();
        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        EnsurePasswordSessionIsCurrent::remember($request, $user);

        $this->writeAuthenticationLog(
            request: $request,
            user: $user,
            action: 'student_login',
            data: [
                'status' => 'success',
                'email' => $user->email,
                'portal' => 'student',
            ],
        );

        return redirect()->intended(route('dashboard.member'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user !== null) {
            $this->writeAuthenticationLog(
                request: $request,
                user: $user,
                action: 'student_logout',
                data: [
                    'status' => 'success',
                    'portal' => 'student',
                ],
            );
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('student.login')
            ->with('status', 'Anda berhasil keluar dari dashboard siswa.');
    }

    /** @param array<string, mixed> $data */
    private function writeAuthenticationLog(
        Request $request,
        User $user,
        string $action,
        array $data,
    ): void {
        try {
            DB::table('audit_logs')->insert([
                'user_id' => $user->id,
                'action' => $action,
                'module_name' => 'student_authentication',
                'table_name' => 'users',
                'record_id' => $user->id,
                'old_data' => null,
                'new_data' => json_encode($data, JSON_UNESCAPED_UNICODE),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
