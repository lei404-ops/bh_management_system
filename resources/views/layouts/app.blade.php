{{-- Shared layout. Pages set: $pageTitle, $activePage, optional $pageKey (see PageController). --}}
@php
    $user = auth()->user();
    $nav = [
        'admin'    => [['dashboard', 'dashboard', 'Dashboard'], ['rooms', 'rooms', 'Rooms'], ['tenants', 'tenants', 'Tenants'], ['maintenance', 'maintenance', 'Maintenance']],
        'landlord' => [['dashboard', 'dashboard', 'Dashboard'], ['rooms', 'rooms', 'Rooms'], ['tenants', 'tenants', 'Tenants'], ['maintenance', 'maintenance', 'Maintenance']],
        'tenant'   => [['dashboard', 'dashboard', 'My Dashboard'], ['rooms', 'rooms', 'Browse Rooms'], ['maintenance', 'maintenance', 'My Requests']],
        'staff'    => [['dashboard', 'dashboard', 'Dashboard'], ['maintenance', 'maintenance', 'My Tickets']],
    ][$user->role] ?? [];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="app-base" content="{{ rtrim(url('/'), '/') }}">
    <title>{{ $pageTitle }} · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
</head>
<body data-page="{{ $pageKey ?? $activePage }}" data-role="{{ $user->role }}">
<div class="app">
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <span class="brand__logo" aria-hidden="true">B</span>
            <span class="brand__name">BHMS</span>
        </div>
        <nav class="nav" aria-label="Main">
            @foreach ($nav as [$href, $key, $label])
                <a href="{{ url($href) }}" class="nav__link{{ $activePage === $key ? ' is-active' : '' }}">{{ $label }}</a>
            @endforeach
            <a href="{{ url('account') }}" class="nav__link{{ $activePage === 'account' ? ' is-active' : '' }}">Change password</a>
        </nav>
        <form method="post" action="{{ route('logout') }}" class="sidebar__foot">
            @csrf
            <div class="user">
                <span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->full_name, 0, 1)) }}</span>
                <span class="user__meta">
                    <strong>{{ $user->full_name }}</strong>
                    <small>{{ ucfirst($user->role) }}</small>
                </span>
            </div>
            <button type="submit" class="btn btn--ghost btn--block">Log out</button>
        </form>
    </aside>

    <div class="main">
        <header class="topbar">
            <button class="icon-btn" id="menuToggle" type="button" aria-label="Toggle menu" aria-controls="sidebar">☰</button>
            <h1 class="topbar__title">{{ $pageTitle }}</h1>
        </header>
        <main class="content">
            @yield('content')
        </main>
    </div>
</div>
<div class="toast-stack" id="toastStack" aria-live="polite"></div>
<script src="{{ asset('assets/js/app.js') }}"></script>
</body>
</html>
