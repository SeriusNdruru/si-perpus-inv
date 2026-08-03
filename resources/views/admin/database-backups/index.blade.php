@extends('layouts.app')

@section('title', 'Backup Database')
@section('page-title', 'Backup dan Restore Database')

@section('content')
    <div class="detail-heading">
        <div>
            <p class="eyebrow">Keamanan data</p>
            <h2>Cadangan database aplikasi</h2>
            <p class="panel-description">
                Buat backup sebelum perubahan besar, pengujian transaksi, atau proses hosting. File SQL disimpan pada folder privat dan hanya dapat diakses Super Admin.
            </p>
        </div>
        <form method="POST" action="{{ route('admin.database-backups.store') }}">
            @csrf
            <button type="submit" class="button-primary">Buat backup sekarang</button>
        </form>
    </div>

    <div class="stat-grid">
        <article class="stat-card">
            <span>Jumlah file backup</span>
            <strong>{{ number_format($backups->count()) }}</strong>
        </article>
        <article class="stat-card">
            <span>Total penyimpanan</span>
            <strong>{{ number_format($totalSize / 1024 / 1024, 2) }} MB</strong>
        </article>
        <article class="stat-card">
            <span>Backup terbaru</span>
            @php
                $latestBackup = $backups->first();
            @endphp
            <strong style="font-size: 20px;">
                {{ $latestBackup ? $latestBackup['modified_at']->format('d/m/Y H:i') : '-' }}
            </strong>
        </article>
    </div>

    <section class="panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">File tersimpan</p>
                <h2>Daftar backup</h2>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th class="table-number-heading">No.</th>
                        <th>Nama file</th>
                        <th>Jenis</th>
                        <th>Ukuran</th>
                        <th>Dibuat atau diperbarui</th>
                        <th class="table-actions-heading">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($backups as $backup)
                        <tr><td class="table-number">{{ (is_object($backups) && method_exists($backups, 'firstItem') && $backups->firstItem() !== null ? $backups->firstItem() : 1) + $loop->index }}</td>
                            <td>
                                <strong>{{ $backup['filename'] }}</strong>
                                <div class="table-secondary">File SQL privat</div>
                            </td>
                            <td>
                                @if ($backup['is_safety'])
                                    <span class="badge badge-warning">Backup pengaman</span>
                                @else
                                    <span class="badge badge-neutral">Backup manual</span>
                                @endif
                            </td>
                            <td>{{ number_format($backup['size'] / 1024, 2) }} KB</td>
                            <td>{{ $backup['modified_at']->format('d/m/Y H:i:s') }}</td>
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('admin.database-backups.download', $backup['filename']) }}" class="action-link">Unduh</a>
                                    <form method="POST" action="{{ route('admin.database-backups.destroy', $backup['filename']) }}" onsubmit="return confirm('Hapus file backup ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="action-button">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty-state">
                                Belum ada backup. Buat backup pertama sebelum melakukan pengujian berikutnya.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Tindakan berisiko tinggi</p>
                <h2>Pulihkan database</h2>
                <p class="panel-description">
                    Restore mengganti struktur dan isi database saat ini. Sistem otomatis membuat backup pengaman sebelum restore dimulai.
                </p>
            </div>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger content-alert" style="margin: 20px 24px 0;">
                <strong>Restore belum dapat dijalankan.</strong>
                <ul class="error-list">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('admin.database-backups.restore') }}"
            enctype="multipart/form-data"
            class="data-form"
            onsubmit="return confirm('Pulihkan database dari file ini? Data saat ini akan diganti.');"
        >
            @csrf

            <div class="form-grid">
                <div class="form-field form-field-full">
                    <label for="backup_file">File backup aplikasi <span>*</span></label>
                    <input id="backup_file" name="backup_file" type="file" accept=".sql" required>
                    <small>Hanya file .sql yang dibuat melalui modul ini. Ukuran maksimal 50 MB.</small>
                </div>

                <div class="form-field">
                    <label for="current_password">Password Super Admin saat ini <span>*</span></label>
                    <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
                </div>

                <div class="form-field">
                    <label for="confirmation">Konfirmasi tindakan <span>*</span></label>
                    <input id="confirmation" name="confirmation" type="text" autocomplete="off" placeholder="PULIHKAN DATABASE" required>
                    <small>Ketik tepat: <strong>PULIHKAN DATABASE</strong></small>
                </div>
            </div>

            <div class="alert alert-danger content-alert" style="margin-top: 22px;">
                Jangan menutup browser atau menghentikan server selama restore berlangsung. Jika proses gagal, gunakan backup pengaman yang dibuat otomatis.
            </div>

            <div class="form-actions">
                <button type="submit" class="button-danger">Pulihkan database</button>
            </div>
        </form>
    </section>
@endsection
