/* =====================================================================
   BHMS - app.js  (vanilla JS, no build step)
   - api(): fetch wrapper that sends the CSRF token and handles JSON errors
   - Pages are selected by <body data-page="..."> and wired up at the bottom
   - SECURITY: every dynamic value inserted with innerHTML goes through esc()
   - No inline handlers (CSP): all clicks use event delegation on [data-action]
   ===================================================================== */
(() => {
  'use strict';

  /* ---------------- helpers ---------------- */
  const $  = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  const ESC = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
  const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ESC[c]);

  const meta = (name) => document.querySelector(`meta[name="${name}"]`)?.content || '';
  const base = () => meta('app-base').replace(/\/$/, '');
  const role = () => document.body.dataset.role || '';
  const isManager = () => role() === 'admin' || role() === 'landlord';

  const peso = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
  const money = (n) => peso.format(Number(n) || 0);
  const toDate = (s) => new Date(String(s).replace(' ', 'T'));
  const fmtDate = (s) => (s ? toDate(s).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '—');
  const fmtDateTime = (s) => (s ? toDate(s).toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) : '—');
  const fmtMonth = (s) => toDate(s).toLocaleDateString('en-PH', { year: 'numeric', month: 'long' });
  const label = (s) => String(s ?? '').replace(/_/g, ' ').replace(/^./, (c) => c.toUpperCase());
  const badge = (status) => `<span class="badge badge--${esc(status)}">${esc(label(status))}</span>`;
  const emptyRow = (cols, text) => `<tr><td colspan="${cols}" class="muted">${esc(text)}</td></tr>`;

  const debounce = (fn, ms = 300) => {
    let t;
    return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); };
  };

  /* ---------------- toast ---------------- */
  function toast(message, type = 'info') {
    const stack = $('#toastStack');
    if (!stack) return;
    const el = document.createElement('div');
    el.className = `toast toast--${type}`;
    el.textContent = message;               // textContent: never HTML
    stack.appendChild(el);
    setTimeout(() => el.remove(), 4500);
  }

  /* ---------------- API wrapper ---------------- */
  class ApiError extends Error {
    constructor(message, status, errors = {}) { super(message); this.status = status; this.errors = errors; }
  }

  async function api(path, { method = 'GET', body = null, query = null } = {}) {
    const url = new URL(`${base()}/api/${path}`, location.origin);
    if (query) {
      Object.entries(query).forEach(([k, v]) => { if (v !== '' && v != null) url.searchParams.set(k, v); });
    }
    const opts = {
      method,
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-CSRF-Token': meta('csrf-token') },
    };
    if (body) {
      if (body instanceof FormData) {
        opts.body = body;                     // browser sets the multipart boundary
      } else {
        const fd = new FormData();
        Object.entries(body).forEach(([k, v]) => fd.append(k, v));
        opts.body = fd;
      }
    }

    let res, json;
    try { res = await fetch(url, opts); } catch { throw new ApiError('Network error. Check your connection.', 0); }
    try { json = await res.json(); } catch { throw new ApiError('Unexpected server response.', res.status); }

    if (res.status === 401) { location.href = `${base()}/login`; throw new ApiError(json.message, 401); }
    if (!res.ok || !json.success) throw new ApiError(json.message || 'Request failed.', res.status, json.errors || {});
    return json;
  }

  /* ---------------- form helpers ---------------- */
  function clearErrors(form) {
    $$('.field__error', form).forEach((el) => { el.textContent = ''; });
    $$('.is-invalid', form).forEach((el) => el.classList.remove('is-invalid'));
  }
  function showErrors(form, errors) {
    Object.entries(errors || {}).forEach(([name, msg]) => {
      const slot = $(`[data-error-for="${CSS.escape(name)}"]`, form);
      if (slot) slot.textContent = msg;
      $(`[name="${CSS.escape(name)}"]`, form)?.classList.add('is-invalid');
    });
  }
  /** Submit a form through fetch; disables the button while waiting. */
  async function submitForm(form, endpoint, extra = {}) {
    clearErrors(form);
    const btn = $('button[type="submit"]', form);
    const fd = new FormData(form);
    Object.entries(extra).forEach(([k, v]) => fd.set(k, v));
    if (btn) btn.disabled = true;
    try {
      return await api(endpoint, { method: 'POST', body: fd });
    } catch (err) {
      showErrors(form, err.errors);
      toast(err.message, 'error');
      return null;
    } finally {
      if (btn) btn.disabled = false;
    }
  }

  const setStat = (key, value) => $$(`[data-stat="${key}"]`).forEach((el) => { el.textContent = value; });

  function renderNotifications(list = []) {
    const ul = $('#notificationList');
    if (!ul) return;
    ul.innerHTML = list.length
      ? list.map((n) => `<li class="${n.is_read ? '' : 'is-unread'}"><span>${esc(n.message)}</span><small class="muted">${esc(fmtDateTime(n.created_at))}</small></li>`).join('')
      : '<li class="muted">No notifications.</li>';
  }

  /* =====================================================================
     DASHBOARD - admin / landlord
     ===================================================================== */
  async function loadAdminDashboard() {
    const [{ data: d }, pending] = await Promise.all([
      api('dashboard'),
      api('payments', { query: { pending: 1 } }),
    ]);

    setStat('total_revenue', money(d.total_revenue));
    setStat('revenue_this_month', money(d.revenue_this_month));
    setStat('occupancy_rate', `${d.occupancy_rate}%`);
    setStat('rooms_summary', `${d.rooms.occupied} occupied · ${d.rooms.available} available · ${d.rooms.maintenance} maintenance`);
    setStat('pending_tickets', d.pending_tickets);
    setStat('unpaid_bills', d.unpaid_bills);
    setStat('unpaid_amount', money(d.unpaid_amount));
    setStat('overdue_bills', d.overdue_bills);
    setStat('pending_payments_label', `${d.pending_payments} pending`);

    // Bar chart: heights are set through the CSSOM (allowed by CSP), not inline style attributes.
    const chart = $('#revenueChart');
    const max = Math.max(1, ...d.monthly_revenue.map((m) => m.revenue));
    chart.innerHTML = d.monthly_revenue.map((m) => `
      <div class="chart__col">
        <span class="chart__val">${m.revenue ? esc(money(m.revenue).replace('.00', '')) : ''}</span>
        <div class="chart__bar" data-h="${(m.revenue / max) * 100}"></div>
        <span class="chart__lbl">${esc(m.month.split(' ')[0])}</span>
      </div>`).join('');
    $$('.chart__bar', chart).forEach((bar) => { bar.style.height = `${bar.dataset.h}%`; });

    $('#expiringBody').innerHTML = d.expiring_leases.length
      ? d.expiring_leases.map((l) => `<tr><td>${esc(l.room_number)}</td><td>${esc(l.tenant_name)}</td><td>${esc(fmtDate(l.end_date))}</td><td><span class="badge badge--${l.days_left <= 7 ? 'urgent' : 'pending'}">${esc(l.days_left)} days</span></td></tr>`).join('')
      : emptyRow(4, 'No leases ending soon.');

    $('#recentTicketsBody').innerHTML = d.recent_tickets.length
      ? d.recent_tickets.map((t) => `<tr><td>${esc(t.ticket_no)}</td><td>${esc(t.room_number)}</td><td>${esc(t.title)}</td><td>${badge(t.status)}</td></tr>`).join('')
      : emptyRow(4, 'No tickets yet.');

    $('#pendingPaymentsBody').innerHTML = pending.data.length
      ? pending.data.map((p) => `
        <tr>
          <td>${esc(p.receipt_no)}${p.has_receipt ? ` · <a href="${esc(base())}/file?type=receipt&amp;id=${esc(p.id)}" target="_blank" rel="noopener">proof</a>` : ''}</td>
          <td>${esc(p.tenant_name)}</td><td>${esc(money(p.amount))}</td><td>${esc(label(p.method))}</td>
          <td class="actions">
            <button class="btn btn--primary btn--sm" data-action="decide-payment" data-id="${esc(p.id)}" data-decision="confirm">Confirm</button>
            <button class="btn btn--danger btn--sm" data-action="decide-payment" data-id="${esc(p.id)}" data-decision="reject">Reject</button>
          </td>
        </tr>`).join('')
      : emptyRow(5, 'Nothing to verify.');

    renderNotifications(d.notifications);
  }

  /* =====================================================================
     DASHBOARD - tenant
     ===================================================================== */
  async function loadTenantDashboard() {
    const [{ data: d }, history] = await Promise.all([
      api('dashboard'),
      api('payments', { query: { history: 1 } }),
    ]);

    const l = d.lease;
    setStat('room', l ? `${l.room_number} (${label(l.room_type)})` : 'No active lease');
    setStat('lease_period', l ? `${fmtDate(l.start_date)} – ${fmtDate(l.end_date)}` : '');
    setStat('deposit', l ? `Deposit ${money(l.security_deposit)} (${l.deposit_status})` : '');
    setStat('balance', money(d.balance));
    setStat('open_tickets', d.open_tickets);
    setStat('days_left', l ? l.days_left : '—');

    $('#invoiceBody').innerHTML = d.invoices.length
      ? d.invoices.map((i) => `
        <tr>
          <td>${esc(i.invoice_no)}</td><td>${esc(fmtMonth(i.billing_month))}</td><td>${esc(fmtDate(i.due_date))}</td>
          <td>${esc(money(i.total_amount))}</td><td>${esc(money(i.balance))}</td><td>${badge(i.status)}</td>
          <td>${['paid', 'cancelled'].includes(i.status) ? '' :
            `<button class="btn btn--primary btn--sm" data-action="pay-invoice" data-id="${esc(i.id)}" data-balance="${esc(i.balance)}" data-label="${esc(i.invoice_no)}">Pay</button>`}</td>
        </tr>`).join('')
      : emptyRow(7, 'No statements yet.');

    $('#historyBody').innerHTML = history.data.length
      ? history.data.map((p) => `
        <tr><td>${esc(p.receipt_no)}</td><td>${esc(money(p.amount))}</td><td>${esc(label(p.method))}</td><td>${badge(p.status)}</td>
        <td>${p.status === 'confirmed' ? `<a href="${esc(base())}/receipt?id=${esc(p.id)}" target="_blank" rel="noopener">Receipt</a>` : ''}</td></tr>`).join('')
      : emptyRow(5, 'No payments yet.');

    renderNotifications(d.notifications);
  }

  async function loadStaffDashboard() {
    const { data: d } = await api('dashboard');
    ['pending', 'in_progress', 'resolved'].forEach((k) => setStat(k, d[k]));
    renderNotifications(d.notifications);
  }

  /* =====================================================================
     ROOMS page
     ===================================================================== */
  const roomState = { page: 1, perPage: 10 };

  async function loadRooms() {
    const f = $('#roomFilters');
    const query = { q: f.q.value.trim(), type: f.type.value, status: f.status.value, page: roomState.page, per_page: roomState.perPage };
    const body = $('#roomsBody');
    const cols = isManager() ? 8 : 6;
    try {
      const { data } = await api('rooms', { query });
      body.innerHTML = data.items.length ? data.items.map((r) => `
        <tr>
          <td><strong>${esc(r.room_number)}</strong><br><small class="muted">${esc(r.description || '')}</small></td>
          <td>${esc(label(r.room_type))}</td><td>${esc(r.floor_no)}</td>
          <td>${esc(r.occupant_count)} / ${esc(r.capacity)}</td>
          <td>${esc(money(r.monthly_rate))}</td><td>${badge(r.status)}</td>
          ${isManager() ? `<td>${esc(r.occupants || '—')}</td>
          <td><select data-action="room-status" data-id="${esc(r.id)}" aria-label="Status of room ${esc(r.room_number)}">
            ${['available', 'occupied', 'maintenance'].map((s) => `<option value="${s}"${s === r.status ? ' selected' : ''}>${esc(label(s))}</option>`).join('')}
          </select></td>` : ''}
        </tr>`).join('') : emptyRow(cols, 'No rooms match your filters.');

      const pages = Math.max(1, Math.ceil(data.total / data.per_page));
      $('#roomsPager').innerHTML = `
        <span>${esc(data.total)} room(s) · page ${esc(data.page)} of ${esc(pages)}</span>
        <button class="btn btn--ghost btn--sm" data-action="rooms-prev"${data.page <= 1 ? ' disabled' : ''}>Prev</button>
        <button class="btn btn--ghost btn--sm" data-action="rooms-next"${data.page >= pages ? ' disabled' : ''}>Next</button>`;
    } catch (err) {
      body.innerHTML = emptyRow(cols, err.message);
    }
  }

  /* =====================================================================
     TENANTS page (admin / landlord)
     ===================================================================== */
  const tenantState = { page: 1, perPage: 10 };

  async function loadTenants() {
    const body = $('#tenantsBody');
    try {
      const { data } = await api('tenants', { query: { q: $('#tenantFilters').q.value.trim(), page: tenantState.page, per_page: tenantState.perPage } });
      body.innerHTML = data.items.length ? data.items.map((t) => `
        <tr>
          <td><strong>${esc(t.full_name)}</strong><br><small class="muted">${esc(t.occupation || '')}</small></td>
          <td>${esc(t.email)}<br><small class="muted">${esc(t.phone || '')}</small></td>
          <td>${esc(t.room_number || '—')}</td>
          <td>${esc(t.end_date ? fmtDate(t.end_date) : '—')}</td>
          <td>${badge(Number(t.is_active) ? 'available' : 'cancelled').replace('Available', 'Active').replace('Cancelled', 'Disabled')}</td>
          <td class="actions">
            <button class="btn btn--ghost btn--sm" data-action="tenant-reset" data-id="${esc(t.user_id)}" data-name="${esc(t.full_name)}" data-email="${esc(t.email)}">Reset password</button>
            <button class="btn ${Number(t.is_active) ? 'btn--danger' : 'btn--primary'} btn--sm" data-action="tenant-toggle" data-id="${esc(t.user_id)}" data-active="${Number(t.is_active) ? 0 : 1}">${Number(t.is_active) ? 'Disable' : 'Enable'}</button>
          </td>
        </tr>`).join('') : emptyRow(6, 'No tenants yet. Click "+ Add tenant" to create one.');

      const pages = Math.max(1, Math.ceil(data.total / data.per_page));
      $('#tenantsPager').innerHTML = `
        <span>${esc(data.total)} tenant(s) · page ${esc(data.page)} of ${esc(pages)}</span>
        <button class="btn btn--ghost btn--sm" data-action="tenants-prev"${data.page <= 1 ? ' disabled' : ''}>Prev</button>
        <button class="btn btn--ghost btn--sm" data-action="tenants-next"${data.page >= pages ? ' disabled' : ''}>Next</button>`;
    } catch (err) {
      body.innerHTML = emptyRow(6, err.message);
    }
  }

  async function openTenantDialog() {
    const f = $('#tenantForm');
    clearErrors(f); f.reset();
    $('#leaseFields').hidden = true;
    const sel = $('#trm');
    sel.innerHTML = '<option value="">— Don\'t assign a room yet —</option>';
    try {
      const { data } = await api('tenants', { query: { rooms: 1 } });
      sel.innerHTML += data.map((r) => `<option value="${esc(r.id)}">${esc(r.room_number)} · ${esc(label(r.room_type))} · ${esc(money(r.monthly_rate))}/mo · ${esc(r.occupants)}/${esc(r.capacity)} occupied</option>`).join('');
    } catch (err) { toast(err.message, 'error'); }
    const today = new Date();
    const nextYear = new Date(today.getFullYear() + 1, today.getMonth(), today.getDate());
    const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    f.start_date.value = iso(today);
    f.end_date.value = iso(nextYear);
    $('#tenantDialog').showModal();
  }

  function showCredentials(title, email, password) {
    $('#credTitle').textContent = title;
    $('#credEmail').value = email;
    $('#credPass').value = password;
    $('#credDialog').showModal();
  }

  /* =====================================================================
     MAINTENANCE page
     ===================================================================== */
  let staffOptions = [];

  function ticketActions(t) {
    const btn = (status, text, cls = 'btn--ghost') =>
      `<button class="btn ${cls} btn--sm" data-action="ticket-status" data-id="${esc(t.id)}" data-status="${status}">${text}</button>`;
    const parts = [];

    if (isManager() && ['pending', 'in_progress'].includes(t.status)) {
      parts.push(`<select data-action="ticket-assign" data-id="${esc(t.id)}" aria-label="Assign ticket ${esc(t.ticket_no)}">
        <option value="">Assign…</option>
        ${staffOptions.map((s) => `<option value="${esc(s.id)}"${String(s.id) === String(t.assigned_to) ? ' selected' : ''}>${esc(s.full_name)}</option>`).join('')}
      </select>`);
    }
    if (role() !== 'tenant') {
      if (t.status === 'pending') parts.push(btn('in_progress', 'Start', 'btn--primary'));
      if (t.status === 'in_progress') parts.push(btn('resolved', 'Resolve', 'btn--primary'));
    }
    if ((isManager() && ['pending', 'in_progress'].includes(t.status)) || (role() === 'tenant' && t.status === 'pending')) {
      parts.push(btn('cancelled', 'Cancel', 'btn--danger'));
    }
    parts.push(`<button class="btn btn--ghost btn--sm" data-action="ticket-log" data-id="${esc(t.id)}">Timeline</button>`);
    return `<div class="actions">${parts.join('')}</div>`;
  }

  async function loadTickets() {
    const body = $('#ticketsBody');
    try {
      const { data } = await api('maintenance', { query: { status: $('#statusFilter').value } });
      body.innerHTML = data.length ? data.map((t) => `
        <tr>
          <td><strong>${esc(t.ticket_no)}</strong><br><small class="muted">${esc(fmtDateTime(t.created_at))}</small></td>
          <td>${esc(t.room_number)}${role() !== 'tenant' ? `<br><small class="muted">${esc(t.tenant_name)}</small>` : ''}</td>
          <td><strong>${esc(t.title)}</strong> <small class="muted">· ${esc(label(t.category))}</small><br>
              <small>${esc(t.description)}</small>
              ${Number(t.has_photo) ? `<br><a href="${esc(base())}/file?type=maintenance&amp;id=${esc(t.id)}" target="_blank" rel="noopener">View photo</a>` : ''}</td>
          <td>${badge(t.priority)}</td><td>${badge(t.status)}</td>
          <td>${esc(t.staff_name || '—')}</td>
          <td>${ticketActions(t)}</td>
        </tr>`).join('') : emptyRow(7, 'No tickets found.');
    } catch (err) {
      body.innerHTML = emptyRow(7, err.message);
    }
  }

  async function showTimeline(id) {
    try {
      const { data } = await api('maintenance', { query: { id, log: 1 } });
      $('#logList').innerHTML = data.map((l) => `
        <li><strong>${esc(label(l.action))}</strong>${l.to_status ? ` → ${esc(label(l.to_status))}` : ''}
            ${l.note ? `<br>${esc(l.note)}` : ''}<small>${esc(l.by_name || 'System')} · ${esc(fmtDateTime(l.logged_at))}</small></li>`).join('');
      $('#logDialog').showModal();
    } catch (err) { toast(err.message, 'error'); }
  }

  /* =====================================================================
     Global event delegation (clicks + changes)
     ===================================================================== */
  document.addEventListener('click', async (e) => {
    const el = e.target.closest('[data-action]');
    if (!el || el.tagName === 'SELECT') return;
    const { action, id } = el.dataset;

    try {
      switch (action) {
        case 'close-dialog': el.closest('dialog')?.close(); break;
        case 'print': window.print(); break;

        case 'open-room-dialog': $('#roomDialog').showModal(); break;
        case 'rooms-prev': roomState.page = Math.max(1, roomState.page - 1); loadRooms(); break;
        case 'rooms-next': roomState.page += 1; loadRooms(); break;

        case 'open-tenant-dialog': await openTenantDialog(); break;
        case 'tenants-prev': tenantState.page = Math.max(1, tenantState.page - 1); loadTenants(); break;
        case 'tenants-next': tenantState.page += 1; loadTenants(); break;
        case 'tenant-toggle': {
          const enable = el.dataset.active === '1';
          if (!enable && !window.confirm('Disable this tenant account? They will not be able to log in.')) return;
          await api('tenants', { method: 'POST', body: { action: 'active', user_id: id, active: el.dataset.active } });
          toast(enable ? 'Tenant account enabled.' : 'Tenant account disabled.', 'success');
          loadTenants();
          break;
        }
        case 'tenant-reset': {
          if (!window.confirm(`Reset the password of ${el.dataset.name}? The old password will stop working.`)) return;
          const { data } = await api('tenants', { method: 'POST', body: { action: 'reset_password', user_id: id } });
          showCredentials(`New password for ${el.dataset.name}`, el.dataset.email, data.temporary_password);
          break;
        }
        case 'copy-cred': {
          const text = `Email: ${$('#credEmail').value}\nPassword: ${$('#credPass').value}`;
          try { await navigator.clipboard.writeText(text); toast('Copied.', 'success'); }
          catch { $('#credPass').select(); toast('Press Ctrl+C to copy.', 'info'); }
          break;
        }

        case 'pay-invoice': {
          const f = $('#payForm');
          clearErrors(f); f.reset();
          $('#payInvoiceId').value = id;
          $('#payAmount').value = Number(el.dataset.balance).toFixed(2);
          $('#payInvoiceLabel').textContent = `Invoice ${el.dataset.label}`;
          $('#payDialog').showModal();
          break;
        }
        case 'decide-payment': {
          const decision = el.dataset.decision;
          let remarks = '';
          if (decision === 'reject') {
            remarks = window.prompt('Reason for rejecting (shown to the tenant):') ?? null;
            if (remarks === null) return;
          }
          await api('payments', { method: 'POST', body: { action: 'decide', payment_id: id, decision, remarks } });
          toast(decision === 'confirm' ? 'Payment confirmed.' : 'Payment rejected.', 'success');
          loadAdminDashboard();
          break;
        }

        case 'ticket-status': {
          let note = '';
          if (el.dataset.status === 'resolved') note = window.prompt('Resolution note (optional):') ?? '';
          await api('maintenance', { method: 'POST', body: { action: 'status', id, status: el.dataset.status, note } });
          toast('Ticket updated.', 'success');
          loadTickets();
          break;
        }
        case 'ticket-log': showTimeline(id); break;
      }
    } catch (err) {
      toast(err.message, 'error');
    }
  });

  document.addEventListener('change', async (e) => {
    const el = e.target.closest('select[data-action]');
    if (!el) return;
    try {
      if (el.dataset.action === 'room-status') {
        await api('rooms', { method: 'POST', body: { action: 'status', id: el.dataset.id, status: el.value } });
        toast('Room status updated.', 'success');
        loadRooms();
      } else if (el.dataset.action === 'ticket-assign' && el.value) {
        await api('maintenance', { method: 'POST', body: { action: 'assign', id: el.dataset.id, staff_id: el.value } });
        toast('Ticket assigned.', 'success');
        loadTickets();
      }
    } catch (err) {
      toast(err.message, 'error');
      if ($('#roomsBody')) loadRooms();      // reset the dropdown to the real server value
      if ($('#ticketsBody')) loadTickets();
    }
  });

  /* =====================================================================
     Page bootstrap
     ===================================================================== */
  const pages = {
    'dashboard-admin': () => {
      loadAdminDashboard().catch((e) => toast(e.message, 'error'));
      setInterval(() => { if (!document.hidden) loadAdminDashboard().catch(() => {}); }, 60000);   // live refresh
    },

    'dashboard-tenant': () => {
      loadTenantDashboard().catch((e) => toast(e.message, 'error'));
      $('#payForm').addEventListener('submit', async (ev) => {
        ev.preventDefault();
        const res = await submitForm(ev.target, 'payments', { action: 'submit' });
        if (res) { $('#payDialog').close(); toast(res.message, 'success'); loadTenantDashboard(); }
      });
    },

    'dashboard-staff': () => { loadStaffDashboard().catch((e) => toast(e.message, 'error')); },

    rooms: () => {
      const f = $('#roomFilters');
      f.addEventListener('submit', (ev) => ev.preventDefault());
      const reload = () => { roomState.page = 1; loadRooms(); };
      f.q.addEventListener('input', debounce(reload));
      f.type.addEventListener('change', reload);
      f.status.addEventListener('change', reload);
      loadRooms();

      $('#roomForm')?.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        const res = await submitForm(ev.target, 'rooms', { action: 'create' });
        if (res) { $('#roomDialog').close(); ev.target.reset(); toast(res.message, 'success'); loadRooms(); }
      });
    },

    account: () => {
      $('#passwordForm').addEventListener('submit', async (ev) => {
        ev.preventDefault();
        const form = ev.target;
        const res = await submitForm(form, 'account', { action: 'change_password' });
        if (res) { form.reset(); toast(res.message, 'success'); }
      });
    },

    tenants: () => {
      const f = $('#tenantFilters');
      f.addEventListener('submit', (ev) => ev.preventDefault());
      f.q.addEventListener('input', debounce(() => { tenantState.page = 1; loadTenants(); }));
      $('#trm').addEventListener('change', (ev) => { $('#leaseFields').hidden = !ev.target.value; });
      loadTenants();

      $('#tenantForm').addEventListener('submit', async (ev) => {
        ev.preventDefault();
        const res = await submitForm(ev.target, 'tenants', { action: 'create' });
        if (res) {
          $('#tenantDialog').close();
          toast(res.message, 'success');
          showCredentials('Tenant account created', res.data.email, res.data.temporary_password);
          loadTenants();
        }
      });
    },

    maintenance: async () => {
      if (isManager()) {
        try { staffOptions = (await api('maintenance', { query: { staff: 1 } })).data; } catch { /* list stays empty */ }
      }
      $('#statusFilter').addEventListener('change', loadTickets);
      loadTickets();

      $('#ticketForm')?.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        const res = await submitForm(ev.target, 'maintenance', { action: 'create' });
        if (res) { ev.target.reset(); toast(res.message, 'success'); loadTickets(); }
      });
    },
  };

  document.addEventListener('DOMContentLoaded', () => {
    // mobile sidebar
    const sidebar = $('#sidebar');
    $('#menuToggle')?.addEventListener('click', () => sidebar.classList.toggle('is-open'));
    document.addEventListener('click', (e) => {
      if (sidebar?.classList.contains('is-open') && !e.target.closest('#sidebar, #menuToggle')) sidebar.classList.remove('is-open');
    });
    pages[document.body.dataset.page]?.();
  });
})();
