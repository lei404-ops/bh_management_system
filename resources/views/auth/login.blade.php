<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
</head>
<body class="auth-page">
    <main class="auth-card">
        <div class="brand brand--center">
            <span class="brand__logo" aria-hidden="true">B</span>
            <h1 class="brand__name">Boarding House Manager</h1>
            <p class="muted">Sign in to continue</p>
        </div>

        @if (!empty($error))
            <div class="alert alert--danger" role="alert">{{ $error }}</div>
        @endif

        <form method="post" action="{{ route('login.submit') }}" novalidate>
            @csrf
            <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" autocomplete="username" required autofocus
                       value="{{ $email ?? '' }}">
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" autocomplete="current-password" required>
            </div>
            <button type="submit" class="btn btn--primary btn--block">Sign in</button>
        </form>
    </main>
</body>
</html>
