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
        uiManager._page.invoices = 1;
        uiManager.renderInvoicesTable(uiManager.invoicesData, status, query);
    });

    // ── Invoice Status Filter Tabs ──
    document.querySelectorAll('#invoice-status-filter .filter-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('#invoice-status-filter .filter-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            const search = document.getElementById('invoice-search')?.value?.toLowerCase() || '';
            uiManager._page.invoices = 1;
            uiManager.renderInvoicesTable(uiManager.invoicesData, tab.dataset.status, search);
        });
    });

    // ── Export/Print/PDF Buttons ──
    document.getElementById('export-invoices-btn')?.addEventListener('click', () => {
        uiManager.exportInvoicesCSV();
    });

    // ── Client Search ──
    document.getElementById('client-search')?.addEventListener('input', (e) => {
        uiManager._page.clients = 1;
        uiManager.renderClientsGrid(uiManager.clientsData, e.target.value.toLowerCase());
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

    document.getElementById('preview-duplicate-btn')?.addEventListener('click', () => {
        const inv = uiManager._currentPreviewInvoice;
        if (inv?.id) uiManager.duplicateInvoice(inv.id);
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
            uiManager.loadReports(tab.dataset.period);
        });
    });

    // ── Report Export Buttons ──
    document.getElementById('export-report-csv-btn')?.addEventListener('click', () => {
        uiManager.exportReportsCSV();
    });

    document.getElementById('export-report-pdf-btn')?.addEventListener('click', () => {
        uiManager.exportReportsPDF();
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

    // ── Save Invoice Settings (template + color) ──
    document.getElementById('save-invoice-settings')?.addEventListener('click', () => {
        const activeTpl = document.querySelector('.settings-tpl-btn.active');
        const tpl = activeTpl ? parseInt(activeTpl.dataset.tpl) : 1;
        const color = document.getElementById('settings-accent-picker')?.value || '#6366f1';
        localStorage.setItem('inv_template', tpl);
        localStorage.setItem('inv_accent_color', color);
        uiManager.showToast('success', 'Saved', 'Invoice appearance saved as default.');
    });

    // ── Save Account Settings ──
    document.getElementById('save-account-settings')?.addEventListener('click', () => {
        const name = document.getElementById('setting-name')?.value.trim();
        const theme = document.getElementById('setting-theme')?.value || 'light';

        // Persist theme
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('theme', theme);
        const themeBtn = document.getElementById('theme-toggle');
        if (themeBtn) themeBtn.querySelector('i').className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';

        // Persist name — write to user_name (same key auth.js reads on init)
        if (name) {
            localStorage.setItem('user_name', name);
            // Also patch the in-memory authManager user object directly
            if (typeof authManager !== 'undefined') {
                const user = authManager.getCurrentUser();
                if (user) user.name = name;
            }
        }

        // Refresh sidebar/topbar via the central method
        uiManager.updateUserDisplay();

        uiManager.showToast('success', 'Saved', 'Account settings saved.');
    });

    // ── Logo Upload — save base64 to localStorage, show preview ──
    document.getElementById('logo-upload')?.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (!file) return;
        if (file.size > 2 * 1024 * 1024) {
            uiManager.showToast('error', 'Too Large', 'Logo must be under 2 MB.');
            return;
        }
        const reader = new FileReader();
        reader.onload = (ev) => {
            const dataUrl = ev.target.result;
            localStorage.setItem('business_logo', dataUrl);
            // Show preview in settings
            const preview = document.getElementById('logo-preview');
            if (preview) {
                preview.innerHTML = `<img src="${dataUrl}" style="width:100%;height:100%;object-fit:contain;border-radius:10px;">`;
            }
            uiManager.showToast('success', 'Logo Saved', 'Logo will appear on your invoices.');
        };
        reader.readAsDataURL(file);
    });

    // Restore logo preview on settings load
    const savedLogo = localStorage.getItem('business_logo');
    if (savedLogo) {
        const preview = document.getElementById('logo-preview');
        if (preview) {
            preview.innerHTML = `<img src="${savedLogo}" style="width:100%;height:100%;object-fit:contain;border-radius:10px;">`;
        }
    }

    console.log('✅ InvoicePro initialized');

    // ── Email Settings Handlers ──
    document.getElementById('save-email-settings')?.addEventListener('click', () => {
        uiManager.saveEmailSettings();
    });

    document.getElementById('test-smtp-btn')?.addEventListener('click', () => {
        uiManager.testSmtpConnection();
    });

    // Load email settings when the email tab is opened
    document.querySelector('[data-tab="email-settings"]')?.addEventListener('click', () => {
        uiManager.loadEmailSettings();
    });

    // ── Send Email Modal — Send button ──
    document.getElementById('send-email-btn')?.addEventListener('click', () => {
        uiManager._doSendEmail();
    });
});