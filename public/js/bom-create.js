/**
 * BOM Creation Application
 * Interactive BOM creation with drag & drop, autosave, and validation
 */

// API is loaded globally from api.js script tag
const { API } = window;

class BOMCreateApp {
    constructor(bomId = null, bomData = null, projectId = null) {
        // Check for edit data from window (for dynamic import)
        if (!bomId && !projectId && window.__bomEditData) {
            bomId = window.__bomEditData.bomId;
            bomData = window.__bomEditData.bomData;
            projectId = window.__bomEditData.projectId;
            // Don't delete here - let router clean up after instantiation
        }
        
        this.isEditMode = !!bomId;
        this.bomId = bomId;
        this.bomData = bomData;
        this.preSelectedProjectId = projectId;
        
        this.state = {
            projects: [],
            componentGroups: [],
            components: [],
            selectedProject: null,
            groups: [this.createEmptyGroup()],
            formData: {
                sku: '',
                name: '',
                description: '',
                notes: ''
            },
            isDirty: false,
            autosaveTimer: null,
            isSaving: false,
            erpSearchPerformed: false // Track if we've done a full ERP search
        };
        
        // Store original state for edit mode change detection
        this.originalState = null;
        
        this.draggedItem = null;
        this.groupSelectorMenu = null;
        
        this.init();
    }
    
    /**
     * Helper to log errors only when they are unexpected (non-4xx)
     */
    logError(context, error) {
        if (!error.status || error.status >= 500) {
            console.error(`${context}:`, error);
        } else {
            console.warn(`${context} (Expected):`, error.message);
        }
    }
    
    async init() {
        try {
            // Check if returning from group management
            const fromGroupManagement = localStorage.getItem('bomCreationContext') === 'true';
                
            // Load all required data first
            await this.loadData();
                
            // Try to restore draft if coming back from groups management
            if (fromGroupManagement) {
                this.restoreBOMDraftFromGroupManagement();
                // Clear the context flag after restoration
                localStorage.removeItem('bomCreationContext');
            }
            // Populate form with existing BOM data if in edit mode
            else if (this.isEditMode && this.bomData) {
                this.populateFromBOM(this.bomData);
            }
            // Pre-select project if provided
            else if (this.preSelectedProjectId) {
                this.state.selectedProject = this.state.projects.find(p => p.id == this.preSelectedProjectId);
            }
                
            // Setup event listeners before rendering
            this.setupEventListeners();
                
            // Render the UI
            this.render();
                
            // Don't load autosave in edit mode or when restoring from groups
            if (!this.isEditMode && !fromGroupManagement) {
                // Small delay to ensure DOM is fully rendered before autosave check
                setTimeout(() => this.loadAutosave(), 50);
            }
                
            this.hideLoading();
        } catch (error) {
            this.logError('Initialization error', error);
            this.showError('Failed to initialize BOM creation interface');
        }
    }
    
    async loadData() {
        const [projects, componentGroups, components] = await Promise.all([
            API.get('projects.php'),
            API.get('component-groups.php'),
            API.get('components.php?source=bommer') // Load only Bommer components initially
        ]);
        
        this.state.projects = projects.data || [];
        this.state.componentGroups = componentGroups.data || [];
        this.state.components = components.data || [];
        
        // If in edit mode, check if BOM contains ERP components and load them
        if (this.isEditMode && this.bomData && this.bomData.groups) {
            const hasErpComponents = this.bomData.groups.some(group => 
                group.items && group.items.some(item => item.component_source === 'erp')
            );
            
            if (hasErpComponents) {
                try {
                    // Get unique ERP component IDs from the BOM
                    const erpComponentIds = new Set();
                    this.bomData.groups.forEach(group => {
                        if (group.items) {
                            group.items.forEach(item => {
                                if (item.component_source === 'erp') {
                                    erpComponentIds.add(item.component_id);
                                }
                            });
                        }
                    });
                    
                    // Fetch each ERP component by ID
                    const erpComponentPromises = Array.from(erpComponentIds).map(id => 
                        API.get(`components.php?id=${id}&source=erp`).catch(err => {
                            console.warn(`Failed to load ERP component ${id}:`, err);
                            return null;
                        })
                    );
                    
                    const erpResults = await Promise.all(erpComponentPromises);
                    const erpComponents = erpResults
                        .filter(result => result && result.success && result.data)
                        .map(result => result.data);
                    
                    // Merge ERP components into state
                    this.state.components = [...this.state.components, ...erpComponents];
                } catch (error) {
                    console.error('Error loading ERP components for BOM:', error);
                }
            }
        }
    }
    
    populateFromBOM(bomData) {
        // Populate form data
        this.state.formData.sku = bomData.sku || '';
        this.state.formData.name = bomData.name || '';
        this.state.formData.description = bomData.description || '';
        this.state.formData.notes = bomData.revision_notes || '';
        
        // Find and set selected project
        if (bomData.project_id) {
            this.state.selectedProject = this.state.projects.find(p => p.id === bomData.project_id);
        }
        
        // Populate groups and items
        if (bomData.groups && bomData.groups.length > 0) {
            this.state.groups = bomData.groups.map(group => {
                // Find matching template by name
                const template = this.state.componentGroups.find(t => t.name === group.name);
                
                return {
                    id: 'group_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9),
                    name: group.name,
                    group_template_id: template ? template.id : null,
                    items: (group.items || []).map(item => ({
                        component_id: item.component_id,
                        component_source: item.component_source || 'bommer',
                        quantity: parseFloat(item.quantity) || 1,
                        reference_designator: item.reference_designator || null,
                        notes: item.notes || null
                    }))
                };
            });
        }
        
        // Store original state snapshot for change detection in edit mode
        this.originalState = this.captureStateSnapshot();
    }
    
    createEmptyGroup() {
        return {
            id: 'group_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9),
            name: '',
            group_template_id: null, // Track by component_groups.id instead of name
            items: []
        };
    }
    
    render() {
        const app = document.getElementById('bom-create-app');
        if (!app) {
            console.warn('BOM create app container not found, skipping render');
            return;
        }
        
        app.innerHTML = `
            ${this.renderHeader()}
            ${this.renderContent()}
        `;
        
        this.attachEventListeners();
    }
    
    renderHeader() {
        const title = this.isEditMode ? 'Edit BOM' : 'Create New BOM';
        const subtitle = this.isEditMode ? 'Modify an existing bill of materials' : 'Build a new bill of materials';
        
        return `
            <header class="bom-header">
                <div class="bom-header-title">
                    <div class="bom-header-icon">
                        <clr-icon shape="organization"></clr-icon>
                    </div>
                    <div class="bom-header-text">
                        <h1>${title}</h1>
                        <p>${subtitle}</p>
                    </div>
                </div>
                <div class="bom-header-actions">
                    <button class="btn btn-secondary" id="manageGroupsBtn" onclick="window.navigateToGroups(); return false;">
                        <clr-icon shape="wrench"></clr-icon>
                        <span>Manage Groups</span>
                    </button>
                    <button class="btn btn-secondary" id="cancelBtn">
                        <clr-icon shape="times"></clr-icon>
                        <span>Cancel</span>
                    </button>
                    <button class="btn btn-primary" id="saveBomBtn" ${this.canSave() ? '' : 'disabled'}>
                        <clr-icon shape="floppy"></clr-icon>
                        <span>Save BOM</span>
                    </button>
                </div>
            </header>
        `;
    }
    
    renderContent() {
        return `
            <div class="bom-content">
                <main class="bom-main">
                    ${this.renderFormSection()}
                    ${this.renderGroupsSection()}
                </main>
                ${this.renderComponentLibrary()}
            </div>
        `;
    }
    
    renderFormSection() {
        return `
            <div class="bom-form-section">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="projectSelect" class="required">Project</label>
                        <input 
                            type="text" 
                            id="projectSearch" 
                            class="form-control" 
                            placeholder="Type to search (min 3 chars)..."
                            value="${this.state.selectedProject ? this.state.selectedProject.name : ''}"
                        >
                        <div id="projectDropdown" class="hidden"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="skuInput" class="required">SKU</label>
                        <div class="sku-field-group">
                            <input 
                                type="text" 
                                id="skuInput" 
                                class="form-control" 
                                placeholder="BOM-XXX-001"
                                value="${this.state.formData.sku}"
                                ${this.isEditMode ? 'disabled title="SKU cannot be changed"' : (this.state.selectedProject ? '' : 'disabled')}
                            >
                            <button 
                                class="btn-suggest" 
                                id="suggestSkuBtn" 
                                title="Suggest SKU based on project"
                                ${this.isEditMode || !this.state.selectedProject ? 'disabled' : ''}
                            >
                                <clr-icon shape="lightbulb"></clr-icon>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="nameInput" class="required">BOM Name</label>
                        <input 
                            type="text" 
                            id="nameInput" 
                            class="form-control" 
                            placeholder="Enter BOM name"
                            value="${this.state.formData.name}"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="descriptionInput">Description</label>
                        <textarea 
                            id="descriptionInput" 
                            class="form-control" 
                            placeholder="Optional description"
                        >${this.state.formData.description}</textarea>
                    </div>
                    
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="notesInput" class="required">Reason for Creation</label>
                        <textarea 
                            id="notesInput" 
                            class="form-control" 
                            placeholder="Why is this BOM being created? (Required)"
                        >${this.state.formData.notes}</textarea>
                    </div>
                </div>
            </div>
        `;
    }
    
    renderGroupsSection() {
        return `
            <div class="bom-groups-container">
                ${this.state.groups.map((group, index) => this.renderGroup(group, index)).join('')}
                <button class="add-group-btn" id="addGroupBtn">
                    <clr-icon shape="plus-circle"></clr-icon>
                    <span>Add Group</span>
                </button>
            </div>
        `;
    }
    
    renderGroup(group, index) {
        const selectedTemplate = this.state.componentGroups.find(g => g.id === group.group_template_id);
        const icon = selectedTemplate ? selectedTemplate.icon : 'badge';
        
        // Get list of already used group template IDs (excluding current group)
        const usedGroupIds = this.state.groups
            .filter((g, idx) => idx !== index && g.group_template_id)
            .map(g => g.group_template_id);
        
        // Filter available groups to exclude already used ones
        const availableGroups = this.state.componentGroups.filter(tpl => 
            !usedGroupIds.includes(tpl.id)
        );
        
        // Calculate group total with proper decimal precision
        const groupTotal = group.items.reduce((sum, item) => {
            const component = this.state.components.find(c => c.id === item.component_id);
            if (!component) return sum;
            const unitCost = parseFloat(component.unit_cost) || 0;
            const quantity = parseFloat(item.quantity) || 1;
            // Round each line item to 2 decimal places before summing to avoid floating point drift
            const lineTotal = Math.round((quantity * unitCost) * 100) / 100;
            return Math.round((sum + lineTotal) * 100) / 100;
        }, 0);
        
        return `
            <div class="bom-group" data-group-id="${group.id}">
                <div class="bom-group-header">
                    <div class="bom-group-title">
                        <clr-icon shape="${icon}"></clr-icon>
                        <select class="form-control" data-group-index="${index}" style="width: auto; padding: 0.25rem 0.5rem;">
                            <option value="">Select group...</option>
                            ${availableGroups.map(tpl => 
                                `<option value="${tpl.id}" ${group.group_template_id === tpl.id ? 'selected' : ''}>${tpl.name}</option>`
                            ).join('')}
                        </select>
                        <span class="bom-group-count">${group.items.length} items</span>
                        <span class="bom-group-total">¥${groupTotal.toFixed(3)}</span>
                    </div>
                    <div class="bom-group-actions">
                        <button class="btn-icon danger" data-action="remove-group" data-group-id="${group.id}" title="Remove group">
                            <clr-icon shape="trash"></clr-icon>
                        </button>
                    </div>
                </div>
                
                ${this.renderGroupItems(group)}
            </div>
        `;
    }
    
    renderGroupItems(group) {
        const hasItems = group.items && group.items.length > 0;
        return `
            <table class="bom-items-table">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>Component</th>
                        <th>Description</th>
                        <th>Supplier</th>
                        <th class="text-right" style="width: 200px;">Unit Cost</th>
                        <th class="text-center" style="width: 160px;">Qty</th>
                        <th class="text-right" style="width: 200px;">Total</th>
                        <th style="width: 40px;"></th>
                    </tr>
                </thead>
                <tbody>
                    ${hasItems
                        ? group.items.map((item, idx) => this.renderGroupItem(item, idx, group.id)).join('')
                        : `
                            <tr class="empty-group-row">
                                <td colspan="8">
                                    ${this.renderEmptyGroup()}
                                </td>
                            </tr>
                        `
                    }
                    ${this.renderQuickAddRow(group.id)}
                </tbody>
            </table>
        `;
    }
    
    renderQuickAddRow(groupId) {
        return `
            <tr class="quick-add-row" data-group-id="${groupId}">
                <td></td>
                <td>
                    <div class="quick-add-component-fields">
                        <input
                            type="text"
                            class="form-control quick-part-number-input"
                            placeholder="New part number"
                            data-group-id="${groupId}"
                        >
                        <input
                            type="text"
                            class="form-control quick-name-input"
                            placeholder="New component name"
                            data-group-id="${groupId}"
                        >
                    </div>
                </td>
                <td>
                    <input
                        type="text"
                        class="form-control quick-description-input"
                        placeholder="Description (optional)"
                        data-group-id="${groupId}"
                    >
                </td>
                <td>
                    <input
                        type="text"
                        class="form-control quick-supplier-input"
                        placeholder="Supplier (optional)"
                        data-group-id="${groupId}"
                    >
                </td>
                <td class="text-right">
                    <input
                        type="number"
                        class="form-control quick-unit-cost-input"
                        placeholder="0.0000"
                        step="0.0001"
                        min="0"
                        data-group-id="${groupId}"
                    >
                </td>
                <td class="text-center">
                    <span class="text-muted">1</span>
                </td>
                <td class="text-right"></td>
                <td>
                    <button
                        class="btn-icon"
                        data-action="quick-add-component"
                        data-group-id="${groupId}"
                        title="Quick add new component to this group"
                    >
                        <clr-icon shape="plus-circle"></clr-icon>
                    </button>
                </td>
            </tr>
        `;
    }
    
    renderGroupItem(item, index, groupId) {
        const component = this.state.components.find(c => c.id === item.component_id && (c.source || 'bommer') === (item.component_source || 'bommer'));
        if (!component) return '';
        
        const unitCost = parseFloat(component.unit_cost) || 0;
        const quantity = parseFloat(item.quantity) || 1;
        // Round to 2 decimal places to avoid floating point precision errors
        const total = Math.round((quantity * unitCost) * 100) / 100;
        
        const source = item.component_source || component.source || 'bommer';
        const sourceClass = source === 'erp' ? 'source-erp' : 'source-bommer';
        const sourceLabel = source === 'erp' ? 'ERP' : 'Bommer';
        const sourceIcon = source === 'erp' ? 'building' : 'storage';
        let sourceTitle = `Source: ${sourceLabel}`;
        if (source === 'erp' && component.erp_sync_status) {
            const sync = component.erp_sync_status;
            const lastSync = component.last_sync_at || 'unknown';
            sourceTitle += ` (Sync: ${sync}, Last: ${lastSync})`;
        }
        
        // Only show edit button for Bommer components
        const canEdit = source === 'bommer';
        
        return `
            <tr>
                <td>
                    <div class="item-drag-handle">
                        <clr-icon shape="drag-handle"></clr-icon>
                    </div>
                </td>
                <td>
                    <div class="item-name">
                        ${component.name}
                        <span class="item-source-badge ${sourceClass}" title="${sourceTitle}">
                            <clr-icon shape="${sourceIcon}"></clr-icon>
                            <span>${sourceLabel}</span>
                        </span>
                        ${canEdit ? `
                            <button 
                                class="btn-icon-inline" 
                                data-action="edit-component-inline" 
                                data-component-id="${component.id}"
                                title="Edit component"
                            >
                                <clr-icon shape="pencil"></clr-icon>
                            </button>
                        ` : ''}
                    </div>
                    <div class="item-mpn">MPN: ${component.mpn || 'N/A'}</div>
                </td>
                <td class="item-description">${component.description || ''}</td>
                <td>
                    <div class="item-supplier">
                        <div class="supplier-dot"></div>
                        <span>${component.supplier || 'N/A'}</span>
                    </div>
                </td>
                <td class="text-right item-cost">¥${unitCost.toFixed(3)}</td>
                <td class="text-center">
                    <input 
                        type="number" 
                        class="item-qty-input" 
                        value="${quantity}" 
                        min="0.0001"
                        step="1"
                        data-group-id="${groupId}"
                        data-item-index="${index}"
                    >
                </td>
                <td class="text-right item-total">¥${total.toFixed(3)}</td>
                <td>
                    <button 
                        class="btn-icon danger" 
                        data-action="remove-item" 
                        data-group-id="${groupId}"
                        data-item-index="${index}"
                        title="Remove component"
                    >
                        <clr-icon shape="trash"></clr-icon>
                    </button>
                </td>
            </tr>
        `;
    }
    
    renderEmptyGroup() {
        return `
            <div class="empty-group">
                <p>No components in this group yet.</p>
                <p>Drag components from the library or click the + button on component cards.</p>
            </div>
        `;
    }
    
    renderComponentLibrary() {
        const rawCategories = [...new Set(this.state.components.map(c => (c.category || '').trim()))];
        const namedCategories = rawCategories.filter(name => !!name);
        const hasUncategorized = this.state.components.some(c => !c.category || !c.category.trim());
        const categories = [
            ...namedCategories,
            ...(hasUncategorized ? ['Uncategorized'] : [])
        ];
        
        const bommerCount = this.state.components.filter(c => c.source === 'bommer').length;
        const erpCount = this.state.components.filter(c => c.source === 'erp').length;
        
        return `
            <aside class="component-library">
                <div class="library-header">
                    <div class="library-title">
                        <h3>Component Library</h3>
                        <small style="color: var(--clr-text-muted); font-size: 0.75rem;">
                            ${bommerCount} Bommer${erpCount > 0 ? ` + ${erpCount} ERP` : ''}
                        </small>
                    </div>
                    <div class="library-search">
                        <clr-icon shape="search"></clr-icon>
                        <input 
                            type="text" 
                            id="componentSearch" 
                            placeholder="Search (includes ERP)..."
                            value="${this.state.componentSearchValue || ''}"
                        >
                    </div>
                </div>
                <div class="library-content">
                    ${categories.map(category => this.renderComponentCategory(category)).join('')}
                </div>
            </aside>
        `;
    }
    
    renderComponentCategory(category) {
        let components;
        if (category === 'Uncategorized') {
            components = this.state.components.filter(c => (!c.category || !c.category.trim()) && c.status === 'active');
        } else {
            components = this.state.components.filter(c => c.category === category && c.status === 'active');
        }
        
        if (components.length === 0) return '';
        
        return `
            <div class="library-category">
                <div class="library-category-title">${category}</div>
                ${components.map(c => this.renderComponentCard(c)).join('')}
            </div>
        `;
    }
    
    renderComponentCard(component) {
        const unitCost = parseFloat(component.unit_cost) || 0;
        const source = component.source || 'bommer';
        const sourceClass = source === 'erp' ? 'component-card-erp' : 'component-card-bommer';
        const borderStyle = source === 'erp' ? 'border-dashed' : '';
        const sourceIcon = source === 'erp' ? 'building' : 'storage';
        const sourceLabel = source === 'erp' ? 'ERP' : 'Bommer';
        
        let syncBadge = '';
        if (source === 'erp') {
            const syncStatus = component.erp_sync_status || 'synced';
            const syncIcon = syncStatus === 'synced' ? 'check-circle' : 'warning-standard';
            const syncClass = syncStatus === 'synced' ? 'sync-ok' : 'sync-warning';
            const lastSync = component.last_sync_at || 'unknown';
            syncBadge = `<div class="erp-sync-badge ${syncClass}" title="Sync: ${syncStatus}, Last: ${lastSync}">
                <clr-icon shape="${syncIcon}"></clr-icon>
            </div>`;
        }
        
        return `
            <div class="component-card ${sourceClass} ${borderStyle}" draggable="true" data-component-id="${component.id}" data-component-source="${source}">
                <div class="component-card-row-1">
                    <div class="component-source-badge">
                        <clr-icon shape="${sourceIcon}"></clr-icon>
                        <span>${sourceLabel}</span>
                    </div>
                    ${syncBadge}
                    <span class="component-price">¥${unitCost.toFixed(3)}</span>
                    <button class="component-add-btn" data-component-id="${component.id}" data-component-source="${source}" title="Add to BOM">
                        <clr-icon shape="plus"></clr-icon>
                    </button>
                </div>
                <div class="component-card-row-2">
                    <div class="component-name" title="${component.name}">${component.name}</div>
                </div>
                <div class="component-card-row-3">
                    <div class="component-desc" title="${component.description || ''}">${component.description || ''}</div>
                </div>
            </div>
        `;
    }
    
    attachEventListeners() {
        // Save button
        const saveBtn = document.getElementById('saveBomBtn');
        if (saveBtn) {
            saveBtn.addEventListener('click', () => this.saveBOM());
        }
        
        // Cancel button
        const cancelBtn = document.getElementById('cancelBtn');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => this.handleCancel());
        }
        
        // Manage groups button
        const manageBtn = document.getElementById('manageGroupsBtn');
        if (manageBtn) {
            manageBtn.addEventListener('click', () => this.showGroupManagementModal());
        }
        
        // Add group button
        const addGroupBtn = document.getElementById('addGroupBtn');
        if (addGroupBtn) {
            addGroupBtn.addEventListener('click', () => this.addGroup());
        }
        
        // Project search
        const projectSearch = document.getElementById('projectSearch');
        if (projectSearch) {
            projectSearch.addEventListener('input', (e) => this.handleProjectSearch(e));
            projectSearch.addEventListener('blur', () => {
                setTimeout(() => {
                    const dropdown = document.getElementById('projectDropdown');
                    if (dropdown) dropdown.classList.add('hidden');
                }, 200);
            });
        }
        
        // SKU suggest button
        const suggestBtn = document.getElementById('suggestSkuBtn');
        if (suggestBtn) {
            suggestBtn.addEventListener('click', () => this.suggestSKU());
        }
        
        // Form inputs
        ['skuInput', 'nameInput', 'descriptionInput', 'notesInput'].forEach(id => {
            const input = document.getElementById(id);
            if (input) {
                input.addEventListener('input', (e) => this.handleFormInput(id, e.target.value));
            }
        });
        
        // Group selectors
        document.querySelectorAll('[data-group-index]').forEach(select => {
            select.addEventListener('change', (e) => {
                const index = parseInt(e.target.dataset.groupIndex);
                const templateId = parseInt(e.target.value) || null;
                
                if (templateId) {
                    // Check if this group template is already used in another group
                    const isDuplicate = this.state.groups.some((g, idx) => 
                        idx !== index && g.group_template_id === templateId
                    );
                    
                    if (isDuplicate) {
                        const template = this.state.componentGroups.find(t => t.id === templateId);
                        this.showError(`Group "${template.name}" is already in this BOM. Each group can only appear once.`);
                        // Reset to previous value
                        e.target.value = this.state.groups[index].group_template_id || '';
                        return;
                    }
                    
                    const template = this.state.componentGroups.find(t => t.id === templateId);
                    this.state.groups[index].group_template_id = templateId;
                    this.state.groups[index].name = template ? template.name : '';
                } else {
                    this.state.groups[index].group_template_id = null;
                    this.state.groups[index].name = '';
                }
                
                this.markDirty();
                this.render();
            });
        });
        
        // Remove group buttons
        document.querySelectorAll('[data-action="remove-group"]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const groupId = e.currentTarget.dataset.groupId;
                this.removeGroup(groupId);
            });
        });
        
        // Remove item buttons
        document.querySelectorAll('[data-action="remove-item"]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const groupId = e.currentTarget.dataset.groupId;
                const itemIndex = parseInt(e.currentTarget.dataset.itemIndex);
                this.removeItem(groupId, itemIndex);
            });
        });
        
        // Quantity inputs
        document.querySelectorAll('.item-qty-input').forEach(input => {
            input.addEventListener('change', (e) => {
                const groupId = e.target.dataset.groupId;
                const itemIndex = parseInt(e.target.dataset.itemIndex);
                const quantity = parseFloat(e.target.value) || 1;
                this.updateItemQuantity(groupId, itemIndex, quantity);
            });
        });
        
        // Component drag & drop
        this.setupDragAndDrop();
        
        // Component add buttons
        document.querySelectorAll('.component-add-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const componentId = parseInt(e.currentTarget.dataset.componentId);
                this.showGroupSelector(e.currentTarget, componentId);
            });
        });
        
        // Component search
        const componentSearch = document.getElementById('componentSearch');
        if (componentSearch) {
            componentSearch.addEventListener('input', (e) => this.filterComponents(e.target.value));
        }
        
        // Quick add component buttons
        document.querySelectorAll('[data-action="quick-add-component"]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const groupId = e.currentTarget.dataset.groupId;
                this.handleQuickAddComponent(groupId);
            });
        });
        
        // Inline component edit buttons
        document.querySelectorAll('[data-action="edit-component-inline"]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const componentId = parseInt(e.currentTarget.dataset.componentId);
                this.handleInlineComponentEdit(componentId);
            });
        });
        
        // Allow Enter key to trigger quick add from inputs
        document.querySelectorAll('.quick-add-row input').forEach(input => {
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const row = e.currentTarget.closest('.quick-add-row');
                    if (!row) return;
                    const groupId = row.dataset.groupId;
                    this.handleQuickAddComponent(groupId);
                }
            });
        });
    }
    
    setupEventListeners() {
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Ctrl+S to save
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                if (this.canSave()) {
                    this.saveBOM();
                }
            }
        });
        
        // Warn before leaving if dirty
        window.addEventListener('beforeunload', (e) => {
            if (this.state.isDirty) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    }
    
    // Continued in next part...
    
    async handleProjectSearch(e) {
        const query = e.target.value.trim();
        const dropdown = document.getElementById('projectDropdown');
        
        console.log('Project search triggered:', query, 'Projects available:', this.state.projects.length);
        
        if (query.length < 3) {
            dropdown.classList.add('hidden');
            return;
        }
        
        const filtered = this.state.projects.filter(p => 
            p.name.toLowerCase().includes(query.toLowerCase()) ||
            p.code.toLowerCase().includes(query.toLowerCase())
        );
        
        console.log('Filtered projects:', filtered.length);
        
        if (filtered.length === 0) {
            dropdown.classList.add('hidden');
            return;
        }
        
        dropdown.innerHTML = filtered.map(p => `
            <div class="dropdown-item" data-project-id="${p.id}">
                <strong>${p.name}</strong>
                <br><small>${p.code}</small>
            </div>
        `).join('');
        
        dropdown.classList.remove('hidden');
        
        // Add click handlers
        dropdown.querySelectorAll('.dropdown-item').forEach(item => {
            item.addEventListener('click', () => {
                const projectId = parseInt(item.dataset.projectId);
                this.selectProject(projectId);
            });
        });
    }
    
    selectProject(projectId) {
        const project = this.state.projects.find(p => p.id === projectId);
        if (!project) return;
        
        this.state.selectedProject = project;
        this.markDirty();
        this.render();
    }
    
    async suggestSKU() {
        if (!this.state.selectedProject) return;
        
        try {
            const response = await API.get(`boms.php?action=suggest_sku&project_id=${this.state.selectedProject.id}`);
            if (response.success && response.data.sku) {
                this.state.formData.sku = response.data.sku;
                document.getElementById('skuInput').value = response.data.sku;
                this.markDirty();
            }
        } catch (error) {
            this.logError('SKU suggestion error', error);
            this.showError('Failed to suggest SKU');
        }
    }
    
    handleFormInput(fieldId, value) {
        const fieldMap = {
            'skuInput': 'sku',
            'nameInput': 'name',
            'descriptionInput': 'description',
            'notesInput': 'notes'
        };
        
        const fieldName = fieldMap[fieldId];
        if (fieldName) {
            this.state.formData[fieldName] = value;
            this.markDirty();
            
            // Update save button state
            const saveBtn = document.getElementById('saveBomBtn');
            if (saveBtn) {
                saveBtn.disabled = !this.canSave();
            }
        }
    }
    
    handleCancel() {
        if (this.state.isDirty) {
            if (confirm('You have unsaved changes. Are you sure you want to cancel?')) {
                this.clearAutosave();
                if (typeof navigateTo === 'function') {
                    navigateTo('boms'); // SPA navigation
                } else {
                    window.location.href = '/app.php#/boms';
                }
            }
        } else {
            if (typeof navigateTo === 'function') {
                navigateTo('boms');
            } else {
                window.location.href = '/app.php#/boms';
            }
        }
    }
    
    addGroup() {
        this.state.groups.push(this.createEmptyGroup());
        this.markDirty();
        this.render();
    }
    
    removeGroup(groupId) {
        if (this.state.groups.length === 1) {
            this.showError('Cannot remove the last group. At least one group is required.');
            return;
        }
        
        if (confirm('Are you sure you want to remove this group and all its components?')) {
            this.state.groups = this.state.groups.filter(g => g.id !== groupId);
            this.markDirty();
            this.render();
        }
    }
    
    removeItem(groupId, itemIndex) {
        const group = this.state.groups.find(g => g.id === groupId);
        if (group) {
            group.items.splice(itemIndex, 1);
            this.markDirty();
            this.render();
        }
    }
    
    updateItemQuantity(groupId, itemIndex, quantity) {
        const group = this.state.groups.find(g => g.id === groupId);
        if (group && group.items[itemIndex]) {
            group.items[itemIndex].quantity = quantity;
            this.markDirty();
            this.render();
        }
    }
    
    setupDragAndDrop() {
        // Component cards drag
        document.querySelectorAll('.component-card').forEach(card => {
            card.addEventListener('dragstart', (e) => {
                const componentId = parseInt(card.dataset.componentId);
                const componentSource = card.dataset.componentSource || 'bommer';
                this.draggedItem = { type: 'component', componentId, componentSource };
                e.dataTransfer.effectAllowed = 'copy';
            });
            
            card.addEventListener('dragend', () => {
                this.draggedItem = null;
                document.querySelectorAll('.bom-group').forEach(g => g.classList.remove('drag-over'));
            });
        });
        
        // Group drop zones
        document.querySelectorAll('.bom-group').forEach(groupEl => {
            groupEl.addEventListener('dragover', (e) => {
                e.preventDefault();
                groupEl.classList.add('drag-over');
            });
            
            groupEl.addEventListener('dragleave', () => {
                groupEl.classList.remove('drag-over');
            });
            
            groupEl.addEventListener('drop', (e) => {
                e.preventDefault();
                groupEl.classList.remove('drag-over');
                
                if (this.draggedItem && this.draggedItem.type === 'component') {
                    const groupId = groupEl.dataset.groupId;
                    this.addComponentToGroup(groupId, this.draggedItem.componentId, this.draggedItem.componentSource || 'bommer');
                }
            });
        });
    }
    
    addComponentToGroup(groupId, componentId, source = 'bommer') {
        const group = this.state.groups.find(g => g.id === groupId);
        if (!group) return;
        
        if (!group.name) {
            this.showError('Please select a group type first.');
            return;
        }
        
        // Check if component already in group (same id + source)
        if (group.items.some(item => item.component_id === componentId && (item.component_source || 'bommer') === source)) {
            this.showError('Component already in this group.');
            return;
        }
        
        // Check if component is banned (Bommer only; ERP status managed externally)
        const component = this.state.components.find(c => c.id === componentId && (c.source || 'bommer') === source);
        if (component && component.status === 'banned') {
            this.showError(`Cannot add banned component: ${component.name}`);
            return;
        }
        
        group.items.push({
            component_id: componentId,
            component_source: source,
            quantity: 1,
            reference_designator: null,
            notes: null
        });
        
        this.markDirty();
        this.render();
    }
    
    async handleQuickAddComponent(groupId) {
        const group = this.state.groups.find(g => g.id === groupId);
        if (!group) {
            return;
        }
        
        if (!group.name) {
            this.showError('Please select a group type first.');
            return;
        }
        
        const row = document.querySelector(`.quick-add-row[data-group-id="${groupId}"]`);
        if (!row) {
            return;
        }
        
        const partNumberInput = row.querySelector('.quick-part-number-input');
        const nameInput = row.querySelector('.quick-name-input');
        const descriptionInput = row.querySelector('.quick-description-input');
        const supplierInput = row.querySelector('.quick-supplier-input');
        const unitCostInput = row.querySelector('.quick-unit-cost-input');
        
        const partNumber = partNumberInput?.value.trim() || '';
        const name = nameInput?.value.trim() || '';
        const description = descriptionInput?.value.trim() || '';
        const supplier = supplierInput?.value.trim() || '';
        const unitCostRaw = unitCostInput?.value.trim() || '';
        
        // If nothing entered at all, do nothing
        if (!partNumber && !name && !description && !supplier && !unitCostRaw) {
            return;
        }
        
        if (!partNumber || !name) {
            this.showError('Part number and name are required to create a component.');
            if (!partNumber && partNumberInput) {
                partNumberInput.focus();
            } else if (!name && nameInput) {
                nameInput.focus();
            }
            return;
        }
        
        const unitCost = unitCostRaw ? parseFloat(unitCostRaw) : 0;
        
        if (Number.isNaN(unitCost)) {
            this.showError('Unit cost must be a valid number.');
            if (unitCostInput) {
                unitCostInput.focus();
            }
            return;
        }
        
        const componentPayload = {
            part_number: partNumber,
            name: name,
            description: description || null,
            category: null,
            manufacturer: null,
            mpn: null,
            supplier: supplier || null,
            unit_cost: unitCost,
            stock_level: 0,
            min_stock: 0,
            lead_time_days: 0,
            status: 'active',
            notes: null
        };
        
        try {
            const response = await API.createComponent(componentPayload);
            const newId = response?.data?.id;
            if (!newId) {
                throw new Error('Component created but no ID returned from server.');
            }
        
            const newComponent = {
                id: parseInt(newId, 10),
                ...componentPayload,
                source: 'bommer',
                last_sync_at: null,
                erp_sync_status: null,
                created_by_name: null
            };
        
            this.state.components.push(newComponent);
        
            // Add to current group (quantity defaults to 1 in addComponentToGroup)
            this.addComponentToGroup(groupId, newComponent.id, 'bommer');
        
            // After re-render, focus back to part number for fast entry
            setTimeout(() => {
                const nextPartInput = document.querySelector(`.quick-part-number-input[data-group-id="${groupId}"]`);
                if (nextPartInput) {
                    nextPartInput.focus();
                }
            }, 0);
        } catch (error) {
            this.logError('Quick component creation error', error);
            const serverMessage = error?.data?.error || error?.message;
            this.showError(serverMessage || 'Failed to create component.');
        }
    }
    
    showGroupSelector(btnElement, componentId) {
        // Remove existing menu
        if (this.groupSelectorMenu) {
            this.groupSelectorMenu.remove();
        }
        
        const rect = btnElement.getBoundingClientRect();
        const menu = document.createElement('div');
        menu.className = 'group-selector-menu';
        menu.style.top = rect.bottom + 5 + 'px';
        menu.style.right = (window.innerWidth - rect.right) + 'px';
        
        let selectedIndex = 0;
        const validGroups = this.state.groups.filter(g => g.name);
        
        if (validGroups.length === 0) {
            menu.innerHTML = '<div class="group-selector-item" style="cursor: default;">No groups available. Please create a group first.</div>';
        } else {
            menu.innerHTML = validGroups.map((group, index) => {
                const icon = this.state.componentGroups.find(t => t.id === group.group_template_id)?.icon || 'badge';
                return `
                    <div class="group-selector-item ${index === 0 ? 'selected' : ''}" data-group-id="${group.id}" data-index="${index}">
                        <clr-icon shape="${icon}"></clr-icon>
                        <span>${group.name}</span>
                    </div>
                `;
            }).join('');
            
            // Add click handlers
            menu.querySelectorAll('.group-selector-item').forEach(item => {
                item.addEventListener('click', () => {
                    const groupId = item.dataset.groupId;
                    this.addComponentToGroup(groupId, componentId, (btnElement.dataset.componentSource || 'bommer'));
                    menu.remove();
                });
                
                item.addEventListener('mouseenter', () => {
                    menu.querySelectorAll('.group-selector-item').forEach(i => i.classList.remove('selected'));
                    item.classList.add('selected');
                    selectedIndex = parseInt(item.dataset.index);
                });
            });
            
            // Keyboard navigation
            const handleKeyDown = (e) => {
                const items = menu.querySelectorAll('.group-selector-item');
                
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    selectedIndex = (selectedIndex + 1) % items.length;
                    items.forEach((item, idx) => {
                        item.classList.toggle('selected', idx === selectedIndex);
                    });
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    selectedIndex = (selectedIndex - 1 + items.length) % items.length;
                    items.forEach((item, idx) => {
                        item.classList.toggle('selected', idx === selectedIndex);
                    });
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    const selectedItem = items[selectedIndex];
                    if (selectedItem) {
                        const groupId = selectedItem.dataset.groupId;
                        this.addComponentToGroup(groupId, componentId);
                        menu.remove();
                        document.removeEventListener('keydown', handleKeyDown);
                    }
                } else if (e.key === 'Escape') {
                    menu.remove();
                    document.removeEventListener('keydown', handleKeyDown);
                }
            };
            
            document.addEventListener('keydown', handleKeyDown);
        }
        
        document.body.appendChild(menu);
        this.groupSelectorMenu = menu;
        
        // Click outside to close
        setTimeout(() => {
            const closeHandler = (e) => {
                if (!menu.contains(e.target)) {
                    menu.remove();
                    document.removeEventListener('click', closeHandler);
                }
            };
            document.addEventListener('click', closeHandler);
        }, 100);
    }
    
    async filterComponents(query) {
        const normalized = query.toLowerCase().trim();
        
        // If search query is 3+ characters and we haven't done a full ERP search yet, fetch them
        if (normalized.length >= 3 && !this.state.erpSearchPerformed) {
            try {
                // Show loading indicator
                const searchInput = document.getElementById('componentSearch');
                if (searchInput) {
                    searchInput.style.opacity = '0.6';
                }
                
                // Fetch ERP components with search filter
                const response = await API.get(`components.php?source=erp&search=${encodeURIComponent(query)}&limit=100`);
                
                if (response.success && response.data) {
                    // Get existing ERP component IDs to avoid duplicates
                    const existingErpIds = new Set(
                        this.state.components
                            .filter(c => c.source === 'erp')
                            .map(c => c.id)
                    );
                    
                    // Filter out duplicates
                    const newErpComponents = response.data.filter(c => !existingErpIds.has(c.id));
                    
                    // Merge new ERP components into state
                    this.state.components = [...this.state.components, ...newErpComponents];
                    
                    // Mark that we've performed an ERP search
                    this.state.erpSearchPerformed = true;
                    
                    // Store the current search value before re-render
                    this.state.componentSearchValue = query;
                    // Re-render to show new components
                    this.render();
                    // Re-apply filter after render
                    setTimeout(() => {
                        const searchInputAfterRender = document.getElementById('componentSearch');
                        if (searchInputAfterRender) {
                            searchInputAfterRender.value = query;
                            searchInputAfterRender.style.opacity = '1';
                            // Restore focus and cursor position
                            searchInputAfterRender.focus();
                            searchInputAfterRender.setSelectionRange(query.length, query.length);
                            // Apply filter to newly rendered components
                            this.applyComponentFilter(normalized);
                        }
                    }, 0);
                    return;
                }
                
                if (searchInput) {
                    searchInput.style.opacity = '1';
                }
            } catch (error) {
                console.error('ERP component search error:', error);
                this.logError('ERP component search error', error);
                // Continue with filtering existing components
            }
        }
        
        this.applyComponentFilter(normalized);
    }
    
    applyComponentFilter(normalized) {
        // Only apply filtering if query is 3+ characters
        const shouldFilter = normalized && normalized.length >= 3;
        
        // Filter component cards
        let visibleCount = 0;
        document.querySelectorAll('.component-card').forEach(card => {
            const nameEl = card.querySelector('.component-name');
            const descEl = card.querySelector('.component-desc');
            
            if (!nameEl) return;
            
            let matches = true; // Default to showing all
            
            if (shouldFilter) {
                // Get component ID and source from card attributes
                const componentId = parseInt(card.dataset.componentId);
                const componentSource = card.dataset.componentSource || 'bommer';
                
                // Find the actual component data
                const component = this.state.components.find(c => 
                    c.id === componentId && (c.source || 'bommer') === componentSource
                );
                
                if (component) {
                    // Search across all component fields
                    const searchFields = [
                        component.name,
                        component.description,
                        component.part_number,
                        component.mpn,
                        component.category,
                        component.manufacturer,
                        component.supplier
                    ].filter(field => field != null);
                    
                    const searchText = searchFields.join(' ').toLowerCase();
                    matches = searchText.includes(normalized);
                } else {
                    // Fallback to DOM text if component not found in state
                    const name = nameEl.textContent.toLowerCase();
                    const desc = descEl ? descEl.textContent.toLowerCase() : '';
                    matches = name.includes(normalized) || desc.includes(normalized);
                }
            }
            
            card.style.display = matches ? 'flex' : 'none';
            if (matches) visibleCount++;
        });
        
        // Hide categories that have no visible components
        document.querySelectorAll('.library-category').forEach(category => {
            const visibleCards = Array.from(category.querySelectorAll('.component-card'))
                .filter(card => card.style.display !== 'none');
            category.style.display = visibleCards.length > 0 ? 'block' : 'none';
        });
    }
    
    scheduleAutosave() {
        if (this.state.autosaveTimer) {
            clearTimeout(this.state.autosaveTimer);
        }
        
        this.state.autosaveTimer = setTimeout(() => {
            this.autosave();
        }, 2000); // Autosave after 2 seconds of inactivity
    }
    
    autosave() {
        // Don't autosave if we're currently saving
        if (this.state.isSaving) {
            return;
        }
        
        try {
            const draftKey = `bom_draft_${window.userId || 'anonymous'}_new`;
            const draftData = {
                formData: this.state.formData,
                selectedProject: this.state.selectedProject,
                groups: this.state.groups,
                bomId: this.bomId || null,
                isEditMode: this.isEditMode || false,
                timestamp: Date.now()
            };
            localStorage.setItem(draftKey, JSON.stringify(draftData));
        } catch (error) {
            this.logError('Autosave error', error);
        }
    }
    
    loadAutosave() {
        try {
            // Verify we're on the correct page before attempting to load autosave
            const container = document.getElementById('bom-create-app');
            if (!container) {
                console.warn('BOM create container not found, skipping autosave load');
                return;
            }
                
            const draftKey = `bom_draft_${window.userId || 'anonymous'}_new`;
            const draftData = localStorage.getItem(draftKey);
                
            if (draftData) {
                const parsed = JSON.parse(draftData);
                const age = Date.now() - parsed.timestamp;
                    
                // Only restore if less than 24 hours old
                if (age < 24 * 60 * 60 * 1000) {
                    // Check if autosave matches current context
                    const draftBomId = parsed.bomId || null;
                    const currentBomId = this.bomId || null;
                        
                    // Only prompt if contexts match
                    if (draftBomId === currentBomId) {
                        const context = currentBomId ? `editing BOM #${currentBomId}` : 'creating a new BOM';
                        if (confirm(`A draft from ${context} was found. Would you like to restore it?`)) {
                            this.state.formData = parsed.formData || this.state.formData;
                            this.state.selectedProject = parsed.selectedProject || this.state.selectedProject;
                            this.state.groups = parsed.groups || this.state.groups;
                            this.state.isDirty = true;
                            this.render();
                        } else {
                            localStorage.removeItem(draftKey);
                        }
                    }
                    // If contexts don't match, silently skip (router already handled the warning)
                } else {
                    localStorage.removeItem(draftKey);
                }
            }
        } catch (error) {
            this.logError('Error loading autosave', error);
        }
    }
    
    clearAutosave() {
        try {
            const draftKey = `bom_draft_${window.userId || 'anonymous'}_new`;
            localStorage.removeItem(draftKey);
        } catch (error) {
            this.logError('Error clearing autosave', error);
        }
    }
    
    async saveBOM() {
        if (!this.canSave()) {
            this.showError('Please fill in all required fields.');
            return;
        }
        
        // Validate groups
        const invalidGroups = this.state.groups.filter(g => !g.name);
        if (invalidGroups.length > 0) {
            this.showError('All groups must have a type selected.');
            return;
        }
        
        // Set saving flag to prevent autosave
        this.state.isSaving = true;
        
        // Clear any pending autosave timer
        if (this.state.autosaveTimer) {
            clearTimeout(this.state.autosaveTimer);
            this.state.autosaveTimer = null;
        }
        
        // Disable all form elements during save
        this.disableForm(true);
        
        const saveBtn = document.getElementById('saveBomBtn');
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<clr-icon shape="sync"></clr-icon><span>Saving...</span>';
        
        try {
            const payload = {
                name: this.state.formData.name,
                project_id: this.state.selectedProject.id,
                description: this.state.formData.description,
                notes: this.state.formData.notes,
                groups: this.state.groups.map((group, index) => ({
                    name: group.name,
                    display_order: index,
                    items: group.items.map((item, itemIndex) => ({
                        component_id: item.component_id,
                        component_source: item.component_source || 'bommer',
                        quantity: item.quantity || 1,
                        reference_designator: item.reference_designator,
                        notes: item.notes,
                        display_order: itemIndex
                    }))
                }))
            };
            
            let response;
            if (this.isEditMode) {
                // Update existing BOM
                payload.id = this.bomId;
                response = await API.put('boms.php', payload);
            } else {
                // Create new BOM
                payload.sku = this.state.formData.sku;
                response = await API.post('boms.php', payload);
            }
            
            if (response.success) {
                // Clear autosave data
                this.clearAutosave();
                this.state.isDirty = false;
                
                // Redirect to BOM list immediately using SPA navigation
                if (typeof navigateTo === 'function') {
                    // Clear any pending localStorage flags
                    localStorage.removeItem('bomCreationContext');
                    localStorage.removeItem('bomCreationDraft');
                    navigateTo('boms');
                } else {
                    window.location.href = '/app.php#/boms';
                }
            } else {
                throw new Error(response.message || `Failed to ${this.isEditMode ? 'update' : 'create'} BOM`);
            }
        } catch (error) {
            this.logError('Save error', error);
            this.showError(error.message || `Failed to ${this.isEditMode ? 'update' : 'save'} BOM. Please try again.`);
            
            // Re-enable form on error
            this.state.isSaving = false;
            this.disableForm(false);
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<clr-icon shape="floppy"></clr-icon><span>Save BOM</span>';
        }
    }
    
    disableForm(disable) {
        // Disable/enable all input fields, textareas, and buttons
        const elements = document.querySelectorAll('#bom-create-app input, #bom-create-app textarea, #bom-create-app select, #bom-create-app button');
        elements.forEach(el => {
            if (disable) {
                el.setAttribute('disabled', 'disabled');
            } else {
                // Don't re-enable the SKU field if no project is selected
                if (el.id === 'skuInput' && !this.state.selectedProject) {
                    return;
                }
                // Don't re-enable the suggest SKU button if no project is selected
                if (el.id === 'suggestSkuBtn' && !this.state.selectedProject) {
                    return;
                }
                el.removeAttribute('disabled');
            }
        });
    }
    
    showGroupManagementModal() {
        // Save current BOM state before navigating
        this.saveBOMDraftForGroupManagement();
            
        // Set navigation context flag (using localStorage for cross-page persistence)
        localStorage.setItem('bomCreationContext', 'true');
            
        // Navigate to admin groups management page immediately (no delay needed)
        if (window.navigateTo) {
            window.navigateTo('groups');
        } else {
            window.location.href = '/app.php#/groups';
        }
    }
    
    saveBOMDraftForGroupManagement() {
        // Save current state to localStorage for restoration (cross-page persistence)
        const draftData = {
            formData: this.state.formData,
            selectedProject: this.state.selectedProject,
            groups: this.state.groups,
            isEditMode: this.isEditMode,
            bomId: this.bomId,
            timestamp: Date.now()
        };
        localStorage.setItem('bomCreationDraft', JSON.stringify(draftData));
    }
    
    restoreBOMDraftFromGroupManagement() {
        try {
            const draftData = localStorage.getItem('bomCreationDraft');
            if (draftData) {
                const parsed = JSON.parse(draftData);
                
                // Restore form data
                this.state.formData = parsed.formData || this.state.formData;
                this.state.selectedProject = parsed.selectedProject || this.state.selectedProject;
                this.state.groups = parsed.groups || this.state.groups;
                this.state.isDirty = true;
                
                // Clear the draft after restoration
                localStorage.removeItem('bomCreationDraft');
                return true;
            }
        } catch (error) {
            this.logError('Error restoring BOM draft', error);
        }
        return false;
    }
    
    hideLoading() {
        const loading = document.getElementById('loadingOverlay');
        if (loading) {
            loading.classList.add('hidden');
        }
    }
    
    showError(message) {
        // Remove any existing error messages
        const existingError = document.querySelector('.error-message-banner');
        if (existingError) {
            existingError.remove();
        }
        
        // Create error banner
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message-banner';
        errorDiv.innerHTML = `
            <clr-icon shape="exclamation-triangle"></clr-icon>
            <span>${message}</span>
            <button class="error-close" onclick="this.parentElement.remove()">
                <clr-icon shape="times"></clr-icon>
            </button>
        `;
        
        // Insert at top of BOM app
        const bomApp = document.getElementById('bom-create-app');
        if (bomApp && bomApp.firstChild) {
            bomApp.insertBefore(errorDiv, bomApp.firstChild);
        } else {
            document.body.appendChild(errorDiv);
        }
        
        // Auto-remove after 10 seconds
        setTimeout(() => {
            if (errorDiv.parentElement) {
                errorDiv.remove();
            }
        }, 10000);
    }
    
    showSuccess(message) {
        const successDiv = document.createElement('div');
        successDiv.className = 'success-message';
        successDiv.textContent = message;
        document.body.appendChild(successDiv);
        
        setTimeout(() => {
            successDiv.remove();
        }, 3000);
    }
    
    canSave() {
        // Check if all required fields are filled
        const requiredFieldsFilled = this.state.formData.sku && 
                                     this.state.formData.name && 
                                     this.state.selectedProject && 
                                     this.state.formData.notes.trim();
        
        if (!requiredFieldsFilled) {
            return false;
        }
        
        // In edit mode, also check if changes were made
        if (this.isEditMode) {
            return this.hasChanges();
        }
        
        // In create mode, just check required fields
        return true;
    }
    
    captureStateSnapshot() {
        // Create a deep copy of the current state for comparison
        return JSON.stringify({
            formData: this.state.formData,
            selectedProject: this.state.selectedProject ? this.state.selectedProject.id : null,
            groups: this.state.groups.map(g => ({
                name: g.name,
                group_template_id: g.group_template_id,
                items: g.items.map(i => ({
                    component_id: i.component_id,
                    component_source: i.component_source,
                    quantity: i.quantity,
                    reference_designator: i.reference_designator,
                    notes: i.notes
                }))
            }))
        });
    }
    
    hasChanges() {
        if (!this.originalState) {
            return false;
        }
        
        const currentSnapshot = this.captureStateSnapshot();
        return currentSnapshot !== this.originalState;
    }
    
    markDirty() {
        this.state.isDirty = true;
        this.scheduleAutosave();
        
        // Update save button state
        const saveBtn = document.getElementById('saveBomBtn');
        if (saveBtn) {
            saveBtn.disabled = !this.canSave();
        }
    }
    
    handleInlineComponentEdit(componentId) {
        // Use the global router's component edit modal with BOM context
        if (window.router && typeof window.router.showEditComponentModal === 'function') {
            window.router.showEditComponentModal(componentId, 'bom-create');
        } else {
            console.error('Router not available for component editing');
            alert('Component editing is not available at this time. Please try refreshing the page.');
        }
    }
}

// Initialize app when DOM is ready or immediately if already loaded
// BUT: Only if we're actually on the BOM create page (check for the container element)
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initBOMApp);
} else {
    // DOM already loaded (when dynamically imported)
    initBOMApp();
}

function initBOMApp() {
    // Only initialize if the BOM create app container exists
    const container = document.getElementById('bom-create-app');
    if (!container) {
        return;
    }
    
    // Check if edit data was passed from router
    const editData = window.__bomEditData;
    if (editData) {
        window.bomApp = new BOMCreateApp(editData.bomId, editData.bomData);
        delete window.__bomEditData; // Clean up
    } else {
        window.bomApp = new BOMCreateApp();
    }
    window.BOMCreateApp = BOMCreateApp; // Export class for re-initialization
    
    // Make the navigation function globally accessible
    window.navigateToGroups = function() {
        if (window.bomApp) {
            window.bomApp.showGroupManagementModal();
        }
    };
}

