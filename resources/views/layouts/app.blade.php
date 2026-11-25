<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') · {{ config('app.name', 'Originality Checker') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <a class="brand" href="{{ route('dashboard') }}"><span class="brand-mark">O</span><span>originality<span class="brand-light">check</span></span></a>
        <div class="nav-label">WORKSPACE</div>
        <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">▦ <span>Overview</span></a>
        <a class="nav-link {{ request()->routeIs('submissions.*') ? 'active' : '' }}" href="{{ route('submissions.index') }}">▤ <span>My submissions</span></a>
        <a class="nav-link {{ request()->routeIs('submissions.create') ? 'active' : '' }}" href="{{ route('submissions.create') }}">＋ <span>New submission</span></a>
        @can('manage-sources')
            <div class="nav-label nav-spaced">ADMINISTRATION</div>
            <a class="nav-link {{ request()->routeIs('admin.*') ? 'active' : '' }}" href="{{ route('admin.sources.index') }}">◈ <span>Source library</span></a>
        @endcan
        <div class="sidebar-bottom">
            <div class="privacy-card"><span class="privacy-icon">✓</span><div><strong>No Repository</strong><small>Your submissions are not added to the source library.</small></div></div>
            <div class="user-mini"><div class="avatar">{{ strtoupper(substr(auth()->user()->name,0,1)) }}</div><div class="user-meta"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->role === 'admin' ? 'Administrator' : 'Member' }}</small></div>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="logout" title="Logout">↗</button></form>
            </div>
        </div>
    </aside>
    <main class="main-area">
        <header class="topbar"><div class="breadcrumb">Workspace <span>/</span> @yield('crumb', 'Overview')</div><div class="topbar-right"><span class="status-dot"></span><span>System operational</span><div class="avatar small">{{ strtoupper(substr(auth()->user()->name,0,1)) }}</div></div></header>
        <div class="page-content">
            @if(session('success'))<div class="alert success">{{ session('success') }}</div>@endif
            @if($errors->any())<div class="alert error">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
            @yield('content')
        </div>
        <footer class="footer">Originality Check · Independent similarity analysis · Not affiliated with Turnitin</footer>
    </main>
</div>
</body>
</html>
