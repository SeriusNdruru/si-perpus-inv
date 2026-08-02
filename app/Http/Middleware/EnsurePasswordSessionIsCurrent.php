<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordSessionIsCurrent
{
    public const SESSION_KEY = 'auth.password_fingerprint';

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $storedFingerprint = $request->session()->get(self::SESSION_KEY);
        $currentFingerprint = self::fingerprint($user);

        if (
            ! is_string($storedFingerprint)
            || ! hash_equals($currentFingerprint, $storedFingerprint)
        ) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $this->redirectAfterInvalidation($user);
        }

        return $next($request);
    }

    public static function remember(Request $request, User $user): void
    {
        $request->session()->put(
            self::SESSION_KEY,
            self::fingerprint($user)
        );
    }

    public static function fingerprint(User $user): string
    {
        $changedAt = $user->password_changed_at?->format('Y-m-d H:i:s.u') ?? '';

        return hash(
            'sha256',
            $user->getAuthPassword().'|'.$changedAt
        );
    }

    private function redirectAfterInvalidation(User $user): RedirectResponse
    {
        $route = $user->hasRole(User::ROLE_MEMBER)
            ? 'student.login'
            : 'login';

        return redirect()
            ->route($route)
            ->withErrors([
                'session' => 'Sesi berakhir karena keamanan akun berubah. Silakan login kembali.',
            ]);
    }
}
