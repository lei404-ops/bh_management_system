@extends('layouts.app')

@section('content')
<section class="grid grid--stats" aria-label="My workload">
    <article class="stat"><span class="stat__label">To start</span><strong class="stat__value" data-stat="pending">—</strong></article>
    <article class="stat"><span class="stat__label">In progress</span><strong class="stat__value" data-stat="in_progress">—</strong></article>
    <article class="stat"><span class="stat__label">Resolved</span><strong class="stat__value" data-stat="resolved">—</strong></article>
</section>

<section class="panel">
    <header class="panel__head"><h2>My assigned tickets</h2></header>
    <p><a class="btn btn--primary" href="{{ url('maintenance') }}">Open my tickets</a></p>
</section>

<section class="panel">
    <header class="panel__head"><h2>Notifications</h2></header>
    <ul class="list" id="notificationList"><li class="muted">Loading…</li></ul>
</section>
@endsection
