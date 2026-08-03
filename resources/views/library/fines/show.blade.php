@extends('layouts.app')

@section('title', 'Detail Denda')
@section('page-title', 'Detail dan Pembayaran Denda')

@section('content')
    @php
        $loan = $loanItem->loan;
        $member = $loan?->member;
        $asset = $loanItem->asset;
        $isPaid = $remainingAmount <= 0;
    @endphp

    <div class="detail-heading">
        <div>
            <p class="eyebrow">{{ $loan?->loan_code }}</p>
            <h2>{{ $member?->member_name }}</h2>
            <div class="detail-badges">
                <span class="badge {{ $isPaid ? 'badge-success' : ($paidAmount > 0 ? 'badge-warning' : 'badge-danger') }}">
                    {{ $isPaid ? 'Lunas' : ($paidAmount > 0 ? 'Dibayar sebagian' : 'Belum dibayar') }}
                </span>
                <span class="badge badge-neutral">{{ $member?->member_code }}</span>
            </div>
        </div>
        <div class="detail-actions">
            <a href="{{ route('library.fines.index') }}" class="button-secondary">Kembali</a>
            <a href="{{ route('library.loans.show', $loan) }}" class="button-secondary">Detail peminjaman</a>
        </div>
    </div>

    <div class="detail-grid detail-grid-fines">
        <section class="panel detail-card">
            <div class="panel-header"><h2>Informasi Tagihan</h2></div>
            <dl class="definition-list">
                <div><dt>Anggota</dt><dd>{{ $member?->member_name }}</dd></div>
                <div><dt>Kode anggota</dt><dd>{{ $member?->member_code }}</dd></div>
                <div><dt>Judul buku</dt><dd>{{ $asset?->item?->item_name }}</dd></div>
                <div><dt>Kode aset</dt><dd>{{ $asset?->asset_code }}</dd></div>
                <div><dt>Jatuh tempo</dt><dd>{{ $loanItem->due_date?->translatedFormat('d F Y') }}</dd></div>
                <div><dt>Dikembalikan</dt><dd>{{ $loanItem->returned_at?->translatedFormat('d F Y H:i') }}</dd></div>
                <div><dt>Status pengembalian</dt><dd>{{ $loanItem->returnStatusLabel() }}</dd></div>
                <div><dt>Kondisi kembali</dt><dd>{{ ucfirst((string) $loanItem->condition_in) }}</dd></div>
            </dl>
        </section>

        <section class="panel detail-card fine-balance-card">
            <div class="panel-header"><h2>Ringkasan Pembayaran</h2></div>
            <div class="fine-balance-grid">
                <div><span>Denda final</span><strong>Rp{{ number_format((float) $loanItem->fine_amount, 0, ',', '.') }}</strong></div>
                <div><span>Sudah dibayar</span><strong>Rp{{ number_format($paidAmount, 0, ',', '.') }}</strong></div>
                <div class="{{ $remainingAmount > 0 ? 'fine-balance-outstanding' : 'fine-balance-paid' }}">
                    <span>Sisa tagihan</span>
                    <strong>Rp{{ number_format($remainingAmount, 0, ',', '.') }}</strong>
                </div>
            </div>
        </section>
    </div>

    @if (! $isPaid)
        <section class="panel form-panel">
            <div class="panel-header">
                <div>
                    <p class="eyebrow">Transaksi pembayaran</p>
                    <h2>Catat Pembayaran Denda</h2>
                </div>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger form-alert">
                    <strong>Pembayaran belum dapat disimpan.</strong>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('library.fines.store', $loanItem) }}" class="form-body">
                @csrf

                <div class="form-grid">
                    <div class="form-field">
                        <label for="amount">Nominal pembayaran <span class="required">*</span></label>
                        <input
                            id="amount"
                            name="amount"
                            type="number"
                            min="0.01"
                            max="{{ number_format($remainingAmount, 2, '.', '') }}"
                            step="0.01"
                            value="{{ old('amount', number_format($remainingAmount, 2, '.', '')) }}"
                            required
                        >
                        <small>Maksimal Rp{{ number_format($remainingAmount, 0, ',', '.') }}. Pembayaran sebagian diperbolehkan.</small>
                    </div>

                    <div class="form-field">
                        <label for="payment_method">Metode pembayaran <span class="required">*</span></label>
                        <select id="payment_method" name="payment_method" required>
                            <option value="cash" @selected(old('payment_method', 'cash') === 'cash')>Tunai</option>
                            <option value="transfer" @selected(old('payment_method') === 'transfer')>Transfer</option>
                            <option value="other" @selected(old('payment_method') === 'other')>Lainnya</option>
                        </select>
                    </div>

                    <div class="form-field form-field-full">
                        <label for="notes">Catatan pembayaran</label>
                        <textarea id="notes" name="notes" rows="4" maxlength="2000" placeholder="Nomor referensi transfer atau catatan lain">{{ old('notes') }}</textarea>
                    </div>
                </div>

                <div class="fine-payment-warning">
                    Periksa nominal sebelum menyimpan. Pembayaran yang sudah dicatat tidak dapat dihapus melalui halaman ini agar riwayat keuangan tetap terjaga.
                </div>

                <div class="form-actions">
                    <a href="{{ route('library.fines.index') }}" class="button-secondary">Batal</a>
                    <button type="submit" class="button-primary">Simpan pembayaran</button>
                </div>
            </form>
        </section>
    @else
        <div class="inline-notice fine-paid-notice">
            Denda telah dibayar lunas. Riwayat pembayaran tetap tersedia di bawah ini.
        </div>
    @endif

    <section class="panel">
        <div class="panel-header">
            <div>
                <p class="eyebrow">Jejak transaksi</p>
                <h2>Riwayat Pembayaran</h2>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th class="table-number-heading">No.</th>
                        <th>Kode pembayaran</th>
                        <th>Tanggal</th>
                        <th>Nominal</th>
                        <th>Metode</th>
                        <th>Petugas</th>
                        <th>Catatan</th>
                        <th class="table-actions-heading">Kuitansi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($loanItem->finePayments as $payment)
                        <tr><td class="table-number">{{ (is_object($loanItem->finePayments) && method_exists($loanItem->finePayments, 'firstItem') && $loanItem->finePayments->firstItem() !== null ? $loanItem->finePayments->firstItem() : 1) + $loop->index }}</td>
                            <td><strong>{{ $payment->payment_code }}</strong></td>
                            <td>{{ $payment->payment_date?->translatedFormat('d F Y H:i') }}</td>
                            <td><strong>Rp{{ number_format((float) $payment->amount, 0, ',', '.') }}</strong></td>
                            <td>{{ $payment->paymentMethodLabel() }}</td>
                            <td>{{ $payment->receiver?->full_name ?? '-' }}</td>
                            <td>{{ $payment->notes ?: '-' }}</td>
                            <td>
                                <a href="{{ route('library.fines.receipt', $payment) }}" class="action-link" target="_blank" rel="noopener">Cetak</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty-state">Belum ada pembayaran untuk tagihan ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
