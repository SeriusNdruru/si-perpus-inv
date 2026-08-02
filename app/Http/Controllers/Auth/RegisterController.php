<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterMemberRequest;
use App\Models\Member;
use App\Models\User;
use App\Services\StudentEmailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class RegisterController extends Controller
{
    public function __construct(
        private readonly StudentEmailService $emails,
    ) {
    }

    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user() !== null) {
            return $request->user()->hasRole(User::ROLE_MEMBER)
                ? redirect()->route('dashboard.member')
                : redirect()->route('dashboard');
        }

        return view('auth.register');
    }

    public function store(RegisterMemberRequest $request): RedirectResponse
    {
        $data = $request->validated();

        try {
            $user = DB::transaction(function () use ($data, $request): User {
                $roleId = DB::table('roles')
                    ->where('role_code', User::ROLE_MEMBER)
                    ->value('id');

                if ($roleId === null) {
                    throw new RuntimeException('Role MEMBER belum tersedia.');
                }

                $user = User::query()->create([
                    'full_name' => $data['full_name'],
                    'username' => $data['username'],
                    'email' => $data['email'],
                    'password_hash' => Hash::make($data['password']),
                    'password_changed_at' => now(),
                    'phone' => $data['phone'] ?? null,
                    'email_verified_at' => null,
                    'status' => 'inactive',
                ]);

                DB::table('user_roles')->insert([
                    'user_id' => $user->id,
                    'role_id' => $roleId,
                    'assigned_at' => now(),
                ]);

                Member::query()->create([
                    'member_code' => $this->generateMemberCode(),
                    'user_id' => $user->id,
                    'member_name' => $data['full_name'],
                    'member_type' => 'student',
                    'identity_number' => $data['identity_number'],
                    'department' => $data['department'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'email' => $data['email'],
                    'address' => $data['address'] ?? null,
                    'join_date' => today(),
                    'expiry_date' => today()->addYear(),
                    'status' => 'inactive',
                    'created_by' => null,
                ]);

                DB::table('audit_logs')->insert([
                    'user_id' => $user->id,
                    'action' => 'insert',
                    'module_name' => 'member_self_registration',
                    'table_name' => 'users',
                    'record_id' => $user->id,
                    'old_data' => null,
                    'new_data' => json_encode([
                        'username' => $user->username,
                        'email' => $user->email,
                        'role' => User::ROLE_MEMBER,
                    ], JSON_UNESCAPED_UNICODE),
                    'ip_address' => $request->ip(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
                    'created_at' => now(),
                ]);

                return $user;
            }, 3);
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->withErrors([
                    'registration' => 'Pendaftaran belum dapat diselesaikan. Periksa data lalu coba lagi.',
                ]);
        }

        $request->session()->put('verification_email', $user->email);
        $sent = $this->emails->sendVerification($user);

        return redirect()
            ->route('student.verification.notice')
            ->with(
                $sent ? 'status' : 'error',
                $sent
                    ? 'Pendaftaran berhasil. Tautan verifikasi sudah dikirim ke email siswa.'
                    : 'Pendaftaran berhasil, tetapi email verifikasi belum dapat dikirim. Gunakan tombol kirim ulang setelah SMTP diperiksa.'
            );
    }

    private function generateMemberCode(): string
    {
        $prefix = 'AGT-'.now()->format('Ym').'-';
        $last = (string) Member::query()
            ->where('member_code', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('member_code')
            ->value('member_code');

        $number = $last !== '' ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}
