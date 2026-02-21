/**
 * API Client — handles all HTTP communication with the backend
 */
class ApiClient {
    constructor() {
        this.baseUrl = typeof API_BASE_URL !== 'undefined' ? API_BASE_URL : '/invoice-management/backend/api';
        this.token = localStorage.getItem('auth_token') || '';
    }

    async request(endpoint, method = 'GET', data = null) {
        const url = `${this.baseUrl}/index.php?route=${endpoint}`;

        const options = {
            method,
            headers: {
                'Content-Type': 'application/json',
                'Authorization': this.token ? `Bearer ${this.token}` : '',
                'X-CSRF-Token': this.getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        if (data && (method === 'POST' || method === 'PUT' || method === 'PATCH')) {
            options.body = JSON.stringify(data);
        }

        try {
            const response = await fetch(url, options);

            if (response.status === 401) {
                console.warn('Unauthorized — returning empty response');
                return this.getEmptyResponse(endpoint);
            }

            if (response.status === 404) {
                console.warn('Endpoint not found — returning empty response');
                return this.getEmptyResponse(endpoint);
            }

            if (!response.ok) {
                let errorData;
                try {
                    errorData = await response.json();
                } catch {
                    errorData = { message: `Server error (${response.status})` };
                }
                // Structured errors (e.g. LIMIT_REACHED) — return the body so callers can inspect error_code
                if (errorData?.error_code) return errorData;
                throw new Error(errorData?.message || `API Error ${response.status}`);
            }

            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return await response.json();
            }
            return { success: true };
        } catch (error) {
            if (error.message.includes('Failed to fetch') || error.message.includes('NetworkError')) {
                console.warn('Network error — returning empty response');
                return this.getEmptyResponse(endpoint);
            }
            throw error;
        }
    }

    getCsrfToken() {
        return localStorage.getItem('csrf_token') || '';
    }

    getEmptyResponse(endpoint) {
        const route = endpoint.split('&')[0]; // strip query params
        const emptyMap = {
            'client.list': { success: true, data: { clients: [] } },
            'invoice.list': { success: true, data: { invoices: [] } },
            'payment.list': { success: true, data: { payments: [] } },
            'recurring.list': { success: true, data: { recurring_invoices: [] } },
            'expense.list': { success: true, data: { expenses: [] } },
            'expense.categories': { success: true, data: { categories: [] } },
            'expense.summary': { success: true, data: { summary: { total_count: 0, total_amount: 0, billable_amount: 0, unbilled_amount: 0 }, by_category: [] } },
            'dashboard.stats': {
                success: true,
                data: {
                    total_invoices: 0, total_revenue: 0, pending_amount: 0,
                    total_clients: 0, recent_invoices: [],
                    status_counts: { draft: 0, sent: 0, partial: 0, paid: 0, overdue: 0 }
                }
            },
            'settings.get': {
                success: true,
                data: {
                    business_name: '', address: '', gst_number: '',
                    default_tax: 18, invoice_prefix: 'INV',
                    number_format: 'YYYY-MM-NNNN', payment_terms: ''
                }
            }
        };

        return emptyMap[route] || { success: true, data: {} };
    }

    // ── Dashboard ──
    async getDashboardStats(period = '30d') {
        return await this.request(`dashboard.stats&period=${period}`);
    }

    async getReportStats(period = '30d') {
        return await this.request(`dashboard.stats&period=${period}`);
    }

    // ── Clients ──
    async getClients() {
        return await this.request('client.list');
    }

    async getClient(id) {
        return await this.request(`client.get&id=${id}`);
    }

    async createClient(data) {
        return await this.request('client.create', 'POST', data);
    }

    async updateClient(id, data) {
        return await this.request(`client.update&id=${id}`, 'PUT', data);
    }

    async deleteClient(id) {
        return await this.request(`client.delete&id=${id}`, 'DELETE');
    }

    // ── Invoices ──
    async getInvoices() {
        return await this.request('invoice.list');
    }

    async getInvoice(id) {
        return await this.request(`invoice.get&id=${id}`);
    }

    async createInvoice(data) {
        return await this.request('invoice.create', 'POST', data);
    }

    async updateInvoice(id, data) {
        return await this.request(`invoice.update&id=${id}`, 'PUT', data);
    }

    async deleteInvoice(id) {
        return await this.request(`invoice.delete&id=${id}`, 'DELETE');
    }

    async duplicateInvoice(id) {
        return await this.request(`invoice.duplicate&id=${id}`, 'POST');
    }

    // ── Payments ──
    async getPayments() {
        return await this.request('payment.list');
    }

    async recordPayment(data) {
        return await this.request('payment.create', 'POST', data);
    }

    // ── Settings ──
    async getSettings() {
        return await this.request('settings.get');
    }

    async updateSettings(data) {
        return await this.request('settings.update', 'POST', data);
    }

    async getEmailSettings() {
        return await this.request('email.settings.get');
    }

    async updateEmailSettings(data) {
        return await this.request('email.settings.update', 'POST', data);
    }

    async testSmtpConnection(data) {
        return await this.request('email.settings.test', 'POST', data);
    }

    async sendInvoiceEmail(data) {
        return await this.request('invoice.email.send', 'POST', data);
    }

    // ── Auth ──
    async login(email, password) {
        return await this.request('auth.login', 'POST', { email, password });
    }

    async logout() {
        return await this.request('auth.logout', 'POST');
    }

    // ── Version / Plan ──
    async getPlanLimits() {
        return await this.request('version.limits');
    }

    async setPlan(plan) {
        return await this.request('version.plan.set', 'POST', { plan });
    }

    // ── Razorpay ──
    async createRazorpayOrder(plan, extraClients = 0, extraInvoices = 0) {
        return await this.request('razorpay.order.create', 'POST', {
            plan, extra_clients: extraClients, extra_invoices: extraInvoices
        });
    }

    async verifyRazorpayPayment(orderId, paymentId, signature) {
        return await this.request('razorpay.payment.verify', 'POST', {
            razorpay_order_id: orderId,
            razorpay_payment_id: paymentId,
            razorpay_signature: signature
        });
    }

    async getRazorpayPricing() {
        return await this.request('razorpay.pricing');
    }

    // ── Recurring Invoices ──
    async getRecurringInvoices() {
        return await this.request('recurring.list');
    }

    async getRecurringInvoice(id) {
        return await this.request(`recurring.get&id=${id}`);
    }

    async createRecurringInvoice(data) {
        return await this.request('recurring.create', 'POST', data);
    }

    async updateRecurringInvoice(id, data) {
        return await this.request(`recurring.update&id=${id}`, 'PUT', data);
    }

    async pauseRecurringInvoice(id) {
        return await this.request(`recurring.pause&id=${id}`, 'POST');
    }

    async resumeRecurringInvoice(id) {
        return await this.request(`recurring.resume&id=${id}`, 'POST');
    }

    async deleteRecurringInvoice(id) {
        return await this.request(`recurring.delete&id=${id}`, 'DELETE');
    }

    async generateRecurringInvoice(id) {
        return await this.request(`recurring.generate&id=${id}`, 'POST');
    }

    // ── Expenses ──
    async getExpenses(filters = {}) {
        let query = 'expense.list';
        const params = [];
        if (filters.category) params.push(`category=${encodeURIComponent(filters.category)}`);
        if (filters.client_id) params.push(`client_id=${filters.client_id}`);
        if (filters.date_from) params.push(`date_from=${filters.date_from}`);
        if (filters.date_to) params.push(`date_to=${filters.date_to}`);
        if (filters.is_billable) params.push('is_billable=1');
        if (filters.unbilled) params.push('unbilled=1');
        if (params.length) query += '&' + params.join('&');
        return await this.request(query);
    }

    async getExpense(id) {
        return await this.request(`expense.get&id=${id}`);
    }

    async createExpense(data) {
        return await this.request('expense.create', 'POST', data);
    }

    async updateExpense(id, data) {
        return await this.request(`expense.update&id=${id}`, 'PUT', data);
    }

    async deleteExpense(id) {
        return await this.request(`expense.delete&id=${id}`, 'DELETE');
    }

    async getExpenseSummary(dateFrom = null, dateTo = null) {
        let query = 'expense.summary';
        const params = [];
        if (dateFrom) params.push(`date_from=${dateFrom}`);
        if (dateTo) params.push(`date_to=${dateTo}`);
        if (params.length) query += '&' + params.join('&');
        return await this.request(query);
    }

    async getExpenseCategories() {
        return await this.request('expense.categories');
    }

    async createExpenseCategory(name, color = '#6366f1') {
        return await this.request('expense.category.create', 'POST', { name, color });
    }

    async deleteExpenseCategory(id) {
        return await this.request(`expense.category.delete&id=${id}`, 'DELETE');
    }

    // ── Public Invoice Link ──
    async generatePublicLink(invoiceId) {
        return await this.request(`public.invoice.token.generate&id=${invoiceId}`, 'POST');
    }

    async revokePublicLink(invoiceId) {
        return await this.request(`public.invoice.token.revoke&id=${invoiceId}`, 'DELETE');
    }
}

// Global API instance
window.api = new ApiClient();