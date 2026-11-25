@extends('layouts.app')
@section('title', 'Verifikasi Pembayaran')
@section('crumb', 'Verifikasi Pembayaran')

@section('content')
<div class="page-heading">
    <div>
        <div class="eyebrow">ADMIN PANEL</div>
        <h1>Verifikasi Pembayaran Kuota</h1>
        <p>Periksa bukti transfer masuk dan setujui penambahan kuota pengguna.</p>
    </div>
</div>

@if(session('success'))
    <div class="notice" style="border-color:#188458; background:#f0faf5; color:#126643; margin-bottom:20px;">
        <span>✓</span>
        <div>
            <strong>Berhasil</strong>
            <p>{{ session('success') }}</p>
        </div>
    </div>
@endif

<div class="panel table-panel">
    @if($transactions->isEmpty())
        <div class="empty-state">
            <div class="empty-icon">▤</div>
            <h3>Belum ada pembayaran masuk</h3>
            <p>Permintaan top-up kuota dari pengguna akan muncul di sini.</p>
        </div>
    @else
        <table>
            <thead>
                <tr>
                    <th>PENGGUNA</th>
                    <th>PAKET</th>
                    <th>NOMINAL</th>
                    <th>BUKTI TRANSFER</th>
                    <th>STATUS</th>
                    <th>AKSI</th>
                </tr>
            </thead>
            <tbody>
                @foreach($transactions as $trx)
                    <tr>
                        <td>
                            <strong>{{ $trx->user->name }}</strong><br>
                            <small>{{ $trx->user->email }}</small>
                        </td>
                        <td>{{ $trx->package_name }} (+{{ $trx->quota_amount }})</td>
                        <td>Rp {{ number_format($trx->price, 0, ',', '.') }}</td>
                        <td>
                            <a href="{{ asset('storage/' . $trx->proof_image) }}" target="_blank" class="text-link">
                                Lihat Bukti ↗
                            </a>
                        </td>
                        <td>
                            @if($trx->status === 'approved')
                                <span class="score-pill score-low">● Approved</span>
                            @elseif($trx->status === 'rejected')
                                <span class="score-pill score-high">● Rejected</span>
                            @else
                                <span class="score-pill score-mid">● Pending</span>
                            @endif
                        </td>
                        <td>
                            @if($trx->status === 'pending')
                                <div style="display:flex; gap:8px;">
                                    <form action="{{ route('admin.transactions.approve', $trx) }}" method="POST">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="btn btn-primary" style="padding:6px 12px; font-size:12px;">Approve</button>
                                    </form>
                                    <form action="{{ route('admin.transactions.reject', $trx) }}" method="POST">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="btn" style="padding:6px 12px; font-size:12px; background:#fee2e2; color:#b91c1c;">Tolak</button>
                                    </form>
                                </div>
                            @else
                                <span style="font-size:12px; color:#888;">Selesai</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection