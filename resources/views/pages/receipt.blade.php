<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt {{ $p['receipt_no'] }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
</head>
<body class="receipt-page" data-page="receipt">
    <article class="receipt">
        <header class="receipt__head">
            <h1>{{ config('app.name') }} · Official Receipt</h1>
            <p class="muted">Receipt no. <strong>{{ $p['receipt_no'] }}</strong></p>
        </header>
        <dl class="receipt__grid">
            <dt>Tenant</dt><dd>{{ $p['tenant_name'] }}</dd>
            <dt>Room</dt><dd>{{ $p['room_number'] }}</dd>
            <dt>Invoice</dt><dd>{{ $p['invoice_no'] }} ({{ date('F Y', strtotime($p['billing_month'])) }})</dd>
            <dt>Date paid</dt><dd>{{ date('M d, Y g:i A', strtotime($p['paid_at'])) }}</dd>
            <dt>Method</dt><dd>{{ $methodLabel }}{{ $p['reference_no'] ? ' · Ref ' . $p['reference_no'] : '' }}</dd>
            <dt>Status</dt><dd><span class="badge badge--{{ $p['status'] }}">{{ ucfirst($p['status']) }}</span></dd>
            <dt>Amount</dt><dd class="receipt__amount">{{ \App\Support\Format::money($p['amount']) }}</dd>
            <dt>Invoice balance</dt><dd>{{ \App\Support\Format::money((float) $p['total_amount'] - (float) $p['amount_paid']) }}</dd>
            @if ($p['verified_name'])<dt>Verified by</dt><dd>{{ $p['verified_name'] }}</dd>@endif
        </dl>
        <p class="muted receipt__note">This is a computer-generated receipt.</p>
        <p class="no-print"><button type="button" class="btn btn--primary" data-action="print">Print / Save as PDF</button>
           <a class="btn btn--ghost" href="{{ url('dashboard') }}">Back</a></p>
    </article>
    <script src="{{ asset('assets/js/app.js') }}"></script>
</body>
</html>
