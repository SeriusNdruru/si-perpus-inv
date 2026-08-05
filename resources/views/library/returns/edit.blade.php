@extends('layouts.app')

@section('title', 'Proses Pengembalian')
@section('page-title', 'Proses Pengembalian Buku')

@section('content')
    @php
        $asset = $loanItem->asset;
        $loan = $loanItem->loan;
        $member = $loan?->member;
    @endphp

    <div class="detail-heading">
        <div>
            <p class="eyebrow">{{ $loan?->loan_code }}</p>
            <h2>{{ $asset?->item?->item_name }}</h2>
            <div class="detail-badges">
                <span class="badge {{ $daysLate > 0 ? 'badge-warning' : 'badge-success' }}">
                    {{ $daysLate > 0 ? $daysLate.' hari terlambat' : 'Tidak terlambat' }}
                </span>
                <span class="badge badge-neutral">{{ $asset?->asset_code }}</span>
            </div>
        </div>
        <div class="detail-actions">
            <a href="{{ route('library.returns.index') }}" class="button-secondary">Kembali</a>
            <a href="{{ route('library.loans.show', $loan) }}" class="button-secondary">Detail peminjaman</a>
        </div>
    </div>

    <div class="detail-grid detail-grid-return">
        <section class="panel detail-card">
            <div class="panel-header"><h2>Informasi Peminjaman</h2></div>
            <dl class="definition-list">
                <div><dt>Anggota</dt><dd>{{ $member?->member_name }}</dd></div>
                <div><dt>Kode anggota</dt><dd>{{ $member?->member_code }}</dd></div>
                <div><dt>Tanggal pinjam</dt><dd>{{ $loanItem->borrowed_at?->translatedFormat('d F Y H:i') }}</dd></div>
                <div><dt>Jatuh tempo</dt><dd>{{ $loanItem->due_date?->translatedFormat('d F Y') }}</dd></div>
                <div><dt>Kondisi keluar</dt><dd>{{ $loanItem->conditionOutLabel() }}</dd></div>
                <div><dt>Rak asal</dt><dd>{{ $asset?->shelf?->shelf_code ?? '-' }}</dd></div>
                <div class="definition-full"><dt>Barcode</dt><dd>{{ $asset?->barcode ?: '-' }}</dd></div>
            </dl>
        </section>

        <section class="panel detail-card return-fine-card">
            <div class="panel-header"><h2>Denda Final Saat Ini</h2></div>
            <div class="return-fine-amount">Rp{{ number_format($fineAmount, 0, ',', '.') }}</div>
            <dl class="definition-list">
                <div><dt>Keterlambatan</dt><dd>{{ number_format($daysLate) }} hari</dd></div>
                <div><dt>Tarif per hari</dt><dd>Rp{{ number_format($finePerDay, 0, ',', '.') }}</dd></div>
            </dl>
            <div class="inline-notice inline-notice-compact">
                Nilai denda dihitung ulang ketika tombol simpan ditekan. Pembayaran denda akan dibuat pada tahap pengelolaan denda berikutnya.
            </div>
        </section>
    </div>

    <section class="panel form-panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Pemeriksaan fisik</p>
                <h2>Hasil Pengembalian</h2>
            </div>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger form-alert">
                <strong>Data belum dapat disimpan.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('library.returns.update', $loanItem) }}" class="data-form return-edit-form">
            @csrf
            @method('PUT')

            <div class="form-grid">
                <div class="form-field">
                    <label for="return_status">Status pengembalian <span class="required">*</span></label>
                    <select id="return_status" name="return_status" required>
                        <option value="returned" @selected(old('return_status', 'returned') === 'returned')>Dikembalikan normal</option>
                        <option value="damaged" @selected(old('return_status') === 'damaged')>Dikembalikan dalam kondisi rusak</option>
                        <option value="lost" @selected(old('return_status') === 'lost')>Dinyatakan hilang</option>
                    </select>
                    <small>Pilihan rusak atau hilang akan langsung mengubah status aset.</small>
                </div>

                <div class="form-field">
                    <label for="condition_in">Kondisi saat kembali</label>
                    <select id="condition_in" name="condition_in">
                        <option value="good" @selected(old('condition_in', $loanItem->condition_out) === 'good')>Baik</option>
                        <option value="fair" @selected(old('condition_in', $loanItem->condition_out) === 'fair')>Cukup</option>
                    </select>
                    <small>Digunakan untuk pengembalian normal. Buku rusak dan hilang ditentukan otomatis.</small>
                </div>

                <div class="form-field form-field-full">
                    <label for="return_notes">Catatan pengembalian</label>
                    <textarea
                        id="return_notes"
                        name="return_notes"
                        rows="5"
                        maxlength="2000"
                        placeholder="Tuliskan kerusakan, kronologi kehilangan, atau catatan pemeriksaan lainnya"
                    >{{ old('return_notes') }}</textarea>
                    <small>Catatan wajib untuk buku rusak atau hilang.</small>
                </div>
            </div>

            <div class="return-warning">
                Pastikan kode aset dan judul buku sudah sesuai. Pengembalian yang disimpan akan mengubah status aset dan mencatat pergerakan stok secara otomatis.
            </div>

            <div class="form-actions">
                <a href="{{ route('library.returns.index') }}" class="button-secondary">Batal</a>
                <button type="submit" class="button-primary">Simpan pengembalian</button>
            </div>
        </form>
    </section>
@endsection
