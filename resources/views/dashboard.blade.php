@extends('layouts.app')
@section('title','Overview')
@section('crumb','Overview')
@section('content')
<div class="page-heading">
    <div>
        <div class="eyebrow">YOUR WORKSPACE</div>
        <h1>Good to see you, {{ auth()->user()->name }}.</h1>
        <p>Review your document originality and manage your submissions.</p>
    </div>
    <a class="btn btn-primary" href="{{ route('submissions.create') }}">＋ New submission</a>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);">
    <div class="stat-card">
        <div class="stat-top"><span>Total submissions</span><span class="stat-icon blue">▤</span></div>
        <div class="stat-value">{{ $total }}</div>
        <div class="stat-foot">{{ $completed }} completed reports</div>
    </div>
    <div class="stat-card">
        <div class="stat-top"><span>Average similarity</span><span class="stat-icon purple">◉</span></div>
        <div class="stat-value">{{ number_format($average, 1) }}<small>%</small></div>
        <div class="stat-foot">Across all checked files</div>
    </div>
    <div class="stat-card">
        <div class="stat-top"><span>Score distribution</span><span class="stat-icon green">✓</span></div>
        <div class="stat-value" style="font-size:18px;margin-top:18px;">
            <span style="color:#188458;">{{ $lowCount }} Low</span> ·
            <span style="color:#ad7a12;">{{ $midCount }} Mid</span> ·
            <span style="color:#c34e4b;">{{ $highCount }} High</span>
        </div>
        <div class="stat-foot">&lt;20% safe · 20–35% review · &gt;35% high</div>
    </div>
    <div class="stat-card">
        <div class="stat-top"><span>Indexed sources</span><span class="stat-icon blue">◈</span></div>
        <div class="stat-value">{{ number_format($sourceCount) }}</div>
        <div class="stat-foot">Journals &amp; web sources cached</div>
    </div>
</div>

<div class="section-heading">
    <div>
        <h2>Recent submissions</h2>
        <p>Your latest document checks</p>
    </div>
    <a class="text-link" href="{{ route('submissions.index') }}">View all →</a>
</div>

<div class="panel table-panel">
    @if($recent->isEmpty())
        <div class="empty-state">
            <div class="empty-icon">▤</div>
            <h3>No submissions yet</h3>
            <p>Upload your first document to generate a similarity report.</p>
            <a class="btn btn-primary" href="{{ route('submissions.create') }}">Check a document</a>
        </div>
    @else
        <table>
            <thead>
                <tr>
                    <th>DOCUMENT</th>
                    <th>DATE</th>
                    <th>SIMILARITY</th>
                    <th>STATUS</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach($recent as $item)
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
                    <td>{{ $item->created_at->format('d M Y') }}</td>
                    <td>
                        <span class="score-pill {{ $item->similarity_score < 20 ? 'score-low' : ($item->similarity_score <= 35 ? 'score-mid' : 'score-high') }}">
                            {{ number_format($item->similarity_score, 2) }}%
                        </span>
                    </td>
                    <td><span class="status-complete">● Completed</span></td>
                    <td><a class="row-action" href="{{ route('reports.show', $item) }}">View report →</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="notice">
    <span>ⓘ</span>
    <div>
        <strong>Deep Research &amp; No Repository Active</strong>
        <p>Every check automatically scans academic indexes (OpenAlex, Crossref, DOAJ, Semantic Scholar) and web repositories while keeping your private submissions excluded from public indexing unless unchecked.</p>
    </div>
</div>
@endsection