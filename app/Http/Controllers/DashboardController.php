<?php

namespace App\Http\Controllers;

use App\Models\ComparisonSource;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $query = $request->user()->submissions();

        return view('dashboard', [
            'total' => (clone $query)->count(),
            'completed' => (clone $query)->where('status', 'completed')->count(),
            'average' => round((clone $query)->where('status', 'completed')->avg('similarity_score') ?? 0, 2),
            'lowCount' => (clone $query)->where('similarity_score', '<', 20)->count(),
            'midCount' => (clone $query)->whereBetween('similarity_score', [20, 35])->count(),
            'highCount' => (clone $query)->where('similarity_score', '>', 35)->count(),
            'sourceCount' => ComparisonSource::where('is_active', true)->count(),
            'recent' => (clone $query)->latest()->take(6)->get(),
        ]);
    }
}