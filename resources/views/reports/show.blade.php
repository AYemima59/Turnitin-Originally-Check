@extends('layouts.app')
@section('title','Similarity report')
@section('crumb','Similarity report')
@section('content')
@php
    $report = $submission->report_data ?? [];
    $palette = [
        1 => ['bg' => '#ffd6d6', 'border' => '#e04848', 'badge' => '#d93838'],
        2 => ['bg' => '#e4d9ff', 'border' => '#7c4dff', 'badge' => '#6b3ce9'],
        3 => ['bg' => '#d2ecff', 'border' => '#0284c7', 'badge' => '#0369a1'],
        4 => ['bg' => '#ccf5e7', 'border' => '#0d9488', 'badge' => '#0f766e'],
        5 => ['bg' => '#ffedd5', 'border' => '#ea580c', 'badge' => '#c2410c'],
        6 => ['bg' => '#fce7f3', 'border' => '#db2777', 'badge' => '#be185d'],
    ];
@endphp

<div class="report-header">
    <div>
        <a class="back-link" href="{{ route('submissions.index') }}">← Back to submissions</a>
        <div class="eyebrow mt-16">ORIGINALITY REPORT</div>
        <h1>{{ $submission->title }}</h1>
        <p>{{ $submission->original_filename }} · Submitted {{ $submission->created_at->format('d M Y, H:i') }}</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <form method="POST" action="{{ route('submissions.recheck', $submission) }}" onsubmit="this.querySelector('button').textContent='Scanning...';">
            @csrf
            <button type="submit" class="btn btn-secondary">↻ Re-check</button>
        </form>
        <button class="btn btn-primary" onclick="window.print()">↓ Print / Save PDF</button>
    </div>
</div>

<!-- Live Turnitin Filter Toolbar -->
<div class="report-toolbar no-print">
    <div class="filter-form" style="justify-content:space-between;">
        <div style="display:flex;align-items:center;gap:18px;flex-wrap:wrap;">
            <strong>Live Filters:</strong>
            <label><input type="checkbox" id="filter-exclude-quotes"> Exclude Quotes ("...")</label>
            <label>
                Exclude Sources Below:
                <select id="filter-min-words">
                    <option value="0" selected>Show all sources</option>
                    <option value="10">10 words</option>
                    <option value="15">15 words</option>
                    <option value="25">25 words (&lt; 1%)</option>
                </select>
            </label>
        </div>
        <button type="button" id="reset-filters-btn" class="btn btn-secondary btn-small" style="display:none;">Reset Exclusions</button>
    </div>
</div>

<div class="report-summary">
    <div class="score-main">
        <div class="score-ring" id="live-score-ring" style="--score: {{ min(100,(float)$submission->similarity_score) }}">
            <div>
                <strong id="live-score-text">{{ number_format($submission->similarity_score,0) }}<small>%</small></strong>
                <span>SIMILARITY</span>
            </div>
        </div>
        <div>
            <h2>Similarity score</h2>
            <p>Percentage of document words matched against academic journals, repositories, and internet sources.</p>
        </div>
    </div>
    <div class="summary-stat">
        <strong id="live-matched-words">{{ number_format($submission->matched_words) }}</strong>
        <span>Matched words</span>
    </div>
    <div class="summary-stat">
        <strong id="live-total-words" data-total="{{ max(1, (int)$submission->total_words) }}">{{ number_format($submission->total_words) }}</strong>
        <span>Total words</span>
    </div>
    <div class="summary-stat">
        <strong id="live-matched-sources">{{ count($report['matches'] ?? []) }}</strong>
        <span>Matched sources</span>
    </div>
</div>

<div class="report-grid">
    <section class="panel document-panel">
        <div class="panel-heading">
            <div>
                <h2>Document viewer</h2>
                <p>Color-coded highlights with source index badges.</p>
            </div>
            <span class="legend"><i></i> Matched passage</span>
        </div>
        <article class="document-page" style="white-space: pre-wrap;">
            <div class="document-meta">SUBMITTED DOCUMENT · {{ strtoupper($submission->file_type) }} · {{ number_format($submission->total_words) }} WORDS</div>@foreach(($report['segments'] ?? []) as $segment)@if($segment['matched'])@php($sNum = $segment['source_num'] ?? 1)@php($c = $palette[(($sNum - 1) % 6) + 1])@php($wCount = $segment['word_count'] ?? max(1, str_word_count($segment['text'])))<mark class="turnitin-mark" data-source="{{ $sNum }}" data-words="{{ $wCount }}" data-quote="{{ !empty($segment['in_quote']) ? '1' : '0' }}" data-bg="{{ $c['bg'] }}" data-border="{{ $c['border'] }}" style="background:{{ $c['bg'] }};border-bottom:2px solid {{ $c['border'] }};"><sup class="turnitin-sup" style="background:{{ $c['badge'] }};">{{ $sNum }}</sup>{{ $segment['text'] }}</mark>@else{{ $segment['text'] }}@endif @endforeach
        </article>
    </section>

    <aside class="panel matches-panel">
        <div class="panel-heading">
            <div>
                <h2>Match overview</h2>
                <p id="overview-subtitle">{{ count($report['matches'] ?? []) }} sources found</p>
            </div>
        </div>
        @forelse(($report['matches'] ?? []) as $index => $match)
            @php($num = $match['rank'] ?? ($index + 1))
            @php($c = $palette[(($num - 1) % 6) + 1])
            @php($pct = $match['percentage'] ?? max(1, round(($match['matched_words'] / max(1, $submission->total_words)) * 100)))
            <div class="match-item" data-source-row="{{ $num }}" data-words="{{ $match['matched_words'] }}">
                <div class="match-number" style="background:{{ $c['badge'] }};">{{ $num }}</div>
                <div class="match-content">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
                        <strong>{{ $match['title'] }}</strong>
                        <span style="font-weight:800;font-size:13px;color:{{ $c['badge'] }};white-space:nowrap;">{{ $pct }}%</span>
                    </div>
                    <small>{{ $match['source_type'] }} · {{ $match['matched_words'] }} matched words</small>
                    <div class="match-bar">
                        <span style="width:{{ min(100, ($match['matched_words'] / max(1, $submission->total_words)) * 100) }}%;background:{{ $c['badge'] }};"></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:7px;">
                        @if(!empty($match['url']))
                            <a href="{{ $match['url'] }}" target="_blank" rel="noopener" style="margin-top:0;">Open source ↗</a>
                        @else
                            <span style="font-size:9px;color:#94a0ae;">Local Repository</span>
                        @endif
                        <button type="button" class="tiny-btn exclude-source-btn no-print" data-exclude-id="{{ $num }}" title="Exclude this source">✕ Exclude</button>
                    </div>
                </div>
            </div>
        @empty
            <div class="no-matches">
                <div>✓</div>
                <strong>No matches found</strong>
                <p>No matching phrases were detected across academic journals, repositories, or web sources.</p>
            </div>
        @endforelse
    </aside>
</div>

<div class="report-disclaimer">
    ⓘ Independent Originality Report · Analyzed against academic publication indexes (OpenAlex, Crossref, DOAJ, Semantic Scholar), web repositories, and internal library.
</div>

<script>
(function() {
    const excludedSources = new Set();
    const excludeQuotesCb = document.getElementById('filter-exclude-quotes');
    const minWordsSelect = document.getElementById('filter-min-words');
    const resetBtn = document.getElementById('reset-filters-btn');
    const totalWords = parseInt(document.getElementById('live-total-words').dataset.total || '1', 10);

    function recalculateLive() {
        const hideQuotes = excludeQuotesCb.checked;
        const minWordsThreshold = parseInt(minWordsSelect.value || '0', 10);
        const smallSources = new Set();

        document.querySelectorAll('.match-item').forEach(row => {
            const sId = row.dataset.sourceRow;
            const sWords = parseInt(row.dataset.words || '0', 10);
            if (sWords < minWordsThreshold) {
                smallSources.add(sId);
            }
        });

        let activeMatchedWords = 0;
        const activeSourceIds = new Set();

        document.querySelectorAll('.turnitin-mark').forEach(mark => {
            const sId = mark.dataset.source;
            const wCount = parseInt(mark.dataset.words || '0', 10);
            const isQuote = mark.dataset.quote === '1';
            const sup = mark.querySelector('.turnitin-sup');

            const isHidden = excludedSources.has(sId) || smallSources.has(sId) || (hideQuotes && isQuote);

            if (isHidden) {
                mark.style.background = 'transparent';
                mark.style.borderBottom = 'none';
                if (sup) sup.style.display = 'none';
            } else {
                mark.style.background = mark.dataset.bg;
                mark.style.borderBottom = '2px solid ' + mark.dataset.border;
                if (sup) sup.style.display = 'inline-block';
                activeMatchedWords += wCount;
                activeSourceIds.add(sId);
            }
        });

        document.querySelectorAll('.match-item').forEach(row => {
            const sId = row.dataset.sourceRow;
            const btn = row.querySelector('.exclude-source-btn');
            if (excludedSources.has(sId) || smallSources.has(sId)) {
                row.style.opacity = '0.42';
                if (btn && excludedSources.has(sId)) btn.textContent = '↺ Restore';
            } else {
                row.style.opacity = '1';
                if (btn) btn.textContent = '✕ Exclude';
            }
        });

        const pct = Math.min(100, Math.round((activeMatchedWords / totalWords) * 100));
        document.getElementById('live-score-text').innerHTML = pct + '<small>%</small>';
        document.getElementById('live-score-ring').style.setProperty('--score', pct);
        document.getElementById('live-matched-words').textContent = activeMatchedWords.toLocaleString();
        document.getElementById('live-matched-sources').textContent = activeSourceIds.size;
        document.getElementById('overview-subtitle').textContent = activeSourceIds.size + ' active sources';

        resetBtn.style.display = (excludedSources.size > 0 || hideQuotes || minWordsThreshold > 0) ? 'inline-flex' : 'none';
    }

    document.querySelectorAll('.exclude-source-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.excludeId;
            if (excludedSources.has(id)) excludedSources.delete(id);
            else excludedSources.add(id);
            recalculateLive();
        });
    });

    excludeQuotesCb.addEventListener('change', recalculateLive);
    minWordsSelect.addEventListener('change', recalculateLive);
    resetBtn.addEventListener('click', () => {
        excludedSources.clear();
        excludeQuotesCb.checked = false;
        minWordsSelect.value = '0';
        recalculateLive();
    });
})();
</script>
@endsection