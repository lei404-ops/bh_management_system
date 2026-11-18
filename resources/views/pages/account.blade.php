@extends('layouts.app')

@section('content')
<section class="panel panel--narrow">
    <header class="panel__head"><h2>Change your password</h2></header>
    <p class="muted">Signed in as <strong>{{ auth()->user()->email }}</strong>. Use at least 8 characters with a letter and a number.</p>

    <form id="passwordForm" novalidate>
        <input type="text" name="username" value="{{ auth()->user()->email }}" autocomplete="username" hidden>
        <div class="field">
            <label for="cp">Current password</label>
            <input id="cp" name="current_password" type="password" autocomplete="current-password" required>
            <small class="field__error" data-error-for="current_password"></small>
        </div>
        <div class="field">
            <label for="np">New password</label>
            <input id="np" name="new_password" type="password" autocomplete="new-password" minlength="8" maxlength="72" required>
            <small class="field__error" data-error-for="new_password"></small>
        </div>
        <div class="field">
            <label for="cf">Confirm new password</label>
            <input id="cf" name="confirm_password" type="password" autocomplete="new-password" required>
            <small class="field__error" data-error-for="confirm_password"></small>
        </div>
        <button type="submit" class="btn btn--primary">Update password</button>
    </form>
</section>
@endsection
