@extends('layouts.app')

@section('content')
<section class="grid grid--stats" aria-label="Key metrics">
    <article class="stat">
        <span class="stat__label">Total revenue</span>
        <strong class="stat__value" data-stat="total_revenue">—</strong>
        <small class="stat__sub">This month: <span data-stat="revenue_this_month">—</span></small>
    </article>
    <article class="stat">
        <span class="stat__label">Occupancy rate</span>
        <strong class="stat__value" data-stat="occupancy_rate">—</strong>
        <small class="stat__sub" data-stat="rooms_summary">&nbsp;</small>
    </article>
    <article class="stat">
        <span class="stat__label">Open maintenance tickets</span>
        <strong class="stat__value" data-stat="pending_tickets">—</strong>
        <small class="stat__sub"><a href="{{ url('maintenance') }}">View tickets →</a></small>
    </article>
    <article class="stat">
        <span class="stat__label">Unpaid bills</span>
        <strong class="stat__value" data-stat="unpaid_bills">—</strong>
        <small class="stat__sub">Outstanding: <span data-stat="unpaid_amount">—</span> · Overdue: <span data-stat="overdue_bills">—</span></small>
    </article>
</section>

<section class="grid grid--2">
    <article class="panel">
        <header class="panel__head"><h2>Monthly revenue</h2><small class="muted">Last 6 months · confirmed payments</small></header>
        <div class="chart" id="revenueChart" role="img" aria-label="Monthly revenue bar chart"></div>
    </article>

    <article class="panel">
        <header class="panel__head"><h2>Leases ending soon</h2><small class="muted">Next 30 days</small></header>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Room</th><th>Tenant</th><th>Ends</th><th>Days left</th></tr></thead>
                <tbody id="expiringBody"><tr><td colspan="4" class="muted">Loading…</td></tr></tbody>
            </table>
        </div>
    </article>
</section>

<section class="grid grid--2">
    <article class="panel">
        <header class="panel__head"><h2>Payments awaiting verification</h2><small class="muted" data-stat="pending_payments_label"></small></header>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Receipt</th><th>Tenant</th><th>Amount</th><th>Method</th><th></th></tr></thead>
                <tbody id="pendingPaymentsBody"><tr><td colspan="5" class="muted">Loading…</td></tr></tbody>
            </table>
        </div>
    </article>

    <article class="panel">
        <header class="panel__head"><h2>Recent maintenance tickets</h2></header>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Ticket</th><th>Room</th><th>Issue</th><th>Status</th></tr></thead>
                <tbody id="recentTicketsBody"><tr><td colspan="4" class="muted">Loading…</td></tr></tbody>
            </table>
        </div>
    </article>
</section>

<section class="panel">
    <header class="panel__head"><h2>Notifications</h2></header>
    <ul class="list" id="notificationList"><li class="muted">Loading…</li></ul>
</section>
@endsection
