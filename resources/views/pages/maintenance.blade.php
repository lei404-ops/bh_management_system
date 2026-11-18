@extends('layouts.app')

@section('content')
@if(auth()->user()->role === 'tenant')
<section class="panel">
    <header class="panel__head"><h2>Report a problem</h2></header>
    <form id="ticketForm" enctype="multipart/form-data" novalidate>
        <div class="grid grid--2">
            <div class="field">
                <label for="tTitle">Short title</label>
                <input id="tTitle" name="title" maxlength="150" required placeholder="e.g. Leaking faucet in bathroom">
                <small class="field__error" data-error-for="title"></small>
            </div>
            <div class="grid grid--2">
                <div class="field">
                    <label for="tCat">Category</label>
                    <select id="tCat" name="category">
                        <option value="plumbing">Plumbing</option><option value="electrical">Electrical</option>
                        <option value="carpentry">Carpentry</option><option value="appliance">Appliance</option>
                        <option value="internet">Internet</option><option value="other">Other</option>
                    </select>
                </div>
                <div class="field">
                    <label for="tPri">Priority</label>
                    <select id="tPri" name="priority">
                        <option value="low">Low</option><option value="medium" selected>Medium</option>
                        <option value="high">High</option><option value="urgent">Urgent</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="field">
            <label for="tDesc">Describe the issue</label>
            <textarea id="tDesc" name="description" rows="4" maxlength="2000" required></textarea>
            <small class="field__error" data-error-for="description"></small>
        </div>
        <div class="field">
            <label for="tPhoto">Photo (optional · JPG/PNG/WEBP, max 2&nbsp;MB)</label>
            <input id="tPhoto" name="photo" type="file" accept="image/jpeg,image/png,image/webp">
        </div>
        <button type="submit" class="btn btn--primary">Submit request</button>
    </form>
</section>
@endif

<section class="panel">
    <header class="panel__head">
        <h2>Tickets</h2>
        <select id="statusFilter" aria-label="Filter by status">
            <option value="">All statuses</option>
            <option value="pending">Pending</option>
            <option value="in_progress">In progress</option>
            <option value="resolved">Resolved</option>
            <option value="cancelled">Cancelled</option>
        </select>
    </header>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Ticket</th><th>Room</th><th>Issue</th><th>Priority</th><th>Status</th><th>Assigned to</th><th>Actions</th></tr></thead>
            <tbody id="ticketsBody"><tr><td colspan="7" class="muted">Loading…</td></tr></tbody>
        </table>
    </div>
</section>

<dialog class="dialog" id="logDialog">
    <h2>Ticket timeline</h2>
    <ol class="timeline" id="logList"></ol>
    <div class="dialog__actions"><button type="button" class="btn btn--ghost" data-action="close-dialog">Close</button></div>
</dialog>
@endsection
