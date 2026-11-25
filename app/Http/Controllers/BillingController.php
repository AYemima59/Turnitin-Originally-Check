<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BillingController extends Controller
{
    public function index()
    {
        $packages = [
            ['id' => 'paket_1', 'name' => 'Paket Hemat (3x Download)', 'quota' => 3, 'price' => 5000],
            ['id' => 'paket_2', 'name' => 'Paket Skripsi (10x Download)', 'quota' => 10, 'price' => 15000],
            ['id' => 'paket_3', 'name' => 'Paket Unlimited Revisi (25x Download)', 'quota' => 25, 'price' => 30000],
        ];

        $transactions = Transaction::where('user_id', Auth::id())->latest()->get();

        return view('billing.index', compact('packages', 'transactions'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'package_name' => 'required|string',
            'quota_amount' => 'required|integer|min:1',
            'price'        => 'required|integer|min:1000',
            'proof_image'  => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $path = $request->file('proof_image')->store('payment_proofs', 'public');

        Transaction::create([
            'user_id'      => Auth::id(),
            'package_name' => $request->package_name,
            'quota_amount' => $request->quota_amount,
            'price'        => $request->price,
            'proof_image'  => $path,
            'status'       => 'pending',
        ]);

        return back()->with('success', 'Bukti transfer berhasil dikirim! Menunggu konfirmasi admin.');
    }
}