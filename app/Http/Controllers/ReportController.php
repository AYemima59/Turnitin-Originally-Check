<?php

namespace App\Http\Controllers;

use App\Models\Submission;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function show(Request $request, Submission $submission)
    {
        abort_unless($submission->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
        return view('reports.show', compact('submission'));
    }
}
