<?php

namespace App\Services;

use App\Support\AccessMatrix;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AcceptanceTestService
{
    private const CATEGORY_LABELS = [
        'public_access' => 'Akses portal publik',
        'role_access' => 'Pemisahan akses setiap role',
        'security' => 'Keamanan autentikasi dan route',
        'accounts' => 'Akun dan identitas siswa',
        'workflows' => 'Alur transaksi utama',
        'notifications' => 'Notifikasi dan email',
    ];

    public function run(): array
    {
        $checks = [];

        $this->checkPublicRoutes($checks);
        $this->checkRoleRoutes($checks);
        $this->checkSecurityRoutes($checks);
        $this->checkLoginSeparation($checks);
        $this->checkAccountIntegrity($checks);
        $this->checkWorkflowIntegrity($checks);
        $this->checkNotifications($checks);

        $counts = [
            'pass' => collect($checks)->where('status', 'pass')->count(),
            'warning' => collect($checks)->where('status', 'warning')->count(),
            'fail' => collect($checks)->where('status', 'fail')->count(),
        ];

        $total = max(count($checks), 1);
        $score = (int) round(
            (($counts['pass'] * 100) + ($counts['warning'] * 55)) / $total
        );

        $status = match (true) {
            $counts['fail'] > 0 => 'failed',
            $counts['warning'] > 0 => 'passed_with_notes',
            default => 'passed',
        };

        return [
            'generated_at' => now(),
            'checks' => $checks,
            'grouped_checks' => collect($checks)
                ->groupBy('category')
                ->map(fn ($group, string $category): array => [
                    'label' => self::CATEGORY_LABELS[$category] ?? str($category)->headline()->toString(),
                    'checks' => $group->values()->all(),
                ])
                ->all(),
            'counts' => $counts,
            'total' => count($checks),
            'score' => $score,
            'status' => $status,
            'status_label' => match ($status) {
                'passed' => 'Seluruh pemeriksaan utama lulus',
                'passed_with_notes' => 'Lulus dengan catatan',
                default => 'Masih ada pemeriksaan yang gagal',
            },
            'manual_checklist' => $this->manualChecklist(),
        ];
    }

    public function toText(array $report): string
    {
        $lines = [
            'LAPORAN UJI AKSES DAN ALUR SISTEM',
            '==================================',
            'Dibuat: '.$report['generated_at']->format('d/m/Y H:i:s'),
            'Status: '.$report['status_label'],
            'Skor: '.$report['score'].'/100',
            sprintf(
                'Ringkasan: %d lulus, %d peringatan, %d gagal',
                $report['counts']['pass'],
                $report['counts']['warning'],
                $report['counts']['fail'],
            ),
            '',
        ];

        foreach ($report['grouped_checks'] as $category) {
            $lines[] = strtoupper($category['label']);
            $lines[] = str_repeat('-', mb_strlen($category['label']));

            foreach ($category['checks'] as $check) {
                $status = match ($check['status']) {
                    'pass' => 'LULUS',
                    'warning' => 'PERINGATAN',
                    default => 'GAGAL',
                };

                $lines[] = "[{$status}] {$check['title']}";
                $lines[] = 'Hasil: '.$check['message'];

                if ($check['recommendation']) {
                    $lines[] = 'Tindakan: '.$check['recommendation'];
                }

                $lines[] = '';
            }
        }

        $lines[] = 'CHECKLIST UJI MANUAL';
        $lines[] = '--------------------';

        foreach ($report['manual_checklist'] as $section) {
            $lines[] = '';
            $lines[] = $section['title'];

            foreach ($section['items'] as $index => $item) {
                $lines[] = '[ ] '.($index + 1).'. '.$item;
            }
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    private function checkPublicRoutes(array &$checks): void
    {
        $missing = [];
        $protected = [];

        foreach (AccessMatrix::PUBLIC_ROUTES as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);

            if (! $route instanceof IlluminateRoute) {
                $missing[] = $routeName;
                continue;
            }

            $middleware = $route->gatherMiddleware();

            if ($this->containsProtectedMiddleware($middleware)) {
                $protected[] = $routeName;
            }
        }

        $this->add(
            $checks,
            'public_access',
            'public_routes_available',
            'Route portal publik tersedia',
            $missing === [] ? 'pass' : 'fail',
            $missing === []
                ? count(AccessMatrix::PUBLIC_ROUTES).' route publik utama tersedia.'
                : 'Route belum tersedia: '.implode(', ', $missing).'.',
            $missing === [] ? null : 'Periksa routes/web.php dan routes/access_portal.php.',
        );

        $this->add(
            $checks,
            'public_access',
            'public_routes_without_auth',
            'Portal publik tidak meminta login',
            $protected === [] ? 'pass' : 'fail',
            $protected === []
                ? 'Portal perpustakaan, inventaris umum, audit, dan form publik dapat dibuka tanpa autentikasi.'
                : 'Route publik masih memakai middleware autentikasi: '.implode(', ', $protected).'.',
            $protected === [] ? null : 'Hapus middleware auth, password.session, atau role dari route publik.',
        );
    }

    private function checkRoleRoutes(array &$checks): void
    {
        $missing = [];
        $withoutAuth = [];
        $withoutPasswordSession = [];
        $roleMismatch = [];

        foreach (AccessMatrix::PROTECTED_ROUTE_ROLES as $routeName => $expectedRoles) {
            $route = Route::getRoutes()->getByName($routeName);

            if (! $route instanceof IlluminateRoute) {
                $missing[] = $routeName;
                continue;
            }

            $middleware = $route->gatherMiddleware();

            if (! in_array('auth', $middleware, true)) {
                $withoutAuth[] = $routeName;
            }

            if (! in_array('password.session', $middleware, true)) {
                $withoutPasswordSession[] = $routeName;
            }

            $actualRoles = $this->rolesFromMiddleware($middleware);
            $expected = collect($expectedRoles)->map('strtoupper')->sort()->values()->all();
            $actual = collect($actualRoles)->map('strtoupper')->sort()->values()->all();

            if ($actual !== $expected) {
                $roleMismatch[] = $routeName
                    .' [harus: '.implode('|', $expected)
                    .'; ditemukan: '.($actual === [] ? '-' : implode('|', $actual)).']';
            }
        }

        $this->add(
            $checks,
            'role_access',
            'protected_routes_available',
            'Route dashboard dan modul role tersedia',
            $missing === [] ? 'pass' : 'fail',
            $missing === []
                ? count(AccessMatrix::PROTECTED_ROUTE_ROLES).' route role penting tersedia.'
                : 'Route belum tersedia: '.implode(', ', $missing).'.',
            $missing === [] ? null : 'Periksa nama route dan kelompok middleware.',
        );

        $this->add(
            $checks,
            'role_access',
            'protected_routes_auth',
            'Route internal wajib login',
            $withoutAuth === [] ? 'pass' : 'fail',
            $withoutAuth === []
                ? 'Semua route internal memakai middleware auth.'
                : 'Route tanpa auth: '.implode(', ', $withoutAuth).'.',
            $withoutAuth === [] ? null : 'Masukkan route ke kelompok middleware auth.',
        );

        $this->add(
            $checks,
            'role_access',
            'protected_routes_password_session',
            'Sesi lama diperiksa setelah password berubah',
            $withoutPasswordSession === [] ? 'pass' : 'fail',
            $withoutPasswordSession === []
                ? 'Semua route internal memakai password.session.'
                : 'Route tanpa password.session: '.implode(', ', $withoutPasswordSession).'.',
            $withoutPasswordSession === [] ? null : 'Masukkan route ke kelompok middleware password.session.',
        );

        $this->add(
            $checks,
            'role_access',
            'role_matrix',
            'Matriks akses role sesuai',
            $roleMismatch === [] ? 'pass' : 'fail',
            $roleMismatch === []
                ? 'Super Admin, Admin Inventaris, Admin Perpustakaan, Pimpinan, dan Siswa memiliki batas akses yang sesuai.'
                : implode('; ', $roleMismatch),
            $roleMismatch === [] ? null : 'Perbaiki parameter middleware role pada route yang disebutkan.',
        );
    }

    private function checkSecurityRoutes(array &$checks): void
    {
        $problems = [];

        foreach (AccessMatrix::SECURITY_ROUTES as $routeName => $requirements) {
            $route = Route::getRoutes()->getByName($routeName);

            if (! $route instanceof IlluminateRoute) {
                $problems[] = $routeName.' tidak ditemukan';
                continue;
            }

            $methods = array_values(array_diff($route->methods(), ['HEAD']));
            $expectedMethods = $requirements['methods'];

            if (array_diff($expectedMethods, $methods) !== []) {
                $problems[] = $routeName.' method harus '.implode('|', $expectedMethods);
            }

            $middleware = $route->gatherMiddleware();

            foreach ($requirements['middleware'] as $requiredMiddleware) {
                if (! in_array($requiredMiddleware, $middleware, true)) {
                    $problems[] = $routeName.' tidak memakai '.$requiredMiddleware;
                }
            }
        }

        $this->add(
            $checks,
            'security',
            'security_routes',
            'Pembatasan route sensitif',
            $problems === [] ? 'pass' : 'fail',
            $problems === []
                ? 'Login, akses akun siswa, verifikasi email, reset password, kontak, dan laporan kerusakan memakai method serta pembatasan yang sesuai.'
                : implode('; ', $problems).'.',
            $problems === [] ? null : 'Periksa method, signed URL, dan middleware throttle pada route sensitif.',
        );
    }

    private function checkLoginSeparation(array &$checks): void
    {
        $adminLogin = Route::getRoutes()->getByName('login');
        $studentLogin = Route::getRoutes()->getByName('student.login');

        $adminAction = $adminLogin instanceof IlluminateRoute
            ? $adminLogin->getActionName()
            : '';
        $studentAction = $studentLogin instanceof IlluminateRoute
            ? $studentLogin->getActionName()
            : '';

        $separated = str_contains($adminAction, 'LoginController@create')
            && str_contains($studentAction, 'StudentLoginController@create')
            && $adminAction !== $studentAction;

        $this->add(
            $checks,
            'security',
            'separate_login_controllers',
            'Login siswa dan pengguna internal terpisah',
            $separated ? 'pass' : 'fail',
            $separated
                ? 'Form admin dan siswa memakai controller serta URL yang berbeda.'
                : 'Controller login admin dan siswa belum terpisah dengan benar.',
            $separated ? null : 'Pastikan /admin/login dan /siswa/login memakai controller berbeda.',
        );
    }

    private function checkAccountIntegrity(array &$checks): void
    {
        $requiredTables = ['users', 'roles', 'user_roles', 'members'];

        if (! $this->tablesAvailable($requiredTables)) {
            $this->add(
                $checks,
                'accounts',
                'account_tables',
                'Struktur akun tersedia',
                'fail',
                'Satu atau beberapa tabel akun belum tersedia.',
                'Pastikan patch SQL sampai Tahap 33 sudah diimpor.',
            );

            return;
        }

        try {
            $usersWithoutRoles = DB::table('users')
                ->leftJoin('user_roles', 'user_roles.user_id', '=', 'users.id')
                ->whereNull('user_roles.user_id')
                ->count();

            $this->addCountCheck(
                $checks,
                'accounts',
                'users_without_roles',
                'Pengguna memiliki role',
                $usersWithoutRoles,
                'Semua pengguna memiliki role.',
                'pengguna belum memiliki role',
                'Tetapkan satu role yang sesuai melalui menu Pengguna Sistem.',
            );

            $mixedMemberInternal = DB::table('users')
                ->whereExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('user_roles as member_user_roles')
                        ->join(
                            'roles as member_roles',
                            'member_roles.id',
                            '=',
                            'member_user_roles.role_id'
                        )
                        ->whereColumn(
                            'member_user_roles.user_id',
                            'users.id'
                        )
                        ->where('member_roles.role_code', 'MEMBER');
                })
                ->whereExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('user_roles as internal_user_roles')
                        ->join(
                            'roles as internal_roles',
                            'internal_roles.id',
                            '=',
                            'internal_user_roles.role_id'
                        )
                        ->whereColumn(
                            'internal_user_roles.user_id',
                            'users.id'
                        )
                        ->whereIn(
                            'internal_roles.role_code',
                            AccessMatrix::INTERNAL_ROLES
                        );
                })
                ->count();

            $this->addCountCheck(
                $checks,
                'accounts',
                'mixed_member_internal_roles',
                'Akun siswa tidak bercampur dengan role internal',
                $mixedMemberInternal,
                'Tidak ada akun yang memiliki role siswa sekaligus role internal.',
                'akun memiliki role siswa dan internal sekaligus',
                'Pisahkan akun siswa dari akun admin, guru, kepala sekolah, atau staf.',
            );

            $memberUsersWithoutProfile = DB::table('users')
                ->join('user_roles', 'user_roles.user_id', '=', 'users.id')
                ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                ->leftJoin('members', 'members.user_id', '=', 'users.id')
                ->where('roles.role_code', 'MEMBER')
                ->whereNull('members.id')
                ->count();

            $this->addCountCheck(
                $checks,
                'accounts',
                'member_users_without_profile',
                'Akun siswa terhubung dengan profil anggota',
                $memberUsersWithoutProfile,
                'Semua akun siswa terhubung dengan data anggota.',
                'akun siswa belum memiliki profil anggota',
                'Hubungkan users.id dengan members.user_id.',
            );

            $nameMismatch = DB::table('members')
                ->join('users', 'users.id', '=', 'members.user_id')
                ->whereRaw('TRIM(members.member_name) <> TRIM(users.full_name)')
                ->count();

            $this->addCountCheck(
                $checks,
                'accounts',
                'student_name_sync',
                'Nama akun dan profil siswa sinkron',
                $nameMismatch,
                'Nama akun dan profil anggota siswa sudah sama.',
                'profil siswa memiliki nama yang berbeda dengan akun',
                'Simpan ulang data anggota atau buka dashboard siswa agar sinkronisasi otomatis berjalan.',
                'warning',
            );

            $invalidClasses = DB::table('members')
                ->where('member_type', 'student')
                ->where(function ($query): void {
                    $query->whereNull('department')
                        ->orWhereNotIn('department', AccessMatrix::ELEMENTARY_CLASSES);
                })
                ->count();

            $this->addCountCheck(
                $checks,
                'accounts',
                'elementary_classes',
                'Kelas siswa hanya Kelas 1 sampai Kelas 6',
                $invalidClasses,
                'Seluruh data siswa menggunakan kelas SD yang valid.',
                'data siswa memakai kelas kosong atau di luar Kelas 1 sampai Kelas 6',
                'Perbarui kelas melalui menu Anggota.',
            );

            $unverifiedStudents = Schema::hasColumn('users', 'email_verified_at')
                ? DB::table('users')
                    ->join('user_roles', 'user_roles.user_id', '=', 'users.id')
                    ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                    ->where('roles.role_code', 'MEMBER')
                    ->where('users.status', 'active')
                    ->whereNull('users.email_verified_at')
                    ->count()
                : 0;

            $this->addCountCheck(
                $checks,
                'accounts',
                'verified_student_email',
                'Email siswa aktif telah diverifikasi',
                $unverifiedStudents,
                'Semua akun siswa aktif memiliki email terverifikasi.',
                'akun siswa aktif belum memverifikasi email',
                'Kirim ulang verifikasi atau nonaktifkan akun sampai verifikasi selesai.',
            );

            $internalWithoutEmail = DB::table('users')
                ->join('user_roles', 'user_roles.user_id', '=', 'users.id')
                ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                ->whereIn('roles.role_code', AccessMatrix::INTERNAL_ROLES)
                ->where('users.status', 'active')
                ->where(function ($query): void {
                    $query->whereNull('users.email')
                        ->orWhere('users.email', '');
                })
                ->distinct()
                ->count('users.id');

            $this->addCountCheck(
                $checks,
                'accounts',
                'internal_email_for_reset',
                'Email pemulihan pengguna internal',
                $internalWithoutEmail,
                'Semua pengguna internal aktif memiliki email untuk reset password.',
                'pengguna internal aktif belum memiliki email',
                'Lengkapi email melalui menu Pengguna Sistem.',
                'warning',
            );
        } catch (Throwable $exception) {
            report($exception);

            $this->add(
                $checks,
                'accounts',
                'account_integrity_query',
                'Pemeriksaan integritas akun',
                'fail',
                'Pemeriksaan akun tidak dapat dijalankan: '.$exception->getMessage(),
                'Periksa struktur tabel dan koneksi database.',
            );
        }
    }

    private function checkWorkflowIntegrity(array &$checks): void
    {
        if (! $this->tablesAvailable(['loan_requests', 'loan_request_items'])) {
            $this->add(
                $checks,
                'workflows',
                'loan_request_tables',
                'Struktur pengajuan online tersedia',
                'fail',
                'Tabel pengajuan online belum tersedia.',
                'Import patch SQL portal publik dan anggota.',
            );

            return;
        }

        try {
            $requestsWithoutItems = DB::table('loan_requests')
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('loan_request_items')
                        ->whereColumn(
                            'loan_request_items.loan_request_id',
                            'loan_requests.id'
                        );
                })
                ->count();

            $this->addCountCheck(
                $checks,
                'workflows',
                'requests_without_items',
                'Pengajuan online memiliki buku',
                $requestsWithoutItems,
                'Semua pengajuan online memiliki minimal satu buku.',
                'pengajuan online tidak memiliki item buku',
                'Periksa data loan_requests dan loan_request_items.',
            );

            $preparedWithoutAssets = DB::table('loan_requests')
                ->join(
                    'loan_request_items',
                    'loan_request_items.loan_request_id',
                    '=',
                    'loan_requests.id'
                )
                ->whereIn('loan_requests.status', ['approved', 'ready'])
                ->whereNull('loan_request_items.asset_id')
                ->distinct()
                ->count('loan_requests.id');

            $this->addCountCheck(
                $checks,
                'workflows',
                'prepared_requests_assets',
                'Pengajuan disetujui memiliki eksemplar',
                $preparedWithoutAssets,
                'Semua pengajuan disetujui atau siap diambil memiliki eksemplar.',
                'pengajuan disetujui atau siap belum memiliki eksemplar',
                'Batalkan atau proses ulang pengajuan terkait.',
            );

            $reservedAssetMismatch = $this->tablesAvailable(['assets'])
                ? DB::table('loan_requests')
                    ->join(
                        'loan_request_items',
                        'loan_request_items.loan_request_id',
                        '=',
                        'loan_requests.id'
                    )
                    ->join('assets', 'assets.id', '=', 'loan_request_items.asset_id')
                    ->whereIn('loan_requests.status', ['approved', 'ready'])
                    ->where('assets.asset_status', '!=', 'reserved')
                    ->count()
                : 0;

            $this->addCountCheck(
                $checks,
                'workflows',
                'reserved_assets_match',
                'Eksemplar pengajuan berstatus dipesan',
                $reservedAssetMismatch,
                'Eksemplar pada pengajuan disetujui atau siap berstatus dipesan.',
                'eksemplar pengajuan tidak berstatus dipesan',
                'Periksa perubahan status aset dan pengajuan.',
            );

            $readyWithoutExpiry = DB::table('loan_requests')
                ->where('status', 'ready')
                ->whereNull('pickup_expires_at')
                ->count();

            $this->addCountCheck(
                $checks,
                'workflows',
                'ready_request_expiry',
                'Pengajuan siap memiliki batas pengambilan',
                $readyWithoutExpiry,
                'Semua pengajuan siap diambil memiliki batas waktu pengambilan.',
                'pengajuan siap tidak memiliki batas pengambilan',
                'Tetapkan ulang status siap diambil.',
            );

            $collectedWithoutDate = DB::table('loan_requests')
                ->where('status', 'collected')
                ->whereNull('collected_at')
                ->count();

            $this->addCountCheck(
                $checks,
                'workflows',
                'collected_request_date',
                'Pengambilan memiliki tanggal konfirmasi',
                $collectedWithoutDate,
                'Semua pengajuan yang diambil memiliki tanggal konfirmasi.',
                'pengajuan berstatus diambil belum memiliki collected_at',
                'Perbaiki data transaksi pengajuan.',
            );

            if ($this->tablesAvailable(['loans', 'loan_items', 'assets'])) {
                $activeLoanMismatch = DB::table('loan_items')
                    ->join('loans', 'loans.id', '=', 'loan_items.loan_id')
                    ->join('assets', 'assets.id', '=', 'loan_items.asset_id')
                    ->where('loan_items.return_status', 'borrowed')
                    ->where('assets.asset_status', '!=', 'borrowed')
                    ->count();

                $this->addCountCheck(
                    $checks,
                    'workflows',
                    'active_loan_assets',
                    'Aset pinjaman aktif berstatus dipinjam',
                    $activeLoanMismatch,
                    'Status aset sesuai dengan transaksi pinjaman aktif.',
                    'eksemplar pinjaman aktif tidak berstatus dipinjam',
                    'Periksa transaksi peminjaman dan status aset.',
                );
            }
        } catch (Throwable $exception) {
            report($exception);

            $this->add(
                $checks,
                'workflows',
                'workflow_integrity_query',
                'Pemeriksaan alur transaksi',
                'fail',
                'Pemeriksaan alur tidak dapat dijalankan: '.$exception->getMessage(),
                'Periksa struktur tabel dan data transaksi.',
            );
        }
    }

    private function checkNotifications(array &$checks): void
    {
        try {
            $submittedRequests = Schema::hasTable('loan_requests')
                ? DB::table('loan_requests')->where('status', 'submitted')->count()
                : 0;

            $newDamageReports = Schema::hasTable('public_damage_reports')
                ? DB::table('public_damage_reports')->where('status', 'submitted')->count()
                : 0;

            $this->add(
                $checks,
                'notifications',
                'sidebar_badge_sources',
                'Sumber badge notifikasi admin',
                'pass',
                "Pengajuan baru: {$submittedRequests}. Laporan kerusakan baru: {$newDamageReports}.",
                null,
            );

            if (! Schema::hasTable('email_delivery_logs')) {
                $this->add(
                    $checks,
                    'notifications',
                    'email_logs',
                    'Riwayat pengiriman email tersedia',
                    'fail',
                    'Tabel email_delivery_logs belum tersedia.',
                    'Import patch SQL Tahap 32.',
                );

                return;
            }

            $failedLastDay = DB::table('email_delivery_logs')
                ->where('delivery_status', 'failed')
                ->where('created_at', '>=', now()->subDay())
                ->count();

            $this->add(
                $checks,
                'notifications',
                'recent_email_failures',
                'Pengiriman email 24 jam terakhir',
                $failedLastDay === 0 ? 'pass' : 'warning',
                $failedLastDay === 0
                    ? 'Tidak ada pengiriman email gagal dalam 24 jam terakhir.'
                    : "{$failedLastDay} pengiriman email gagal dalam 24 jam terakhir.",
                $failedLastDay === 0
                    ? null
                    : 'Buka menu Email & Notifikasi untuk melihat pesan kesalahan SMTP.',
            );

            $mailer = (string) config('mail.default', 'log');
            $this->add(
                $checks,
                'notifications',
                'mailer_mode',
                'Mode pengiriman email',
                $mailer === 'log' ? 'warning' : 'pass',
                $mailer === 'log'
                    ? 'Mailer masih menggunakan log. Email tidak masuk ke kotak masuk.'
                    : "Mailer aktif: {$mailer}.",
                $mailer === 'log'
                    ? 'Atur SMTP pada .env sebelum pengujian email sungguhan.'
                    : null,
            );
        } catch (Throwable $exception) {
            report($exception);

            $this->add(
                $checks,
                'notifications',
                'notification_query',
                'Pemeriksaan notifikasi',
                'fail',
                'Pemeriksaan notifikasi tidak dapat dijalankan: '.$exception->getMessage(),
                'Periksa tabel notifikasi dan email.',
            );
        }
    }

    private function containsProtectedMiddleware(array $middleware): bool
    {
        foreach ($middleware as $item) {
            if (
                $item === 'auth'
                || $item === 'password.session'
                || str_starts_with($item, 'role:')
            ) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function rolesFromMiddleware(array $middleware): array
    {
        $roles = [];

        foreach ($middleware as $item) {
            if (! str_starts_with($item, 'role:')) {
                continue;
            }

            $values = explode(',', substr($item, strlen('role:')));

            foreach ($values as $role) {
                $role = strtoupper(trim($role));

                if ($role !== '') {
                    $roles[] = $role;
                }
            }
        }

        return array_values(array_unique($roles));
    }

    private function tablesAvailable(array $tables): bool
    {
        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    private function addCountCheck(
        array &$checks,
        string $category,
        string $key,
        string $title,
        int $count,
        string $successMessage,
        string $problemLabel,
        string $recommendation,
        string $problemStatus = 'fail',
    ): void {
        $this->add(
            $checks,
            $category,
            $key,
            $title,
            $count === 0 ? 'pass' : $problemStatus,
            $count === 0
                ? $successMessage
                : "{$count} {$problemLabel}.",
            $count === 0 ? null : $recommendation,
        );
    }

    private function add(
        array &$checks,
        string $category,
        string $key,
        string $title,
        string $status,
        string $message,
        ?string $recommendation,
    ): void {
        $checks[] = compact(
            'category',
            'key',
            'title',
            'status',
            'message',
            'recommendation',
        );
    }

    private function manualChecklist(): array
    {
        return [
            [
                'title' => 'Akses tanpa login',
                'items' => [
                    'Buka portal perpustakaan, katalog, inventaris umum, audit inventaris, dan form laporan kerusakan menggunakan jendela Incognito.',
                    'Pastikan portal publik tidak mengarahkan pengguna ke halaman login.',
                    'Pastikan halaman admin dan siswa tetap menggunakan URL login yang berbeda.',
                ],
            ],
            [
                'title' => 'Super Admin',
                'items' => [
                    'Login melalui /admin/login dan buka Pengguna Sistem, Pengaturan, Email & Notifikasi, Riwayat Aktivitas, Backup, Kesiapan Sistem, dan Uji Akses & Alur.',
                    'Pastikan Super Admin dapat membuka dashboard inventaris dan perpustakaan.',
                    'Uji pembuatan akun percobaan, lalu hapus atau nonaktifkan data percobaan.',
                ],
            ],
            [
                'title' => 'Admin Inventaris',
                'items' => [
                    'Pastikan dapat membuka data barang, stock opname, pemeliharaan, penghapusan aset, dan laporan kerusakan.',
                    'Pastikan tidak dapat membuka Pengguna Sistem atau modul transaksi perpustakaan.',
                    'Kirim laporan kerusakan publik dan pastikan badge jumlah laporan baru muncul.',
                ],
            ],
            [
                'title' => 'Admin Perpustakaan',
                'items' => [
                    'Pastikan dapat membuka katalog, rak, anggota, peminjaman, pengembalian, denda, reservasi, pengajuan online, dan pesan kontak.',
                    'Pastikan tidak dapat membuka master inventaris atau Pengguna Sistem.',
                    'Kirim pengajuan dari akun siswa dan pastikan badge Pengajuan Online muncul.',
                ],
            ],
            [
                'title' => 'Pimpinan',
                'items' => [
                    'Pastikan hanya dashboard pimpinan dan Laporan Terpadu yang dapat dibuka.',
                    'Pastikan menu pengelolaan inventaris, perpustakaan, dan Super Admin tidak tersedia.',
                ],
            ],
            [
                'title' => 'Siswa',
                'items' => [
                    'Daftar akun siswa menggunakan email aktif, NIS, dan pilihan Kelas 1 sampai Kelas 6.',
                    'Verifikasi email, login, buka profil melalui pojok kanan, cari buku, kirim pengajuan, dan lihat notifikasi.',
                    'Pastikan akun siswa tidak dapat membuka /admin/dashboard atau modul internal.',
                    'Uji lupa password siswa dan login kembali menggunakan password baru.',
                ],
            ],
            [
                'title' => 'Alur transaksi',
                'items' => [
                    'Setujui pengajuan siswa, tandai siap diambil, lalu konfirmasi pengambilan.',
                    'Kembalikan buku, hitung denda bila terlambat, dan pastikan status aset kembali sesuai.',
                    'Jalankan php artisan library:send-due-reminders dan periksa notifikasi siswa.',
                    'Buat backup database setelah seluruh pengujian berhasil.',
                ],
            ],
        ];
    }
}
