<?php

namespace App\Http\Controllers;

use App\Models\ComparisonSource;
use App\Models\Submission;
use App\Services\DocumentTextExtractor;
use App\Services\SimilarityChecker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SubmissionController extends Controller
{
    public function index(Request $request)
    {
        $submissions = $request->user()->submissions()->latest()->paginate(10);
        return view('submissions.index', compact('submissions'));
    }

    public function create()
    {
        return view('submissions.create');
    }

    public function store(Request $request, DocumentTextExtractor $extractor, SimilarityChecker $checker)
    {
        $validated = $request->validate([
            'document' => ['required', 'file', 'mimes:pdf,docx', 'max:20480'],
            'title' => ['required', 'string', 'max:180'],
            'no_repository' => ['nullable'],
            'exclude_bibliography' => ['nullable'],
            'exclude_quotes' => ['nullable'],
            'min_words' => ['nullable', 'integer', 'min:4', 'max:15'],
        ], [
            'document.max' => 'Ukuran dokumen maksimal 20 MB.',
            'document.mimes' => 'Gunakan file PDF atau DOCX.',
        ]);

        $file = $request->file('document');
        $extension = strtolower($file->getClientOriginalExtension());
        $tempPath = $file->store('temporary-submissions', 'local');
        $isNoRepository = $request->boolean('no_repository');

        try {
            $absolutePath = Storage::disk('local')->path($tempPath);
            $text = $extractor->extract($absolutePath, $extension);

            $report = $checker->check($text, $validated['title'], [
                'exclude_bibliography' => $request->boolean('exclude_bibliography', true),
                'exclude_quotes' => $request->boolean('exclude_quotes', false),
                'min_words' => $request->input('min_words', 6),
            ]);

            $submission = $request->user()->submissions()->create([
                'title' => $validated['title'],
                'original_filename' => $file->getClientOriginalName(),
                'file_type' => $extension,
                'status' => 'completed',
                'similarity_score' => $report['score'],
                'matched_words' => $report['matched_words'],
                'total_words' => $report['total_words'],
                'extracted_text' => $text,
                'no_repository' => $isNoRepository,
                'report_data' => $report,
                'completed_at' => now(),
            ]);

            if (!$isNoRepository) {
                ComparisonSource::create([
                    'title' => $validated['title'],
                    'source_type' => 'Student Paper Repository',
                    'url' => null,
                    'content' => $text,
                    'is_active' => true,
                ]);
            }

            return redirect()->route('reports.show', $submission)->with('success', 'Pemeriksaan mendalam selesai.');
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['document' => $e->getMessage()]);
        } finally {
            Storage::disk('local')->delete($tempPath);
        }
    }

    public function recheck(Request $request, Submission $submission, SimilarityChecker $checker)
    {
        abort_unless($submission->user_id === $request->user()->id || $request->user()->isAdmin(), 403);

        $report = $checker->check($submission->extracted_text, $submission->title, [
            'exclude_bibliography' => true,
            'exclude_quotes' => false,
            'min_words' => 6,
        ]);

        $submission->update([
            'similarity_score' => $report['score'],
            'matched_words' => $report['matched_words'],
            'total_words' => $report['total_words'],
            'report_data' => $report,
            'completed_at' => now(),
        ]);

        return redirect()->route('reports.show', $submission)->with('success', 'Dokumen berhasil diperiksa ulang dengan sumber terbaru.');
    }

    public function destroy(Request $request, Submission $submission)
    {
        abort_unless($submission->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
        $submission->delete();
        return redirect()->route('submissions.index')->with('success', 'Riwayat pemeriksaan dihapus.');
    }
}