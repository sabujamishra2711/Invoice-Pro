/**
 * ExpenseManager — handles expense tracking CRUD and UI
 */
class ExpenseManager {
    constructor() {
        this.expenses = [];
        this.clients = [];
        this.filters = {};
    }

    init() {
        document.getElementById('create-expense-btn')?.addEventListener('click', () => this.showForm());
        document.getElementById('expense-cancel-btn')?.addEventListener('click', () => this.hideForm());
        document.getElementById('expense-save-btn')?.addEventListener('click', () => this.save());
        document.getElementById('expense-filter-btn')?.addEventListener('click', () => this.applyFilters());
        document.getElementById('expense-clear-filter-btn')?.addEventListener('click', () => this.clearFilters());

        document.getElementById('expense-billable')?.addEventListener('change', (e) => {
            document.getElementById('expense-client-group').style.display = e.target.checked ? 'block' : 'none';
        });
    }

    async load() {
        try {
            const [expensesRes, clientsRes, summaryRes] = await Promise.all([
                api.getExpenses(this.filters),
                api.getClients(),
                api.getExpenseSummary(this.filters.date_from, this.filters.date_to)
            ]);

            this.expenses = expensesRes?.data?.expenses || [];
            this.clients  = clientsRes?.data?.clients  || [];

            this.renderTable();
            this.renderSummary(summaryRes?.data);
            this.populateClientDropdowns();
            this.populateCategoryFilter();
        } catch (err) {
            console.error('Failed to load expenses', err);
            uiManager.showToast('error', 'Error', 'Failed to load expenses.');
        }
    }

    renderTable() {
        const tbody = document.getElementById('expense-table-body');
        if (!tbody) return;

        if (!this.expenses.length) {
            tbody.innerHTML = `<tr><td colspan="7">
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="fas fa-receipt"></i></div>
                    <div class="empty-state-title">No expenses yet</div>
                    <div class="empty-state-text">Start tracking your business expenses.</div>
                    <button class="btn btn-primary" onclick="expenseManager.showForm()"><i class="fas fa-plus"></i> Add Expense</button>
                </div>
            </td></tr>`;
            return;
        }

        tbody.innerHTML = this.expenses.map(exp => {
            const date = new Date(exp.expense_date).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
            const amount = this._fmt(exp.amount);
            const billableIcon = exp.is_billable
                ? (exp.invoice_id
                    ? '<i class="fas fa-check-circle" style="color:var(--success);" title="Billed"></i>'
                    : '<i class="fas fa-clock" style="color:var(--warning);" title="Unbilled"></i>')
                : '<span style="color:var(--text-tertiary);">—</span>';

            return `<tr>
                <td>${date}</td>
                <td><span class="badge badge-secondary">${this._esc(exp.category)}</span></td>
                <td>${this._esc(exp.description || '—')}</td>
                <td>${this._esc(exp.vendor || '—')}</td>
                <td style="font-weight:600;">₹${amount}</td>
                <td>${billableIcon}</td>
                <td>
                    <div style="display:flex;gap:4px;">
                        <button class="btn btn-ghost btn-xs" onclick="expenseManager.edit(${exp.id})" title="Edit"><i class="fas fa-pen"></i></button>
                        <button class="btn btn-ghost btn-xs" onclick="expenseManager.delete(${exp.id})" title="Delete" style="color:var(--danger);"><i class="fas fa-trash"></i></button>
                    </div>
                </td>
            </tr>`;
        }).join('');
    }

    renderSummary(data) {
        if (!data) return;
        const s = data.summary || {};
        const el = (id, val) => { const e = document.getElementById(id); if (e) e.textContent = val; };
        el('expense-total-amount',  '₹' + this._fmt(s.total_amount    || 0));
        el('expense-count',          s.total_count    || 0);
        el('expense-billable-amount','₹' + this._fmt(s.billable_amount  || 0));
        el('expense-unbilled',       '₹' + this._fmt(s.unbilled_amount  || 0));
    }

    populateClientDropdowns() {
        ['expense-client', 'expense-filter-client'].forEach(id => {
            const sel = document.getElementById(id);
            if (!sel) return;
            const cur = sel.value;
            sel.innerHTML = '<option value="">Select client...</option>' +
                this.clients.map(c => `<option value="${c.id}">${this._esc(c.name)}</option>`).join('');
            if (cur) sel.value = cur;
        });
    }

    populateCategoryFilter() {
        const sel = document.getElementById('expense-filter-category');
        if (!sel) return;
        const cats = [...new Set(this.expenses.map(e => e.category))].filter(Boolean).sort();
        const cur = sel.value;
        sel.innerHTML = '<option value="">All Categories</option>' +
            cats.map(c => `<option value="${c}">${this._esc(c)}</option>`).join('');
        if (cur) sel.value = cur;
    }

    showForm(expense = null) {
        document.getElementById('expenses-list-section').style.display = 'none';
        document.getElementById('expense-form-section').style.display  = 'block';
        document.getElementById('expense-form-title').textContent = expense ? 'Edit Expense' : 'New Expense';

        document.getElementById('expense-id').value              = expense?.id            || '';
        document.getElementById('expense-date').value            = expense?.expense_date  || new Date().toISOString().split('T')[0];
        document.getElementById('expense-category').value        = expense?.category       || '';
        document.getElementById('expense-amount').value          = expense?.amount         || '';
        document.getElementById('expense-vendor').value          = expense?.vendor         || '';
        document.getElementById('expense-payment-method').value  = expense?.payment_method || 'cash';
        document.getElementById('expense-description').value     = expense?.description    || '';
        document.getElementById('expense-billable').checked      = expense?.is_billable == 1;
        document.getElementById('expense-notes').value           = expense?.notes          || '';

        document.getElementById('expense-client-group').style.display = expense?.is_billable == 1 ? 'block' : 'none';
        this.populateClientDropdowns();
        document.getElementById('expense-client').value = expense?.client_id || '';
    }

    hideForm() {
        document.getElementById('expenses-list-section').style.display = 'block';
        document.getElementById('expense-form-section').style.display  = 'none';
    }

    async save() {
        const id = document.getElementById('expense-id').value;
        const billable = document.getElementById('expense-billable').checked;

        const data = {
            expense_date:   document.getElementById('expense-date').value,
            category:       document.getElementById('expense-category').value,
            amount:         parseFloat(document.getElementById('expense-amount').value) || 0,
            vendor:         document.getElementById('expense-vendor').value.trim(),
            payment_method: document.getElementById('expense-payment-method').value,
            description:    document.getElementById('expense-description').value.trim(),
            is_billable:    billable ? 1 : 0,
            client_id:      billable ? (document.getElementById('expense-client').value || null) : null,
            notes:          document.getElementById('expense-notes').value.trim()
        };

        if (!data.expense_date) { uiManager.showToast('error', 'Validation', 'Please select a date.'); return; }
        if (!data.category)     { uiManager.showToast('error', 'Validation', 'Please select a category.'); return; }
        if (data.amount <= 0)   { uiManager.showToast('error', 'Validation', 'Please enter a valid amount.'); return; }

        try {
            const res = id ? await api.updateExpense(id, data) : await api.createExpense(data);
            if (res.success) {
                uiManager.showToast('success', id ? 'Updated' : 'Created', `Expense ${id ? 'updated' : 'created'} successfully.`);
                this.hideForm();
                await this.load();
            } else {
                uiManager.showToast('error', 'Error', res.message || 'Failed to save expense.');
            }
        } catch (err) {
            console.error('Failed to save expense', err);
            uiManager.showToast('error', 'Error', 'Failed to save expense.');
        }
    }

    edit(id) {
        const expense = this.expenses.find(e => e.id == id);
        if (expense) this.showForm(expense);
    }

    async delete(id) {
        if (!confirm('Are you sure you want to delete this expense?')) return;
        try {
            const res = await api.deleteExpense(id);
            if (res.success) {
                uiManager.showToast('success', 'Deleted', 'Expense deleted.');
                await this.load();
            } else {
                uiManager.showToast('error', 'Error', res.message || 'Failed to delete expense.');
            }
        } catch (err) {
            console.error('Failed to delete expense', err);
            uiManager.showToast('error', 'Error', 'Failed to delete expense.');
        }
    }

    applyFilters() {
        this.filters = {
            category:  document.getElementById('expense-filter-category')?.value || '',
            date_from: document.getElementById('expense-filter-from')?.value     || '',
            date_to:   document.getElementById('expense-filter-to')?.value       || ''
        };
        this.load();
    }

    clearFilters() {
        const ids = ['expense-filter-category', 'expense-filter-from', 'expense-filter-to'];
        ids.forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
        this.filters = {};
        this.load();
    }

    _fmt(num) {
        return parseFloat(num || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    _esc(str) {
        return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
}

// Global instance
window.expenseManager = new ExpenseManager();
