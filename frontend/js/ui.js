/**
 * UI Manager — handles all view rendering, modals, and user interactions
 */
class UIManager {
    constructor() {
        this.currentView = 'dashboard';
        this.charts = {};
        this.invoicesData = [];
        this.clientsData = [];
        this.paymentsData = [];
        this.loadingOverlay = document.getElementById('loading-overlay');
        // Pagination state
        this._page = { invoices: 1, payments: 1, clients: 1 };
        this._perPage = 10;
    }

    setLoading(show) {
        if (this.loadingOverlay) {
            this.loadingOverlay.classList.toggle('active', show);
        }
    }

    // ── Navigation ──
    showView(viewName) {
        // Map simple names to actual view IDs
        const viewMap = {
            'dashboard': 'dashboard-view',
            'invoices': 'invoices-view',
            'invoice-form': 'invoice-form-view',
            'clients': 'clients-view',
            'payments': 'payments-view',
            'recurring': 'recurring-view',
            'expenses': 'expenses-view',
            'reports': 'reports-view',
            'settings': 'settings-view'
        };

        const viewId = viewMap[viewName];
        if (!viewId) return;

        // Hide all views
        document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));

        // Show target view
        const target = document.getElementById(viewId);
        if (target) {
            target.classList.add('active');
        } else {
            console.error(`View NOT found: ${viewId}`);
        }

        this.currentView = viewName;

        // Update nav
        document.querySelectorAll('.sidebar .nav-link').forEach(link => {
            link.classList.toggle('active', link.dataset.view === viewName);
        });

        // Update page title
        const titles = {
            'dashboard': 'Dashboard',
            'invoices': 'Invoices',
            'invoice-form': 'Invoice',
            'clients': 'Clients',
            'payments': 'Payments',
            'recurring': 'Recurring Invoices',
            'expenses': 'Expenses',
            'reports': 'Reports',
            'settings': 'Settings'
        };
        const pageTitle = document.getElementById('page-title');
        if (pageTitle) pageTitle.textContent = titles[viewName] || 'Dashboard';

        // Update URL hash
        window.location.hash = viewName;

        // Load data for the view
        this.loadViewData(viewName);

        // Close sidebar on mobile
        this.closeSidebar();
    }

    async loadViewData(viewName) {
        this.setLoading(true);
        try {
            switch (viewName) {
                case 'dashboard': await this.loadDashboard(); break;
                case 'invoices':
                    await Promise.all([this.loadInvoices(), this.loadClients()]);
                    break;
                case 'invoice-form':
                    if (!this.clientsData.length) await this.loadClients();
                    break;
                case 'clients': await this.loadClients(); break;
                case 'payments':
                    await Promise.all([this.loadPayments(), this.loadInvoices()]);
                    break;
                case 'reports': await this.loadReports(); break;
                case 'settings': await this.loadSettings(); this.initSettingsTabs(); this.loadPlanUsage(); break;
                case 'recurring': if (window.recurringManager) await recurringManager.load(); break;
                case 'expenses':  if (window.expenseManager)  await expenseManager.load();  break;
            }
        } catch (err) {
            console.error('Error loading view data:', err);
        } finally {
            this.setLoading(false);
        }
    }

    // ── Dashboard ──
    async loadDashboard() {
        try {
            const result = await api.getDashboardStats();
            const data = result?.data || {};

            // Update stat values
            this._setText('stat-total-invoices', data.total_invoices ?? 0);
            this._setText('stat-total-revenue', this.formatCurrency(data.total_revenue ?? 0));
            this._setText('stat-pending-amount', this.formatCurrency(data.pending_amount ?? 0));
            this._setText('stat-total-clients', data.total_clients ?? 0);

            // Render recent invoices
            this.renderRecentInvoices(data.recent_invoices || []);

            // Render charts
            this.renderRevenueChart(data.monthly_revenue || []);
            this.renderStatusChart(data.status_counts || {});

        } catch (err) {
            console.warn('Dashboard load error:', err);
            this._showSampleDashboard();
        }
    }

    _showSampleDashboard() {
        this._setText('stat-total-invoices', '0');
        this._setText('stat-total-revenue', '₹0');
        this._setText('stat-pending-amount', '₹0');
        this._setText('stat-total-clients', '0');
        this.renderRevenueChart([]);
        this.renderStatusChart({});
    }

    renderRecentInvoices(invoices) {
        const tbody = document.getElementById('recent-invoices-body');
        if (!tbody) return;

        if (!invoices.length) {
            tbody.innerHTML = `<tr><td colspan="5"><div class="empty-state" style="padding:40px 20px;">
                <div class="empty-state-icon"><i class="fas fa-file-invoice"></i></div>
                <div class="empty-state-title">No invoices yet</div>
                <div class="empty-state-text">Create your first invoice to get started.</div>
            </div></td></tr>`;
            return;
        }

        tbody.innerHTML = invoices.slice(0, 5).map(inv => `
            <tr>
                <td><span style="font-weight:600;">${this.escapeHtml(inv.invoice_number || '')}</span></td>
                <td>${this.escapeHtml(inv.client_name || inv.client_company || '')}</td>
                <td style="font-weight:600;">${this.formatCurrency(inv.total_amount)}</td>
                <td style="color:var(--text-secondary);">${this.formatDate(inv.issue_date)}</td>
                <td><span class="status-pill ${inv.status}"><span class="status-dot"></span>${inv.status}</span></td>
            </tr>
        `).join('');
    }

    renderRevenueChart(monthlyData, numMonths = 6) {
        const ctx = document.getElementById('revenueChart');
        if (!ctx) return;

        if (this.charts.revenue) this.charts.revenue.destroy();

        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const now = new Date();
        const labels = [];
        const values = [];

        for (let i = numMonths - 1; i >= 0; i--) {
            const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
            labels.push(months[d.getMonth()]);
            const key = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
            const found = monthlyData.find(m => m.month === key);
            values.push(found ? parseFloat(found.total) : 0);
        }

        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const gridColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)';
        const textColor = isDark ? '#94a3b8' : '#64748b';

        this.charts.revenue = new Chart(ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Revenue',
                    data: values,
                    backgroundColor: 'rgba(99, 102, 241, 0.15)',
                    borderColor: '#6366f1',
                    borderWidth: 2,
                    borderRadius: 8,
                    borderSkipped: false,
                    hoverBackgroundColor: 'rgba(99, 102, 241, 0.3)',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: isDark ? '#1e293b' : '#fff',
                        titleColor: isDark ? '#f1f5f9' : '#0f172a',
                        bodyColor: isDark ? '#94a3b8' : '#475569',
                        borderColor: isDark ? '#334155' : '#e2e8f0',
                        borderWidth: 1,
                        cornerRadius: 10,
                        padding: 12,
                        callbacks: { label: (ctx) => '₹' + ctx.raw.toLocaleString('en-IN') }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { color: textColor, font: { size: 12, weight: 500 } } },
                    y: {
                        grid: { color: gridColor },
                        ticks: {
                            color: textColor,
                            font: { size: 11 },
                            callback: (v) => '₹' + (v >= 1000 ? (v / 1000).toFixed(0) + 'k' : v)
                        }
                    }
                }
            }
        });
    }

    renderStatusChart(statusCounts) {
        const ctx = document.getElementById('statusChart');
        if (!ctx) return;

        if (this.charts.status) this.charts.status.destroy();

        const labels = ['Draft', 'Sent', 'Partial', 'Paid', 'Overdue'];
        const data = [
            statusCounts.draft || 0,
            statusCounts.sent || 0,
            statusCounts.partial || 0,
            statusCounts.paid || 0,
            statusCounts.overdue || 0
        ];

        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const total = data.reduce((a, b) => a + b, 0);

        this.charts.status = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{
                    data: total > 0 ? data : [1],
                    backgroundColor: total > 0
                        ? ['#94a3b8', '#3b82f6', '#f59e0b', '#10b981', '#ef4444']
                        : ['rgba(148,163,184,0.15)'],
                    borderWidth: 0,
                    spacing: 3,
                    borderRadius: 4,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: isDark ? '#94a3b8' : '#475569',
                            padding: 20,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            boxWidth: 8,
                            boxHeight: 8,
                            font: { size: 12, weight: 500 }
                        }
                    },
                    tooltip: {
                        backgroundColor: isDark ? '#1e293b' : '#fff',
                        titleColor: isDark ? '#f1f5f9' : '#0f172a',
                        bodyColor: isDark ? '#94a3b8' : '#475569',
                        borderColor: isDark ? '#334155' : '#e2e8f0',
                        borderWidth: 1,
                        cornerRadius: 10,
                        padding: 12,
                    }
                }
            }
        });
    }

    // ── Invoices ──
    async loadInvoices() {
        try {
            const result = await api.getInvoices();
            this.invoicesData = result?.data?.invoices || [];
            this.renderInvoicesTable(this.invoicesData);
        } catch (err) {
            console.warn('Load invoices error:', err);
            this.renderInvoicesTable([]);
        }
    }

    renderInvoicesTable(invoices, filter = 'all', search = '') {
        const tbody = document.getElementById('invoices-table-body');
        if (!tbody) return;

        let filtered = invoices;
        if (filter && filter !== 'all') {
            filtered = filtered.filter(inv => inv.status === filter);
        }
        if (search) {
            const s = search.toLowerCase();
            filtered = filtered.filter(inv =>
                (inv.invoice_number || '').toLowerCase().includes(s) ||
                (inv.client_name || '').toLowerCase().includes(s) ||
                (inv.client_company || '').toLowerCase().includes(s)
            );
        }

        if (!filtered.length) {
            tbody.innerHTML = `<tr><td colspan="7"><div class="empty-state" style="padding:40px 20px;">
                <div class="empty-state-icon"><i class="fas fa-file-invoice"></i></div>
                <div class="empty-state-title">No invoices found</div>
                <div class="empty-state-text">${filter !== 'all' ? 'No invoices match this filter.' : 'Create your first invoice to start tracking revenue.'}</div>
            </div></td></tr>`;
            this._renderPagination('invoices-pagination', 0, 1, 1);
            return;
        }

        // Clamp page
        const totalPages = Math.ceil(filtered.length / this._perPage);
        if (this._page.invoices > totalPages) this._page.invoices = totalPages;
        const page = this._page.invoices;
        const start = (page - 1) * this._perPage;
        const paged = filtered.slice(start, start + this._perPage);

        tbody.innerHTML = paged.map(inv => `
            <tr>
                <td><span style="font-weight:600;color:var(--primary);cursor:pointer;" onclick="uiManager.previewInvoice(${inv.id})">${this.escapeHtml(inv.invoice_number || '')}</span></td>
                <td>
                    <div style="font-weight:500;">${this.escapeHtml(inv.client_name || '')}</div>
                    ${inv.client_company ? `<div style="font-size:0.78rem;color:var(--text-tertiary);">${this.escapeHtml(inv.client_company)}</div>` : ''}
                </td>
                <td style="font-weight:600;">
                    ${this.formatCurrency(inv.total_amount, inv.currency)}
                    ${inv.currency && inv.currency !== 'INR' ? `<span style="font-size:0.72rem;color:var(--text-tertiary);margin-left:3px;">${this.escapeHtml(inv.currency)}</span>` : ''}
                </td>
                <td style="color:var(--text-secondary);font-size:0.84rem;">${this.formatDate(inv.issue_date)}</td>
                <td style="color:var(--text-secondary);font-size:0.84rem;">${this.formatDate(inv.due_date)}</td>
                <td><span class="status-pill ${inv.status}"><span class="status-dot"></span>${inv.status}</span></td>
                <td>
                    <div style="display:flex;gap:4px;">
                        <button class="btn-icon btn-ghost sm" title="View" onclick="uiManager.previewInvoice(${inv.id})"><i class="fas fa-eye"></i></button>
                        <button class="btn-icon btn-ghost sm" title="Edit" onclick="uiManager.editInvoice(${inv.id})"><i class="fas fa-pen"></i></button>
                        <button class="btn-icon btn-ghost sm" title="Duplicate" onclick="uiManager.duplicateInvoice(${inv.id})"><i class="fas fa-copy"></i></button>
                        <button class="btn-icon btn-ghost sm" title="Delete" onclick="uiManager.deleteInvoice(${inv.id})" style="color:var(--danger);"><i class="fas fa-trash"></i></button>
                    </div>
                </td>
            </tr>
        `).join('');

        this._renderPagination('invoices-pagination', filtered.length, page, totalPages,
            (p) => { this._page.invoices = p; this.renderInvoicesTable(invoices, filter, search); });
    }

    async showInvoiceForm(invoiceData = null) {
        // Reset form
        document.getElementById('invoice-form').reset();
        document.getElementById('invoice-id').value = '';
        document.getElementById('invoice-form-title').textContent = invoiceData ? 'Edit Invoice' : 'New Invoice';

        // Ensure clients are loaded before populating dropdown
        if (!this.clientsData.length) {
            try {
                const result = await api.getClients();
                this.clientsData = result?.data?.clients || [];
            } catch (e) { /* ignore */ }
        }
        this._populateClientDropdown();

        // Set default dates
        const today = new Date().toISOString().split('T')[0];
        const dueDate = new Date();
        dueDate.setDate(dueDate.getDate() + 30);
        document.getElementById('invoice-issue-date').value = invoiceData?.issue_date || today;
        document.getElementById('invoice-due-date').value = invoiceData?.due_date || dueDate.toISOString().split('T')[0];

        // Determine currency: invoice currency > settings default > localStorage > INR
        const defaultCurrency = invoiceData?.currency
            || document.getElementById('setting-currency')?.value
            || localStorage.getItem('default_currency')
            || 'INR';
        document.getElementById('invoice-currency').value = defaultCurrency;

        // Wire currency change → update totals symbol immediately
        const currencySelect = document.getElementById('invoice-currency');
        if (currencySelect && !currencySelect._currencyWired) {
            currencySelect._currencyWired = true;
            currencySelect.addEventListener('change', () => this.calculateInvoiceTotals());
        }

        // Clear and add items
        const container = document.getElementById('invoice-items-container');
        container.innerHTML = '';

        if (invoiceData && invoiceData.items && invoiceData.items.length) {
            invoiceData.items.forEach(item => this.addInvoiceItemRow(item));
            document.getElementById('invoice-id').value = invoiceData.id;
            document.getElementById('invoice-client').value = invoiceData.client_id;
            document.getElementById('invoice-notes').value = invoiceData.notes || '';
        } else {
            this.addInvoiceItemRow();
        }

        this.calculateInvoiceTotals();
        this.showView('invoice-form');
    }

    _populateClientDropdown() {
        const select = document.getElementById('invoice-client');
        if (!select) return;
        const currentVal = select.value;
        select.innerHTML = '<option value="">Select client...</option>';
        this.clientsData.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = c.name + (c.company ? ` — ${c.company}` : '');
            select.appendChild(opt);
        });
        if (currentVal) select.value = currentVal;
    }

    addInvoiceItemRow(item = null) {
        const container = document.getElementById('invoice-items-container');
        const row = document.createElement('div');
        row.className = 'invoice-item-row';
        row.innerHTML = `
            <input type="text" class="form-control item-desc" placeholder="Description" value="${this.escapeHtml(item?.description || '')}">
            <input type="number" class="form-control item-qty" placeholder="1" value="${item?.quantity ?? 1}" min="0" step="0.01">
            <input type="number" class="form-control item-rate" placeholder="0.00" value="${item?.rate ?? ''}" min="0" step="0.01">
            <input type="number" class="form-control item-tax" placeholder="0" value="${item?.tax_percent ?? 0}" min="0" max="100" step="0.01">
            <input type="text" class="form-control item-total" value="${item?.line_total ? parseFloat(item.line_total).toFixed(2) : '0.00'}" readonly style="background:var(--bg-surface-hover);font-weight:600;">
            <button class="btn-icon btn-ghost sm remove-item-btn" title="Remove" style="color:var(--danger);"><i class="fas fa-trash"></i></button>
        `;

        // Events
        const inputs = row.querySelectorAll('.item-qty, .item-rate, .item-tax');
        inputs.forEach(input => input.addEventListener('input', () => this.calculateInvoiceTotals()));
        row.querySelector('.remove-item-btn').addEventListener('click', () => {
            row.remove();
            this.calculateInvoiceTotals();
        });

        container.appendChild(row);
    }

    calculateInvoiceTotals() {
        const rows = document.querySelectorAll('#invoice-items-container .invoice-item-row');
        let subtotal = 0;
        let totalTax = 0;

        const currency = document.getElementById('invoice-currency')?.value || 'INR';

        rows.forEach(row => {
            const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
            const rate = parseFloat(row.querySelector('.item-rate').value) || 0;
            const taxPct = parseFloat(row.querySelector('.item-tax').value) || 0;
            const lineSubtotal = qty * rate;
            const lineTax = lineSubtotal * (taxPct / 100);
            const lineTotal = lineSubtotal + lineTax;

            row.querySelector('.item-total').value = lineTotal.toFixed(2);
            subtotal += lineSubtotal;
            totalTax += lineTax;
        });

        const sym = this.currencySymbol(currency);
        this._setText('invoice-subtotal', sym + subtotal.toFixed(2));
        this._setText('invoice-tax',      sym + totalTax.toFixed(2));
        this._setText('invoice-total',    sym + (subtotal + totalTax).toFixed(2));
    }

    async saveInvoice() {
        const clientId = document.getElementById('invoice-client').value;
        const issueDate = document.getElementById('invoice-issue-date').value;
        const dueDate = document.getElementById('invoice-due-date').value;

        if (!clientId || !issueDate || !dueDate) {
            this.showToast('error', 'Missing Fields', 'Please fill in client, issue date, and due date.');
            return;
        }

        const items = [];
        document.querySelectorAll('#invoice-items-container .invoice-item-row').forEach(row => {
            const desc = row.querySelector('.item-desc').value.trim();
            const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
            const rate = parseFloat(row.querySelector('.item-rate').value) || 0;
            const taxPct = parseFloat(row.querySelector('.item-tax').value) || 0;
            if (desc && qty > 0 && rate > 0) {
                items.push({
                    description: desc,
                    quantity: qty,
                    rate: rate,
                    tax_percent: taxPct,
                    line_total: (qty * rate * (1 + taxPct / 100)).toFixed(2)
                });
            }
        });

        if (!items.length) {
            this.showToast('error', 'No Items', 'Add at least one line item.');
            return;
        }

        const payload = {
            client_id: parseInt(clientId),
            issue_date: issueDate,
            due_date: dueDate,
            currency: document.getElementById('invoice-currency').value,
            notes: document.getElementById('invoice-notes').value,
            items: items
        };

        try {
            const invoiceId = document.getElementById('invoice-id').value;
            let result;
            if (invoiceId) {
                result = await api.updateInvoice(invoiceId, payload);
            } else {
                result = await api.createInvoice(payload);
            }

            if (result.success) {
                this.showToast('success', 'Invoice Saved', 'Invoice has been saved successfully.');
                this.showView('invoices');
            } else if (result.error_code === 'LIMIT_REACHED') {
                this.handleLimitReached(result);
            } else {
                this.showToast('error', 'Error', result.message || 'Failed to save invoice.');
            }
        } catch (err) {
            this.showToast('error', 'Error', err.message || 'Failed to save invoice.');
        }
    }

    async editInvoice(id) {
        try {
            const result = await api.getInvoice(id);
            if (result?.data) {
                // Backend returns { invoice: {...}, items: [...] }
                const invoice = result.data.invoice || result.data;
                invoice.items = result.data.items || invoice.items || [];
                this.showInvoiceForm(invoice);
            }
        } catch (err) {
            this.showToast('error', 'Error', 'Could not load invoice details.');
        }
    }

    async deleteInvoice(id) {
        if (!confirm('Are you sure you want to delete this invoice?')) return;
        try {
            const result = await api.deleteInvoice(id);
            if (result.success) {
                this.showToast('success', 'Deleted', 'Invoice has been deleted.');
                await this.loadInvoices();
            } else {
                this.showToast('error', 'Error', result.message || 'Failed to delete invoice.');
            }
        } catch (err) {
            this.showToast('error', 'Error', err.message);
        }
    }

    async duplicateInvoice(id) {
        try {
            this.setLoading(true);
            const result = await api.duplicateInvoice(id);
            if (result.success) {
                const newInv = result.data?.invoice;
                this.showToast('success', 'Duplicated', `Invoice duplicated as ${newInv?.invoice_number || ''} (Draft).`);
                // Close preview modal if open, reload list, then open the new invoice
                this.closeModal('invoice-preview-modal');
                await this.loadInvoices();
                if (newInv?.id) {
                    this.previewInvoice(newInv.id);
                }
            } else if (result.error_code === 'LIMIT_REACHED') {
                this.handleLimitReached(result);
            } else {
                this.showToast('error', 'Error', result.message || 'Failed to duplicate invoice.');
            }
        } catch (err) {
            this.showToast('error', 'Error', err.message || 'Failed to duplicate invoice.');
        } finally {
            this.setLoading(false);
        }
    }

    // ── Clients ──
    async loadClients() {
        try {
            const result = await api.getClients();
            this.clientsData = result?.data?.clients || [];
            this.renderClientsGrid(this.clientsData);
        } catch (err) {
            console.warn('Load clients error:', err);
            this.renderClientsGrid([]);
        }
    }

    renderClientsGrid(clients, search = '') {
        const grid = document.getElementById('clients-grid');
        const empty = document.getElementById('clients-empty');
        if (!grid) return;

        let filtered = clients;
        if (search) {
            const s = search.toLowerCase();
            filtered = filtered.filter(c =>
                (c.name || '').toLowerCase().includes(s) ||
                (c.company || '').toLowerCase().includes(s) ||
                (c.email || '').toLowerCase().includes(s)
            );
        }

        if (!filtered.length) {
            grid.innerHTML = `<div class="empty-state" style="grid-column:1/-1;">
                <i class="fas fa-users"></i>
                <h3>${search ? 'No clients found' : 'No clients yet'}</h3>
                <p>${search ? 'Try a different search term' : 'Add your first client to start creating invoices'}</p>
            </div>`;
            if (empty) empty.style.display = 'none';
            this._renderPagination('clients-pagination', 0, 1, 1);
            return;
        }

        if (empty) empty.style.display = 'none';

        const totalPages = Math.ceil(filtered.length / this._perPage);
        if (this._page.clients > totalPages) this._page.clients = totalPages;
        const page = this._page.clients;
        const start = (page - 1) * this._perPage;
        const paged = filtered.slice(start, start + this._perPage);

        const colors = ['#6366f1', '#06b6d4', '#f43f5e', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899'];

        grid.innerHTML = paged.map((c, i) => {
            const initials = this._getInitials(c.name);
            const color = colors[(start + i) % colors.length];
            const cardData = JSON.stringify(c).replace(/"/g, '&quot;');
            return `
                <div class="client-card" onclick="uiManager.showClientModal(${cardData})">
                    <div class="client-card-actions">
                        <button class="btn-icon btn-ghost sm" title="Edit" onclick="event.stopPropagation();uiManager.showClientModal(${cardData})"><i class="fas fa-pen"></i></button>
                        <button class="btn-icon btn-ghost sm" title="Delete" onclick="event.stopPropagation();uiManager.deleteClient(${c.id})" style="color:var(--danger);"><i class="fas fa-trash"></i></button>
                    </div>
                    <div style="display:flex;align-items:center;gap:14px;margin-bottom:14px;">
                        <div class="client-avatar" style="background:${color};">${initials}</div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:600;font-size:0.95rem;">${this.escapeHtml(c.name)}</div>
                            ${c.company ? `<div style="font-size:0.8rem;color:var(--text-tertiary);">${this.escapeHtml(c.company)}</div>` : ''}
                        </div>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:6px;font-size:0.84rem;color:var(--text-secondary);">
                        ${c.email ? `<div><i class="fas fa-envelope" style="width:18px;color:var(--text-tertiary);"></i> ${this.escapeHtml(c.email)}</div>` : ''}
                        ${c.phone ? `<div><i class="fas fa-phone" style="width:18px;color:var(--text-tertiary);"></i> ${this.escapeHtml(c.phone)}</div>` : ''}
                        ${c.address ? `<div><i class="fas fa-map-marker-alt" style="width:18px;color:var(--text-tertiary);"></i> ${this.escapeHtml(c.address.split('\n')[0])}</div>` : ''}
                        ${c.gst_number ? `<div><i class="fas fa-id-card" style="width:18px;color:var(--text-tertiary);"></i> ${this.escapeHtml(c.gst_number)}</div>` : ''}
                    </div>
                </div>
            `;
        }).join('');

        this._renderPagination('clients-pagination', filtered.length, page, totalPages,
            (p) => { this._page.clients = p; this.renderClientsGrid(clients, search); });
    }

    showClientModal(clientData = null) {
        const modal = document.getElementById('client-modal');
        const title = document.getElementById('client-modal-title');
        const form = document.getElementById('client-form');

        form.reset();
        document.getElementById('client-id').value = '';

        if (clientData && typeof clientData === 'object') {
            title.textContent = 'Edit Client';
            document.getElementById('client-id').value = clientData.id || '';
            document.getElementById('client-name').value = clientData.name || '';
            document.getElementById('client-company').value = clientData.company || '';
            document.getElementById('client-email').value = clientData.email || '';
            document.getElementById('client-phone').value = clientData.phone || '';
            document.getElementById('client-address').value = clientData.address || '';
            document.getElementById('client-gst').value = clientData.gst_number || '';
        } else {
            title.textContent = 'Add Client';
        }

        this.openModal('client-modal');
    }

    async saveClient() {
        const name = document.getElementById('client-name').value.trim();
        if (!name) {
            this.showToast('error', 'Missing Name', 'Client name is required.');
            return;
        }

        const payload = {
            name: name,
            company: document.getElementById('client-company').value.trim(),
            email: document.getElementById('client-email').value.trim(),
            phone: document.getElementById('client-phone').value.trim(),
            address: document.getElementById('client-address').value.trim(),
            gst_number: document.getElementById('client-gst').value.trim()
        };

        try {
            const clientId = document.getElementById('client-id').value;
            let result;
            if (clientId) {
                result = await api.updateClient(clientId, payload);
            } else {
                result = await api.createClient(payload);
            }

            if (result.success) {
                this.showToast('success', 'Client Saved', `${name} has been saved successfully.`);
                this.closeModal('client-modal');
                await this.loadClients();
            } else if (result.error_code === 'LIMIT_REACHED') {
                this.handleLimitReached(result);
            } else {
                this.showToast('error', 'Error', result.message || 'Failed to save client.');
            }
        } catch (err) {
            this.showToast('error', 'Error', err.message);
        }
    }

    // ── Payments ──
    async loadPayments() {
        try {
            const result = await api.getPayments();
            this.paymentsData = result?.data?.payments || [];
            this.renderPaymentsTable(this.paymentsData);
        } catch (err) {
            console.warn('Load payments error:', err);
            this.renderPaymentsTable([]);
        }
    }

    renderPaymentsTable(payments) {
        const tbody = document.getElementById('payments-table-body');
        if (!tbody) return;

        if (!payments.length) {
            tbody.innerHTML = `<tr><td colspan="6"><div class="empty-state" style="padding:40px 20px;">
                <div class="empty-state-icon"><i class="fas fa-credit-card"></i></div>
                <div class="empty-state-title">No payments recorded</div>
                <div class="empty-state-text">Record a payment to track your collections.</div>
            </div></td></tr>`;
            this._renderPagination('payments-pagination', 0, 1, 1);
            return;
        }

        const methodIcons = {
            'Bank Transfer': 'fa-building-columns',
            'UPI': 'fa-mobile-screen-button',
            'Cash': 'fa-money-bill',
            'Cheque': 'fa-money-check',
            'Card': 'fa-credit-card',
            'Other': 'fa-receipt'
        };

        const totalPages = Math.ceil(payments.length / this._perPage);
        if (this._page.payments > totalPages) this._page.payments = totalPages;
        const page = this._page.payments;
        const start = (page - 1) * this._perPage;
        const paged = payments.slice(start, start + this._perPage);

        tbody.innerHTML = paged.map(p => `
            <tr>
                <td><span style="font-weight:600;color:var(--primary);">${this.escapeHtml(p.invoice_number || '')}</span></td>
                <td>${this.escapeHtml(p.client_name || '')}</td>
                <td style="font-weight:600;color:var(--success);">${this.formatCurrency(p.amount)}</td>
                <td style="color:var(--text-secondary);font-size:0.84rem;">${this.formatDate(p.payment_date)}</td>
                <td><span style="display:inline-flex;align-items:center;gap:6px;"><i class="fas ${methodIcons[p.method] || 'fa-receipt'}" style="color:var(--text-tertiary);"></i> ${this.escapeHtml(p.method || '')}</span></td>
                <td style="color:var(--text-secondary);font-size:0.84rem;">${this.escapeHtml(p.reference || '—')}</td>
            </tr>
        `).join('');

        this._renderPagination('payments-pagination', payments.length, page, totalPages,
            (p) => { this._page.payments = p; this.renderPaymentsTable(payments); });
    }

    showPaymentModal() {
        const form = document.getElementById('payment-form');
        form.reset();

        // Set today's date
        document.getElementById('payment-date').value = new Date().toISOString().split('T')[0];

        // Populate invoice dropdown
        const select = document.getElementById('payment-invoice');
        select.innerHTML = '<option value="">Select invoice...</option>';
        this.invoicesData.filter(inv => inv.status !== 'paid').forEach(inv => {
            const opt = document.createElement('option');
            opt.value = inv.id;
            opt.textContent = `${inv.invoice_number} — ${inv.client_name || ''} (${this.formatCurrency(inv.total_amount)})`;
            select.appendChild(opt);
        });

        this.openModal('payment-modal');
    }

    async savePayment() {
        const invoiceId = document.getElementById('payment-invoice').value;
        const amount = parseFloat(document.getElementById('payment-amount').value);
        const date = document.getElementById('payment-date').value;
        const method = document.getElementById('payment-method').value;

        if (!invoiceId || !amount || !date || !method) {
            this.showToast('error', 'Missing Fields', 'Please fill in all required fields.');
            return;
        }

        try {
            const result = await api.recordPayment({
                invoice_id: parseInt(invoiceId),
                amount: amount,
                payment_date: date,
                method: method,
                reference: document.getElementById('payment-reference').value.trim()
            });

            if (result.success) {
                this.showToast('success', 'Payment Recorded', 'Payment has been recorded successfully.');
                this.closeModal('payment-modal');
                await this.loadPayments();
            } else {
                this.showToast('error', 'Error', result.message || 'Failed to record payment.');
            }
        } catch (err) {
            this.showToast('error', 'Error', err.message);
        }
    }

    // ── Reports ──
    async loadReports(period) {
        // Use the active tab period if not supplied
        if (!period) {
            const active = document.querySelector('#report-period-tabs .filter-tab.active');
            period = active?.dataset?.period || '30d';
        }
        this._reportPeriod = period;
        try {
            const result = await api.getReportStats(period);
            const data = result?.data || {};
            this._reportData = data; // cache for export

            // ── Stat cards ──
            this._setText('report-total-revenue', this.formatCurrency(data.total_revenue || 0));
            this._setText('report-total-invoices', data.total_invoices || 0);
            this._setText('report-avg-invoice', this.formatCurrency(data.avg_invoice || 0));
            this._setText('report-outstanding', this.formatCurrency(data.pending_amount || 0));

            const totalBilled = parseFloat(data.total_billed || 0);
            const revenue     = parseFloat(data.total_revenue || 0);
            const rate = totalBilled > 0 ? ((revenue / totalBilled) * 100).toFixed(0) : 0;
            this._setText('report-collection-rate', rate + '%');

            // ── Invoice breakdown table ──
            this._renderReportBreakdownTable(data.invoice_breakdown || []);

            // ── Charts ──
            this.renderReportCharts(data, period);
        } catch (err) {
            console.warn('Load reports error:', err);
        }
    }

    _renderReportBreakdownTable(rows) {
        const tbody = document.getElementById('report-breakdown-body');
        if (!tbody) return;
        if (!rows.length) {
            tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:24px;color:var(--text-tertiary);">No invoices in this period.</td></tr>`;
            return;
        }
        tbody.innerHTML = rows.map(r => `
            <tr>
                <td style="font-weight:600;color:var(--primary);">${this.escapeHtml(r.invoice_number)}</td>
                <td>${this.escapeHtml(r.client_name)}${r.client_company ? `<br><span style="font-size:0.78rem;color:var(--text-tertiary);">${this.escapeHtml(r.client_company)}</span>` : ''}</td>
                <td style="font-size:0.84rem;color:var(--text-secondary);">${this.formatDate(r.issue_date)}</td>
                <td style="font-weight:600;">${this.formatCurrency(r.total_amount)}</td>
                <td style="color:var(--success);font-weight:600;">${this.formatCurrency(r.paid_amount)}</td>
                <td><span class="status-pill ${r.status}"><span class="status-dot"></span>${r.status}</span></td>
            </tr>
        `).join('');
    }

    renderReportCharts(data, period = '30d') {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const gridColor  = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)';
        const textColor  = isDark ? '#94a3b8' : '#64748b';

        // ── Revenue Line Chart ──
        const revCtx = document.getElementById('reportRevenueChart');
        if (revCtx) {
            if (this.charts.reportRevenue) this.charts.reportRevenue.destroy();

            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const monthlyData = data.monthly_revenue || [];

            // Determine how many months to show
            const numMonths = period === '30d' ? 1 : period === '90d' ? 3 : period === '1y' ? 12 : Math.max(monthlyData.length, 1);
            const now = new Date();
            const labels = [];
            const values = [];
            const billedValues = [];

            for (let i = numMonths - 1; i >= 0; i--) {
                const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
                labels.push(months[d.getMonth()] + (numMonths > 12 ? ` '${String(d.getFullYear()).slice(2)}` : ''));
                const key = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
                const found = monthlyData.find(m => m.month === key);
                values.push(found ? parseFloat(found.total) : 0);
                billedValues.push(found ? parseFloat(found.billed) : 0);
            }

            this.charts.reportRevenue = new Chart(revCtx, {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        {
                            label: 'Collected',
                            data: values,
                            borderColor: '#6366f1',
                            backgroundColor: 'rgba(99,102,241,0.08)',
                            borderWidth: 2.5,
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#6366f1',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 5,
                            pointHoverRadius: 7,
                        },
                        {
                            label: 'Billed',
                            data: billedValues,
                            borderColor: '#06b6d4',
                            backgroundColor: 'rgba(6,182,212,0.04)',
                            borderWidth: 2,
                            borderDash: [5, 4],
                            fill: false,
                            tension: 0.4,
                            pointBackgroundColor: '#06b6d4',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            align: 'end',
                            labels: { color: textColor, usePointStyle: true, pointStyle: 'circle', boxWidth: 8, padding: 16, font: { size: 12 } }
                        },
                        tooltip: {
                            backgroundColor: isDark ? '#1e293b' : '#fff',
                            titleColor: isDark ? '#f1f5f9' : '#0f172a',
                            bodyColor: isDark ? '#94a3b8' : '#475569',
                            borderColor: isDark ? '#334155' : '#e2e8f0',
                            borderWidth: 1,
                            cornerRadius: 10,
                            padding: 12,
                            callbacks: { label: (ctx) => ` ${ctx.dataset.label}: ₹${ctx.raw.toLocaleString('en-IN')}` }
                        }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: textColor, font: { size: 11 } } },
                        y: {
                            grid: { color: gridColor },
                            ticks: { color: textColor, font: { size: 11 }, callback: v => '₹' + (v >= 1000 ? (v / 1000).toFixed(0) + 'k' : v) }
                        }
                    }
                }
            });
        }

        // ── Top Clients Doughnut ──
        const clientCtx = document.getElementById('reportClientChart');
        if (clientCtx) {
            if (this.charts.reportClient) this.charts.reportClient.destroy();
            const topClients = (data.top_clients || []).slice(0, 5);

            this.charts.reportClient = new Chart(clientCtx, {
                type: 'doughnut',
                data: {
                    labels: topClients.length ? topClients.map(c => c.name) : ['No data'],
                    datasets: [{
                        data: topClients.length ? topClients.map(c => parseFloat(c.revenue)) : [1],
                        backgroundColor: topClients.length
                            ? ['#6366f1', '#06b6d4', '#10b981', '#f59e0b', '#f43f5e']
                            : ['rgba(148,163,184,0.15)'],
                        borderWidth: 0,
                        spacing: 3,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '68%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { color: textColor, padding: 12, usePointStyle: true, pointStyle: 'circle', boxWidth: 8, boxHeight: 8, font: { size: 11 } }
                        },
                        tooltip: {
                            backgroundColor: isDark ? '#1e293b' : '#fff',
                            titleColor: isDark ? '#f1f5f9' : '#0f172a',
                            bodyColor: isDark ? '#94a3b8' : '#475569',
                            borderColor: isDark ? '#334155' : '#e2e8f0',
                            borderWidth: 1,
                            cornerRadius: 10,
                            padding: 12,
                            callbacks: { label: (ctx) => ` ${ctx.label}: ₹${ctx.raw.toLocaleString('en-IN')}` }
                        }
                    }
                }
            });
        }
    }

    // ── Export Reports CSV ──
    exportReportsCSV() {
        const data = this._reportData;
        if (!data) { this.showToast('error', 'No Data', 'Load a report period first.'); return; }

        const period = this._reportPeriod || 'all';
        const periodLabel = { '30d': '30 Days', '90d': '90 Days', '1y': '1 Year', 'all': 'All Time' }[period] || period;

        const rows = [
            [`Report Period: ${periodLabel}`, '', '', '', '', ''],
            ['Invoice #', 'Client', 'Issue Date', 'Total Billed', 'Paid', 'Status'],
            ...(data.invoice_breakdown || []).map(r => [
                r.invoice_number,
                r.client_name + (r.client_company ? ` (${r.client_company})` : ''),
                r.issue_date,
                parseFloat(r.total_amount).toFixed(2),
                parseFloat(r.paid_amount).toFixed(2),
                r.status
            ]),
            [],
            ['', '', 'Total Billed', parseFloat(data.total_billed || 0).toFixed(2), '', ''],
            ['', '', 'Total Collected', parseFloat(data.total_revenue || 0).toFixed(2), '', ''],
            ['', '', 'Outstanding', parseFloat(data.pending_amount || 0).toFixed(2), '', ''],
        ];

        const csv = rows.map(row => row.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href = url;
        a.download = `report-${period}-${new Date().toISOString().split('T')[0]}.csv`;
        a.click();
        URL.revokeObjectURL(url);
        this.showToast('success', 'Exported', 'Report downloaded as CSV.');
    }

    // ── Export Reports PDF (print-based) ──
    exportReportsPDF() {
        const data = this._reportData;
        if (!data) { this.showToast('error', 'No Data', 'Load a report period first.'); return; }

        const period = this._reportPeriod || 'all';
        const periodLabel = { '30d': 'Last 30 Days', '90d': 'Last 90 Days', '1y': 'Last 1 Year', 'all': 'All Time' }[period] || period;
        const today = new Date().toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });

        const rows = (data.invoice_breakdown || []).map(r => `
            <tr>
                <td>${this.escapeHtml(r.invoice_number)}</td>
                <td>${this.escapeHtml(r.client_name)}${r.client_company ? `<br><small>${this.escapeHtml(r.client_company)}</small>` : ''}</td>
                <td>${this.formatDate(r.issue_date)}</td>
                <td style="text-align:right;">₹${parseFloat(r.total_amount).toLocaleString('en-IN', {minimumFractionDigits:2})}</td>
                <td style="text-align:right;color:#10b981;">₹${parseFloat(r.paid_amount).toLocaleString('en-IN', {minimumFractionDigits:2})}</td>
                <td><span style="padding:2px 8px;border-radius:20px;font-size:11px;background:${this._statusColor(r.status)};color:#fff;">${r.status}</span></td>
            </tr>
        `).join('');

        const sc = data.status_counts || {};
        const html = `<!DOCTYPE html><html><head><meta charset="UTF-8">
            <title>Report — ${periodLabel}</title>
            <style>
                body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;margin:0;padding:32px;color:#1e293b;font-size:13px;}
                h1{font-size:22px;font-weight:700;margin:0 0 4px;}
                .subtitle{color:#64748b;margin-bottom:28px;font-size:13px;}
                .stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px;}
                .stat{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px;}
                .stat-val{font-size:20px;font-weight:700;color:#6366f1;}
                .stat-lbl{font-size:11px;color:#64748b;margin-top:4px;text-transform:uppercase;letter-spacing:.5px;}
                table{width:100%;border-collapse:collapse;font-size:12px;}
                th{background:#f1f5f9;padding:8px 12px;text-align:left;font-weight:600;color:#475569;font-size:11px;text-transform:uppercase;letter-spacing:.5px;}
                td{padding:8px 12px;border-bottom:1px solid #f1f5f9;}
                tr:last-child td{border-bottom:none;}
                .section-title{font-size:14px;font-weight:700;margin:24px 0 12px;color:#0f172a;}
                small{color:#94a3b8;font-size:10px;}
                @media print{body{padding:16px;}}
            </style>
        </head><body>
            <h1>Invoice Report</h1>
            <div class="subtitle">Period: ${periodLabel} &nbsp;&middot;&nbsp; Generated: ${today}</div>
            <div class="stats">
                <div class="stat"><div class="stat-val">₹${parseFloat(data.total_revenue||0).toLocaleString('en-IN',{minimumFractionDigits:2})}</div><div class="stat-lbl">Total Collected</div></div>
                <div class="stat"><div class="stat-val">₹${parseFloat(data.total_billed||0).toLocaleString('en-IN',{minimumFractionDigits:2})}</div><div class="stat-lbl">Total Billed</div></div>
                <div class="stat"><div class="stat-val">${data.total_invoices||0}</div><div class="stat-lbl">Invoices</div></div>
                <div class="stat"><div class="stat-val" style="color:${parseFloat(data.pending_amount||0)>0?'#ef4444':'#10b981'};">₹${parseFloat(data.pending_amount||0).toLocaleString('en-IN',{minimumFractionDigits:2})}</div><div class="stat-lbl">Outstanding</div></div>
            </div>
            <div class="stats" style="grid-template-columns:repeat(5,1fr);">
                <div class="stat"><div class="stat-val" style="color:#94a3b8;">${sc.draft||0}</div><div class="stat-lbl">Draft</div></div>
                <div class="stat"><div class="stat-val" style="color:#3b82f6;">${sc.sent||0}</div><div class="stat-lbl">Sent</div></div>
                <div class="stat"><div class="stat-val" style="color:#f59e0b;">${sc.partial||0}</div><div class="stat-lbl">Partial</div></div>
                <div class="stat"><div class="stat-val" style="color:#10b981;">${sc.paid||0}</div><div class="stat-lbl">Paid</div></div>
                <div class="stat"><div class="stat-val" style="color:#ef4444;">${sc.overdue||0}</div><div class="stat-lbl">Overdue</div></div>
            </div>
            <div class="section-title">Invoice Breakdown</div>
            <table>
                <thead><tr><th>Invoice #</th><th>Client</th><th>Date</th><th style="text-align:right;">Billed</th><th style="text-align:right;">Paid</th><th>Status</th></tr></thead>
                <tbody>${rows || '<tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:24px;">No invoices in this period.</td></tr>'}</tbody>
            </table>
        </body></html>`;

        const win = window.open('', '_blank');
        win.document.write(html);
        win.document.close();
        win.focus();
        setTimeout(() => { win.print(); }, 400);
    }

    _statusColor(status) {
        const m = { draft:'#94a3b8', sent:'#3b82f6', partial:'#f59e0b', paid:'#10b981', overdue:'#ef4444' };
        return m[status] || '#94a3b8';
    }

    // ── Settings ──
    async loadSettings() {
        try {
            const result = await api.getSettings();
            // Handle both nested data.settings and direct data
            const data = result?.data?.settings || result?.data || {};

            this._setVal('setting-business-name', data.business_name);
            this._setVal('setting-gst', data.gst_number);
            this._setVal('setting-address', data.address);
            this._setVal('setting-payment-terms', data.payment_terms);
            this._setVal('setting-tax-rate', data.default_tax);
            this._setVal('setting-invoice-prefix', data.invoice_prefix || 'INV');

            // Razorpay payment gateway fields
            this._setVal('setting-rzp-key-id', data.razorpay_key_id || '');
            this._setVal('setting-rzp-key-secret', data.razorpay_key_secret || '');

            // Show connected badge if key is configured
            const rzpBadge = document.getElementById('rzp-status-badge');
            if (rzpBadge) {
                if (data.razorpay_key_id) {
                    rzpBadge.style.display = '';
                    rzpBadge.innerHTML = '<span style="display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:20px;background:#d1fae5;color:#065f46;font-size:0.78rem;font-weight:600;"><i class="fas fa-check-circle"></i> Connected</span>';
                } else {
                    rzpBadge.style.display = '';
                    rzpBadge.innerHTML = '<span style="display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:20px;background:#fef3c7;color:#92400e;font-size:0.78rem;font-weight:600;"><i class="fas fa-exclamation-triangle"></i> Not configured</span>';
                }
            }

            // UPI fields
            this._setVal('setting-upi-id', data.upi_id || '');

            // UPI QR preview
            const qrPreviewWrap = document.getElementById('upi-qr-preview-wrap');
            const qrPreviewImg  = document.getElementById('upi-qr-preview-img');
            const qrUploadArea  = document.getElementById('upi-qr-upload-area');
            if (data.upi_qr_url && qrPreviewWrap && qrPreviewImg) {
                qrPreviewImg.src = data.upi_qr_url;
                qrPreviewWrap.style.display = '';
                if (qrUploadArea) qrUploadArea.style.display = 'none';
            } else if (qrPreviewWrap) {
                qrPreviewWrap.style.display = 'none';
                if (qrUploadArea) qrUploadArea.style.display = '';
            }

            // UPI status badge
            const upiStatusBadge = document.getElementById('upi-status-badge');
            if (upiStatusBadge) {
                if (data.upi_id || data.upi_qr_url) {
                    upiStatusBadge.style.display = '';
                    upiStatusBadge.innerHTML = '<span style="display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:20px;background:#d1fae5;color:#065f46;font-size:0.78rem;font-weight:600;"><i class="fas fa-check-circle"></i> Configured</span>';
                } else {
                    upiStatusBadge.style.display = '';
                    upiStatusBadge.innerHTML = '<span style="display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:20px;background:#fef3c7;color:#92400e;font-size:0.78rem;font-weight:600;"><i class="fas fa-exclamation-triangle"></i> Not configured</span>';
                }
            }

            // Show server logo if present
            const preview   = document.getElementById('logo-preview');
            const removeBtn = document.getElementById('logo-remove-btn');
            if (data.logo_url && preview) {
                preview.innerHTML = `<img src="${data.logo_url}" style="width:100%;height:100%;object-fit:contain;border-radius:10px;">`;
                if (removeBtn) removeBtn.style.display = '';
            } else if (preview) {
                preview.innerHTML = '<i class="fas fa-image"></i>';
                if (removeBtn) removeBtn.style.display = 'none';
            }
        } catch (err) {
            console.warn('Load settings error:', err);
        }

        // ── Populate Account tab ──
        try {
            const user       = typeof authManager !== 'undefined' ? authManager.getCurrentUser() : null;
            const isGoogle   = typeof authManager !== 'undefined' && authManager.isGoogleUser();
            if (user) {
                this._setVal('setting-name',  localStorage.getItem('user_name')  || user.name  || '');
                this._setVal('setting-email', localStorage.getItem('user_email') || user.email || '');
                this._setVal('setting-phone', localStorage.getItem('user_phone') || user.phone || '');
            }

            // Lock email + show badge for Google users
            const emailInput = document.getElementById('setting-email');
            const emailNote  = document.getElementById('setting-email-note');
            const googleBadge= document.getElementById('google-auth-badge');
            const pwCard     = document.getElementById('change-password-card');

            if (isGoogle) {
                if (emailInput)  { emailInput.disabled = true; emailInput.style.opacity = '0.55'; emailInput.style.cursor = 'not-allowed'; }
                if (emailNote)   { emailNote.style.display = 'block'; }
                if (googleBadge) { googleBadge.style.display = 'flex'; }
                if (pwCard)      { pwCard.style.display = 'none'; }
            } else {
                if (emailInput)  { emailInput.disabled = false; emailInput.style.opacity = ''; emailInput.style.cursor = ''; }
                if (emailNote)   { emailNote.style.display = 'none'; }
                if (googleBadge) { googleBadge.style.display = 'none'; }
                if (pwCard)      { pwCard.style.display = ''; }
            }
        } catch (e) { console.warn('Account tab populate error:', e); }
        const savedTheme = localStorage.getItem('theme') || 'light';
        this._setVal('setting-theme', savedTheme);

        // ── Populate Invoice Appearance tab ──
        const savedTpl      = parseInt(localStorage.getItem('inv_template') || '1');
        const savedColor    = localStorage.getItem('inv_accent_color') || '#6366f1';
        const savedCurrency = localStorage.getItem('default_currency') || 'INR';

        document.querySelectorAll('.settings-tpl-btn').forEach(b => {
            b.classList.toggle('active', parseInt(b.dataset.tpl) === savedTpl);
        });
        const picker = document.getElementById('settings-accent-picker');
        const label  = document.getElementById('settings-color-label');
        if (picker) picker.value = savedColor;
        if (label)  label.textContent = savedColor;
        document.querySelectorAll('.settings-color-swatch').forEach(s => {
            s.classList.toggle('active', s.dataset.color === savedColor);
        });

        // Restore default currency dropdown
        const currencyDropdown = document.getElementById('setting-currency');
        if (currencyDropdown) currencyDropdown.value = savedCurrency;
    }

    initSettingsTabs() {
        const tabButtons = document.querySelectorAll('#settings-tabs .tab-btn');
        const tabPanels = document.querySelectorAll('#settings-view .tab-panel');

        // Add click event listeners to tab buttons
        tabButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                const tabId = e.target.getAttribute('data-tab');

                // Remove active class from all buttons and panels
                tabButtons.forEach(btn => btn.classList.remove('active'));
                tabPanels.forEach(panel => panel.classList.remove('active'));

                // Add active class to clicked button
                e.target.classList.add('active');

                // Show corresponding panel
                const activePanel = document.getElementById(`tab-${tabId}`);
                if (activePanel) {
                    activePanel.classList.add('active');
                }


            });
        });
    }

    async saveSettings() {
        const payload = {
            business_name: document.getElementById('setting-business-name')?.value || '',
            gst_number: document.getElementById('setting-gst')?.value || '',
            address: document.getElementById('setting-address')?.value || '',
            payment_terms: document.getElementById('setting-payment-terms')?.value || '',
            default_tax: parseFloat(document.getElementById('setting-tax-rate')?.value) || 0,
            invoice_prefix: document.getElementById('setting-invoice-prefix')?.value || 'INV'
        };

        try {
            const result = await api.updateSettings(payload);
            if (result.success) {
                this.showToast('success', 'Settings Saved', 'Your settings have been updated.');
            } else {
                this.showToast('error', 'Error', result.message || 'Failed to save settings.');
            }
        } catch (err) {
            this.showToast('error', 'Error', err.message);
        }
    }

    // ── Modal Management ──
    openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }

    closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    // ── Sidebar ──
    toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        sidebar.classList.toggle('open');
        overlay.classList.toggle('show');
    }

    closeSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        if (window.innerWidth <= 1024) {
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
        }
    }

    // ── Theme ──
    toggleTheme() {
        const html = document.documentElement;
        const current = html.getAttribute('data-theme');
        const next = current === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', next);
        localStorage.setItem('theme', next);

        // Update icon
        const btn = document.getElementById('theme-toggle');
        if (btn) {
            btn.querySelector('i').className = next === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }

        // Rebuild charts with new colors
        if (this.currentView === 'dashboard') this.loadDashboard();
        if (this.currentView === 'reports') this.loadReports();
    }

    applyTheme() {
        const saved = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', saved);
        const btn = document.getElementById('theme-toggle');
        if (btn) {
            btn.querySelector('i').className = saved === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }
    }

    // ── Invoice Preview ──
    async previewInvoice(id) {
        try {
            const result = await api.getInvoice(id);
            if (!result?.data) {
                this.showToast('error', 'Error', 'Could not load invoice.');
                return;
            }

            const inv = result.data.invoice || result.data;
            const items = result.data.items || inv.items || [];
            const payments = result.data.payments || [];

            // Fetch business settings for the header
            let business = {};
            try {
                const settingsRes = await api.getSettings();
                business = settingsRes?.data?.settings || {};
            } catch { }

            const subtotal = items.reduce((a, it) => a + (parseFloat(it.quantity) * parseFloat(it.rate)), 0);
            const totalTax = items.reduce((a, it) => {
                const base = parseFloat(it.quantity) * parseFloat(it.rate);
                return a + (base * (parseFloat(it.tax_percent) || 0) / 100);
            }, 0);
            const total = parseFloat(inv.total_amount) || (subtotal + totalTax);
            const paid = parseFloat(inv.paid_amount) || 0;
            const balance = total - paid;
            const status = inv.calculated_status || inv.status || 'draft';

            // Save invoice data for re-render when template/color changes
            this._previewData = { inv, items, payments, business, subtotal, totalTax, total, paid, balance, status };

            // Restore or default template/color
            const savedTpl = parseInt(localStorage.getItem('inv_template') || '1');
            const savedColor = localStorage.getItem('inv_accent_color') || '#6366f1';

            this._currentTemplate = savedTpl;
            this._currentAccentColor = savedColor;

            // Render
            this._renderInvoicePreview();

            // Update toolbar UI to match saved settings
            document.querySelectorAll('.inv-tpl-btn').forEach(b => {
                b.classList.toggle('active', parseInt(b.dataset.tpl) === savedTpl);
            });
            const picker = document.getElementById('inv-accent-picker');
            if (picker) picker.value = savedColor;
            document.querySelectorAll('.inv-color-swatch').forEach(s => {
                s.classList.toggle('active', s.dataset.color === savedColor);
            });

            // Store for print/download
            this._currentPreviewInvoice = inv;
            this.openModal('invoice-preview-modal');

            // Init toolbar listeners (once — guard with flag)
            if (!this._toolbarInitialized) {
                this._initPreviewToolbar();
                this._toolbarInitialized = true;
            }

        } catch (err) {
            this.showToast('error', 'Error', 'Could not load invoice preview.');
            console.error(err);
        }
    }

    _initPreviewToolbar() {
        // Template buttons
        document.querySelectorAll('.inv-tpl-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                this._currentTemplate = parseInt(btn.dataset.tpl);
                localStorage.setItem('inv_template', this._currentTemplate);
                document.querySelectorAll('.inv-tpl-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                this._renderInvoicePreview();
            });
        });

        // Color swatches
        document.querySelectorAll('.inv-color-swatch').forEach(swatch => {
            swatch.addEventListener('click', () => {
                this._currentAccentColor = swatch.dataset.color;
                localStorage.setItem('inv_accent_color', this._currentAccentColor);
                document.querySelectorAll('.inv-color-swatch').forEach(s => s.classList.remove('active'));
                swatch.classList.add('active');
                const picker = document.getElementById('inv-accent-picker');
                if (picker) picker.value = this._currentAccentColor;
                this._renderInvoicePreview();
            });
        });

        // Color picker
        const picker = document.getElementById('inv-accent-picker');
        if (picker) {
            picker.addEventListener('input', () => {
                this._currentAccentColor = picker.value;
                localStorage.setItem('inv_accent_color', this._currentAccentColor);
                document.querySelectorAll('.inv-color-swatch').forEach(s => s.classList.remove('active'));
                this._renderInvoicePreview();
            });
        }
    }

    _renderInvoicePreview() {
        const d = this._previewData;
        if (!d) return;

        const tpl = this._currentTemplate || 1;
        const accent = this._currentAccentColor || '#6366f1';

        const paper = document.getElementById('invoice-preview-content');
        paper.className = `inv-tpl-${tpl}`;
        paper.style.setProperty('--inv-accent', accent);

        const { inv, items, payments, business, subtotal, totalTax, total, paid, balance, status } = d;

        const itemsHTML = items.map(it => {
            const lineBase = parseFloat(it.quantity) * parseFloat(it.rate);
            const lineTax = lineBase * (parseFloat(it.tax_percent) || 0) / 100;
            return `<tr>
                <td>${this.escapeHtml(it.description)}</td>
                <td style="text-align:right;">${parseFloat(it.quantity)}</td>
                <td style="text-align:right;">${this.formatCurrency(it.rate, inv.currency)}</td>
                <td style="text-align:right;">${parseFloat(it.tax_percent || 0)}%</td>
                <td style="text-align:right;font-weight:600;">${this.formatCurrency(lineBase + lineTax, inv.currency)}</td>
            </tr>`;
        }).join('');

        const tableHTML = `
            <table>
                <thead>
                    <tr>
                        <th style="width:40%;">Description</th>
                        <th style="text-align:right;">Qty</th>
                        <th style="text-align:right;">Rate</th>
                        <th style="text-align:right;">Tax %</th>
                        <th style="text-align:right;">Amount</th>
                    </tr>
                </thead>
                <tbody>${itemsHTML}</tbody>
            </table>`;

        const totalsHTML = `
            <div class="inv-totals">
                <div class="inv-totals-table">
                    <div class="row"><span>Subtotal</span><span>${this.formatCurrency(subtotal, inv.currency)}</span></div>
                    <div class="row"><span>Tax</span><span>${this.formatCurrency(totalTax, inv.currency)}</span></div>
                    <div class="row grand"><span>Total</span><span>${this.formatCurrency(total, inv.currency)}</span></div>
                    ${paid > 0 ? `
                        <div class="row" style="margin-top:6px;"><span>Paid</span><span>- ${this.formatCurrency(paid, inv.currency)}</span></div>
                        <div class="row" style="font-weight:600;"><span>Balance Due</span><span>${this.formatCurrency(balance, inv.currency)}</span></div>
                    ` : ''}
                </div>
            </div>`;

        const notesHTML = inv.notes ? `<div style="margin-top:16px;padding:12px;border-radius:8px;font-size:12px;opacity:.8;"><strong>Notes:</strong> ${this.escapeHtml(inv.notes)}</div>` : '';

        const paymentsTableHTML = payments.length ? `
                <div class="inv-party-label" style="margin-bottom:8px;">Payment History</div>
                <table>
                    <thead><tr><th>Date</th><th>Method</th><th style="text-align:right;">Amount</th><th>Reference</th></tr></thead>
                    <tbody>${payments.map(p => `
                        <tr>
                            <td>${this.formatDate(p.payment_date)}</td>
                            <td>${this.escapeHtml(p.method)}</td>
                            <td style="text-align:right;font-weight:600;">${this.formatCurrency(p.amount, inv.currency)}</td>
                            <td>${this.escapeHtml(p.reference || '—')}</td>
                        </tr>
                    `).join('')}</tbody>
                </table>` : '';

        const paymentsHTML = `
            <div style="margin-top:24px;break-inside:avoid;page-break-inside:avoid;">
                ${paymentsTableHTML}
                <div class="inv-footer">Thank you for your business! &nbsp;&middot;&nbsp; Generated by InvoicePro</div>
            </div>`;

          const bName = this.escapeHtml(business.business_name || 'Your Business');
          const bAddr = business.address ? `<p>${this.escapeHtml(business.address)}</p>` : '';
          const bGst = business.gst_number ? `<p>GST: ${this.escapeHtml(business.gst_number)}</p>` : '';

           // Logo — always use server-stored URL, never localStorage (prevents cross-account leakage)
           const logoDataUrl = business.logo_url || null;
           const logoHTML = logoDataUrl
               ? `<img src="${logoDataUrl}" alt="Logo" style="max-height:56px;max-width:160px;object-fit:contain;margin-bottom:6px;display:block;">`
               : '';
        const invNum = this.escapeHtml(inv.invoice_number || '');
        const clientName = this.escapeHtml(inv.client_name || inv.client_name_snapshot || '');
        const clientCo = inv.client_company || inv.client_company_snapshot;
        const clientEmail = inv.client_email || inv.client_email_snapshot;
        const clientAddr = inv.client_address || inv.client_address_snapshot;
        const clientGst = inv.client_gst_snapshot;
        const currency = this.escapeHtml(inv.currency || 'INR');
        const payTerms = business.payment_terms;

        const billToHTML = `
            <div class="inv-party-name">${clientName}</div>
            ${clientCo ? `<div class="inv-party-detail">${this.escapeHtml(clientCo)}</div>` : ''}
            ${clientEmail ? `<div class="inv-party-detail">${this.escapeHtml(clientEmail)}</div>` : ''}
            ${clientAddr ? `<div class="inv-party-detail">${this.escapeHtml(clientAddr)}</div>` : ''}
            ${clientGst ? `<div class="inv-party-detail">GST: ${this.escapeHtml(clientGst)}</div>` : ''}`;

        const payInfoHTML = `
            <div class="inv-party-detail">Currency: ${currency}</div>
            ${payTerms ? `<div class="inv-party-detail" style="white-space:pre-line;">${this.escapeHtml(payTerms)}</div>` : ''}`;

          // ── Brand block (logo if available, else text name) ──
          const brandInner = logoHTML
              ? `${logoHTML}<div style="font-size:12px;font-weight:700;margin-bottom:2px;">${bName}</div>${bAddr}${bGst}`
              : `<h1>${bName}</h1>${bAddr}${bGst}`;

          // ── Build HTML per template ──
          switch (tpl) {
              case 1: paper.innerHTML = `
                  <div class="inv-header">
                      <div class="inv-brand">${brandInner}</div>
                    <div class="inv-meta">
                        <div class="inv-number">${invNum}</div>
                        <div class="inv-date">Issued: ${this.formatDate(inv.issue_date)}</div>
                        <div class="inv-date">Due: ${this.formatDate(inv.due_date)}</div>
                        <div style="margin-top:8px;"><span class="inv-status-badge ${status}">${status}</span></div>
                    </div>
                </div>
                <div class="inv-parties">
                    <div><div class="inv-party-label">Bill To</div>${billToHTML}</div>
                    <div><div class="inv-party-label">Payment Info</div>${payInfoHTML}</div>
                </div>
                ${tableHTML}${totalsHTML}${notesHTML}${paymentsHTML}`;
                break;

              case 2: paper.innerHTML = `
                  <div class="inv-header">
                      <div class="inv-brand">${brandInner}</div>
                    <div class="inv-meta">
                        <div class="inv-number">${invNum}</div>
                        <div class="inv-date">Issued: ${this.formatDate(inv.issue_date)}</div>
                        <div class="inv-date">Due: ${this.formatDate(inv.due_date)}</div>
                        <div style="margin-top:8px;"><span class="inv-status-badge ${status}">${status}</span></div>
                    </div>
                </div>
                <div class="inv-body-wrap">
                    <div class="inv-parties">
                        <div><div class="inv-party-label">Bill To</div>${billToHTML}</div>
                        <div><div class="inv-party-label">Payment Info</div>${payInfoHTML}</div>
                    </div>
                    ${tableHTML}${totalsHTML}${notesHTML}${paymentsHTML}
                </div>`;
                break;

              case 3: paper.innerHTML = `
                  <div class="inv-sidebar">
                      <div class="inv-logo-text">${logoHTML || bName}</div>
                    <div>
                        <div class="inv-sidebar-label">Invoice</div>
                        <div class="inv-sidebar-value" style="font-size:1.1rem;font-weight:700;">${invNum}</div>
                    </div>
                    <div>
                        <div class="inv-sidebar-label">Issued</div>
                        <div class="inv-sidebar-value">${this.formatDate(inv.issue_date)}</div>
                    </div>
                    <div>
                        <div class="inv-sidebar-label">Due Date</div>
                        <div class="inv-sidebar-value">${this.formatDate(inv.due_date)}</div>
                    </div>
                    <div>
                        <div class="inv-sidebar-label">Status</div>
                        <div style="margin-top:4px;"><span class="inv-status-badge ${status}">${status}</span></div>
                    </div>
                    ${bAddr ? `<div><div class="inv-sidebar-label">Address</div><div class="inv-sidebar-value">${this.escapeHtml(business.address || '')}</div></div>` : ''}
                    ${bGst ? `<div><div class="inv-sidebar-label">GST</div><div class="inv-sidebar-value">${this.escapeHtml(business.gst_number || '')}</div></div>` : ''}
                </div>
                <div class="inv-main">
                    <div class="inv-header">
                        <div class="inv-number">INVOICE</div>
                        <div style="font-size:12px;color:#94a3b8;">${this.formatDate(inv.issue_date)}</div>
                    </div>
                    <div class="inv-parties">
                        <div><div class="inv-party-label">Bill To</div>${billToHTML}</div>
                        <div><div class="inv-party-label">Payment Info</div>${payInfoHTML}</div>
                    </div>
                    ${tableHTML}${totalsHTML}${notesHTML}${paymentsHTML}
                </div>`;
                break;

              case 4: paper.innerHTML = `
                  <div class="inv-topbar"></div>
                  <div class="inv-header">
                      <div class="inv-brand">${brandInner}</div>
                    <div class="inv-meta">
                        <div class="inv-invoice-badge">INVOICE</div>
                        <div class="inv-number">${invNum}</div>
                        <div class="inv-date">Issued: ${this.formatDate(inv.issue_date)}</div>
                        <div class="inv-date">Due: ${this.formatDate(inv.due_date)}</div>
                        <div style="margin-top:8px;"><span class="inv-status-badge ${status}">${status}</span></div>
                    </div>
                </div>
                <div class="inv-body-wrap">
                    <div class="inv-parties">
                        <div><div class="inv-party-label">Bill To</div>${billToHTML}</div>
                        <div><div class="inv-party-label">Payment Info</div>${payInfoHTML}</div>
                    </div>
                    ${tableHTML}${totalsHTML}${notesHTML}${paymentsHTML}
                </div>`;
                break;

              case 5: paper.innerHTML = `
                  <div class="inv-header">
                      <div class="inv-header-inner">
                          <div class="inv-brand">${brandInner}</div>
                        <div class="inv-meta">
                            <div class="inv-number">${invNum}</div>
                            <div class="inv-date">Issued: ${this.formatDate(inv.issue_date)}</div>
                            <div class="inv-date">Due: ${this.formatDate(inv.due_date)}</div>
                            <div style="margin-top:8px;"><span class="inv-status-badge ${status}">${status}</span></div>
                        </div>
                    </div>
                </div>
                <div class="inv-body-wrap">
                    <div class="inv-parties">
                        <div class="inv-party-box"><div class="inv-party-label">Bill To</div>${billToHTML}</div>
                        <div class="inv-party-box"><div class="inv-party-label">Payment Info</div>${payInfoHTML}</div>
                    </div>
                    ${tableHTML}${totalsHTML}${notesHTML}${paymentsHTML}
                </div>`;
                break;

              case 6: paper.innerHTML = `
                  <div class="inv-header">
                      <div class="inv-brand">${logoHTML ? `${logoHTML}<div style="font-size:12px;font-weight:700;">${bName}</div>${bAddr}${bGst}` : `<h1><span>${bName.charAt(0)}</span>${bName.slice(1)}</h1>${bAddr}${bGst}`}</div>
                    <div class="inv-meta">
                        <div class="inv-invoice-label">Invoice</div>
                        <div class="inv-number">${invNum}</div>
                        <div class="inv-date">Issued: ${this.formatDate(inv.issue_date)}</div>
                        <div class="inv-date">Due: ${this.formatDate(inv.due_date)}</div>
                        <div style="margin-top:10px;"><span class="inv-status-badge ${status}">${status}</span></div>
                    </div>
                </div>
                <div class="inv-parties">
                    <div><div class="inv-party-label">Bill To</div>${billToHTML}</div>
                    <div><div class="inv-party-label">Payment Info</div>${payInfoHTML}</div>
                </div>
                ${tableHTML}${totalsHTML}${notesHTML}${paymentsHTML}`;
                break;

              case 7: paper.innerHTML = `
                  <div class="inv-header">
                      <div class="inv-brand">${brandInner}</div>
                    <div class="inv-meta">
                        <div class="inv-stamp-box">
                            <div class="inv-stamp-title">Invoice</div>
                            <div class="inv-number">${invNum}</div>
                            <div class="inv-date">Issued: ${this.formatDate(inv.issue_date)}</div>
                            <div class="inv-date">Due: ${this.formatDate(inv.due_date)}</div>
                        </div>
                        <div style="margin-top:8px;text-align:right;"><span class="inv-status-badge ${status}">${status}</span></div>
                    </div>
                </div>
                <div class="inv-parties">
                    <div><div class="inv-party-label">Bill To</div>${billToHTML}</div>
                    <div><div class="inv-party-label">Payment Info</div>${payInfoHTML}</div>
                </div>
                ${tableHTML}${totalsHTML}${notesHTML}${paymentsHTML}`;
                break;

              case 8: paper.innerHTML = `
                  <div class="inv-header">
                      <div class="inv-brand">${brandInner}</div>
                    <div class="inv-meta">
                        <div class="inv-number">${invNum}</div>
                        <div class="inv-date">Issued: ${this.formatDate(inv.issue_date)}</div>
                        <div class="inv-date">Due: ${this.formatDate(inv.due_date)}</div>
                        <div style="margin-top:8px;"><span class="inv-status-badge ${status}">${status}</span></div>
                    </div>
                </div>
                <div class="inv-body-wrap">
                    <div class="inv-parties">
                        <div class="inv-party-box"><div class="inv-party-label">Bill To</div>${billToHTML}</div>
                        <div class="inv-party-box"><div class="inv-party-label">Payment Info</div>${payInfoHTML}</div>
                    </div>
                    ${tableHTML}${totalsHTML}${notesHTML}${paymentsHTML}
                </div>`;
                break;

              case 9: paper.innerHTML = `
                  <div class="inv-header">
                      <div class="inv-brand">${brandInner}</div>
                    <div class="inv-meta">
                        <div class="inv-number">${invNum}</div>
                        <div class="inv-date">Issued: ${this.formatDate(inv.issue_date)}</div>
                        <div class="inv-date">Due: ${this.formatDate(inv.due_date)}</div>
                        <div style="margin-top:8px;"><span class="inv-status-badge ${status}">${status}</span></div>
                    </div>
                </div>
                <div class="inv-body-wrap">
                    <div class="inv-divider"></div>
                    <div class="inv-parties">
                        <div><div class="inv-party-label">Bill To</div>${billToHTML}</div>
                        <div><div class="inv-party-label">Payment Info</div>${payInfoHTML}</div>
                    </div>
                    <div class="inv-divider"></div>
                    ${tableHTML}${totalsHTML}${notesHTML}${paymentsHTML}
                </div>`;
                break;

              case 10: paper.innerHTML = `
                  <div class="inv-header">
                      <div class="inv-brand">${brandInner}</div>
                    <div class="inv-meta">
                        <div class="inv-number-chip">${invNum}</div>
                        <div class="inv-date">Issued: ${this.formatDate(inv.issue_date)}</div>
                        <div class="inv-date">Due: ${this.formatDate(inv.due_date)}</div>
                        <div style="margin-top:8px;"><span class="inv-status-badge ${status}">${status}</span></div>
                    </div>
                </div>
                <div class="inv-body-wrap">
                    <div class="inv-parties">
                        <div class="inv-party-card"><div class="inv-party-label">Bill To</div>${billToHTML}</div>
                        <div class="inv-party-card"><div class="inv-party-label">Payment Info</div>${payInfoHTML}</div>
                    </div>
                    ${tableHTML}${totalsHTML}${notesHTML}${paymentsHTML}
                </div>`;
                break;

            default: paper.innerHTML = `<div style="padding:48px;text-align:center;color:#94a3b8;">Template not found</div>`;
        }
    }

    printInvoice() {
        const paper = document.getElementById('invoice-preview-content');
        if (!paper) return;

        // Clone the paper and wrap sections in page-break groups
        const clone = paper.cloneNode(true);
        this._wrapPrintGroups(clone);

        // Collect all stylesheets from the current page
        const styleLinks = Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
            .map(l => `<link rel="stylesheet" href="${l.href}">`)
            .join('\n');
        const inlineStyles = Array.from(document.querySelectorAll('style'))
            .map(s => `<style>${s.textContent}</style>`)
            .join('\n');

        const iframe = document.createElement('iframe');
        iframe.style.cssText = 'position:fixed;top:-9999px;left:-9999px;width:210mm;height:297mm;border:0;';
        document.body.appendChild(iframe);

        const iDoc = iframe.contentDocument || iframe.contentWindow.document;
        iDoc.open();
        iDoc.write(`<!DOCTYPE html><html><head>
            <meta charset="UTF-8">
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
            ${styleLinks}
            ${inlineStyles}
            <style>
                /* margin:0 removes browser-native header/footer (URL, title, date, page#) */
                @page { size: A4 portrait; margin: 0; }
                html, body {
                    margin: 0; padding: 0;
                    background: #fff;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                /* Scale the paper to fit A4 width with gutters */
                .invoice-preview-paper {
                    width: 190mm !important;
                    margin: 10mm auto !important;
                    padding: 10mm 12mm !important;
                    box-shadow: none !important;
                    font-size: 11px !important;
                }
                /* Group 1: header down to status line */
                .pg-group-header,
                /* Group 2: parties + GST */
                .pg-group-parties,
                /* Group 3: items table */
                .pg-group-items,
                /* Group 4: totals + notes */
                .pg-group-totals,
                /* Group 5: payment history */
                .pg-group-payments {
                    break-inside: avoid;
                    page-break-inside: avoid;
                }
                /* keep table rows together where possible */
                tr { break-inside: avoid; page-break-inside: avoid; }
            </style>
        </head><body>${clone.outerHTML}</body></html>`);
        iDoc.close();

        iframe.contentWindow.focus();
        setTimeout(() => {
            iframe.contentWindow.print();
            setTimeout(() => { if (document.body.contains(iframe)) document.body.removeChild(iframe); }, 1500);
        }, 700);
    }

    /* Wrap the 5 logical sections inside the cloned paper with break-inside:avoid divs */
    _wrapPrintGroups(paper) {
        // Helper: wrap a node in a div with a class
        const wrap = (node, cls) => {
            if (!node || !node.parentNode) return;
            const div = document.createElement('div');
            div.className = cls;
            node.parentNode.insertBefore(div, node);
            div.appendChild(node);
        };

        // Group 1 — .inv-header (brand + invoice number + status)
        const header = paper.querySelector('.inv-header');
        if (header) wrap(header, 'pg-group-header');

        // Group 2 — .inv-parties (bill-to + payment info / GST)
        const parties = paper.querySelector('.inv-parties');
        if (parties) wrap(parties, 'pg-group-parties');

        // Group 3 — the items table (first <table> or .inv-items-table)
        const table = paper.querySelector('table');
        if (table) wrap(table, 'pg-group-items');

        // Group 4 — .inv-totals + notes div (the div right after totals)
        const totals = paper.querySelector('.inv-totals');
        if (totals) {
            const notesDiv = totals.nextElementSibling;
            const div = document.createElement('div');
            div.className = 'pg-group-totals';
            totals.parentNode.insertBefore(div, totals);
            div.appendChild(totals);
            if (notesDiv && !notesDiv.classList.contains('pg-group-payments') &&
                !notesDiv.style.marginTop?.includes('24px')) {
                // also grab notes if it follows immediately
                const notesEl = paper.querySelector('[style*="Notes"]') ||
                                 (notesDiv && !notesDiv.querySelector('table') ? notesDiv : null);
                if (notesEl && notesEl.parentNode === div.parentNode) div.appendChild(notesEl);
            }
        }

        // Group 5 — payment history section (div with margin-top:24px containing a table)
        const allDivs = paper.querySelectorAll('div');
        allDivs.forEach(d => {
            if (d.querySelector('.inv-party-label') &&
                d.querySelector('table') &&
                (d.textContent || '').includes('Payment History')) {
                if (!d.parentNode) return;
                wrap(d, 'pg-group-payments');
            }
        });
    }

    async downloadInvoicePDF() {
        const paper = document.getElementById('invoice-preview-content');
        const inv = this._currentPreviewInvoice;
        if (!paper || !inv) return;

        if (typeof html2pdf === 'undefined') {
            this.showToast('error', 'Error', 'PDF library not loaded. Please check your connection.');
            return;
        }

        const btn = document.getElementById('preview-download-btn');
        const origHTML = btn ? btn.innerHTML : '';
        if (btn) btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';

        // Clone, wrap print groups, then apply accent color inline so html2pdf captures it
        const clone = paper.cloneNode(true);
        this._wrapPrintGroups(clone);
        clone.style.cssText = `
            width: 794px;
            padding: 36px 40px;
            background: #fff;
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            --inv-accent: ${this._currentAccentColor || '#6366f1'};
        `;
        // Inline the CSS variable since html2pdf doesn't resolve CSS vars in canvas
        const accent = this._currentAccentColor || '#6366f1';
        clone.querySelectorAll('[style]').forEach(el => {
            el.style.cssText = el.style.cssText
                .replace(/var\(--inv-accent\)/g, accent);
        });

        const filename = `${inv.invoice_number || 'invoice'}.pdf`;

        const opt = {
            margin:       [10, 10, 10, 10],
            filename:     filename,
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, useCORS: true, logging: false, backgroundColor: '#ffffff' },
            jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' },
            pagebreak:    { mode: 'avoid-all' }
        };

        try {
            await html2pdf().set(opt).from(clone).save();
            this.showToast('success', 'Downloaded', `${filename} saved.`);
        } catch (err) {
            console.error('PDF error:', err);
            this.showToast('error', 'PDF Error', 'Could not generate PDF. Try the Print button instead.');
        } finally {
            if (btn) btn.innerHTML = origHTML;
        }
    }

    // ── CSV Export ──
    async exportInvoicesCSV() {
        if (!this.invoicesData.length) {
            this.showToast('warning', 'No Data', 'No invoices to export.');
            return;
        }
        await this._fetchAndDownloadCSV('invoice.export', `invoices_${new Date().toISOString().slice(0,10)}.csv`);
    }

    async exportPaymentsCSV() {
        if (!this.paymentsData.length) {
            this.showToast('warning', 'No Data', 'No payments to export.');
            return;
        }
        await this._fetchAndDownloadCSV('payment.export', `payments_${new Date().toISOString().slice(0,10)}.csv`);
    }

    async _fetchAndDownloadCSV(route, fallbackFilename) {
        try {
            const token = localStorage.getItem('auth_token') || '';
            const url = `${API_BASE_URL}/index.php?route=${route}`;
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Authorization': token ? `Bearer ${token}` : '',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) throw new Error(`Server error ${response.status}`);

            const blob = await response.blob();

            // Try to get filename from Content-Disposition header
            const disposition = response.headers.get('Content-Disposition') || '';
            const match = disposition.match(/filename[^;=\n]*=["']?([^"';\n]+)/i);
            const filename = match ? match[1].trim() : fallbackFilename;

            this._downloadFile(blob, filename);
            this.showToast('success', 'Exported', `${filename} downloaded.`);
        } catch (e) {
            console.error('CSV export failed:', e);
            this.showToast('error', 'Export Failed', e.message || 'Could not download CSV.');
        }
    }

    // ── Confirm Dialog ──
    confirmAction(title, message, actionLabel, callback) {
        const titleEl = document.getElementById('confirm-modal-title');
        const msgEl = document.getElementById('confirm-modal-message');
        const actionBtn = document.getElementById('confirm-modal-action');

        if (titleEl) titleEl.textContent = title;
        if (msgEl) msgEl.textContent = message;
        if (actionBtn) {
            actionBtn.textContent = actionLabel;
            actionBtn.onclick = async () => {
                this.closeModal('confirm-modal');
                if (callback) await callback();
            };
        }

        this.openModal('confirm-modal');
    }

    // ── Client Delete ──
    async deleteClient(id) {
        const client = this.clientsData.find(c => c.id == id);
        const name = client?.name || 'this client';

        this.confirmAction(
            'Delete Client',
            `Are you sure you want to delete "${name}"? All associated data will be kept but the client will be archived.`,
            'Delete',
            async () => {
                try {
                    const result = await api.deleteClient(id);
                    if (result.success) {
                        this.showToast('success', 'Deleted', `${name} has been deleted.`);
                        await this.loadClients();
                    } else {
                        this.showToast('error', 'Error', result.message || 'Failed to delete client.');
                    }
                } catch (err) {
                    this.showToast('error', 'Error', err.message);
                }
            }
        );
    }

    // Override deleteInvoice to use confirm dialog
    async deleteInvoice(id) {
        const inv = this.invoicesData.find(i => i.id == id);
        const num = inv?.invoice_number || '#' + id;

        this.confirmAction(
            'Delete Invoice',
            `Are you sure you want to delete invoice ${num}? This action cannot be undone.`,
            'Delete',
            async () => {
                try {
                    const result = await api.deleteInvoice(id);
                    if (result.success) {
                        this.showToast('success', 'Deleted', `Invoice ${num} has been deleted.`);
                        await this.loadInvoices();
                    } else {
                        this.showToast('error', 'Error', result.message || 'Failed to delete invoice.');
                    }
                } catch (err) {
                    this.showToast('error', 'Error', err.message);
                }
            }
        );
    }

    // ── Toast Notifications ──
    showToast(type, title, message) {
        const container = document.getElementById('toast-container');
        if (!container) return;

        const icons = { success: 'fa-check', error: 'fa-xmark', warning: 'fa-exclamation', info: 'fa-info' };

        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <div class="toast-icon"><i class="fas ${icons[type] || icons.info}"></i></div>
            <div class="toast-content">
                <div class="toast-title">${this.escapeHtml(title)}</div>
                <div class="toast-message">${this.escapeHtml(message)}</div>
            </div>
            <button class="toast-close" onclick="this.closest('.toast').remove()"><i class="fas fa-times"></i></button>
        `;

        container.appendChild(toast);

        // Auto-remove after 4s
        setTimeout(() => {
            toast.classList.add('removing');
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    async updateInvoiceStatus(id, newStatus) {
        if (!id || !newStatus) return;

        try {
            this.setLoading(true);
            const result = await api.updateInvoice(id, { status: newStatus });

            if (result.success) {
                this.showToast('success', 'Status Updated', `Invoice status changed to ${newStatus}.`);
                  // Update local data
                  if (this._currentPreviewInvoice && this._currentPreviewInvoice.id == id) {
                      this._currentPreviewInvoice.status = newStatus;
                      // Update preview data and re-render to reflect new status
                      if (this._previewData) {
                          this._previewData.status = newStatus;
                          this._previewData.inv.status = newStatus;
                          this._renderInvoicePreview();
                      }
                  }
                // Refresh list
                await this.loadInvoices();
            } else {
                this.showToast('error', 'Update Failed', result.message || 'Could not update status.');
            }
        } catch (err) {
            console.error('Status update error:', err);
            this.showToast('error', 'Error', 'Something went wrong while updating status.');
        } finally {
            this.setLoading(false);
        }
    }

    sendInvoiceEmail() {
        const inv = this._currentPreviewInvoice;
        if (!inv) return;

        // Pre-populate modal fields
        const bizName = document.getElementById('setting-business-name')?.value
            || localStorage.getItem('business_name') || 'Our Company';

        document.getElementById('email-invoice-id').value = inv.id;
        document.getElementById('email-to').value         = inv.client_email || inv.client_email_snapshot || '';
        document.getElementById('email-to-name').value    = inv.client_name || '';
        document.getElementById('email-subject').value    = `Invoice ${inv.invoice_number} from ${bizName}`;

        const balance = parseFloat(inv.total_amount) - parseFloat(inv.paid_amount || 0);
        const currency = inv.currency || 'INR';
        const sym      = this.currencySymbol(currency);

        document.getElementById('email-message').value =
            `Dear ${inv.client_name},\n\nPlease find attached invoice ${inv.invoice_number} for ${sym}${balance.toLocaleString('en-IN', {minimumFractionDigits: 2})}.\n\nDue date: ${inv.due_date}\n\nThank you for your business!\n\nBest regards,\n${bizName}`;

        // Hide SMTP warning initially
        document.getElementById('email-smtp-warning').style.display = 'none';

        this.openModal('send-email-modal');
    }

    async _doSendEmail() {
        const invoiceId = document.getElementById('email-invoice-id').value;
        const toEmail   = document.getElementById('email-to').value.trim();
        const toName    = document.getElementById('email-to-name').value.trim();
        const subject   = document.getElementById('email-subject').value.trim();
        const message   = document.getElementById('email-message').value.trim();
        const attachPdf = document.getElementById('email-attach-pdf').checked;

        if (!toEmail || !subject) {
            this.showToast('warning', 'Missing Fields', 'Please fill in the recipient email and subject.');
            return;
        }

        const btn = document.getElementById('send-email-btn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

        try {
            const result = await api.sendInvoiceEmail({
                invoice_id: invoiceId,
                to_email:   toEmail,
                to_name:    toName,
                subject:    subject,
                message:    message,
                attach_pdf: attachPdf
            });

            if (result.success) {
                this.closeModal('send-email-modal');
                this.showToast('success', 'Email Sent', result.message || `Invoice emailed to ${toEmail}.`);
            } else {
                if (result.error_code === 'SMTP_NOT_CONFIGURED') {
                    document.getElementById('email-smtp-warning').style.display = 'block';
                }
                this.showToast('error', 'Send Failed', result.message || 'Could not send email.');
            }
        } catch (err) {
            this.showToast('error', 'Error', 'Something went wrong: ' + err.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Invoice';
        }
    }

    async loadEmailSettings() {
        try {
            const result = await api.getEmailSettings();
            const s = result?.data?.email_settings || {};
            const _v = (id, val) => { const el = document.getElementById(id); if (el && val) el.value = val; };
            _v('smtp-host',       s.smtp_host);
            _v('smtp-port',       s.smtp_port);
            _v('smtp-username',   s.smtp_username);
            _v('smtp-password',   s.smtp_password);
            _v('smtp-from-name',  s.smtp_from_name);
            _v('smtp-from-email', s.smtp_from_email);
            const encEl = document.getElementById('smtp-encryption');
            if (encEl && s.smtp_encryption) encEl.value = s.smtp_encryption;
        } catch (e) {
            // Non-fatal — SMTP just won't be pre-filled
        }
    }

    async saveEmailSettings() {
        const data = {
            smtp_host:       document.getElementById('smtp-host')?.value.trim()       || '',
            smtp_port:       parseInt(document.getElementById('smtp-port')?.value)     || 587,
            smtp_username:   document.getElementById('smtp-username')?.value.trim()   || '',
            smtp_password:   document.getElementById('smtp-password')?.value          || '',
            smtp_encryption: document.getElementById('smtp-encryption')?.value        || 'tls',
            smtp_from_email: document.getElementById('smtp-from-email')?.value.trim() || '',
            smtp_from_name:  document.getElementById('smtp-from-name')?.value.trim()  || '',
        };

        const btn = document.getElementById('save-email-settings');
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...'; }

        try {
            const result = await api.updateEmailSettings(data);
            if (result.success) {
                this.showToast('success', 'Saved', 'Email settings saved successfully.');
            } else {
                this.showToast('error', 'Error', result.message || 'Could not save email settings.');
            }
        } catch (e) {
            this.showToast('error', 'Error', e.message);
        } finally {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check"></i> Save Email Settings'; }
        }
    }

    async testSmtpConnection() {
        const data = {
            smtp_host:       document.getElementById('smtp-host')?.value.trim()       || '',
            smtp_port:       parseInt(document.getElementById('smtp-port')?.value)     || 587,
            smtp_username:   document.getElementById('smtp-username')?.value.trim()   || '',
            smtp_password:   document.getElementById('smtp-password')?.value          || '',
            smtp_encryption: document.getElementById('smtp-encryption')?.value        || 'tls',
            smtp_from_email: document.getElementById('smtp-from-email')?.value.trim() || '',
            smtp_from_name:  document.getElementById('smtp-from-name')?.value.trim()  || '',
        };

        if (!data.smtp_host || !data.smtp_username || !data.smtp_password) {
            this.showToast('warning', 'Missing Fields', 'Please fill in host, username and password first.');
            return;
        }

        const btn = document.getElementById('test-smtp-btn');
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...'; }

        try {
            const result = await api.testSmtpConnection(data);
            if (result.success) {
                this.showToast('success', 'Connection OK', 'SMTP connection test succeeded!');
            } else {
                this.showToast('error', 'Connection Failed', result.message || 'SMTP connection failed.');
            }
        } catch (e) {
            this.showToast('error', 'Error', e.message);
        } finally {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-plug"></i> Test Connection'; }
        }
    }

    _downloadFile(blob, filename) {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');

        a.style.display = 'none';
        a.href = url;
        a.download = filename;
        a.setAttribute('download', filename);



        document.body.appendChild(a);
        a.click();

        // Wait longer before cleanup to ensure OS/Browser registers the file name
        setTimeout(() => {
            if (document.body.contains(a)) document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }, 5000);
    }

    // ── Pagination Helper ──
    _renderPagination(containerId, total, currentPage, totalPages, onPageChange) {
        const container = document.getElementById(containerId);
        if (!container) return;

        if (!onPageChange || totalPages <= 1) {
            container.innerHTML = total > 0
                ? `<div class="pagination-info">Showing ${total} item${total !== 1 ? 's' : ''}</div>`
                : '';
            return;
        }

        const perPage = this._perPage;
        const start = (currentPage - 1) * perPage + 1;
        const end = Math.min(currentPage * perPage, total);

        // Build page number buttons — show max 5 around current page
        let pages = [];
        const delta = 2;
        for (let i = Math.max(1, currentPage - delta); i <= Math.min(totalPages, currentPage + delta); i++) {
            pages.push(i);
        }

        const btnClass = (p) => `pagination-btn${p === currentPage ? ' active' : ''}`;
        const pageButtons = [];

        if (pages[0] > 1) {
            pageButtons.push(`<button class="pagination-btn" data-page="1">1</button>`);
            if (pages[0] > 2) pageButtons.push(`<span class="pagination-ellipsis">…</span>`);
        }
        pages.forEach(p => pageButtons.push(`<button class="${btnClass(p)}" data-page="${p}">${p}</button>`));
        if (pages[pages.length - 1] < totalPages) {
            if (pages[pages.length - 1] < totalPages - 1) pageButtons.push(`<span class="pagination-ellipsis">…</span>`);
            pageButtons.push(`<button class="pagination-btn" data-page="${totalPages}">${totalPages}</button>`);
        }

        container.innerHTML = `
            <div class="pagination-info">Showing ${start}–${end} of ${total}</div>
            <div class="pagination-controls">
                <button class="pagination-btn prev" data-page="${currentPage - 1}" ${currentPage <= 1 ? 'disabled' : ''}>
                    <i class="fas fa-chevron-left"></i>
                </button>
                ${pageButtons.join('')}
                <button class="pagination-btn next" data-page="${currentPage + 1}" ${currentPage >= totalPages ? 'disabled' : ''}>
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        `;

        container.querySelectorAll('.pagination-btn[data-page]').forEach(btn => {
            btn.addEventListener('click', () => {
                const p = parseInt(btn.dataset.page);
                if (!isNaN(p) && p >= 1 && p <= totalPages && p !== currentPage) {
                    onPageChange(p);
                }
            });
        });
    }

    // ── Helpers ──

    // Currency metadata: symbol + locale for proper number formatting
    static get CURRENCIES() {
        return {
            INR: { symbol: '₹',     locale: 'en-IN',  decimals: 2 },
            USD: { symbol: '$',     locale: 'en-US',  decimals: 2 },
            EUR: { symbol: '€',     locale: 'de-DE',  decimals: 2 },
            GBP: { symbol: '£',     locale: 'en-GB',  decimals: 2 },
            AED: { symbol: 'د.إ',  locale: 'ar-AE',  decimals: 2 },
            SGD: { symbol: 'S$',    locale: 'en-SG',  decimals: 2 },
            AUD: { symbol: 'A$',    locale: 'en-AU',  decimals: 2 },
            CAD: { symbol: 'C$',    locale: 'en-CA',  decimals: 2 },
            JPY: { symbol: '¥',     locale: 'ja-JP',  decimals: 0 },
            CNY: { symbol: '¥',     locale: 'zh-CN',  decimals: 2 },
            HKD: { symbol: 'HK$',   locale: 'zh-HK',  decimals: 2 },
            MYR: { symbol: 'RM',    locale: 'ms-MY',  decimals: 2 },
            IDR: { symbol: 'Rp',    locale: 'id-ID',  decimals: 0 },
            PHP: { symbol: '₱',     locale: 'fil-PH', decimals: 2 },
            THB: { symbol: '฿',     locale: 'th-TH',  decimals: 2 },
            KRW: { symbol: '₩',     locale: 'ko-KR',  decimals: 0 },
            NZD: { symbol: 'NZ$',   locale: 'en-NZ',  decimals: 2 },
            BDT: { symbol: '৳',     locale: 'bn-BD',  decimals: 2 },
            LKR: { symbol: 'Rs',    locale: 'si-LK',  decimals: 2 },
            NPR: { symbol: 'Rs',    locale: 'ne-NP',  decimals: 2 },
            PKR: { symbol: 'Rs',    locale: 'ur-PK',  decimals: 2 },
            CHF: { symbol: 'Fr',    locale: 'de-CH',  decimals: 2 },
            SEK: { symbol: 'kr',    locale: 'sv-SE',  decimals: 2 },
            NOK: { symbol: 'kr',    locale: 'nb-NO',  decimals: 2 },
            DKK: { symbol: 'kr',    locale: 'da-DK',  decimals: 2 },
            PLN: { symbol: 'zł',    locale: 'pl-PL',  decimals: 2 },
            CZK: { symbol: 'Kč',    locale: 'cs-CZ',  decimals: 2 },
            HUF: { symbol: 'Ft',    locale: 'hu-HU',  decimals: 0 },
            RON: { symbol: 'lei',   locale: 'ro-RO',  decimals: 2 },
            TRY: { symbol: '₺',     locale: 'tr-TR',  decimals: 2 },
            RUB: { symbol: '₽',     locale: 'ru-RU',  decimals: 2 },
            BRL: { symbol: 'R$',    locale: 'pt-BR',  decimals: 2 },
            MXN: { symbol: 'MX$',   locale: 'es-MX',  decimals: 2 },
            CLP: { symbol: '$',     locale: 'es-CL',  decimals: 0 },
            COP: { symbol: '$',     locale: 'es-CO',  decimals: 0 },
            ARS: { symbol: '$',     locale: 'es-AR',  decimals: 2 },
            SAR: { symbol: '﷼',     locale: 'ar-SA',  decimals: 2 },
            QAR: { symbol: '﷼',     locale: 'ar-QA',  decimals: 2 },
            KWD: { symbol: 'KD',    locale: 'ar-KW',  decimals: 3 },
            BHD: { symbol: 'BD',    locale: 'ar-BH',  decimals: 3 },
            OMR: { symbol: '﷼',     locale: 'ar-OM',  decimals: 3 },
            ZAR: { symbol: 'R',     locale: 'en-ZA',  decimals: 2 },
            EGP: { symbol: '£',     locale: 'ar-EG',  decimals: 2 },
            NGN: { symbol: '₦',     locale: 'en-NG',  decimals: 2 },
        };
    }

    formatCurrency(amount, currency = 'INR') {
        const num = parseFloat(amount) || 0;
        const meta = UIManager.CURRENCIES[currency] || UIManager.CURRENCIES['INR'];
        try {
            const formatted = num.toLocaleString(meta.locale, {
                minimumFractionDigits: meta.decimals === 0 ? 0 : 2,
                maximumFractionDigits: meta.decimals
            });
            return meta.symbol + formatted;
        } catch {
            return meta.symbol + num.toFixed(meta.decimals);
        }
    }

    // Return just the symbol for a currency code
    currencySymbol(currency = 'INR') {
        return (UIManager.CURRENCIES[currency] || UIManager.CURRENCIES['INR']).symbol;
    }

    formatDate(dateStr) {
        if (!dateStr) return '—';
        const d = new Date(dateStr);
        return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
    }

    escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    _setText(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    }

    _setVal(id, value) {
        const el = document.getElementById(id);
        if (el && value !== undefined && value !== null) el.value = value;
    }

    _getInitials(name) {
        if (!name) return '?';
        const parts = name.trim().split(/\s+/);
        if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
        return name.substring(0, 2).toUpperCase();
    }

    // ── Plan / Tier Limits ──

    /**
     * Handle a LIMIT_REACHED API response. Shows a toast + opens the upgrade modal.
     */
    handleLimitReached(result) {
        const resource = result.resource || 'item';
        const limit    = result.limit    || 0;
        const plan     = result.plan     || 'pro';
        const planLabels = { pro: 'Pro', professional: 'Professional', enterprise: 'Enterprise' };
        const planLabel  = planLabels[plan] || plan;

        this.showToast('error', 'Limit Reached',
            `You've used all ${limit} ${resource}s on the ${planLabel} plan.`);

        this.showUpgradeModal(resource, result.current, limit, plan);
    }

    /**
     * Show the new full-screen upgrade modal.
     */
    showUpgradeModal(resource = null, current = null, limit = null, currentPlan = null) {
        const modal = document.getElementById('upgrade-modal');
        if (!modal) return;

        // Update subtitle
        const reasonEl = document.getElementById('upgrade-modal-reason');
        if (reasonEl) {
            if (resource && limit !== null) {
                const planLabels = { pro: 'Pro', professional: 'Professional', enterprise: 'Enterprise' };
                const planLabel  = planLabels[currentPlan] || currentPlan || 'your current';
                reasonEl.textContent =
                    `Your ${planLabel} plan allows up to ${limit} ${resource}s (you have ${current}). Upgrade to unlock more.`;
            } else {
                reasonEl.textContent = 'Unlock higher limits and powerful features for your business.';
            }
        }

        // Highlight the card matching current plan
        ['pro', 'professional', 'enterprise'].forEach(p => {
            const card = document.getElementById(`upg-card-${p}`);
            if (card) card.classList.toggle('upg-card--active', p === currentPlan);
        });

        // Show cards view, hide calc view
        const cardsView = document.getElementById('upg-cards-view');
        const calcView  = document.getElementById('upg-calc-view');
        if (cardsView) cardsView.style.display = '';
        if (calcView)  calcView.style.display  = 'none';

        // Show modal
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';

        // Init event listeners (idempotent)
        this._initUpgradeModalEvents();
    }

    _initUpgradeModalEvents() {
        if (this._upgEventsInited) return;
        this._upgEventsInited = true;

        const modal     = document.getElementById('upgrade-modal');
        const closeBtn  = document.getElementById('upg-close-btn');
        const cardsView = document.getElementById('upg-cards-view');
        const calcView  = document.getElementById('upg-calc-view');
        const configBtn = document.getElementById('upg-configure-enterprise');
        const backBtn   = document.getElementById('upg-calc-back');
        const payPro    = document.getElementById('upg-pay-professional');
        const payEnt    = document.getElementById('upg-pay-enterprise');

        // Close on X or backdrop
        if (closeBtn) closeBtn.addEventListener('click', () => this._closeUpgradeModal());
        if (modal) modal.addEventListener('click', e => {
            if (e.target === modal) this._closeUpgradeModal();
        });

        // Enterprise configure button → show calculator
        if (configBtn) configBtn.addEventListener('click', () => {
            if (cardsView) cardsView.style.display = 'none';
            if (calcView)  calcView.style.display  = '';
            this._initEnterpriseCalc();
        });

        // Back from calculator
        if (backBtn) backBtn.addEventListener('click', () => {
            if (cardsView) cardsView.style.display = '';
            if (calcView)  calcView.style.display  = 'none';
        });

        // Pay professional
        if (payPro) payPro.addEventListener('click', () => this._startPayment('professional'));

        // Pay enterprise (from summary card)
        if (payEnt) payEnt.addEventListener('click', () => {
            const clients  = parseInt(document.getElementById('ent-clients-input')?.value)  || 200;
            const invoices = parseInt(document.getElementById('ent-invoices-input')?.value) || 500;
            this._startPayment('enterprise', clients - 200, invoices - 500);
        });
    }

    _closeUpgradeModal() {
        const modal = document.getElementById('upgrade-modal');
        if (modal) modal.style.display = 'none';
        document.body.style.overflow = '';
    }

    _initEnterpriseCalc() {
        const cSlider = document.getElementById('ent-clients-slider');
        const cInput  = document.getElementById('ent-clients-input');
        const iSlider = document.getElementById('ent-invoices-slider');
        const iInput  = document.getElementById('ent-invoices-input');

        const updateCalc = () => {
            const clients  = Math.max(200, parseInt(cInput.value)  || 200);
            const invoices = Math.max(500, parseInt(iInput.value) || 500);

            const extraC   = Math.max(0, clients  - 200);
            const extraI   = Math.max(0, invoices - 500);
            const total    = 2999 + (extraC * 50) + (extraI * 20);

            // Sync slider ↔ input
            if (cSlider) cSlider.value = Math.min(clients,  parseInt(cSlider.max));
            if (iSlider) iSlider.value = Math.min(invoices, parseInt(iSlider.max));

            // Extra client row
            const ecRow  = document.getElementById('ent-extra-c-row');
            const ecLbl  = document.getElementById('ent-extra-c-label');
            const ecCost = document.getElementById('ent-extra-c-cost');
            if (ecRow) ecRow.style.display = extraC > 0 ? '' : 'none';
            if (ecLbl)  ecLbl.textContent  = `${extraC} extra clients × ₹50`;
            if (ecCost) ecCost.textContent = `+₹${(extraC * 50).toLocaleString('en-IN')}`;

            // Extra invoice row
            const eiRow  = document.getElementById('ent-extra-i-row');
            const eiLbl  = document.getElementById('ent-extra-i-label');
            const eiCost = document.getElementById('ent-extra-i-cost');
            if (eiRow) eiRow.style.display = extraI > 0 ? '' : 'none';
            if (eiLbl)  eiLbl.textContent  = `${extraI} extra invoices × ₹20`;
            if (eiCost) eiCost.textContent = `+₹${(extraI * 20).toLocaleString('en-IN')}`;

            // Total
            const totalStr = `₹${total.toLocaleString('en-IN')}`;
            const totEl  = document.getElementById('ent-total-display');
            const sumP   = document.getElementById('ent-sum-price');
            if (totEl) totEl.textContent  = totalStr;
            if (sumP)  sumP.textContent   = totalStr;

            // Summary
            const sumC = document.getElementById('ent-sum-clients');
            const sumI = document.getElementById('ent-sum-invoices');
            if (sumC) sumC.textContent = `${clients.toLocaleString('en-IN')} clients`;
            if (sumI) sumI.textContent = `${invoices.toLocaleString('en-IN')} invoices`;
        };

        // Wire events (once per slider pair)
        if (cSlider && !cSlider._wired) {
            cSlider._wired = true;
            cSlider.addEventListener('input', () => { cInput.value = cSlider.value; updateCalc(); });
            cInput.addEventListener('input',  () => updateCalc());
            iSlider.addEventListener('input', () => { iInput.value = iSlider.value; updateCalc(); });
            iInput.addEventListener('input',  () => updateCalc());
        }

        updateCalc();
    }

    async _startPayment(plan, extraClients = 0, extraInvoices = 0) {
        const btn = plan === 'professional'
            ? document.getElementById('upg-pay-professional')
            : document.getElementById('upg-pay-enterprise');

        const origHtml = btn ? btn.innerHTML : '';
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating order…'; }

        try {
            const res = await api.createRazorpayOrder(plan, extraClients, extraInvoices);
            if (!res?.success) {
                this.showToast('error', 'Order Failed', res?.message || 'Could not create order. Please try again.');
                return;
            }

            const d = res.data;
            const options = {
                key:          d.key_id,
                amount:       d.amount,
                currency:     d.currency,
                order_id:     d.order_id,
                name:         'InvoicePro',
                description:  plan === 'professional' ? 'Professional Plan' : 'Enterprise Plan',
                image:        '/invoice-management/frontend/images/logo.png',
                prefill: {
                    name:  d.user_name  || '',
                    email: d.user_email || '',
                },
                theme: { color: '#6366f1' },
                handler: async (response) => {
                    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying…'; }
                    await this._verifyPayment(response, plan);
                },
                modal: {
                    ondismiss: () => {
                        if (btn) { btn.disabled = false; btn.innerHTML = origHtml; }
                    }
                }
            };

            const rzp = new Razorpay(options);
            rzp.open();

        } catch (e) {
            this.showToast('error', 'Payment Error', e.message || 'Unexpected error. Please try again.');
        } finally {
            if (btn) { btn.disabled = false; btn.innerHTML = origHtml; }
        }
    }

    async _verifyPayment(response, plan) {
        try {
            const res = await api.verifyRazorpayPayment(
                response.razorpay_order_id,
                response.razorpay_payment_id,
                response.razorpay_signature
            );

            if (res?.success) {
                this._closeUpgradeModal();
                this.showToast('success', 'Plan Activated!',
                    `Your ${plan === 'professional' ? 'Professional' : 'Enterprise'} plan is now active. Enjoy your new limits!`);
                // Refresh plan usage display if visible
                setTimeout(() => this.loadPlanUsage(), 800);
            } else {
                this.showToast('error', 'Verification Failed', res?.message || 'Payment could not be verified. Contact support.');
            }
        } catch (e) {
            this.showToast('error', 'Verification Error', e.message);
        }
    }

    /**
     * Load plan limits from the API and render the usage meters in Settings > Account tab.
     */
    async loadPlanUsage() {
        try {
            const result = await api.getPlanLimits();
            if (!result?.data) return;

            const d         = result.data;
            const plan      = d.plan       || 'pro';
            const planLabel = d.plan_label || 'Pro';

            // Support both flat shape (from backend) and nested shape
            const usedClients  = d.used_clients  ?? d.usage?.clients  ?? 0;
            const usedInvoices = d.used_invoices ?? d.usage?.invoices ?? 0;
            const maxClients   = d.max_clients   ?? d.limits?.max_clients  ?? 10;
            const maxInvoices  = d.max_invoices  ?? d.limits?.max_invoices ?? 20;

            const pctC = maxClients  === -1 ? 0 : Math.min(Math.round((usedClients  / maxClients)  * 100), 100);
            const pctI = maxInvoices === -1 ? 0 : Math.min(Math.round((usedInvoices / maxInvoices) * 100), 100);

            // Plan badge
            const badgeEl = document.getElementById('plan-badge');
            if (badgeEl) {
                const colors = { pro: 'var(--primary)', professional: 'var(--success)', enterprise: 'var(--warning)' };
                badgeEl.textContent = planLabel + ' Plan';
                badgeEl.style.background = colors[plan] || 'var(--primary)';
            }

            // Renewal date (enterprise only)
            let renewsEl = document.getElementById('plan-renews-row');
            if (plan === 'enterprise' && d.renews_on) {
                if (!renewsEl) {
                    // Insert below badge
                    const badge = document.getElementById('plan-badge');
                    if (badge && badge.parentNode) {
                        renewsEl = document.createElement('div');
                        renewsEl.id = 'plan-renews-row';
                        renewsEl.style.cssText = 'font-size:0.8rem;color:var(--text-secondary);margin-top:6px;';
                        badge.parentNode.insertBefore(renewsEl, badge.nextSibling);
                    }
                }
                if (renewsEl) renewsEl.innerHTML = `<i class="fas fa-calendar-alt" style="margin-right:4px;color:var(--warning);"></i>Renews <strong>${this.escapeHtml(d.renews_on)}</strong>`;
            } else if (renewsEl) {
                renewsEl.remove();
            }

            // Clients meter
            this._renderUsageMeter(
                'usage-clients-bar', 'usage-clients-text',
                usedClients, maxClients, 'Clients', pctC
            );

            // Invoices meter
            this._renderUsageMeter(
                'usage-invoices-bar', 'usage-invoices-text',
                usedInvoices, maxInvoices, 'Invoices', pctI
            );

            // Feature flags
            const features = d.features || {};
            this._renderFeatureList('plan-features-list', features);

        } catch (e) {
            // Non-fatal — settings page still works
        }
    }

    _renderUsageMeter(barId, textId, used, max, label, pct) {
        const bar  = document.getElementById(barId);
        const text = document.getElementById(textId);
        if (!bar && !text) return;

        const unlimited = (max === -1 || max === '-1');
        const usedN     = parseInt(used) || 0;
        const maxN      = parseInt(max)  || 0;
        const pctN      = unlimited ? 0 : Math.min(parseInt(pct) || 0, 100);

        const color = pctN >= 90 ? 'var(--danger)' : pctN >= 70 ? 'var(--warning)' : 'var(--primary)';

        if (bar) {
            bar.style.width      = unlimited ? '5%' : `${pctN}%`;
            bar.style.background = color;
        }
        if (text) {
            text.textContent = unlimited
                ? `${usedN} / Unlimited`
                : `${usedN} / ${maxN}`;
            text.style.color = pctN >= 90 ? 'var(--danger)' : 'var(--text-secondary)';
        }
    }

    _renderFeatureList(listId, features) {
        const el = document.getElementById(listId);
        if (!el) return;

        const featureNames = {
            export_reports:     'Export Reports (CSV/PDF)',
            email_invoices:     'Email Invoices',
            custom_branding:    'Custom Branding',
            multi_currency:     'Multi-Currency',
            recurring_invoices: 'Recurring Invoices',
            api_access:         'API Access',
            team_collaboration: 'Team Collaboration',
            advanced_reports:   'Advanced Reports',
            bulk_import_export: 'Bulk Import/Export',
            priority_support:   'Priority Support',
        };

        el.innerHTML = Object.entries(featureNames).map(([key, label]) => {
            const available = features[key] === true || features[key] === 1;
            return `
                <div class="plan-feature-item ${available ? 'available' : 'unavailable'}">
                    <i class="fas ${available ? 'fa-check-circle' : 'fa-times-circle'}"></i>
                    <span>${label}</span>
                </div>
            `;
        }).join('');
    }

    // ── Share Invoice Public Link ──
    async shareInvoiceLink(invoiceId) {
        const btn = document.getElementById('preview-share-btn');
        const origHTML = btn ? btn.innerHTML : '';
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating…'; }

        try {
            const result = await api.generatePublicLink(invoiceId);
            if (!result?.success) throw new Error(result?.message || 'Could not generate link');

            const url = result.data?.url;
            if (!url) throw new Error('No URL returned');

            // Copy to clipboard
            try {
                await navigator.clipboard.writeText(url);
                this.showToast('success', 'Link Copied!', 'Public payment link copied to clipboard.');
            } catch {
                // Fallback: prompt
                window.prompt('Copy this public payment link:', url);
            }

            if (btn) btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
            setTimeout(() => { if (btn) { btn.disabled = false; btn.innerHTML = origHTML; } }, 2500);
        } catch (err) {
            this.showToast('error', 'Error', err.message || 'Could not generate public link.');
            if (btn) { btn.disabled = false; btn.innerHTML = origHTML; }
        }
    }

    // Update user info displayed in sidebar and topbar
    updateUserDisplay() {
        if (typeof authManager === 'undefined') return;

        const user = authManager.getCurrentUser();
        if (!user) return;

        // user_name in localStorage is the source of truth (updated by account save)
        const name = localStorage.getItem('user_name') || user.name || user.email?.split('@')[0] || 'User';
        const email = localStorage.getItem('user_email') || user.email || '';

        // Sync in-memory object so initials are correct
        user.name = name;

        const parts = name.trim().split(/\s+/);
        const initials = parts.length >= 2
            ? (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
            : name.substring(0, 2).toUpperCase();

        this._setText('sidebar-username', name);
        this._setText('sidebar-email', email);
        this._setText('sidebar-avatar', initials);
        this._setText('topbar-username', name);
        this._setText('topbar-avatar', initials);
    }
}

// Global instance
window.uiManager = new UIManager();

// Apply theme and user display as soon as DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.uiManager.applyTheme();
    window.uiManager.updateUserDisplay();
});