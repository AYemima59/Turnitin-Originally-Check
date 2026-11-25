@extends('layouts.app')
@section('title','My submissions')
@section('crumb','My submissions')
@section('content')
<div class="page-heading">
    <div>
        <div class="eyebrow">DOCUMENT HISTORY</div>
        <h1>My submissions</h1>
        <p>All your previous similarity checks in one place.</p>
    </div>
    <a class="btn btn-primary" href="{{ route('submissions.create') }}">＋ New submission</a>
</div>

<div class="panel table-panel">
    <table>
        <thead>
            <tr>
                <th>DOCUMENT</th>
                <th>SUBMITTED</th>
                <th>WORDS MATCHED</th>
                <th>SIMILARITY</th>
                <th>REPOSITORY</th>
                <th>ACTIONS</th>
            </tr>
        </thead>
        <tbody>
        @forelse($submissions as $item)
            <tr>
                <td>
                    <div class="file-cell">
                        <span class="file-icon">{{ strtoupper($item->file_type) }}</span>
                        <div>
                            <strong>{{ $item->title }}</strong>
                            <small>{{ $item->original_filename }}</small>
                        </div>
                    </div>
                </td>
                <td>{{ $item->created_at->format('d M Y, H:i') }}</td>
                <td>{{ number_format($item->matched_words) }} / {{ number_format($item->total_words) }}</td>
                <td>
                    <span class="score-pill {{ $item->similarity_score < 20 ? 'score-low' : ($item->similarity_score <= 35 ? 'score-mid' : 'score-high') }}">
                        {{ number_format($item->similarity_score, 2) }}%
                    </span>
                </td>
                <td>
                    <small style="color:{{ $item->no_repository ? '#718096' : '#087fbd' }};font-weight:600;">
                        {{ $item->no_repository ? 'No Repository' : 'Indexed' }}
                    </small>
                </td>
                <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <a class="row-action" href="{{ route('reports.show', $item) }}">View →</a>
                        <form method="POST" action="{{ route('submissions.recheck', $item) }}" style="display:inline;" onsubmit="this.querySelector('button').textContent='...';">
                            @csrf
                            <button type="submit" class="tiny-btn" title="Re-scan document">↻ Re-check</button>
                        </form>
                        <form method="POST" action="{{ route('submissions.destroy', $item) }}" style="display:inline;" onsubmit="return confirm('Hapus riwayat dokumen ini?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="tiny-btn danger" title="Delete">✕</button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6">
                    <div class="empty-state">
                        <h3>No submissions found</h3>
                        <p>Your checked documents will appear here.</p>
                    </div>
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
    <div class="pagination-wrap">{{ $submissions->links() }}</div>
</div>
@endsection