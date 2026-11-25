<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ComparisonSource;
use Illuminate\Http\Request;

class SourceController extends Controller
{
    public function index()
    {
        return view('admin.sources.index', ['sources' => ComparisonSource::latest()->paginate(10)]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'source_type' => ['required', 'in:institutional,publication,web'],
            'url' => ['nullable', 'url', 'max:2048'],
            'content' => ['required', 'string', 'min:30'],
        ]);
        ComparisonSource::create($data);
        return back()->with('success', 'Sumber pembanding berhasil ditambahkan.');
    }

    public function toggle(ComparisonSource $source)
    {
        $source->update(['is_active' => !$source->is_active]);
        return back()->with('success', 'Status sumber diperbarui.');
    }

    public function destroy(ComparisonSource $source)
    {
        $source->delete();
        return back()->with('success', 'Sumber dihapus.');
    }
}
