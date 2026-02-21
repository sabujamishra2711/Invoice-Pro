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
                case 'settings': await this.loadSettings(); this.initSettingsTabs(); break;
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
                <td style="font-weight:600;">${this.formatCurrency(inv.total_amount)}</td>
                <td style="color:var(--text-secondary);font-size:0.84rem;">${this.formatDate(inv.issue_date)}</td>
                <td style="color:var(--text-secondary);font-size:0.84rem;">${this.formatDate(inv.due_date)}</td>
                <td><span class="status-pill ${inv.status}"><span class="status-dot"></span>${inv.status}</span></td>
                <td>
                    <div style="display:flex;gap:4px;">
                        <button class="btn-icon btn-ghost sm" title="View" onclick="uiManager.previewInvoice(${inv.id})"><i class="fas fa-eye"></i></button>
                        <button class="btn-icon btn-ghost sm" title="Edit" onclick="uiManager.editInvoice(${inv.id})"><i class="fas fa-pen"></i></button>
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

        // Clear and add items
        const container = document.getElementById('invoice-items-container');
        container.innerHTML = '';

        if (invoiceData && invoiceData.items && invoiceData.items.length) {
            invoiceData.items.forEach(item => this.addInvoiceItemRow(item));
            document.getElementById('invoice-id').value = invoiceData.id;
            document.getElementById('invoice-client').value = invoiceData.client_id;
            document.getElementById('invoice-currency').value = invoiceData.currency || 'INR';
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

        this._setText('invoice-subtotal', subtotal.toFixed(2));
        this._setText('invoice-tax', totalTax.toFixed(2));
        this._setText('invoice-total', (subtotal + totalTax).toFixed(2));
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
    async loadReports() {
        try {
            // Use dashboard stats for report data
            const result = await api.getDashboardStats();
            const data = result?.data || {};

            // Update stats
            this._setText('report-total-revenue', this.formatCurrency(data.total_revenue || 0));
            this._setText('report-total-invoices', data.total_invoices || 0);

            const totalAmount = parseFloat(data.total_revenue || 0) + parseFloat(data.pending_amount || 0);
            const rate = totalAmount > 0 ? ((parseFloat(data.total_revenue || 0) / totalAmount) * 100).toFixed(0) : 0;
            this._setText('report-collection-rate', rate + '%');

            // Render charts
            this.renderReportCharts(data);
        } catch (err) {
            console.warn('Load reports error:', err);
        }
    }

    renderReportCharts(data) {
        // Revenue chart
        const revCtx = document.getElementById('reportRevenueChart');
        if (revCtx) {
            if (this.charts.reportRevenue) this.charts.reportRevenue.destroy();

            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const now = new Date();
            const labels = [];
            const values = [];
            const monthlyData = data.monthly_revenue || [];

            for (let i = 5; i >= 0; i--) {
                const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
                labels.push(months[d.getMonth()]);
                const key = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
                const found = monthlyData.find(m => m.month === key);
                values.push(found ? parseFloat(found.total) : 0);
            }

            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';

            this.charts.reportRevenue = new Chart(revCtx, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        label: 'Revenue',
                        data: values,
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99, 102, 241, 0.08)',
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#6366f1',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: isDark ? '#94a3b8' : '#64748b' } },
                        y: {
                            grid: { color: isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)' },
                            ticks: { color: isDark ? '#94a3b8' : '#64748b', callback: v => '₹' + (v >= 1000 ? (v / 1000).toFixed(0) + 'k' : v) }
                        }
                    }
                }
            });
        }

        // Client chart (top clients by revenue)
        const clientCtx = document.getElementById('reportClientChart');
        if (clientCtx) {
            if (this.charts.reportClient) this.charts.reportClient.destroy();

            const topClients = (data.top_clients || []).slice(0, 5);
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';

            this.charts.reportClient = new Chart(clientCtx, {
                type: 'doughnut',
                data: {
                    labels: topClients.length ? topClients.map(c => c.name) : ['No data'],
                    datasets: [{
                        data: topClients.length ? topClients.map(c => c.revenue) : [1],
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
                            labels: {
                                color: isDark ? '#94a3b8' : '#475569',
                                padding: 12,
                                usePointStyle: true,
                                pointStyle: 'circle',
                                boxWidth: 8,
                                boxHeight: 8,
                                font: { size: 11 }
                            }
                        }
                    }
                }
            });
        }
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
        } catch (err) {
            console.warn('Load settings error:', err);
        }

        // ── Populate Account tab ──
        try {
            const user = typeof authManager !== 'undefined' ? authManager.getCurrentUser() : null;
            if (user) {
                // Always read from localStorage — these are the canonical saved values
                this._setVal('setting-name', localStorage.getItem('user_name') || user.name || '');
                this._setVal('setting-email', localStorage.getItem('user_email') || user.email || '');
            }
        } catch { }
        const savedTheme = localStorage.getItem('theme') || 'light';
        this._setVal('setting-theme', savedTheme);

        // ── Populate Invoice Appearance tab ──
        const savedTpl   = parseInt(localStorage.getItem('inv_template') || '1');
        const savedColor = localStorage.getItem('inv_accent_color') || '#6366f1';

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

          // Logo support — stored as base64 in localStorage
          const logoDataUrl = localStorage.getItem('business_logo');
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

        const businessName = document.getElementById('setting-business-name')?.value || 'Our Company';
        const clientEmail = inv.client_email || '';
        const subject = encodeURIComponent(`Invoice ${inv.invoice_number} from ${businessName}`);
        const body = encodeURIComponent(`Hello ${inv.client_name},

Please find attached invoice ${inv.invoice_number} for ${inv.currency} ${inv.total_amount}.

DueDate: ${inv.due_date}

Thank you for your business!`);

        window.location.href = `mailto:${clientEmail}?subject=${subject}&body=${body}`;
        this.showToast('info', 'Email Client', 'Opening your default mail app...');
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
    formatCurrency(amount, currency = 'INR') {
        const num = parseFloat(amount) || 0;
        const symbols = { INR: '₹', USD: '$', EUR: '€', GBP: '£' };
        const symbol = symbols[currency] || '₹';
        return symbol + num.toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
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