<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;

class TransactionController extends Controller
{
    public function index()
    {
        $transactions = Transaction::with('user')->latest()->get();
        return view('admin.transactions.index', compact('transactions'));
    }

    public function approve(Transaction $transaction)
    {
        if ($transaction->status === 'pending') {
            $transaction->update(['status' => 'approved']);
            $transaction->user->increment('download_quota', $transaction->quota_amount);
        }

        return back()->with('success', 'Pembayaran disetujui & kuota user telah ditambahkan.');
    }

    public function reject(Transaction $transaction)
    {
        if ($transaction->status === 'pending') {
            $transaction->update(['status' => 'rejected']);
        }

        return back()->with('success', 'Transaksi ditolak.');
    }
}