<?php

use App\Models\Item;
use App\Services\Library\BookCatalogCodeGenerator;
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
    $this->info('Pengujian Sistem');
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


Artisan::command('library:refresh-catalog-codes', function (
    BookCatalogCodeGenerator $codeGenerator
): int {
    $updated = 0;
    $completed = 0;

    Item::query()
        ->where('item_type', 'book')
        ->with([
            'category:id,category_code,category_name,description',
            'bookDetail',
            'authors:id,author_name',
        ])
        ->orderBy('id')
        ->chunkById(100, function ($books) use ($codeGenerator, &$updated, &$completed): void {
            foreach ($books as $book) {
                $detail = $book->bookDetail;

                if ($detail === null) {
                    continue;
                }

                $authors = $book->authors->pluck('author_name')->all();
                $automaticCodes = $codeGenerator->generate($book, $authors);
                $values = [
                    'classification_code' => $automaticCodes['classification_code'],
                    'call_number' => $automaticCodes['call_number'],
                ];

                $catalogHasRequiredData = (! empty($detail->isbn_10) || ! empty($detail->isbn_13))
                    && ! empty($detail->publisher_id)
                    && ! empty($detail->publication_year)
                    && ! empty($detail->grade_level)
                    && $authors !== [];

                if ($detail->completion_status === 'incomplete' && $catalogHasRequiredData) {
                    $values['completion_status'] = 'complete';
                    $completed++;
                }

                $changed = false;
                foreach ($values as $field => $value) {
                    if ((string) $detail->{$field} !== (string) $value) {
                        $changed = true;
                        break;
                    }
                }

                if (! $changed) {
                    continue;
                }

                $detail->fill($values)->save();
                $updated++;
            }
        });

    $this->info("Kode katalog otomatis diperbarui pada {$updated} buku.");
    $this->line("Katalog yang berubah menjadi lengkap: {$completed}.");

    return Command::SUCCESS;
})->purpose('Membuat ulang kode klasifikasi dan nomor panggil seluruh buku secara otomatis');


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

Artisan::command('media:warm-thumbnails {--size=* : Ukuran thumbnail yang dibuat}', function (\App\Services\MediaImageService $images): int {
    if (! $images->supportsThumbnailGeneration()) {
        $this->error('Ekstensi GD tidak tersedia. Thumbnail tidak dapat dibuat di server ini.');

        return 1;
    }

    $requestedSizes = collect($this->option('size'))
        ->map(fn ($size) => max(48, min((int) $size, 1200)))
        ->filter()
        ->unique()
        ->values();
    $sizes = $requestedSizes->isNotEmpty() ? $requestedSizes : collect([160, 480]);

    $files = collect($images->disk()->allFiles())
        ->reject(fn (string $path) => str_starts_with($path, '.thumbnails/'))
        ->filter(function (string $path) use ($images): bool {
            try {
                $images->resolveImage($path);

                return true;
            } catch (\RuntimeException) {
                return false;
            }
        })
        ->values();

    $total = $files->count() * $sizes->count();
    if ($total === 0) {
        $this->info('Tidak ada gambar yang perlu diproses.');

        return 0;
    }

    $bar = $this->output->createProgressBar($total);
    $bar->start();
    $created = 0;

    foreach ($files as $path) {
        foreach ($sizes as $size) {
            if ($images->ensureThumbnail($path, $size) !== null) {
                $created++;
            }
            $bar->advance();
        }
    }

    $bar->finish();
    $this->newLine(2);
    $this->info("Thumbnail siap: {$created} dari {$total} proses.");

    return 0;
})->purpose('Membuat cache thumbnail agar foto daftar lebih cepat dibuka.');
