/**
 * main.js — Entry point for the application
 */
document.addEventListener('DOMContentLoaded', () => {
    // ── Auth Gate ──
    if (typeof authManager === 'undefined' || !authManager.requireAuth()) return;

    // ── Components & UI Initialization ──
    const userDropdown = document.getElementById('user-dropdown');
    const topbarUser = document.getElementById('topbar-user');

    if (topbarUser && userDropdown) {
        topbarUser.addEventListener('click', (e) => {
            e.stopPropagation();
            userDropdown.classList.toggle('show');
        });

        document.addEventListener('click', () => {
            userDropdown.classList.remove('show');
        });
    }

    // ── Theme Toggle ──
    document.getElementById('theme-toggle')?.addEventListener('click', () => {
        uiManager.toggleTheme();
    });

    // ── Notifications ──
    document.getElementById('notification-btn')?.addEventListener('click', () => {
        uiManager.showToast('info', 'Notifications', 'No new notifications.');
    });

    // ── Logout (topbar dropdown) ──
    document.getElementById('logout-btn')?.addEventListener('click', () => {
        authManager.logout();
    });

    // ── Logout (sidebar) ──
    document.getElementById('sidebar-user')?.addEventListener('click', () => {
        authManager.logout();
    });

    // ── View Transitions ──
    // Handle hash change for navigation
    window.addEventListener('hashchange', () => {
        const view = window.location.hash.replace('#', '') || 'dashboard';
        uiManager.showView(view);
    });

    // Initial view load
    const initialView = window.location.hash.replace('#', '') || 'dashboard';
    uiManager.showView(initialView);

    // ── Global Search ──
    const globalSearch = document.getElementById('global-search');
    if (globalSearch) {
        globalSearch.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase();
            if (uiManager.currentView === 'invoices') {
                uiManager.renderInvoicesTable(uiManager.invoicesData, 'all', query);
            } else if (uiManager.currentView === 'clients') {
                uiManager.renderClientsGrid(uiManager.clientsData, query);
            }
        });
    }

    // ── Sidebar Toggle (menu button in topbar) ──
    document.getElementById('menu-toggle')?.addEventListener('click', () => {
        uiManager.toggleSidebar();
    });

    // ── Invoice Form Events ──
    document.getElementById('create-invoice-btn')?.addEventListener('click', () => {
        uiManager.showInvoiceForm();
    });

    document.getElementById('cancel-invoice-btn')?.addEventListener('click', () => {
        uiManager.showView('invoices');
    });

    document.getElementById('save-invoice-btn')?.addEventListener('click', () => {
        uiManager.saveInvoice();
    });

    document.getElementById('add-item-row')?.addEventListener('click', () => {
        uiManager.addInvoiceItemRow();
    });

    // ── Invoice Search ──
    document.getElementById('invoice-search')?.addEventListener('input', (e) => {
        const query = e.target.value.toLowerCase();
        const activeFilter = document.querySelector('#invoice-status-filter .filter-tab.active');
        const status = activeFilter?.dataset?.status || 'all';
        uiManager.renderInvoicesTable(uiManager.invoicesData, status, query);
    });

    // ── Invoice Status Filter Tabs ──
    document.querySelectorAll('#invoice-status-filter .filter-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('#invoice-status-filter .filter-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            const search = document.getElementById('invoice-search')?.value?.toLowerCase() || '';
            uiManager.renderInvoicesTable(uiManager.invoicesData, tab.dataset.status, search);
        });
    });

    // ── Export/Print/PDF Buttons ──
    document.getElementById('export-invoices-btn')?.addEventListener('click', () => {
        uiManager.exportInvoicesCSV();
    });

    document.getElementById('export-payments-btn')?.addEventListener('click', () => {
        uiManager.exportPaymentsCSV();
    });

    // ── Invoice Preview Actions ──
    document.getElementById('preview-print-btn')?.addEventListener('click', () => {
        uiManager.printInvoice();
    });

    document.getElementById('preview-download-btn')?.addEventListener('click', () => {
        uiManager.downloadInvoicePDF();
    });

    document.getElementById('preview-status-btn')?.addEventListener('click', (e) => {
        e.stopPropagation();
        document.getElementById('preview-status-menu')?.classList.toggle('show');
    });

    document.querySelectorAll('#preview-status-menu .dropdown-item').forEach(item => {
        item.addEventListener('click', () => {
            const status = item.dataset.status;
            const inv = uiManager._currentPreviewInvoice;
            if (inv && status) {
                uiManager.updateInvoiceStatus(inv.id, status);
            }
            document.getElementById('preview-status-menu')?.classList.remove('show');
        });
    });

    document.getElementById('preview-email-btn')?.addEventListener('click', () => {
        uiManager.sendInvoiceEmail();
    });

    // ── Dashboard Period Tabs ──
    document.querySelectorAll('#revenue-period-tabs .filter-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('#revenue-period-tabs .filter-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            const period = tab.dataset.period;
            const numMonths = period === '1y' ? 12 : 6;
            api.getDashboardStats(period).then(result => {
                const data = result?.data || {};
                uiManager.renderRevenueChart(data.monthly_revenue || [], numMonths);
            });
        });
    });

    // ── Reports Period Tabs ──
    document.querySelectorAll('#report-period-tabs .filter-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('#report-period-tabs .filter-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            api.getDashboardStats(tab.dataset.period).then(result => {
                uiManager.renderReportCharts(result?.data || {});
            });
        });
    });

    // Close menus/dropdowns on click outside
    document.addEventListener('click', () => {
        document.getElementById('preview-status-menu')?.classList.remove('show');
        document.getElementById('user-dropdown')?.classList.remove('show');
    });

    // ── Settings Appearance Panel ──
    function syncSettingsAppearanceUI() {
        const savedTpl = parseInt(localStorage.getItem('inv_template') || '1');
        const savedColor = localStorage.getItem('inv_accent_color') || '#6366f1';

        document.querySelectorAll('.settings-tpl-btn').forEach(b => {
            b.classList.toggle('active', parseInt(b.dataset.tpl) === savedTpl);
        });
        const picker = document.getElementById('settings-accent-picker');
        const label = document.getElementById('settings-color-label');
        if (picker) picker.value = savedColor;
        if (label) label.textContent = savedColor;
        document.querySelectorAll('.settings-color-swatch').forEach(s => {
            s.classList.toggle('active', s.dataset.color === savedColor);
        });
    }

    document.querySelectorAll('.settings-tpl-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const tpl = parseInt(btn.dataset.tpl);
            localStorage.setItem('inv_template', tpl);
            // Also sync preview toolbar if open
            document.querySelectorAll('.inv-tpl-btn').forEach(b => {
                b.classList.toggle('active', parseInt(b.dataset.tpl) === tpl);
            });
            document.querySelectorAll('.settings-tpl-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            // Re-render preview if data is loaded
            if (uiManager._previewData) {
                uiManager._currentTemplate = tpl;
                uiManager._renderInvoicePreview();
            }
        });
    });

    document.querySelectorAll('.settings-color-swatch').forEach(swatch => {
        swatch.addEventListener('click', () => {
            const color = swatch.dataset.color;
            localStorage.setItem('inv_accent_color', color);
            document.querySelectorAll('.settings-color-swatch').forEach(s => s.classList.remove('active'));
            swatch.classList.add('active');
            const picker = document.getElementById('settings-accent-picker');
            const label = document.getElementById('settings-color-label');
            if (picker) picker.value = color;
            if (label) label.textContent = color;
            // Also sync preview toolbar
            document.querySelectorAll('.inv-color-swatch:not(.settings-color-swatch)').forEach(s => {
                s.classList.toggle('active', s.dataset.color === color);
            });
            if (uiManager._previewData) {
                uiManager._currentAccentColor = color;
                uiManager._renderInvoicePreview();
            }
        });
    });

    const settingsColorPicker = document.getElementById('settings-accent-picker');
    if (settingsColorPicker) {
        settingsColorPicker.addEventListener('input', () => {
            const color = settingsColorPicker.value;
            localStorage.setItem('inv_accent_color', color);
            const label = document.getElementById('settings-color-label');
            if (label) label.textContent = color;
            document.querySelectorAll('.settings-color-swatch').forEach(s => s.classList.remove('active'));
            if (uiManager._previewData) {
                uiManager._currentAccentColor = color;
                uiManager._renderInvoicePreview();
            }
        });
    }

    // Sync settings appearance UI when settings tab opens
    window.addEventListener('hashchange', () => {
        if (window.location.hash === '#settings') syncSettingsAppearanceUI();
    });
    if (window.location.hash === '#settings') syncSettingsAppearanceUI();

    console.log('✅ InvoicePro initialized');
});