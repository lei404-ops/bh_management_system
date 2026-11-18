@extends('layouts.app')

@section('content')
<section class="panel">
    <form class="toolbar" id="roomFilters" role="search">
        <input type="search" name="q" placeholder="Search room no. or description" aria-label="Search rooms">
        <select name="type" aria-label="Room type">
            <option value="">All types</option>
            <option value="single">Single</option>
            <option value="double">Double</option>
            <option value="dormitory">Dormitory</option>
        </select>
        <select name="status" aria-label="Status">
            <option value="">All statuses</option>
            <option value="available">Available</option>
            <option value="occupied">Occupied</option>
            <option value="maintenance">Under maintenance</option>
        </select>
        @if($canManage)
            <button type="button" class="btn btn--primary toolbar__end" data-action="open-room-dialog">+ Add room</button>
        @endif
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Room</th><th>Type</th><th>Floor</th><th>Capacity</th><th>Monthly rate</th><th>Status</th>
                    @if($canManage)<th>Occupants</th><th>Change status</th>@endif
                </tr>
            </thead>
            <tbody id="roomsBody"><tr><td colspan="8" class="muted">Loading…</td></tr></tbody>
        </table>
    </div>
    <div class="pager" id="roomsPager"></div>
</section>

@if($canManage)
<dialog class="dialog" id="roomDialog">
    <form method="dialog" id="roomForm" novalidate>
        <h2>Add room</h2>
        <div class="field"><label for="rn">Room number</label>
            <input id="rn" name="room_number" maxlength="20" required><small class="field__error" data-error-for="room_number"></small></div>
        <div class="field"><label for="rt">Type</label>
            <select id="rt" name="room_type"><option value="single">Single</option><option value="double">Double</option><option value="dormitory">Dormitory</option></select></div>
        <div class="field"><label for="rr">Monthly rate (₱)</label>
            <input id="rr" name="monthly_rate" type="number" step="0.01" min="0.01" required><small class="field__error" data-error-for="monthly_rate"></small></div>
        <div class="grid grid--2">
            <div class="field"><label for="rc">Capacity</label>
                <input id="rc" name="capacity" type="number" min="1" value="1" required><small class="field__error" data-error-for="capacity"></small></div>
            <div class="field"><label for="rf">Floor</label>
                <input id="rf" name="floor_no" type="number" min="1" value="1" required><small class="field__error" data-error-for="floor_no"></small></div>
        </div>
        <div class="field"><label for="rd">Description</label>
            <input id="rd" name="description" maxlength="255"></div>
        <div class="dialog__actions">
            <button type="button" class="btn btn--ghost" data-action="close-dialog">Cancel</button>
            <button type="submit" class="btn btn--primary">Save room</button>
        </div>
    </form>
</dialog>
@endif
@endsection
