<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Throwable;

class SystemReadinessService
{
    private const REQUIRED_TABLES = [
        'roles',
        'users',
        'user_roles',
        'categories',
        'units',
        'suppliers',
        'locations',
        'publishers',
        'authors',
        'items',
        'book_details',
        'book_authors',
        'library_shelves',
        'assets',
        'asset_shelf_history',
        'stock_balances',
        'stock_movements',
        'members',
        'loans',
        'loan_items',
        'reservations',
        'fine_payments',
        'maintenance_records',
        'stock_opnames',
        'stock_opname_items',
        'disposals',
        'system_settings',
        'audit_logs',
        'loan_requests',
        'loan_request_items',
        'member_notifications',
        'public_damage_reports',
        'public_contact_messages',
        'email_delivery_logs',
        'password_reset_tokens',
    ];

    private const REQUIRED_ROLES = [
        'SUPER_ADMIN',
        'INVENTORY_ADMIN',
        'LIBRARY_ADMIN',
        'MANAGER',
        'MEMBER',
    ];

    private const REQUIRED_SETTINGS = [
        'application.name',
        'application.short_name',
        'institution.name',
        'institution.address',
        'institution.phone',
        'institution.email',
        'library.default_loan_days',
        'library.max_active_loans',
        'library.fine_per_day',
        'library.reservation_hold_days',
        'library.max_active_reservations',
        'inventory.asset_code_separator',
        'portal.hero_title',
        'portal.about_content',
        'portal.contact_intro',
        'library.loan_request_hold_days',
    ];

    private const REQUIRED_ROUTES = [
        'login',
        'dashboard.super-admin',
        'dashboard.inventory',
        'dashboard.library',
        'admin.users.index',
        'admin.settings.edit',
        'admin.audit-logs.index',
        'admin.database-backups.index',
        'inventory.items.index',
        'inventory.stock-opnames.index',
        'inventory.maintenance-records.index',
        'inventory.disposals.index',
        'library.books.index',
        'library.members.index',
        'library.loans.index',
        'library.returns.index',
        'library.fines.index',
        'library.reservations.index',
        'reports.index',
        'public.home',
        'public.catalog',
        'public.inventory.audit',
        'register',
        'student.login',
        'student.login.store',
        'member.loan-requests.index',
        'member.profile.show',
        'library.loan-requests.index',
        'student.verification.notice',
        'student.verification.verify',
        'student.verification.resend',
        'admin.email-notifications.index',
        'student.password.request',
        'student.password.email',
        'student.password.reset',
        'student.password.update',
        'admin.password.request',
        'admin.password.email',
        'admin.password.reset',
        'admin.password.update',
        'admin.acceptance-tests.index',
        'admin.acceptance-tests.download',
    ];

    private const CATEGORY_LABELS = [
        'environment' => 'Lingkungan aplikasi',
        'storage' => 'File dan penyimpanan',
        'database' => 'Database dan struktur',
        'security' => 'Keamanan dasar',
        'integrity' => 'Konsistensi data',
        'backup' => 'Backup dan pemulihan',
        'routing' => 'Route dan modul',
        'email' => 'Email dan notifikasi',
    ];

    public function run(): array
    {
        $checks = [];

        $this->checkEnvironment($checks);
        $this->checkStorage($checks);
        $this->checkRouting($checks);

        $tables = $this->checkDatabaseConnectionAndTables($checks);

        if ($tables !== null) {
            $this->checkRolesAndUsers($checks, $tables);
            $this->checkSettings($checks, $tables);
            $this->checkDataIntegrity($checks, $tables);
        }

        $this->checkBackups($checks);

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
            $counts['fail'] > 0 => 'not_ready',
            $counts['warning'] > 0 => 'ready_with_notes',
            default => 'ready',
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
                'ready' => 'Siap untuk hosting',
                'ready_with_notes' => 'Siap dengan catatan',
                default => 'Belum siap untuk hosting',
            },
            'manual_checklist' => $this->manualChecklist(),
        ];
    }

    public function toText(array $report): string
    {
        $lines = [
            'LAPORAN KESIAPAN SISTEM',
            '========================',
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

                if ($check['detail'] !== null && $check['detail'] !== '') {
                    $lines[] = 'Detail: '.$check['detail'];
                }

                if ($check['recommendation'] !== null && $check['recommendation'] !== '') {
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
                $number = $index + 1;
                $lines[] = "[ ] {$number}. {$item}";
            }
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    private function checkEnvironment(array &$checks): void
    {
        $requiredPhp = '8.2.0';
        $phpPass = version_compare(PHP_VERSION, $requiredPhp, '>=');

        $this->add(
            $checks,
            'environment',
            'php_version',
            'Versi PHP',
            $phpPass ? 'pass' : 'fail',
            'PHP '.PHP_VERSION,
            "Minimal yang digunakan proyek adalah PHP {$requiredPhp}.",
            $phpPass ? null : 'Gunakan PHP 8.2 atau versi yang lebih baru.',
        );

        $requiredExtensions = [
            'ctype',
            'fileinfo',
            'json',
            'mbstring',
            'openssl',
            'pdo',
            'pdo_mysql',
            'tokenizer',
        ];
        $missingExtensions = array_values(array_filter(
            $requiredExtensions,
            static fn (string $extension): bool => ! extension_loaded($extension)
        ));

        $this->add(
            $checks,
            'environment',
            'php_extensions',
            'Ekstensi PHP',
            $missingExtensions === [] ? 'pass' : 'fail',
            $missingExtensions === []
                ? 'Semua ekstensi utama tersedia.'
                : 'Ekstensi belum tersedia: '.implode(', ', $missingExtensions),
            'Diperiksa: '.implode(', ', $requiredExtensions).'.',
            $missingExtensions === [] ? null : 'Aktifkan ekstensi tersebut pada php.ini lalu restart Apache atau PHP.',
        );

        $appKey = trim((string) config('app.key'));
        $this->add(
            $checks,
            'security',
            'application_key',
            'Kunci aplikasi',
            $appKey !== '' ? 'pass' : 'fail',
            $appKey !== '' ? 'APP_KEY sudah terpasang.' : 'APP_KEY masih kosong.',
            null,
            $appKey !== '' ? null : 'Jalankan php artisan key:generate.',
        );

        $environment = (string) app()->environment();
        $this->add(
            $checks,
            'environment',
            'application_environment',
            'Mode lingkungan aplikasi',
            $environment === 'production' ? 'pass' : 'warning',
            'APP_ENV saat ini: '.$environment,
            'Mode local sesuai untuk pengembangan. Saat hosting sebaiknya menggunakan production.',
            $environment === 'production' ? null : 'Ubah APP_ENV=production setelah website dipindahkan ke hosting.',
        );

        $debug = (bool) config('app.debug');
        $this->add(
            $checks,
            'security',
            'debug_mode',
            'Mode debug',
            $debug ? 'warning' : 'pass',
            $debug ? 'APP_DEBUG masih aktif.' : 'APP_DEBUG sudah nonaktif.',
            'Debug membantu saat pengembangan, tetapi dapat menampilkan detail sensitif.',
            $debug ? 'Saat hosting, ubah APP_DEBUG=false.' : null,
        );

        $appUrl = rtrim((string) config('app.url'), '/');
        $usesHttps = str_starts_with(strtolower($appUrl), 'https://');

        $this->add(
            $checks,
            'security',
            'https_url',
            'Alamat aplikasi dan HTTPS',
            $usesHttps ? 'pass' : 'warning',
            $appUrl !== '' ? 'APP_URL: '.$appUrl : 'APP_URL belum diatur.',
            'HTTPS diperlukan saat aplikasi sudah berada di internet.',
            $usesHttps ? null : 'Pada hosting, isi APP_URL dengan alamat HTTPS yang benar.',
        );

        $timezone = (string) config('app.timezone');
        $this->add(
            $checks,
            'environment',
            'timezone',
            'Zona waktu aplikasi',
            $timezone === 'Asia/Jakarta' ? 'pass' : 'warning',
            'Zona waktu aplikasi: '.$timezone,
            'Sistem digunakan dengan waktu Indonesia Barat.',
            $timezone === 'Asia/Jakarta' ? null : 'Pertimbangkan menggunakan APP_TIMEZONE=Asia/Jakarta.',
        );

        $vendorExists = is_file(base_path('vendor/autoload.php'));
        $this->add(
            $checks,
            'environment',
            'composer_dependencies',
            'Dependensi Composer',
            $vendorExists ? 'pass' : 'fail',
            $vendorExists ? 'Dependensi Composer tersedia.' : 'Folder vendor belum tersedia.',
            null,
            $vendorExists ? null : 'Jalankan composer install --no-dev --optimize-autoloader.',
        );

        $composerLockExists = is_file(base_path('composer.lock'));
        $this->add(
            $checks,
            'environment',
            'composer_lock',
            'Kunci versi Composer',
            $composerLockExists ? 'pass' : 'warning',
            $composerLockExists ? 'composer.lock tersedia.' : 'composer.lock belum ditemukan.',
            'composer.lock menjaga versi paket tetap konsisten.',
            $composerLockExists ? null : 'Jalankan composer update sekali pada komputer pengembangan dan simpan composer.lock.',
        );

        $mailer = (string) config('mail.default');
        $mailFrom = trim((string) config('mail.from.address'));
        $smtpHost = trim((string) config('mail.mailers.smtp.host'));
        $mailReady = $mailer !== 'log'
            && $mailFrom !== ''
            && ! str_contains(strtolower($mailFrom), 'example.com')
            && ($mailer !== 'smtp' || $smtpHost !== '');

        $this->add(
            $checks,
            'email',
            'mail_configuration',
            'Konfigurasi pengiriman email',
            $mailReady ? 'pass' : 'warning',
            $mailReady
                ? "Mailer {$mailer} siap digunakan dengan pengirim {$mailFrom}."
                : "Mailer saat ini {$mailer} dengan pengirim {$mailFrom}.",
            'Email verifikasi dan pengingat membutuhkan konfigurasi pengiriman yang aktif.',
            $mailReady ? null : 'Atur MAIL_MAILER, MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, dan MAIL_FROM_ADDRESS pada file .env.',
        );
    }

    private function checkStorage(array &$checks): void
    {
        $directories = [
            'storage' => storage_path(),
            'log' => storage_path('logs'),
            'cache' => storage_path('framework/cache'),
            'session' => storage_path('framework/sessions'),
            'view' => storage_path('framework/views'),
            'bootstrap cache' => base_path('bootstrap/cache'),
        ];

        $problems = [];

        foreach ($directories as $label => $directory) {
            if (! is_dir($directory)) {
                $problems[] = "{$label}: folder tidak ditemukan";
                continue;
            }

            if (! is_writable($directory)) {
                $problems[] = "{$label}: tidak dapat ditulis";
            }
        }

        $this->add(
            $checks,
            'storage',
            'writable_directories',
            'Izin folder runtime',
            $problems === [] ? 'pass' : 'fail',
            $problems === []
                ? 'Folder runtime Laravel dapat ditulis.'
                : implode('; ', $problems),
            'Mencakup log, cache, session, compiled view, dan bootstrap cache.',
            $problems === [] ? null : 'Atur izin folder storage dan bootstrap/cache agar dapat ditulis oleh PHP.',
        );

        $publicEnvExists = is_file(public_path('.env'));
        $this->add(
            $checks,
            'security',
            'public_env_file',
            'Keamanan file .env',
            $publicEnvExists ? 'fail' : 'pass',
            $publicEnvExists
                ? 'File .env ditemukan di dalam folder public.'
                : 'Tidak ada file .env di folder public.',
            null,
            $publicEnvExists ? 'Hapus file public/.env. File .env hanya boleh berada di root proyek.' : null,
        );
    }

    private function checkRouting(array &$checks): void
    {
        $missingRoutes = array_values(array_filter(
            self::REQUIRED_ROUTES,
            static fn (string $route): bool => ! Route::has($route)
        ));

        $this->add(
            $checks,
            'routing',
            'required_routes',
            'Route utama aplikasi',
            $missingRoutes === [] ? 'pass' : 'fail',
            $missingRoutes === []
                ? count(self::REQUIRED_ROUTES).' route utama tersedia.'
                : 'Route belum tersedia: '.implode(', ', $missingRoutes),
            'Pemeriksaan mencakup login, dashboard, administrasi, inventaris, perpustakaan, dan laporan.',
            $missingRoutes === [] ? null : 'Pastikan routes/web.php berasal dari proyek terbaru dan jalankan php artisan optimize:clear.',
        );

        $healthRouteAvailable = Route::has('health') || $this->routeUriExists('up');
        $this->add(
            $checks,
            'routing',
            'health_endpoint',
            'Endpoint kesehatan aplikasi',
            $healthRouteAvailable ? 'pass' : 'warning',
            $healthRouteAvailable
                ? 'Endpoint pemeriksaan aplikasi /up tersedia.'
                : 'Endpoint /up belum ditemukan.',
            'Endpoint ini dapat digunakan untuk memeriksa apakah aplikasi dapat merespons.',
            $healthRouteAvailable ? null : 'Periksa konfigurasi health pada bootstrap/app.php.',
        );
    }

    private function checkDatabaseConnectionAndTables(array &$checks): ?array
    {
        try {
            DB::connection()->getPdo();

            $versionRow = DB::selectOne('SELECT VERSION() AS version');
            $version = (string) ($versionRow->version ?? 'tidak diketahui');
            $databaseName = (string) DB::connection()->getDatabaseName();

            $this->add(
            $checks,
            'database',
            'database_connection',
            'Koneksi database',
            'pass',
            "Terhubung ke {$databaseName}.",
            'Server database: '.$version,
        );

            $tables = collect(DB::select(
                "SELECT TABLE_NAME
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_TYPE = 'BASE TABLE'"
            ))
                ->map(static fn (object $row): string => (string) $row->TABLE_NAME)
                ->all();

            $missingTables = array_values(array_diff(self::REQUIRED_TABLES, $tables));

            $this->add(
            $checks,
            'database',
            'required_tables',
            'Tabel utama database',
            $missingTables === [] ? 'pass' : 'fail',
            $missingTables === []
                    ? count(self::REQUIRED_TABLES).' tabel utama tersedia.'
                    : 'Tabel belum tersedia: '.implode(', ', $missingTables),
            'Jumlah tabel yang ditemukan: '.count($tables).'.',
            $missingTables === [] ? null : 'Impor database utama dan seluruh patch SQL yang diwajibkan.',
        );

            return $tables;
        } catch (Throwable $exception) {
            report($exception);

            $this->add(
            $checks,
            'database',
            'database_connection',
            'Koneksi database',
            'fail',
            'Koneksi database gagal.',
            $this->safeDatabaseMessage($exception),
            'Periksa DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD, dan layanan MySQL/MariaDB.',
        );

            return null;
        }
    }

    private function checkRolesAndUsers(array &$checks, array $tables): void
    {
        if (! $this->hasTables($tables, ['roles'])) {
            return;
        }

        $roleCodes = DB::table('roles')->pluck('role_code')->map(
            static fn (string $code): string => strtoupper($code)
        )->all();
        $missingRoles = array_values(array_diff(self::REQUIRED_ROLES, $roleCodes));

        $this->add(
            $checks,
            'database',
            'required_roles',
            'Peran pengguna',
            $missingRoles === [] ? 'pass' : 'fail',
            $missingRoles === []
                ? 'Seluruh peran utama tersedia.'
                : 'Peran belum tersedia: '.implode(', ', $missingRoles),
            'Peran wajib: '.implode(', ', self::REQUIRED_ROLES).'.',
            $missingRoles === [] ? null : 'Jalankan patch pemisahan role admin.',
        );

        if (! $this->hasTables($tables, ['users', 'user_roles'])) {
            return;
        }

        $activeSuperAdmins = DB::table('users')
            ->join('user_roles', 'user_roles.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('roles.role_code', 'SUPER_ADMIN')
            ->where('users.status', 'active')
            ->distinct()
            ->count('users.id');

        $this->add(
            $checks,
            'security',
            'active_super_admin',
            'Super Admin aktif',
            $activeSuperAdmins >= 1 ? 'pass' : 'fail',
            $activeSuperAdmins >= 1
                ? "{$activeSuperAdmins} Super Admin aktif tersedia."
                : 'Tidak ada Super Admin aktif.',
            null,
            $activeSuperAdmins >= 1 ? null : 'Aktifkan atau buat minimal satu akun Super Admin.',
        );

        $usersWithoutRole = DB::table('users')
            ->leftJoin('user_roles', 'user_roles.user_id', '=', 'users.id')
            ->whereNull('user_roles.user_id')
            ->count();

        $this->add(
            $checks,
            'integrity',
            'users_without_role',
            'Pengguna tanpa peran',
            $usersWithoutRole === 0 ? 'pass' : 'warning',
            $usersWithoutRole === 0
                ? 'Semua pengguna memiliki peran.'
                : "{$usersWithoutRole} pengguna belum memiliki peran.",
            null,
            $usersWithoutRole === 0 ? null : 'Periksa akun tersebut pada tabel users dan user_roles.',
        );

        $invalidHashes = DB::table('users')
            ->where('status', 'active')
            ->get(['id', 'username', 'password_hash'])
            ->filter(function (object $user): bool {
                $info = password_get_info((string) $user->password_hash);

                return ($info['algoName'] ?? 'unknown') === 'unknown';
            })
            ->pluck('username')
            ->all();

        $this->add(
            $checks,
            'security',
            'password_hashes',
            'Keamanan hash password',
            $invalidHashes === [] ? 'pass' : 'fail',
            $invalidHashes === []
                ? 'Password akun aktif tersimpan sebagai hash yang dikenali.'
                : 'Hash password tidak valid pada akun: '.implode(', ', $invalidHashes),
            'Password asli tidak dibaca atau ditampilkan.',
            $invalidHashes === [] ? null : 'Reset password akun tersebut melalui menu Pengguna Sistem.',
        );


        $membersWithoutEmail = DB::table('users')
            ->join('user_roles', 'user_roles.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('roles.role_code', User::ROLE_MEMBER)
            ->where('users.status', 'active')
            ->where(function ($query): void {
                $query->whereNull('users.email')
                    ->orWhere('users.email', '');
            })
            ->distinct()
            ->count('users.id');

        $this->add(
            $checks,
            'security',
            'student_email_login',
            'Email login siswa',
            $membersWithoutEmail === 0 ? 'pass' : 'warning',
            $membersWithoutEmail === 0
                ? 'Semua akun siswa aktif memiliki email.'
                : "{$membersWithoutEmail} akun siswa aktif belum memiliki email.",
            'Login siswa hanya menerima email.',
            $membersWithoutEmail === 0
                ? null
                : 'Lengkapi email akun siswa agar dapat menggunakan halaman Login Siswa.',
        );

        $emailVerifiedColumn = collect(DB::select(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'email_verified_at'"
        ))->isNotEmpty();

        $this->add(
            $checks,
            'email',
            'email_verification_column',
            'Kolom verifikasi email siswa',
            $emailVerifiedColumn ? 'pass' : 'fail',
            $emailVerifiedColumn
                ? 'Kolom users.email_verified_at tersedia.'
                : 'Kolom users.email_verified_at belum tersedia.',
            null,
            $emailVerifiedColumn ? null : 'Import database/sql/patch_tahap32_verifikasi_email_dan_notifikasi.sql.',
        );

        $passwordChangedColumn = collect(DB::select(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'password_changed_at'"
        ))->isNotEmpty();

        $this->add(
            $checks,
            'security',
            'password_changed_column',
            'Penanda perubahan password',
            $passwordChangedColumn ? 'pass' : 'fail',
            $passwordChangedColumn
                ? 'Kolom users.password_changed_at tersedia.'
                : 'Kolom users.password_changed_at belum tersedia.',
            'Kolom ini digunakan untuk mengakhiri sesi lama setelah password berubah.',
            $passwordChangedColumn ? null : 'Import database/sql/patch_tahap33_lupa_password_dan_keamanan.sql.',
        );
    }

    private function checkSettings(array &$checks, array $tables): void
    {
        if (! in_array('system_settings', $tables, true)) {
            return;
        }

        $available = DB::table('system_settings')
            ->whereIn('setting_key', self::REQUIRED_SETTINGS)
            ->pluck('setting_key')
            ->all();
        $missing = array_values(array_diff(self::REQUIRED_SETTINGS, $available));

        $this->add(
            $checks,
            'database',
            'system_settings',
            'Pengaturan sistem',
            $missing === [] ? 'pass' : 'warning',
            $missing === []
                ? 'Seluruh pengaturan utama tersedia.'
                : 'Pengaturan belum tersimpan: '.implode(', ', $missing),
            null,
            $missing === [] ? null : 'Buka Pengaturan Sistem lalu simpan formulir satu kali.',
        );

        $identity = DB::table('system_settings')
            ->whereIn('setting_key', ['institution.name', 'institution.address'])
            ->pluck('setting_value', 'setting_key');

        $usesDefaultIdentity = trim((string) ($identity['institution.name'] ?? '')) === ''
            || trim((string) ($identity['institution.address'] ?? '')) === ''
            || str_contains(
                strtolower((string) ($identity['institution.address'] ?? '')),
                'belum diatur'
            );

        $this->add(
            $checks,
            'environment',
            'institution_identity',
            'Identitas instansi',
            $usesDefaultIdentity ? 'warning' : 'pass',
            $usesDefaultIdentity
                ? 'Identitas instansi belum lengkap.'
                : 'Nama dan alamat instansi sudah diatur.',
            null,
            $usesDefaultIdentity ? 'Lengkapi nama, alamat, telepon, dan email pada Pengaturan Sistem.' : null,
        );
    }

    private function checkDataIntegrity(array &$checks, array $tables): void
    {
        if ($this->hasTables($tables, ['items', 'book_details'])) {
            $booksWithoutDetails = DB::table('items')
                ->leftJoin('book_details', 'book_details.item_id', '=', 'items.id')
                ->where('items.item_type', 'book')
                ->whereNull('book_details.item_id')
                ->count();

            $this->add(
            $checks,
            'integrity',
            'books_without_catalog',
            'Kelengkapan katalog buku',
            $booksWithoutDetails === 0 ? 'pass' : 'warning',
            $booksWithoutDetails === 0
                    ? 'Semua barang buku memiliki detail katalog.'
                    : "{$booksWithoutDetails} barang buku belum memiliki detail katalog.",
            null,
            $booksWithoutDetails === 0 ? null : 'Lengkapi melalui menu Buku Baru dan Katalog.',
        );
        }

        if ($this->hasTables($tables, ['assets', 'items'])) {
            $availableBooksWithoutShelf = DB::table('assets')
                ->join('items', 'items.id', '=', 'assets.item_id')
                ->where('items.item_type', 'book')
                ->where('assets.asset_status', 'available')
                ->whereNull('assets.current_shelf_id')
                ->count();

            $this->add(
            $checks,
            'integrity',
            'available_books_without_shelf',
            'Penempatan buku tersedia',
            $availableBooksWithoutShelf === 0 ? 'pass' : 'warning',
            $availableBooksWithoutShelf === 0
                    ? 'Semua buku tersedia sudah memiliki rak.'
                    : "{$availableBooksWithoutShelf} buku tersedia belum memiliki rak.",
            null,
            $availableBooksWithoutShelf === 0 ? null : 'Periksa menu Penempatan Buku.',
        );
        }

        if ($this->hasTables($tables, ['loan_items', 'assets'])) {
            $borrowedItemsMismatch = DB::table('loan_items')
                ->join('assets', 'assets.id', '=', 'loan_items.asset_id')
                ->where('loan_items.return_status', 'borrowed')
                ->where('assets.asset_status', '<>', 'borrowed')
                ->count();

            $this->add(
            $checks,
            'integrity',
            'active_loan_asset_status',
            'Kesesuaian peminjaman aktif',
            $borrowedItemsMismatch === 0 ? 'pass' : 'fail',
            $borrowedItemsMismatch === 0
                    ? 'Status aset sesuai dengan peminjaman aktif.'
                    : "{$borrowedItemsMismatch} peminjaman aktif tidak cocok dengan status aset.",
            null,
            $borrowedItemsMismatch === 0 ? null : 'Periksa loan_items dan assets sebelum menerima transaksi baru.',
        );

            $borrowedAssetsWithoutLoan = DB::table('assets')
                ->leftJoin('loan_items', function ($join): void {
                    $join->on('loan_items.asset_id', '=', 'assets.id')
                        ->where('loan_items.return_status', '=', 'borrowed');
                })
                ->where('assets.asset_status', 'borrowed')
                ->whereNull('loan_items.id')
                ->count();

            $this->add(
            $checks,
            'integrity',
            'borrowed_asset_without_loan',
            'Aset borrowed tanpa peminjaman',
            $borrowedAssetsWithoutLoan === 0 ? 'pass' : 'fail',
            $borrowedAssetsWithoutLoan === 0
                    ? 'Tidak ada aset borrowed tanpa transaksi aktif.'
                    : "{$borrowedAssetsWithoutLoan} aset borrowed tidak memiliki transaksi peminjaman aktif.",
            null,
            $borrowedAssetsWithoutLoan === 0 ? null : 'Periksa status aset dan riwayat peminjaman terkait.',
        );
        }

        if ($this->hasTables($tables, ['maintenance_records', 'assets'])) {
            $maintenanceMismatch = DB::table('maintenance_records')
                ->join('assets', 'assets.id', '=', 'maintenance_records.asset_id')
                ->whereIn('maintenance_records.status', ['reported', 'in_progress'])
                ->where('assets.asset_status', '<>', 'maintenance')
                ->count();

            $this->add(
            $checks,
            'integrity',
            'maintenance_asset_status',
            'Kesesuaian pemeliharaan aktif',
            $maintenanceMismatch === 0 ? 'pass' : 'warning',
            $maintenanceMismatch === 0
                    ? 'Status aset sesuai dengan pemeliharaan aktif.'
                    : "{$maintenanceMismatch} pemeliharaan aktif tidak cocok dengan status aset.",
            null,
            $maintenanceMismatch === 0 ? null : 'Periksa modul Pemeliharaan Aset dan status aset terkait.',
        );
        }

        if ($this->hasTables($tables, ['disposals', 'assets'])) {
            $completedDisposalMismatch = DB::table('disposals')
                ->join('assets', 'assets.id', '=', 'disposals.asset_id')
                ->where('disposals.status', 'completed')
                ->where('assets.asset_status', '<>', 'disposed')
                ->count();

            $this->add(
            $checks,
            'integrity',
            'completed_disposal_asset_status',
            'Kesesuaian penghapusan selesai',
            $completedDisposalMismatch === 0 ? 'pass' : 'fail',
            $completedDisposalMismatch === 0
                    ? 'Status aset sesuai dengan penghapusan selesai.'
                    : "{$completedDisposalMismatch} penghapusan selesai belum berstatus disposed.",
            null,
            $completedDisposalMismatch === 0 ? null : 'Periksa disposals dan assets sebelum deployment.',
        );
        }

        if ($this->hasTables($tables, ['loans', 'loan_items'])) {
            $emptyLoans = DB::table('loans')
                ->leftJoin('loan_items', 'loan_items.loan_id', '=', 'loans.id')
                ->whereNull('loan_items.id')
                ->count();

            $this->add(
            $checks,
            'integrity',
            'loans_without_items',
            'Peminjaman tanpa eksemplar',
            $emptyLoans === 0 ? 'pass' : 'warning',
            $emptyLoans === 0
                    ? 'Semua transaksi peminjaman memiliki eksemplar.'
                    : "{$emptyLoans} transaksi peminjaman tidak memiliki eksemplar.",
            null,
            $emptyLoans === 0 ? null : 'Periksa transaksi tersebut pada tabel loans dan loan_items.',
        );
        }
    }

    private function checkBackups(array &$checks): void
    {
        $directory = storage_path('app/private/'.DatabaseBackupService::DIRECTORY);

        if (! is_dir($directory)) {
            $this->add(
            $checks,
            'backup',
            'backup_directory',
            'Folder backup database',
            'warning',
            'Folder backup belum tersedia.',
            $directory,
            'Buka menu Backup Database atau buat folder tersebut dengan izin tulis.',
        );

            return;
        }

        $writable = is_writable($directory);
        $this->add(
            $checks,
            'backup',
            'backup_directory',
            'Folder backup database',
            $writable ? 'pass' : 'fail',
            $writable ? 'Folder backup dapat ditulis.' : 'Folder backup tidak dapat ditulis.',
            $directory,
            $writable ? null : 'Atur izin tulis untuk storage/app/private/backups.',
        );

        $files = collect(glob($directory.DIRECTORY_SEPARATOR.'*.sql') ?: [])
            ->filter(static fn (string $path): bool => is_file($path))
            ->sortByDesc(static fn (string $path): int => filemtime($path) ?: 0)
            ->values();

        if ($files->isEmpty()) {
            $this->add(
            $checks,
            'backup',
            'latest_backup',
            'Backup database terbaru',
            'warning',
            'Belum ada file backup database.',
            null,
            'Buat dan unduh backup sebelum pengujian akhir atau hosting.',
        );

            return;
        }

        $latest = $files->first();
        $latestTime = Carbon::createFromTimestamp(filemtime($latest) ?: time());
        $ageDays = $latestTime->diffInDays(now());
        $fresh = $ageDays <= 7;

        $this->add(
            $checks,
            'backup',
            'latest_backup',
            'Backup database terbaru',
            $fresh ? 'pass' : 'warning',
            'Backup terbaru: '.basename($latest),
            sprintf(
                'Dibuat %s, ukuran %.2f MB.',
                $latestTime->format('d/m/Y H:i:s'),
                (filesize($latest) ?: 0) / 1024 / 1024,
            ),
            $fresh ? null : 'Buat backup baru karena backup terbaru sudah lebih dari tujuh hari.',
        );
    }

    private function manualChecklist(): array
    {
        return [
            [
                'title' => 'Login dan hak akses',
                'items' => [
                    'Login sebagai Super Admin dan pastikan seluruh menu administrasi tampil.',
                    'Login sebagai Admin Inventaris dan pastikan menu perpustakaan serta administrasi Super Admin tidak dapat dibuka.',
                    'Login sebagai Admin Perpustakaan dan pastikan menu inventaris serta administrasi Super Admin tidak dapat dibuka.',
                    'Login sebagai Pimpinan dan pastikan hanya dashboard serta laporan yang sesuai dapat dibuka.',
                    'Uji logout dan pastikan halaman internal kembali meminta login.',
                ],
            ],
            [
                'title' => 'Inventaris',
                'items' => [
                    'Buat kategori, satuan, supplier, lokasi, dan satu barang percobaan.',
                    'Buat aset individual dan pastikan kode aset serta barcode tidak duplikat.',
                    'Jalankan satu stock opname sampai selesai dan periksa perubahan saldo atau status aset.',
                    'Jalankan satu pemeliharaan aset dari laporan sampai selesai.',
                    'Ajukan satu penghapusan aset, setujui sebagai Super Admin, lalu selesaikan pelaksanaannya.',
                ],
            ],
            [
                'title' => 'Perpustakaan',
                'items' => [
                    'Lengkapi katalog buku, penulis, penerbit, dan rak.',
                    'Tempatkan buku pada rak lalu pastikan status buku dapat menjadi available.',
                    'Buat anggota, lakukan peminjaman, dan periksa status aset menjadi borrowed.',
                    'Lakukan pengembalian tepat waktu dan terlambat untuk menguji denda.',
                    'Buat reservasi, ubah menjadi siap diambil, lalu selesaikan atau kedaluwarsakan.',
                ],
            ],
            [
                'title' => 'Administrasi dan laporan',
                'items' => [
                    'Buat satu akun admin percobaan, ubah status, dan reset password.',
                    'Ubah pengaturan nama aplikasi dan aturan perpustakaan lalu pastikan perubahan diterapkan.',
                    'Periksa Riwayat Aktivitas setelah melakukan tambah, ubah, login, dan logout.',
                    'Buka seluruh Laporan Terpadu, uji filter, cetak, dan ekspor CSV.',
                    'Buat backup database dan unduh salinannya ke komputer.',
                ],
            ],
            [
                'title' => 'Persiapan hosting',
                'items' => [
                    'Ubah APP_ENV menjadi production dan APP_DEBUG menjadi false.',
                    'Gunakan APP_URL dengan HTTPS dan isi kredensial database hosting.',
                    'Jalankan composer install --no-dev --optimize-autoloader.',
                    'Jalankan php artisan optimize:clear lalu php artisan config:cache dan php artisan route:cache.',
                    'Pastikan folder storage dan bootstrap/cache dapat ditulis oleh server hosting.',
                ],
            ],
        ];
    }

    private function add(
        array &$checks,
        string $category,
        string $code,
        string $title,
        string $status,
        string $message,
        ?string $detail = null,
        ?string $recommendation = null,
    ): void {
        $checks[] = [
            'category' => $category,
            'code' => $code,
            'title' => $title,
            'status' => $status,
            'message' => $message,
            'detail' => $detail,
            'recommendation' => $recommendation,
        ];
    }

    private function hasTables(array $tables, array $required): bool
    {
        return array_diff($required, $tables) === [];
    }

    private function routeUriExists(string $uri): bool
    {
        return collect(Route::getRoutes()->getRoutes())
            ->contains(static fn ($route): bool => trim($route->uri(), '/') === trim($uri, '/'));
    }

    private function safeDatabaseMessage(Throwable $exception): string
    {
        if ($exception instanceof RuntimeException) {
            return $exception->getMessage();
        }

        return 'Detail teknis dicatat pada storage/logs/laravel.log.';
    }
}
