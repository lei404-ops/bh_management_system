@extends('layouts.app')

@section('content')
<section class="grid grid--stats" aria-label="My summary">
    <article class="stat">
        <span class="stat__label">My room</span>
        <strong class="stat__value" data-stat="room">—</strong>
        <small class="stat__sub" data-stat="lease_period">&nbsp;</small>
    </article>
    <article class="stat">
        <span class="stat__label">Outstanding balance</span>
        <strong class="stat__value" data-stat="balance">—</strong>
        <small class="stat__sub" data-stat="deposit">&nbsp;</small>
    </article>
    <article class="stat">
        <span class="stat__label">Open maintenance requests</span>
        <strong class="stat__value" data-stat="open_tickets">—</strong>
        <small class="stat__sub"><a href="{{ url('maintenance') }}">Submit or track →</a></small>
    </article>
    <article class="stat">
        <span class="stat__label">Lease status</span>
        <strong class="stat__value" data-stat="days_left">—</strong>
        <small class="stat__sub">days until lease ends</small>
    </article>
</section>

<section class="panel">
    <header class="panel__head"><h2>Monthly statements</h2><small class="muted">Rent + water + electricity + internet</small></header>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Invoice</th><th>Month</th><th>Due</th><th>Total</th><th>Balance</th><th>Status</th><th></th></tr></thead>
            <tbody id="invoiceBody"><tr><td colspan="7" class="muted">Loading…</td></tr></tbody>
        </table>
    </div>
</section>

<section class="grid grid--2">
    <article class="panel">
        <header class="panel__head"><h2>Payment history</h2></header>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Receipt</th><th>Amount</th><th>Method</th><th>Status</th><th></th></tr></thead>
                <tbody id="historyBody"><tr><td colspan="5" class="muted">Loading…</td></tr></tbody>
            </table>
        </div>
    </article>
    <article class="panel">
        <header class="panel__head"><h2>Notifications</h2></header>
        <ul class="list" id="notificationList"><li class="muted">Loading…</li></ul>
    </article>
</section>

<!-- Payment dialog (opened by the "Pay" buttons) -->
<dialog class="dialog" id="payDialog">
    <form method="dialog" id="payForm" enctype="multipart/form-data" novalidate>
        <h2>Submit payment</h2>
        <p class="muted" id="payInvoiceLabel"></p>
        <input type="hidden" name="invoice_id" id="payInvoiceId">
        <div class="field">
            <label for="payAmount">Amount (₱)</label>
            <input type="number" id="payAmount" name="amount" step="0.01" min="0.01" required>
            <small class="field__error" data-error-for="amount"></small>
        </div>
        <div class="field">
            <label for="payMethod">Payment method</label>
            <select id="payMethod" name="method" required>
                <option value="gcash">GCash</option>
                <option value="maya">Maya</option>
                <option value="bank_transfer">Bank transfer</option>
                <option value="cash">Cash (pay at the office)</option>
                <option value="mock_gateway">Demo gateway (simulated)</option>
            </select>
        </div>
        <div class="field">
            <label for="payRef">Reference number</label>
            <input type="text" id="payRef" name="reference_no" maxlength="80" autocomplete="off">
            <small class="field__error" data-error-for="reference_no"></small>
        </div>
        <div class="field">
            <label for="payReceipt">Receipt screenshot (JPG/PNG/WEBP, max 2&nbsp;MB)</label>
            <input type="file" id="payReceipt" name="receipt" accept="image/jpeg,image/png,image/webp">
        </div>
        <div class="dialog__actions">
            <button type="button" class="btn btn--ghost" data-action="close-dialog">Cancel</button>
            <button type="submit" class="btn btn--primary">Submit payment</button>
        </div>
    </form>
</dialog>
@endsection
