<?php

namespace App\Http\Controllers;

use App\Models\Submission;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function show(Submission $submission)
    {
        $user = Auth::user();

        // Pastikan hanya pemilik dokumen atau admin yang bisa mengakses
        if ($submission->user_id !== $user->id && $user->role !== 'admin') {
            abort(403);
        }

        // Cek apakah dokumen masih terkunci (belum pernah di-download)
        if (!$submission->is_unlocked && $user->role !== 'admin') {
            if ($user->download_quota <= 0) {
                return redirect()->route('billing.index')
                    ->with('error', 'Kuota download Anda sudah habis. Silakan top-up kuota untuk mengunduh laporan berikutnya.');
            }

            // Potong 1 kuota dan buka kunci dokumen ini selamanya
            $user->decrement('download_quota');
            $submission->update(['is_unlocked' => true]);
        }

        return view('reports.show', compact('submission'));
    }
}