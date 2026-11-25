@extends('layouts.app')
@section('title','New submission')
@section('crumb','New submission')
@section('content')
<div class="page-heading">
    <div>
        <div class="eyebrow">DOCUMENT CHECK</div>
        <h1>New submission</h1>
        <p>Upload a document to run a deep paragraph-by-paragraph originality scan.</p>
    </div>
</div>

<div class="upload-layout">
    <form class="panel upload-panel" id="submission-form" method="POST" action="{{ route('submissions.store') }}" enctype="multipart/form-data">
        @csrf
        <label class="field-label" for="title">Document title</label>
        <input class="text-input" id="title" name="title" value="{{ old('title') }}" placeholder="e.g. Proposal Skripsi — Bab 1 s/d Bab 3" required>

        <label class="field-label mt-24">Upload document</label>
        <label class="dropzone" for="document" id="dropzone">
            <span class="upload-symbol">↑</span>
            <strong>Drop your document here</strong>
            <span>or <u>browse files</u></span>
            <small>PDF or DOCX · Maximum 20 MB</small>
            <input type="file" id="document" name="document" accept=".pdf,.docx" required hidden>
        </label>
        <div id="file-name" class="file-selected">No file selected</div>

        <div class="settings-card">
            <div class="settings-title">Turnitin Scan Filters &amp; Repository Settings</div>
            <label class="check-row">
                <input type="checkbox" name="exclude_bibliography" value="1" checked>
                <div>
                    <strong>Exclude Bibliography / Daftar Pustaka</strong>
                    <small>Automatically detects and ignores the References / Daftar Pustaka section.</small>
                </div>
            </label>
            <label class="check-row">
                <input type="checkbox" name="exclude_quotes" value="1">
                <div>
                    <strong>Exclude Quoted Text ("...")</strong>
                    <small>Ignores direct quotations enclosed in quotation marks.</small>
                </div>
            </label>
            <div class="check-row" style="align-items:center;justify-content:space-between;">
                <div>
                    <strong>Minimum Word Sequence (Sensitivity)</strong>
                    <small>Minimum consecutive words required to flag a match (Turnitin standard: 6–8 words).</small>
                </div>
                <select name="min_words" class="text-input" style="width:120px;padding:7px 10px;">
                    <option value="5">5 words (Strict)</option>
                    <option value="6" selected>6 words (Standard)</option>
                    <option value="8">8 words (Relaxed)</option>
                </select>
            </div>
            <label class="check-row" style="border-bottom:0;padding-bottom:0;">
                <input type="checkbox" name="no_repository" value="1" checked>
                <div>
                    <strong>No Repository (Private Check)</strong>
                    <small>Checked: document is NOT saved to local repository. Unchecked: saved as Student Paper Repository.</small>
                </div>
            </label>
        </div>

        <div class="form-actions">
            <a class="btn btn-secondary" href="{{ route('dashboard') }}">Cancel</a>
            <button class="btn btn-primary" type="submit" id="submit-btn">Start similarity check →</button>
        </div>
    </form>

    <aside class="panel side-info">
        <h3>Deep Scan Engine</h3>
        <div class="info-row"><span>01</span><div><strong>Multi-Source Academic Index</strong><p>Automatically sweeps OpenAlex, Crossref, DOAJ (SINTA), Semantic Scholar, and web repositories per paragraph.</p></div></div>
        <div class="info-row"><span>02</span><div><strong>Full-Text Scraping</strong><p>Downloads and extracts full article text and open-access PDFs for accurate comparison.</p></div></div>
        <div class="info-row"><span>03</span><div><strong>Processing Time</strong><p>Deep paragraph scanning takes 10–25 seconds depending on document length.</p></div></div>
    </aside>
</div>

<!-- Overlay Loading Bertahap -->
<div id="scan-overlay" style="display:none;position:fixed;inset:0;background:rgba(16,43,78,0.82);backdrop-filter:blur(4px);z-index:9999;align-items:center;justify-content:center;padding:20px;">
    <div style="background:#fff;border-radius:12px;max-width:420px;width:100%;padding:32px;text-align:center;box-shadow:0 20px 50px rgba(0,0,0,0.3);">
        <div class="scan-spinner" style="width:48px;height:48px;border:4px solid #e2eef7;border-top-color:#087fbd;border-radius:50%;margin:0 auto 18px;animation:spin 0.9s linear infinite;"></div>
        <h3 style="margin:0 0 8px;font-size:17px;color:#142944;">Running Deep Similarity Scan...</h3>
        <p id="scan-step-text" style="margin:0 0 18px;font-size:12px;color:#66788d;line-height:1.5;">Extracting document structure and paragraphs...</p>
        <div style="height:6px;background:#edf2f7;border-radius:99px;overflow:hidden;">
            <div id="scan-progress-bar" style="height:100%;width:15%;background:#087fbd;transition:width 1.5s ease;"></div>
        </div>
        <small style="display:block;margin-top:12px;font-size:10px;color:#9aa6b5;">Please do not close or refresh this page</small>
    </div>
</div>
<style>@keyframes spin{to{transform:rotate(360deg)}}</style>

<script>
const docInput = document.getElementById('document');
docInput.addEventListener('change', e => {
    const file = e.target.files[0];
    document.getElementById('file-name').textContent = file ? file.name : 'No file selected';
    if (file && !document.getElementById('title').value.trim()) {
        document.getElementById('title').value = file.name.replace(/\.[^/.]+$/, '');
    }
});

const dz = document.getElementById('dropzone');
dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('dragging'); });
dz.addEventListener('dragleave', () => dz.classList.remove('dragging'));
dz.addEventListener('drop', e => {
    e.preventDefault();
    dz.classList.remove('dragging');
    if (e.dataTransfer.files.length) {
        docInput.files = e.dataTransfer.files;
        docInput.dispatchEvent(new Event('change'));
    }
});

document.getElementById('submission-form').addEventListener('submit', function() {
    const overlay = document.getElementById('scan-overlay');
    const stepText = document.getElementById('scan-step-text');
    const bar = document.getElementById('scan-progress-bar');
    overlay.style.display = 'flex';

    const steps = [
        { time: 2000, width: '35%', text: 'Querying OpenAlex, Crossref, DOAJ (SINTA) & Semantic Scholar...' },
        { time: 6000, width: '62%', text: 'Deep-scraping matching web pages and open-access PDFs...' },
        { time: 11000, width: '85%', text: 'Running 6-gram sequence alignment & source ranking...' },
        { time: 16000, width: '95%', text: 'Finalizing color-coded originality report...' }
    ];
    steps.forEach(s => {
        setTimeout(() => {
            bar.style.width = s.width;
            stepText.textContent = s.text;
        }, s.time);
    });
});
</script>
@endsection