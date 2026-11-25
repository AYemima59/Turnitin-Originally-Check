@extends('layouts.app')
@section('title', 'Top-Up Kuota')
@section('crumb', 'Top-Up Kuota')

@section('content')
<div class="page-heading">
    <div>
        <div class="eyebrow">BILLING &amp; QUOTA</div>
        <h1>Top-Up Kuota Download</h1>
        <p>Sisa kuota download Anda saat ini: <strong>{{ auth()->user()->download_quota }}x</strong></p>
    </div>
</div>

@if(session('error'))
    <div class="notice" style="border-color:#c34e4b; background:#fdf3f2; color:#9e3330; margin-bottom:20px;">
        <span>⚠</span>
        <div>
            <strong>Akses Terbatas</strong>
            <p>{{ session('error') }}</p>
        </div>
    </div>
@endif

@if(session('success'))
    <div class="notice" style="border-color:#188458; background:#f0faf5; color:#126643; margin-bottom:20px;">
        <span>✓</span>
        <div>
            <strong>Berhasil</strong>
            <p>{{ session('success') }}</p>
        </div>
    </div>
@endif

<div class="stats-grid" style="grid-template-columns:repeat(2,1fr); margin-bottom:24px;">
    <!-- Instruksi Pembayaran -->
    <div class="panel" style="padding:24px;">
        <div class="eyebrow" style="margin-bottom:6px;">LANGKAH 1</div>
        <h3 style="margin:0 0 8px 0; font-size:18px;">Transfer Manual</h3>
        <p style="font-size:14px; color:#555; margin-bottom:16px;">Silakan transfer sesuai nominal paket yang dipilih ke salah satu rekening berikut:</p>
        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px; font-size:14px; line-height:1.7;">
            <div><strong>Bank BCA:</strong> 1234567890 a.n. Yemima Alda</div>
            <div><strong>Bank Jago / e-Wallet:</strong> 081234567890 a.n. Yemima Alda</div>
        </div>
    </div>

    <!-- Form Konfirmasi -->
    <div class="panel" style="padding:24px;">
        <div class="eyebrow" style="margin-bottom:6px;">LANGKAH 2</div>
        <h3 style="margin:0 0 14px 0; font-size:18px;">Konfirmasi &amp; Upload Bukti</h3>
        <form action="{{ route('billing.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">Pilih Paket Kuota</label>
                <select id="package_select" onchange="updatePackage()" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px;">
                    @foreach($packages as $pkg)
                        <option value="{{ $pkg['name'] }}" data-quota="{{ $pkg['quota'] }}" data-price="{{ $pkg['price'] }}">
                            {{ $pkg['name'] }} — Rp {{ number_format($pkg['price'], 0, ',', '.') }}
                        </option>
                    @endforeach
                </select>
                <input type="hidden" name="package_name" id="package_name" value="{{ $packages[0]['name'] }}">
                <input type="hidden" name="quota_amount" id="quota_amount" value="{{ $packages[0]['quota'] }}">
                <input type="hidden" name="price" id="price" value="{{ $packages[0]['price'] }}">
            </div>

            <div style="margin-bottom:16px;">
                <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">Unggah Bukti Transfer (JPG/PNG, Maks 2MB)</label>
                <input type="file" name="proof_image" accept="image/*" required style="width:100%; font-size:13px;">
            </div>

            <button type="submit" class="btn btn-primary">Kirim Bukti Pembayaran</button>
        </form>
    </div>
</div>

<div class="section-heading">
    <div>
        <h2>Riwayat Pembelian Kuota</h2>
        <p>Status verifikasi pembayaran top-up Anda</p>
    </div>
</div>

<div class="panel table-panel">
    @if($transactions->isEmpty())
        <div class="empty-state">
            <div class="empty-icon">▤</div>
            <h3>Belum ada transaksi</h3>
            <p>Riwayat pembelian kuota Anda akan muncul di sini.</p>
        </div>
    @else
        <table>
            <thead>
                <tr>
                    <th>TANGGAL</th>
                    <th>PAKET</th>
                    <th>NOMINAL</th>
                    <th>STATUS</th>
                </tr>
            </thead>
            <tbody>
                @foreach($transactions as $trx)
                    <tr>
                        <td>{{ $trx->created_at->format('d M Y, H:i') }}</td>
                        <td><strong>{{ $trx->package_name }}</strong> (+{{ $trx->quota_amount }}x)</td>
                        <td>Rp {{ number_format($trx->price, 0, ',', '.') }}</td>
                        <td>
                            @if($trx->status === 'approved')
                                <span class="score-pill score-low">● Disetujui</span>
                            @elseif($trx->status === 'rejected')
                                <span class="score-pill score-high">● Ditolak</span>
                            @else
                                <span class="score-pill score-mid">● Menunggu Verifikasi</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<script>
    function updatePackage() {
        const select = document.getElementById('package_select');
        const selected = select.options[select.selectedIndex];
        document.getElementById('package_name').value = selected.value;
        document.getElementById('quota_amount').value = selected.getAttribute('data-quota');
        document.getElementById('price').value = selected.getAttribute('data-price');
    }
</script>
@endsection