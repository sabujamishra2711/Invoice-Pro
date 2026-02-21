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

    // ── Save Invoice Settings (template + color + default currency) ──
    document.getElementById('save-invoice-settings')?.addEventListener('click', () => {
        const activeTpl = document.querySelector('.settings-tpl-btn.active');
        const tpl = activeTpl ? parseInt(activeTpl.dataset.tpl) : 1;
        const color = document.getElementById('settings-accent-picker')?.value || '#6366f1';
        const currency = document.getElementById('setting-currency')?.value || 'INR';
        localStorage.setItem('inv_template', tpl);
        localStorage.setItem('inv_accent_color', color);
        localStorage.setItem('default_currency', currency);
        uiManager.showToast('success', 'Saved', 'Invoice appearance and default currency saved.');
    });

    // ── Save Account Settings ──
    document.getElementById('save-account-settings')?.addEventListener('click', async () => {
        const name    = document.getElementById('setting-name')?.value.trim();
        const email   = document.getElementById('setting-email')?.value.trim();
        const phone   = document.getElementById('setting-phone')?.value.trim();
        const theme   = document.getElementById('setting-theme')?.value || 'light';
        const isGoogle = typeof authManager !== 'undefined' && authManager.isGoogleUser();

        const msgEl = document.getElementById('account-save-msg');
        const btn   = document.getElementById('save-account-settings');

        // Persist theme immediately
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('theme', theme);
        const themeBtn = document.getElementById('theme-toggle');
        if (themeBtn) themeBtn.querySelector('i').className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';

        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...'; }

        try {
            // Build payload — email excluded for Google users
            const payload = { name, phone };
            if (!isGoogle && email) payload.email = email;

            // Call backend profile update
            if (typeof authManager !== 'undefined') {
                const result = await authManager.updateProfile(payload);
                if (!result.success) throw new Error(result.error || 'Could not save profile');
            } else {
                // Fallback: just update localStorage
                if (name) localStorage.setItem('user_name', name);
                if (phone) localStorage.setItem('user_phone', phone);
                if (!isGoogle && email) localStorage.setItem('user_email', email);
            }

            uiManager.updateUserDisplay();

            if (msgEl) {
                msgEl.style.display = 'block';
                msgEl.style.background = 'rgba(16,185,129,0.1)';
                msgEl.style.border = '1px solid rgba(16,185,129,0.2)';
                msgEl.style.color = 'var(--success)';
                msgEl.innerHTML = '<i class="fas fa-check-circle" style="margin-right:6px;"></i>Profile saved successfully.';
                setTimeout(() => { msgEl.style.display = 'none'; }, 3000);
            } else {
                uiManager.showToast('success', 'Saved', 'Profile updated successfully.');
            }
        } catch (err) {
            if (msgEl) {
                msgEl.style.display = 'block';
                msgEl.style.background = 'rgba(239,68,68,0.08)';
                msgEl.style.border = '1px solid rgba(239,68,68,0.15)';
                msgEl.style.color = 'var(--danger)';
                msgEl.innerHTML = `<i class="fas fa-exclamation-circle" style="margin-right:6px;"></i>${err.message}`;
            } else {
                uiManager.showToast('error', 'Error', err.message);
            }
        } finally {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check"></i> Save Profile'; }
        }
    });

    // ── Logo Upload — POST multipart to backend ──
    document.getElementById('logo-upload')?.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (!file) return;
        if (file.size > 2 * 1024 * 1024) {
            uiManager.showToast('error', 'Too Large', 'Logo must be under 2 MB.');
            e.target.value = '';
            return;
        }

        const statusEl = document.getElementById('logo-upload-status');
        const uploadBtn = document.getElementById('logo-upload-btn');
        if (statusEl) statusEl.textContent = 'Uploading...';
        if (uploadBtn) { uploadBtn.disabled = true; uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...'; }

        try {
            const formData = new FormData();
            formData.append('logo', file);

            const token = localStorage.getItem('auth_token');
            const response = await fetch(`${api.baseUrl}/index.php?route=settings.logo.upload`, {
                method: 'POST',
                headers: token ? { 'Authorization': 'Bearer ' + token } : {},
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                const logoUrl = result.data?.logo_url;
                const preview = document.getElementById('logo-preview');
                if (preview && logoUrl) {
                    preview.innerHTML = `<img src="${logoUrl}?t=${Date.now()}" style="width:100%;height:100%;object-fit:contain;border-radius:10px;">`;
                }
                const removeBtn = document.getElementById('logo-remove-btn');
                if (removeBtn) removeBtn.style.display = '';
                // Also cache in localStorage for offline preview
                const reader = new FileReader();
                reader.onload = (ev) => localStorage.setItem('business_logo', ev.target.result);
                reader.readAsDataURL(file);
                if (statusEl) statusEl.textContent = '';
                uiManager.showToast('success', 'Logo Uploaded', 'Logo saved and will appear on invoices.');
            } else {
                if (statusEl) statusEl.textContent = result.message || 'Upload failed.';
                uiManager.showToast('error', 'Upload Failed', result.message || 'Could not upload logo.');
            }
        } catch (err) {
            if (statusEl) statusEl.textContent = 'Upload failed.';
            uiManager.showToast('error', 'Error', err.message || 'Upload failed.');
        } finally {
            if (uploadBtn) { uploadBtn.disabled = false; uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Upload Logo'; }
            e.target.value = '';
        }
    });

    // ── Logo Remove ──
    document.getElementById('logo-remove-btn')?.addEventListener('click', async () => {
        const removeBtn = document.getElementById('logo-remove-btn');
        if (removeBtn) { removeBtn.disabled = true; removeBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Removing...'; }
        try {
            const result = await api.request('settings.logo.delete', 'POST', {});
            if (result.success) {
                localStorage.removeItem('business_logo');
                const preview = document.getElementById('logo-preview');
                if (preview) preview.innerHTML = '<i class="fas fa-image"></i>';
                if (removeBtn) removeBtn.style.display = 'none';
                uiManager.showToast('success', 'Logo Removed', 'Logo has been removed.');
            } else {
                uiManager.showToast('error', 'Error', result.message || 'Could not remove logo.');
            }
        } catch (err) {
            uiManager.showToast('error', 'Error', err.message || 'Could not remove logo.');
        } finally {
            if (removeBtn) { removeBtn.disabled = false; removeBtn.innerHTML = '<i class="fas fa-trash"></i> Remove'; }
        }
    });

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

    // Load plan usage when the account tab is opened
    document.querySelector('[data-tab="account-settings"]')?.addEventListener('click', () => {
        uiManager.loadPlanUsage();
        uiManager.loadSettings(); // re-populate account fields + lock state
    });

    // ── Send Email Modal — Send button ──
    document.getElementById('send-email-btn')?.addEventListener('click', () => {
        uiManager._doSendEmail();
    });
});

// ── Change Password (called from inline onclick in index.html) ──
async function handleChangePassword() {
    const currentPw = document.getElementById('current-password')?.value;
    const newPw     = document.getElementById('new-password')?.value;
    const confirmPw = document.getElementById('confirm-password')?.value;
    const msgEl     = document.getElementById('password-change-msg');
    const btn       = document.getElementById('change-password-btn');

    function showMsg(text, ok) {
        if (!msgEl) { uiManager.showToast(ok ? 'success' : 'error', ok ? 'Done' : 'Error', text); return; }
        msgEl.style.display = 'block';
        msgEl.style.background = ok ? 'rgba(16,185,129,0.1)' : 'rgba(239,68,68,0.08)';
        msgEl.style.border     = ok ? '1px solid rgba(16,185,129,0.2)' : '1px solid rgba(239,68,68,0.15)';
        msgEl.style.color      = ok ? 'var(--success)' : 'var(--danger)';
        msgEl.innerHTML = `<i class="fas ${ok ? 'fa-check-circle' : 'fa-exclamation-circle'}" style="margin-right:6px;"></i>${text}`;
        if (ok) setTimeout(() => { msgEl.style.display = 'none'; }, 4000);
    }

    if (!currentPw || !newPw || !confirmPw) { showMsg('All password fields are required.', false); return; }
    if (newPw !== confirmPw)                { showMsg('New passwords do not match.', false); return; }
    if (newPw.length < 8)                  { showMsg('Password must be at least 8 characters.', false); return; }
    if (!/[A-Z]/.test(newPw))              { showMsg('Password must include an uppercase letter.', false); return; }
    if (!/[0-9]/.test(newPw))              { showMsg('Password must include a number.', false); return; }

    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Changing...'; }
    try {
        const result = await authManager.changePassword(currentPw, newPw);
        if (!result.success) throw new Error(result.error || 'Password change failed');
        showMsg('Password changed successfully!', true);
        // Clear fields
        ['current-password','new-password','confirm-password'].forEach(id => {
            const el = document.getElementById(id); if (el) el.value = '';
        });
    } catch (err) {
        showMsg(err.message, false);
    } finally {
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-key"></i> Change Password'; }
    }
}