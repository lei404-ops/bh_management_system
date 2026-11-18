@extends('layouts.app')

@section('content')
<section class="panel">
    <form class="toolbar" id="tenantFilters" role="search">
        <input type="search" name="q" placeholder="Search name, email, phone or room" aria-label="Search tenants">
        <button type="button" class="btn btn--primary toolbar__end" data-action="open-tenant-dialog">+ Add tenant</button>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Tenant</th><th>Contact</th><th>Room</th><th>Lease ends</th><th>Account</th><th>Actions</th></tr>
            </thead>
            <tbody id="tenantsBody"><tr><td colspan="6" class="muted">Loading…</td></tr></tbody>
        </table>
    </div>
    <div class="pager" id="tenantsPager"></div>
</section>

<dialog class="dialog" id="tenantDialog">
    <form method="dialog" id="tenantForm" novalidate>
        <h2>Add tenant</h2>
        <p class="muted">This creates the tenant's login. Give them the email and password so they can sign in.</p>

        <h3>Login details</h3>
        <div class="field"><label for="tfn">Full name</label>
            <input id="tfn" name="full_name" maxlength="120" required><small class="field__error" data-error-for="full_name"></small></div>
        <div class="grid grid--2">
            <div class="field"><label for="tem">Email (used to sign in)</label>
                <input id="tem" name="email" type="email" maxlength="150" required autocomplete="off"><small class="field__error" data-error-for="email"></small></div>
            <div class="field"><label for="tph">Phone</label>
                <input id="tph" name="phone" maxlength="30"><small class="field__error" data-error-for="phone"></small></div>
        </div>
        <div class="field"><label for="tpw">Temporary password</label>
            <input id="tpw" name="password" type="text" autocomplete="off" placeholder="Leave blank to auto-generate">
            <small class="field__error" data-error-for="password"></small></div>

        <h3>Personal details</h3>
        <div class="grid grid--2">
            <div class="field"><label for="tbd">Birth date</label>
                <input id="tbd" name="birth_date" type="date"><small class="field__error" data-error-for="birth_date"></small></div>
            <div class="field"><label for="toc">Occupation</label>
                <input id="toc" name="occupation" maxlength="100"></div>
        </div>
        <div class="field"><label for="tad">Home address</label>
            <input id="tad" name="address" maxlength="255"></div>
        <div class="field"><label for="tid">Valid ID presented</label>
            <input id="tid" name="id_type" maxlength="50" placeholder="e.g. Driver License, Student ID"></div>

        <h3>Emergency contact</h3>
        <div class="grid grid--2">
            <div class="field"><label for="ten">Name</label><input id="ten" name="emergency_name" maxlength="120"></div>
            <div class="field"><label for="tep">Phone</label><input id="tep" name="emergency_phone" maxlength="30"></div>
        </div>
        <div class="field"><label for="ter">Relationship</label><input id="ter" name="emergency_relation" maxlength="50" placeholder="e.g. Mother"></div>

        <h3>Room &amp; lease (optional)</h3>
        <div class="field"><label for="trm">Assign room</label>
            <select id="trm" name="room_id"><option value="">— Don't assign a room yet —</option></select>
            <small class="field__error" data-error-for="room_id"></small></div>
        <div id="leaseFields" hidden>
            <div class="grid grid--2">
                <div class="field"><label for="tsd">Lease start</label>
                    <input id="tsd" name="start_date" type="date"><small class="field__error" data-error-for="start_date"></small></div>
                <div class="field"><label for="ted">Lease end</label>
                    <input id="ted" name="end_date" type="date"><small class="field__error" data-error-for="end_date"></small></div>
            </div>
            <div class="grid grid--2">
                <div class="field"><label for="tsp">Security deposit (₱)</label>
                    <input id="tsp" name="security_deposit" type="number" step="0.01" min="0" value="0"><small class="field__error" data-error-for="security_deposit"></small></div>
                <div class="field"><label for="tbl">Billing due day (1–28)</label>
                    <input id="tbl" name="billing_day" type="number" min="1" max="28" value="5"><small class="field__error" data-error-for="billing_day"></small></div>
            </div>
            <label class="check"><input type="checkbox" name="deposit_received" value="1"> Deposit already received</label>
        </div>

        <div class="dialog__actions">
            <button type="button" class="btn btn--ghost" data-action="close-dialog">Cancel</button>
            <button type="submit" class="btn btn--primary">Create tenant</button>
        </div>
    </form>
</dialog>

<dialog class="dialog" id="credDialog">
    <h2 id="credTitle">Tenant login</h2>
    <p class="muted">Copy these now and give them to the tenant. The password is shown <strong>only once</strong>.</p>
    <div class="field"><label>Email</label><input id="credEmail" readonly></div>
    <div class="field"><label>Temporary password</label><input id="credPass" readonly></div>
    <div class="dialog__actions">
        <button type="button" class="btn btn--ghost" data-action="copy-cred">Copy both</button>
        <button type="button" class="btn btn--primary" data-action="close-dialog">Done</button>
    </div>
</dialog>
@endsection
