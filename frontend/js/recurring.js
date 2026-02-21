/**
 * RecurringManager — manages recurring invoices UI
 */
class RecurringManager {
    constructor() {
        this._data = [];
        this._clients = [];
        this._editingId = null;
    }

    // ── init (called once after DOM ready) ───────────────────────────────────

    init() {
        document.getElementById('create-recurring-btn')
            ?.addEventListener('click', () => this.showForm());
        document.getElementById('recurring-cancel-btn')
            ?.addEventListener('click', () => this.hideForm());
        document.getElementById('recurring-save-btn')
            ?.addEventListener('click', () => this.save());
        document.getElementById('recurring-add-item')
            ?.addEventListener('click', () => this.addItemRow());
    }

    // ── Load list ────────────────────────────────────────────────────────────

    async load() {
        try {
            const [listRes, clientRes] = await Promise.all([
                api.getRecurringInvoices(),
                api.getClients()
            ]);
            this._data    = listRes?.data?.recurring_invoices || [];
            this._clients = clientRes?.data?.clients          || [];
            this._populateClientDropdowns();
            this._renderTable();
        } catch (err) {
            console.error('Failed to load recurring invoices', err);
        }
    }

    // ── Table render ─────────────────────────────────────────────────────────

    _renderTable() {
        const tbody = document.getElementById('recurring-table-body');
        if (!tbody) return;

        if (!this._data.length) {
            tbody.innerHTML = `<tr><td colspan="8">
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="fas fa-redo-alt"></i></div>
                    <div class="empty-state-title">No recurring invoices</div>
                    <div class="empty-state-text">Create a recurring invoice to auto-generate invoices on a schedule.</div>
                    <button class="btn btn-primary" onclick="recurringManager.showForm()"><i style="margin:0" class="fas fa-plus"></i> New Recurring Invoice</button>
                </div>
            </td></tr>`;
            return;
        }

        tbody.innerHTML = this._data.map(r => {
            const freq = { weekly: 'Weekly', biweekly: 'Bi-weekly', monthly: 'Monthly', quarterly: 'Quarterly', yearly: 'Yearly' }[r.frequency] || r.frequency;
            const statusBadge = r.is_active
                ? '<span class="status-badge status-sent">Active</span>'
                : '<span class="status-badge status-draft">Paused</span>';
            const pauseBtn = r.is_active
                ? `<button class="btn btn-xs btn-outline" title="Pause" onclick="recurringManager.pause(${r.id})"><i class="fas fa-pause"></i></button>`
                : `<button class="btn btn-xs btn-outline" title="Resume" onclick="recurringManager.resume(${r.id})"><i class="fas fa-play"></i></button>`;

            return `<tr>
                <td><strong>${this._esc(r.title)}</strong></td>
                <td>${this._esc(r.client_name)}${r.client_company ? '<br><small style="color:var(--text-tertiary);">' + this._esc(r.client_company) + '</small>' : ''}</td>
                <td>${freq}</td>
                <td>${r.next_date || '—'}</td>
                <td>${r.end_date || '—'}</td>
                <td>${statusBadge}</td>
                <td style="text-align:center;">${r.invoices_created || 0}</td>
                <td>
                    <div style="display:flex;gap:4px;flex-wrap:wrap;">
                        <button class="btn btn-xs btn-primary" title="Generate now" onclick="recurringManager.generateNow(${r.id})"><i class="fas fa-bolt"></i></button>
                        <button class="btn btn-xs btn-secondary" title="Edit" onclick="recurringManager.edit(${r.id})"><i class="fas fa-pencil-alt"></i></button>
                        ${pauseBtn}
                        <button class="btn btn-xs btn-danger" title="Delete" onclick="recurringManager.remove(${r.id})"><i class="fas fa-trash"></i></button>
                    </div>
                </td>
            </tr>`;
        }).join('');
    }

    // ── Form ─────────────────────────────────────────────────────────────────

    showForm(data = null) {
        this._editingId = data ? data.id : null;
        const titleEl = document.getElementById('recurring-form-title');
        if (titleEl) titleEl.textContent = data ? 'Edit Recurring Invoice' : 'New Recurring Invoice';

        // Reset form
        document.getElementById('recurring-id').value          = data?.id        || '';
        document.getElementById('recurring-title').value       = data?.title     || '';
        document.getElementById('recurring-frequency').value   = data?.frequency || 'monthly';
        document.getElementById('recurring-next-date').value   = data?.next_date || this._today();
        document.getElementById('recurring-end-date').value    = data?.end_date  || '';
        document.getElementById('recurring-currency').value    = data?.currency  || 'INR';
        document.getElementById('recurring-notes').value       = data?.notes     || '';

        // Items
        const container = document.getElementById('recurring-items-container');
        container.innerHTML = '';
        if (data?.items?.length) {
            data.items.forEach(item => this.addItemRow(item));
        } else {
            this.addItemRow();
        }
        this._calcTotals();

        this._editingId = data ? data.id : null;
        document.getElementById('recurring-list-section').style.display = 'none';
        document.getElementById('recurring-form-section').style.display = '';
    }

    hideForm() {
        document.getElementById('recurring-form-section').style.display = 'none';
        document.getElementById('recurring-list-section').style.display = '';
        this._editingId = null;
    }

    async edit(id) {
        try {
            const res = await api.getRecurringInvoice(id);
            if (res?.success) this.showForm(res.data);
        } catch (err) {
            uiManager.showToast('error', 'Error', err.message);
        }
    }

    // ── Save ─────────────────────────────────────────────────────────────────

    async save() {
        const id       = document.getElementById('recurring-id').value;
        const title    = document.getElementById('recurring-title').value.trim();
        const clientId = document.getElementById('recurring-client').value;
        const freq     = document.getElementById('recurring-frequency').value;
        const nextDate = document.getElementById('recurring-next-date').value;
        const endDate  = document.getElementById('recurring-end-date').value;
        const currency = document.getElementById('recurring-currency').value;
        const notes    = document.getElementById('recurring-notes').value.trim();

        if (!title)    { uiManager.showToast('error', 'Validation', 'Title is required'); return; }
        if (!clientId) { uiManager.showToast('error', 'Validation', 'Please select a client'); return; }
        if (!nextDate) { uiManager.showToast('error', 'Validation', 'Start date is required'); return; }

        const items = this._collectItems();
        if (!items.length) { uiManager.showToast('error', 'Validation', 'At least one item is required'); return; }

        const payload = { title, client_id: clientId, frequency: freq, next_date: nextDate,
                          end_date: endDate || null, currency, notes, items };

        const btn = document.getElementById('recurring-save-btn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

        try {
            let res;
            if (id) {
                res = await api.updateRecurringInvoice(id, payload);
            } else {
                res = await api.createRecurringInvoice(payload);
            }
            if (!res?.success) throw new Error(res?.message || 'Save failed');
            uiManager.showToast('success', 'Saved', id ? 'Recurring invoice updated' : 'Recurring invoice created');
            this.hideForm();
            await this.load();
        } catch (err) {
            uiManager.showToast('error', 'Error', err.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check"></i> Save';
        }
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    async generateNow(id) {
        if (!confirm('Generate an invoice from this recurring schedule right now?')) return;
        try {
            const res = await api.generateRecurringInvoice(id);
            if (!res?.success) throw new Error(res?.message || 'Generation failed');
            uiManager.showToast('success', 'Generated', 'Invoice created successfully');
            await this.load();
            // Refresh invoices list if visible
            if (uiManager.currentView === 'invoices') await uiManager.loadInvoices();
        } catch (err) {
            uiManager.showToast('error', 'Error', err.message);
        }
    }

    async pause(id) {
        try {
            const res = await api.pauseRecurringInvoice(id);
            if (!res?.success) throw new Error(res?.message || 'Failed');
            uiManager.showToast('info', 'Paused', 'Recurring invoice paused');
            await this.load();
        } catch (err) {
            uiManager.showToast('error', 'Error', err.message);
        }
    }

    async resume(id) {
        try {
            const res = await api.resumeRecurringInvoice(id);
            if (!res?.success) throw new Error(res?.message || 'Failed');
            uiManager.showToast('success', 'Resumed', 'Recurring invoice resumed');
            await this.load();
        } catch (err) {
            uiManager.showToast('error', 'Error', err.message);
        }
    }

    async remove(id) {
        if (!confirm('Delete this recurring invoice? This will not delete already-generated invoices.')) return;
        try {
            const res = await api.deleteRecurringInvoice(id);
            if (!res?.success) throw new Error(res?.message || 'Failed');
            uiManager.showToast('success', 'Deleted', 'Recurring invoice deleted');
            await this.load();
        } catch (err) {
            uiManager.showToast('error', 'Error', err.message);
        }
    }

    // ── Line Item helpers ────────────────────────────────────────────────────

    addItemRow(item = null) {
        const container = document.getElementById('recurring-items-container');
        const row = document.createElement('div');
        row.className = 'invoice-item-row';
        row.innerHTML = `
            <input type="text"   class="form-control ri-desc"  placeholder="Description" value="${this._esc(item?.description || '')}">
            <input type="number" class="form-control ri-qty"   placeholder="1"    value="${item?.quantity  || 1}"   min="0.01" step="0.01">
            <input type="number" class="form-control ri-rate"  placeholder="0.00" value="${item?.rate      || ''}"  min="0"    step="0.01">
            <input type="number" class="form-control ri-tax"   placeholder="0"    value="${item?.tax_percent || 0}" min="0" max="100" step="0.01">
            <span class="ri-line-total">0.00</span>
            <button type="button" class="btn btn-xs btn-ghost ri-remove-btn" title="Remove"><i class="fas fa-times"></i></button>
        `;
        row.querySelector('.ri-remove-btn').addEventListener('click', () => {
            row.remove();
            this._calcTotals();
        });
        ['ri-qty', 'ri-rate', 'ri-tax'].forEach(cls => {
            row.querySelector('.' + cls).addEventListener('input', () => this._calcTotals());
        });
        container.appendChild(row);
        this._updateLineTotal(row);
        this._calcTotals();
    }

    _updateLineTotal(row) {
        const qty  = parseFloat(row.querySelector('.ri-qty').value)  || 0;
        const rate = parseFloat(row.querySelector('.ri-rate').value) || 0;
        const tax  = parseFloat(row.querySelector('.ri-tax').value)  || 0;
        const total = qty * rate * (1 + tax / 100);
        row.querySelector('.ri-line-total').textContent = total.toFixed(2);
    }

    _calcTotals() {
        let subtotal = 0, taxAmt = 0;
        document.querySelectorAll('#recurring-items-container .invoice-item-row').forEach(row => {
            this._updateLineTotal(row);
            const qty  = parseFloat(row.querySelector('.ri-qty').value)  || 0;
            const rate = parseFloat(row.querySelector('.ri-rate').value) || 0;
            const tax  = parseFloat(row.querySelector('.ri-tax').value)  || 0;
            const line = qty * rate;
            subtotal += line;
            taxAmt   += line * (tax / 100);
        });
        document.getElementById('recurring-subtotal').textContent = subtotal.toFixed(2);
        document.getElementById('recurring-tax').textContent      = taxAmt.toFixed(2);
        document.getElementById('recurring-total').textContent    = (subtotal + taxAmt).toFixed(2);
    }

    _collectItems() {
        const items = [];
        document.querySelectorAll('#recurring-items-container .invoice-item-row').forEach(row => {
            const desc = row.querySelector('.ri-desc').value.trim();
            if (!desc) return;
            items.push({
                description: desc,
                quantity:    parseFloat(row.querySelector('.ri-qty').value)  || 1,
                rate:        parseFloat(row.querySelector('.ri-rate').value) || 0,
                tax_percent: parseFloat(row.querySelector('.ri-tax').value)  || 0
            });
        });
        return items;
    }

    _populateClientDropdowns() {
        const sel = document.getElementById('recurring-client');
        if (!sel) return;
        const current = sel.value;
        sel.innerHTML = '<option value="">Select client...</option>' +
            this._clients.map(c => `<option value="${c.id}">${this._esc(c.name)}${c.company ? ' — ' + this._esc(c.company) : ''}</option>`).join('');
        if (current) sel.value = current;
    }

    _today() {
        return new Date().toISOString().slice(0, 10);
    }

    _esc(str) {
        return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
}

// Global instance
window.recurringManager = new RecurringManager();
