/**
 * AjaxAdapter - Simple fetch-based AJAX helper
 * Used by laramina admin framework for API calls
 */

const AjaxAdapter = {

    /**
     * Get CSRF token from meta tag
     */
    csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    },

    /**
     * Build headers for requests
     */
    headers(extra = {}) {
        return {
            'X-CSRF-TOKEN': this.csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            ...extra
        };
    },

    /**
     * GET request
     */
    async get(url, options = {}) {
        const response = await fetch(url, {
            method: 'GET',
            headers: this.headers(),
            ...options
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        return response.json();
    },

    /**
     * POST request
     */
    async post(url, data = {}, options = {}) {
        let body;
        let headers = this.headers();

        if (data instanceof FormData) {
            body = data;
            // Don't set Content-Type for FormData - browser sets it with boundary
        } else if (typeof data === 'object') {
            body = JSON.stringify(data);
            headers['Content-Type'] = 'application/json';
        } else {
            body = data;
        }

        const response = await fetch(url, {
            method: 'POST',
            headers,
            body,
            ...options
        });

        if (!response.ok) {
            const error = await response.json().catch(() => ({}));
            const err = new Error(error.message || `HTTP ${response.status}`);
            err.status = response.status;
            err.responseJSON = error;
            throw err;
        }

        return response.json();
    },

    /**
     * PUT request
     */
    async put(url, data = {}, options = {}) {
        let body;
        let headers = this.headers();

        if (data instanceof FormData) {
            // Laravel supports _method for PUT
            data.append('_method', 'PUT');
            body = data;
        } else {
            body = JSON.stringify(data);
            headers['Content-Type'] = 'application/json';
        }

        const response = await fetch(url, {
            method: 'POST', // Use POST with _method for Laravel
            headers,
            body,
            ...options
        });

        if (!response.ok) {
            const error = await response.json().catch(() => ({}));
            const err = new Error(error.message || `HTTP ${response.status}`);
            err.status = response.status;
            err.responseJSON = error;
            throw err;
        }

        return response.json();
    },

    /**
     * DELETE request
     */
    async delete(url, options = {}) {
        const response = await fetch(url, {
            method: 'POST', // Use POST with _method for Laravel
            headers: this.headers(),
            body: JSON.stringify({ _method: 'DELETE' }),
            ...options
        });

        if (!response.ok) {
            const error = await response.json().catch(() => ({}));
            const err = new Error(error.message || `HTTP ${response.status}`);
            err.status = response.status;
            err.responseJSON = error;
            throw err;
        }

        return response.json();
    }
};

export default AjaxAdapter;
