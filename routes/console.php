<?php

use App\Services\Library\DueReminderService;
use App\Services\Library\LoanRequestService;
use App\Services\SystemReadinessService;
use App\Services\AcceptanceTestService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command;

$registerRoleAccountCommand = static function (
    string $signature,
    string $roleCode,
    string $roleLabel,
    string $purpose
): void {
    Artisan::command($signature, function () use ($roleCode, $roleLabel): int {
        $fullName = trim((string) $this->ask('Nama lengkap'));
        $username = trim((string) $this->ask('Username'));
        $emailInput = trim((string) $this->ask('Email, boleh dikosongkan'));
        $password = (string) $this->secret('Password minimal 8 karakter');
        $confirmation = (string) $this->secret('Ulangi password');

        if ($fullName === '' || $username === '') {
            $this->error('Nama lengkap dan username wajib diisi.');
            return Command::FAILURE;
        }

        if (! preg_match('/^[A-Za-z0-9._-]{3,60}$/', $username)) {
            $this->error('Username harus 3-60 karakter dan hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung.');
            return Command::FAILURE;
        }

        if (Str::length($password) < 8) {
            $this->error('Password minimal 8 karakter.');
            return Command::FAILURE;
        }

        if ($password !== $confirmation) {
            $this->error('Konfirmasi password tidak sama.');
            return Command::FAILURE;
        }

        $email = $emailInput !== '' ? $emailInput : null;

        if ($email !== null && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Format email tidak valid.');
            return Command::FAILURE;
        }

        try {
            DB::transaction(function () use ($fullName, $username, $email, $password, $roleCode): void {
                $existing = DB::table('users')
                    ->where('username', $username)
                    ->when($email !== null, fn ($query) => $query->orWhere('email', $email))
                    ->first();

                if ($existing) {
                    throw new RuntimeException('Username atau email sudah digunakan.');
                }

                $roleId = DB::table('roles')->where('role_code', $roleCode)->value('id');

                if (! $roleId) {
                    throw new RuntimeException("Role {$roleCode} tidak ditemukan. Jalankan patch SQL pemisahan admin terlebih dahulu.");
                }

                $userId = DB::table('users')->insertGetId([
                    'full_name' => $fullName,
                    'username' => $username,
                    'email' => $email,
                    'password_hash' => Hash::make($password),
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('user_roles')->insert([
                    'user_id' => $userId,
                    'role_id' => $roleId,
                    'assigned_at' => now(),
                ]);
            });
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            return Command::FAILURE;
        }

        $this->info("Akun {$roleLabel} berhasil dibuat.");
        return Command::SUCCESS;
    })->purpose($purpose);
};

$registerRoleAccountCommand(
    'app:create-super-admin',
    'SUPER_ADMIN',
    'Super Admin',
    'Membuat akun Super Admin'
);

$registerRoleAccountCommand(
    'app:create-inventory-admin',
    'INVENTORY_ADMIN',
    'Admin Inventaris',
    'Membuat akun Admin Inventaris'
);

$registerRoleAccountCommand(
    'app:create-library-admin',
    'LIBRARY_ADMIN',
    'Admin Perpustakaan',
    'Membuat akun Admin Perpustakaan'
);

$registerRoleAccountCommand(
    'app:create-manager',
    'MANAGER',
    'Pimpinan',
    'Membuat akun Pimpinan'
);

Artisan::command('app:create-admin', function (): int {
    $this->warn('Perintah app:create-admin sekarang diarahkan ke pembuatan Super Admin.');

    return $this->call('app:create-super-admin');
})->purpose('Alias untuk membuat akun Super Admin');

Artisan::command('app:list-admins', function (): int {
    $rows = DB::table('users')
        ->join('user_roles', 'user_roles.user_id', '=', 'users.id')
        ->join('roles', 'roles.id', '=', 'user_roles.role_id')
        ->whereIn('roles.role_code', [
            'SUPER_ADMIN',
            'INVENTORY_ADMIN',
            'LIBRARY_ADMIN',
            'LIBRARY_OFFICER',
            'MANAGER',
        ])
        ->orderBy('roles.role_name')
        ->orderBy('users.full_name')
        ->get([
            'users.full_name',
            'users.username',
            'users.email',
            'users.status',
            'roles.role_name',
            'roles.role_code',
        ])
        ->map(static fn ($row): array => [
            $row->full_name,
            $row->username,
            $row->email ?? '-',
            $row->role_name,
            $row->role_code,
            $row->status,
        ])
        ->all();

    $this->table(
        ['Nama', 'Username', 'Email', 'Peran', 'Kode', 'Status'],
        $rows
    );

    return Command::SUCCESS;
})->purpose('Menampilkan daftar akun administrator');


Artisan::command('app:acceptance-check {--export= : Simpan laporan teks ke lokasi tertentu}', function (
    AcceptanceTestService $service
): int {
    $report = $service->run();

    $this->newLine();
    $this->info('Uji Akses dan Alur Sistem');
    $this->line('Status: '.$report['status_label']);
    $this->line('Skor: '.$report['score'].'/100');
    $this->line(sprintf(
        'Hasil: %d lulus, %d peringatan, %d gagal',
        $report['counts']['pass'],
        $report['counts']['warning'],
        $report['counts']['fail'],
    ));
    $this->newLine();

    $rows = collect($report['checks'])->map(static function (array $check): array {
        return [
            match ($check['status']) {
                'pass' => 'LULUS',
                'warning' => 'PERINGATAN',
                default => 'GAGAL',
            },
            str($check['category'])->replace('_', ' ')->title()->toString(),
            $check['title'],
            $check['message'],
        ];
    })->all();

    $this->table(
        ['Status', 'Kategori', 'Pemeriksaan', 'Hasil'],
        $rows
    );

    $exportPath = trim((string) $this->option('export'));

    if ($exportPath !== '') {
        $absolutePath = str_starts_with($exportPath, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $exportPath)
            ? $exportPath
            : base_path($exportPath);

        $directory = dirname($absolutePath);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($absolutePath, $service->toText($report));
        $this->info('Laporan disimpan ke: '.$absolutePath);
    }

    return $report['counts']['fail'] > 0
        ? Command::FAILURE
        : Command::SUCCESS;
})->purpose('Memeriksa pemisahan role, keamanan route, akun siswa, dan alur transaksi');


Artisan::command('app:system-check', function (SystemReadinessService $service): int {
    $report = $service->run();

    $this->newLine();
    $this->info('Pemeriksaan Kesiapan Sistem');
    $this->line('Status: '.$report['status_label']);
    $this->line('Skor: '.$report['score'].'/100');
    $this->line(sprintf(
        'Hasil: %d lulus, %d peringatan, %d gagal',
        $report['counts']['pass'],
        $report['counts']['warning'],
        $report['counts']['fail'],
    ));
    $this->newLine();

    $rows = collect($report['checks'])->map(static function (array $check): array {
        return [
            match ($check['status']) {
                'pass' => 'LULUS',
                'warning' => 'PERINGATAN',
                default => 'GAGAL',
            },
            str($check['category'])->replace('_', ' ')->title()->toString(),
            $check['title'],
            $check['message'],
        ];
    })->all();

    $this->table(
        ['Status', 'Kategori', 'Pemeriksaan', 'Hasil'],
        $rows
    );

    return $report['counts']['fail'] > 0
        ? Command::FAILURE
        : Command::SUCCESS;
})->purpose('Memeriksa kesiapan aplikasi, database, keamanan, dan hosting');


Artisan::command('library:send-due-reminders', function (
    DueReminderService $reminders,
    LoanRequestService $loanRequests
): int {
    $expired = $loanRequests->syncExpired();
    $result = $reminders->generateAll();

    $this->info('Notifikasi dashboard dan email siswa berhasil disinkronkan.');
    $this->line('Pengingat H-1 baru: '.$result['due_tomorrow']);
    $this->line('Peringatan terlambat baru: '.$result['overdue']);
    $this->line('Pengajuan kedaluwarsa: '.$expired);

    return Command::SUCCESS;
})->purpose('Membuat notifikasi H-1 pengembalian dan menyelaraskan pengajuan anggota');

Schedule::command('library:send-due-reminders')
    ->dailyAt('07:00')
    ->withoutOverlapping();
