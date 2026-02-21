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
        return await this.request('settings.update', 'PUT', data);
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
}

// Global API instance
window.api = new ApiClient();