/**
 * API Service - Frontend API Client
 * 
 * Handles all communication with backend APIs
 */

// ========================================================================
// Decimal Precision Utilities
// ========================================================================

/**
 * Safely multiply two numbers and round to 2 decimal places
 * Prevents floating point precision errors
 */
window.safeMultiply = function(a, b, decimals = 2) {
    const multiplier = Math.pow(10, decimals);
    return Math.round((parseFloat(a || 0) * parseFloat(b || 0)) * multiplier) / multiplier;
};

/**
 * Safely add two numbers with proper rounding to 2 decimal places
 * Prevents floating point accumulation errors
 */
window.safeAdd = function(a, b, decimals = 2) {
    const multiplier = Math.pow(10, decimals);
    return Math.round((parseFloat(a || 0) + parseFloat(b || 0)) * multiplier) / multiplier;
};

/**
 * Calculate line total for BOM items (quantity * unit_cost)
 * Always rounds to 2 decimal places
 */
window.calculateLineTotal = function(quantity, unitCost) {
    return safeMultiply(quantity, unitCost, 2);
};

/**
 * Calculate group or BOM total by summing line items
 * Properly handles decimal precision throughout accumulation
 */
window.calculateTotal = function(items, quantityKey = 'quantity', costKey = 'unit_cost') {
    return items.reduce((sum, item) => {
        const lineTotal = calculateLineTotal(item[quantityKey], item[costKey]);
        return safeAdd(sum, lineTotal, 2);
    }, 0);
};

class APIService {
    constructor(baseURL = '/api') {
        this.baseURL = baseURL;
    }

    /**
     * Generic fetch wrapper with error handling
     */
    async request(endpoint, options = {}) {
        const url = endpoint.startsWith('http') ? endpoint : `${this.baseURL}/${endpoint}`;
        
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
        };

        const finalOptions = {
            ...defaultOptions,
            ...options,
            headers: {
                ...defaultOptions.headers,
                ...options.headers,
            },
        };

        try {
            const response = await fetch(url, finalOptions);
            
            // Log the response for debugging
            const responseText = await response.text();
            
            // Try to parse as JSON
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                // If JSON parsing fails, log the raw response
                console.error('Failed to parse JSON response:', responseText.substring(0, 200));
                throw new Error('Server returned invalid JSON: ' + responseText.substring(0, 100));
            }

            if (!response.ok || data.success === false) {
                // Attach status code to the error for better handling
                const error = new Error(data.error || 'Request failed');
                error.status = response.status;
                error.data = data;
                throw error;
            }

            return data;
        } catch (error) {
            // Only log unexpected errors (not 4xx client errors or business rule errors with 200)
            if (!error.status || (error.status >= 500)) {
                console.error('API Error:', error);
            }
            throw error;
        }
    }
    
    /**
     * Generic GET request
     */
    async get(endpoint) {
        return this.request(endpoint, { method: 'GET' });
    }
    
    /**
     * Generic POST request
     */
    async post(endpoint, data) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }
    
    /**
     * Generic PUT request
     */
    async put(endpoint, data) {
        return this.request(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    }
    
    /**
     * Generic DELETE request
     */
    async delete(endpoint) {
        return this.request(endpoint, { method: 'DELETE' });
    }

    // ========================================================================
    // BOMs API
    // ========================================================================

    async listBOMs(filters = {}) {
        const params = new URLSearchParams(filters).toString();
        const endpoint = params ? `boms.php?${params}` : 'boms.php';
        return this.request(endpoint);
    }

    async getBOM(id) {
        return this.request(`boms.php?id=${id}`);
    }

    async createBOM(data) {
        return this.request('boms.php', {
            method: 'POST',
            body: JSON.stringify(data),
        });
    }

    async createBOMVariant(data) {
        return this.request('boms.php?action=create_variant', {
            method: 'POST',
            body: JSON.stringify(data),
        });
    }

    async updateBOM(id, data) {
        // Backend expects 'id' in the request body
        return this.request(`boms.php`, {
            method: 'PUT',
            body: JSON.stringify({ ...data, id }),
        });
    }

    async deleteBOM(id) {
        return this.request(`boms.php?id=${id}`, {
            method: 'DELETE',
        });
    }

    async getMatrixData(scope, id) {
        return this.request(`boms.php?action=matrix&scope=${scope}&id=${id}`);
    }

    // ========================================================================
    // Projects API
    // ========================================================================

    async listProjects(filters = {}) {
        const params = new URLSearchParams(filters).toString();
        const endpoint = params ? `projects.php?${params}` : 'projects.php';
        return this.request(endpoint);
    }

    async listProjectsWithOptionals(filters = {}) {
        const params = new URLSearchParams({ ...filters, include_optionals: '1' }).toString();
        return this.request(`projects.php?${params}`);
    }

    async getProject(id) {
        return this.request(`projects.php?id=${id}`);
    }

    async getProjectWithOptionals(id) {
        return this.request(`projects.php?id=${id}&include_optionals=1`);
    }

    async listAllOptionals(filters = {}) {
        const params = new URLSearchParams({ ...filters, action: 'list_all_optionals' }).toString();
        return this.request(`projects.php?${params}`);
    }

    async linkProjectOptional(baseProjectId, optionalProjectId, data = {}) {
        const payload = {
            base_project_id: baseProjectId,
            optional_project_id: optionalProjectId,
            ...data,
        };
        return this.request('projects.php?action=link_optional', {
            method: 'POST',
            body: JSON.stringify(payload),
        });
    }

    async unlinkProjectOptional(baseProjectId, optionalProjectId) {
        const payload = {
            base_project_id: baseProjectId,
            optional_project_id: optionalProjectId,
        };
        return this.request('projects.php?action=unlink_optional', {
            method: 'POST',
            body: JSON.stringify(payload),
        });
    }

    async createProject(data) {
        return this.request('projects.php', {
            method: 'POST',
            body: JSON.stringify(data),
        });
    }

    async updateProject(data) {
        return this.request('projects.php', {
            method: 'PUT',
            body: JSON.stringify(data),
        });
    }

    async deleteProject(id) {
        return this.request(`projects.php?id=${id}`, {
            method: 'DELETE',
        });
    }

    // ========================================================================
    // Assemblies API
    // ========================================================================

    async listAssemblies(filters = {}) {
        const params = new URLSearchParams(filters).toString();
        const endpoint = params ? `assemblies.php?${params}` : 'assemblies.php';
        return this.request(endpoint);
    }

    async getAssembly(id) {
        return this.request(`assemblies.php?id=${id}`);
    }

    async createAssembly(data) {
        return this.request('assemblies.php', {
            method: 'POST',
            body: JSON.stringify(data),
        });
    }

    async updateAssembly(data) {
        return this.request('assemblies.php', {
            method: 'PUT',
            body: JSON.stringify(data),
        });
    }

    async deleteAssembly(id) {
        return this.request(`assemblies.php?id=${id}`, {
            method: 'DELETE',
        });
    }

    // ========================================================================
    // Components API
    // ========================================================================

    async listComponents(filters = {}) {
        const params = new URLSearchParams(filters).toString();
        const endpoint = params ? `components.php?${params}` : 'components.php';
        return this.request(endpoint);
    }

    async getComponent(id) {
        return this.request(`components.php?id=${id}`);
    }

    async createComponent(data) {
        return this.request('components.php', {
            method: 'POST',
            body: JSON.stringify(data),
        });
    }

    async updateComponent(data) {
        return this.request('components.php', {
            method: 'PUT',
            body: JSON.stringify(data),
        });
    }

    async deleteComponent(id) {
        return this.request(`components.php?id=${id}`, {
            method: 'DELETE',
        });
    }

    // ========================================================================
    // Audit Logs API
    // ========================================================================

    async listAuditLogs(filters = {}) {
        const params = new URLSearchParams(filters).toString();
        const endpoint = params ? `audit.php?${params}` : 'audit.php';
        return this.request(endpoint);
    }

    // ========================================================================
    // Component Groups API
    // ========================================================================

    async listComponentGroups(includeInactive = false) {
        const params = includeInactive ? '?include_inactive=true' : '';
        return this.request(`component-groups.php${params}`);
    }

    async getComponentGroup(id) {
        return this.request(`component-groups.php?id=${id}`);
    }

    async createComponentGroup(data) {
        return this.request('component-groups.php', {
            method: 'POST',
            body: JSON.stringify(data),
        });
    }

    async updateComponentGroup(data) {
        return this.request('component-groups.php', {
            method: 'PUT',
            body: JSON.stringify(data),
        });
    }

    async deleteComponentGroup(id) {
        return this.request(`component-groups.php?id=${id}`, {
            method: 'DELETE',
        });
    }
}

// Create global API instance
const API = new APIService();

// Make available globally for all scripts
if (typeof window !== 'undefined') {
    window.API = API;
    window.APIService = APIService;
}

// Export for ES6 modules (if loaded as module)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { API, APIService };
}
