<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kuitansi {{ $finePayment->payment_code }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 32px; color: #142033; background: #eef2f4; font-family: Arial, sans-serif; }
        .receipt { max-width: 760px; margin: 0 auto; padding: 38px; border: 1px solid #d8e0e5; border-radius: 16px; background: #fff; box-shadow: 0 14px 40px rgba(20,32,51,.08); }
        .receipt-header { display: flex; justify-content: space-between; gap: 24px; padding-bottom: 22px; border-bottom: 2px solid #172d3f; }
        .receipt-header h1 { margin: 0 0 8px; font-size: 26px; }
        .receipt-header p, .receipt-code span { margin: 0; color: #667483; }
        .receipt-code { text-align: right; }
        .receipt-code strong { display: block; margin-top: 6px; font-size: 17px; }
        .amount { margin: 30px 0; padding: 24px; border-radius: 12px; text-align: center; background: #edf8f5; }
        .amount span { display: block; margin-bottom: 8px; color: #58706c; font-size: 13px; }
        .amount strong { font-size: 34px; color: #0f675e; }
        dl { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 18px 28px; margin: 0; }
        dl div { padding-bottom: 12px; border-bottom: 1px solid #e4e9ec; }
        dt { margin-bottom: 6px; color: #71808e; font-size: 12px; font-weight: 700; text-transform: uppercase; }
        dd { margin: 0; font-weight: 700; line-height: 1.5; }
        .note { margin-top: 24px; padding: 16px; border: 1px solid #e1e7ea; border-radius: 10px; color: #536170; line-height: 1.6; }
        .receipt-footer { display: flex; justify-content: space-between; gap: 30px; margin-top: 42px; }
        .signature { min-width: 230px; text-align: center; }
        .signature-line { margin-top: 70px; padding-top: 8px; border-top: 1px solid #142033; font-weight: 700; }
        .actions { max-width: 760px; margin: 18px auto 0; text-align: right; }
        button { padding: 11px 18px; border: 0; border-radius: 9px; color: #fff; background: #11796e; font-weight: 700; cursor: pointer; }
        @media print {
            body { padding: 0; background: #fff; }
            .receipt { max-width: none; border: 0; border-radius: 0; box-shadow: none; }
            .actions { display: none; }
        }
        @media (max-width: 620px) {
            body { padding: 12px; }
            .receipt { padding: 24px; }
            .receipt-header, .receipt-footer { flex-direction: column; }
            .receipt-code { text-align: left; }
            dl { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    @php
        $loanItem = $finePayment->loanItem;
        $loan = $loanItem?->loan;
        $member = $loan?->member;
        $asset = $loanItem?->asset;
    @endphp

    <main class="receipt">
        <header class="receipt-header">
            <div>
                <h1>Kuitansi Pembayaran Denda</h1>
                <p>{{ config('app.name', 'Sistem Perpustakaan') }}</p>
            </div>
            <div class="receipt-code">
                <span>Kode pembayaran</span>
                <strong>{{ $finePayment->payment_code }}</strong>
            </div>
        </header>

        <section class="amount">
            <span>Jumlah yang diterima</span>
            <strong>Rp{{ number_format((float) $finePayment->amount, 0, ',', '.') }}</strong>
        </section>

        <dl>
            <div><dt>Nama anggota</dt><dd>{{ $member?->member_name }}</dd></div>
            <div><dt>Kode anggota</dt><dd>{{ $member?->member_code }}</dd></div>
            <div><dt>Kode peminjaman</dt><dd>{{ $loan?->loan_code }}</dd></div>
            <div><dt>Judul buku</dt><dd>{{ $asset?->item?->item_name }}</dd></div>
            <div><dt>Kode aset</dt><dd>{{ $asset?->asset_code }}</dd></div>
            <div><dt>Tanggal pembayaran</dt><dd>{{ $finePayment->payment_date?->translatedFormat('d F Y H:i') }}</dd></div>
            <div><dt>Metode</dt><dd>{{ $finePayment->paymentMethodLabel() }}</dd></div>
            <div><dt>Petugas</dt><dd>{{ $finePayment->receiver?->full_name ?? '-' }}</dd></div>
        </dl>

        @if ($finePayment->notes)
            <div class="note"><strong>Catatan:</strong> {{ $finePayment->notes }}</div>
        @endif

        <footer class="receipt-footer">
            <div>
                <small>Kuitansi ini dibuat oleh sistem dan menjadi bukti pencatatan pembayaran.</small>
            </div>
            <div class="signature">
                <span>Petugas penerima</span>
                <div class="signature-line">{{ $finePayment->receiver?->full_name ?? '________________' }}</div>
            </div>
        </footer>
    </main>

    <div class="actions">
        <button type="button" onclick="window.print()">Cetak kuitansi</button>
    </div>
</body>
</html>
