// Router and Page Management for Bommer Application - Connected to Backend API

const AppRouter = {
    currentRoute: 'dashboard',
    data: {
        boms: [],
        projects: [],
        assemblies: [],
        components: [],
        auditLogs: []
    },
    
    /**
     * Helper to log errors only when they are unexpected (non-4xx)
     */
    logError(context, error) {
        if (!error.status || error.status >= 500) {
            console.error(`${context}:`, error);
        } else {
            // For 4xx errors, we can use console.warn or nothing at all
            console.warn(`${context} (Expected):`, error.message);
        }
    },
    
    async init() {
        try {
            // Load initial data
            await this.loadAllData();
            
            // Set initial user display
            const userAvatar = document.getElementById('userAvatar');
            if (userAvatar) {
                userAvatar.textContent = 'A'; // Will be replaced with actual user data
                userAvatar.title = 'Admin User';
            }
            
            // Load initial route FIRST
            await this.navigateTo('dashboard');
            
            // Check for autosave AFTER dashboard loads to avoid navigation conflicts
            await this.checkAndResumeAutosave();
            
            // Setup global search
            const searchInput = document.getElementById('globalSearch');
            if (searchInput) {
                searchInput.addEventListener('keyup', async (e) => {
                    if (e.key === 'Enter') {
                        await this.performSearch(searchInput.value);
                    }
                });
            }
            
            // Setup event delegation for Change Status buttons
            const self = this; // Preserve AppRouter context
            document.addEventListener('click', (e) => {
                const btn = e.target.closest('[data-action="change-status"]');
                if (btn) {
                    const bomId = parseInt(btn.dataset.bomId);
                    const currentStatus = btn.dataset.currentStatus;
                    self.showChangeStatusModal(bomId, currentStatus);
                }
                
                // Handle close status modal
                const closeBtn = e.target.closest('[data-action="close-status-modal"]');
                if (closeBtn) {
                    self.closeStatusModal();
                }
                
                // Handle confirm status change
                const confirmBtn = e.target.closest('[data-action="confirm-status-change"]');
                if (confirmBtn) {
                    const bomId = parseInt(confirmBtn.dataset.bomId);
                    const newStatus = document.getElementById('newStatus')?.value;
                    self.changeStatus(bomId, newStatus);
                }
                
                // ====== Group Management Actions ======
                
                // Handle create group
                const createGroupBtn = e.target.closest('[data-action="create-group"]');
                if (createGroupBtn) {
                    self.showCreateGroupModal();
                }
                
                // Handle back to BOM creation
                const backToBOMBtn = e.target.closest('[data-action="back-to-bom-creation"]');
                if (backToBOMBtn) {
                    // Check if there's a saved BOM draft to restore
                    const hasDraft = localStorage.getItem('bomCreationDraft');
                    if (hasDraft) {
                        // Mark that we're coming from group management
                        localStorage.setItem('bomCreationContext', 'true');
                    }
                    self.navigateTo('boms/create');
                }
                
                // Handle edit group
                const editGroupBtn = e.target.closest('[data-action="edit-group"]');
                if (editGroupBtn) {
                    const groupId = parseInt(editGroupBtn.dataset.groupId);
                    self.showEditGroupModal(groupId);
                }
                
                // Handle toggle group (activate/deactivate)
                const toggleGroupBtn = e.target.closest('[data-action="toggle-group"]');
                if (toggleGroupBtn) {
                    const groupId = parseInt(toggleGroupBtn.dataset.groupId);
                    const isActive = toggleGroupBtn.dataset.isActive === 'true';
                    self.toggleGroupStatus(groupId, isActive);
                }
                
                // Handle close group modal
                const closeGroupModalBtn = e.target.closest('[data-action="close-group-modal"]');
                if (closeGroupModalBtn) {
                    self.closeGroupModal();
                }
                
                // Handle save group (create/update)
                const saveGroupBtn = e.target.closest('[data-action="save-group"]');
                if (saveGroupBtn) {
                    self.saveGroup();
                }

                // Handle edit project
                const editProjectBtn = e.target.closest('[data-action="edit-project"]');
                if (editProjectBtn) {
                    const projectId = parseInt(editProjectBtn.dataset.projectId);
                    const project = self.data.projects.find(p => p.id === projectId);
                    if (project) {
                        self.showProjectModal(project);
                    }
                }

                // Handle add BOM to project
                const addBomBtn = e.target.closest('[data-action="add-bom"]');
                if (addBomBtn) {
                    const projectId = parseInt(addBomBtn.dataset.projectId);
                    self.navigateTo(`boms/create?projectId=${projectId}`);
                }

                // Handle close project modal
                const closeProjectModalBtn = e.target.closest('[data-action="close-project-modal"]');
                if (closeProjectModalBtn) {
                    self.closeProjectModal();
                }

                // Handle save project
                const saveProjectBtn = e.target.closest('[data-action="save-project"]');
                if (saveProjectBtn) {
                    self.saveProject();
                }

                // Handle create project
                const createProjectBtn = e.target.closest('[data-action="create-project"]');
                if (createProjectBtn) {
                    self.showProjectModal();
                }

                // Handle sync from devapp
                const syncDevappBtn = e.target.closest('[data-action="sync-devapp-projects"]');
                if (syncDevappBtn) {
                    self.showDevappSyncModal();
                }

                // Handle import devapp projects
                const importDevappBtn = e.target.closest('[data-action="import-devapp-projects"]');
                if (importDevappBtn) {
                    self.importDevappProjects();
                }

                // ====== Assembly Management Actions ======

                // Handle create assembly
                const createAssemblyBtn = e.target.closest('[data-action="create-assembly"]');
                if (createAssemblyBtn) {
                    self.showAssemblyModal();
                }

                // Handle edit assembly
                const editAssemblyBtn = e.target.closest('[data-action="edit-assembly"]');
                if (editAssemblyBtn) {
                    const assemblyId = parseInt(editAssemblyBtn.dataset.assemblyId);
                    // Fetch full assembly data with projects before opening modal
                    self.editAssembly(assemblyId);
                }

                // Handle close assembly modal
                const closeAssemblyModalBtn = e.target.closest('[data-action="close-assembly-modal"]');
                if (closeAssemblyModalBtn) {
                    self.closeAssemblyModal();
                }

                // Handle save assembly
                const saveAssemblyBtn = e.target.closest('[data-action="save-assembly"]');
                if (saveAssemblyBtn) {
                    self.saveAssembly();
                }

                // ====== Project Optionals Actions ======

                // Link optional project
                const linkOptionalBtn = e.target.closest('[data-action="link-optional"]');
                if (linkOptionalBtn) {
                    const baseProjectId = parseInt(linkOptionalBtn.dataset.baseProjectId);
                    const select = document.getElementById(`optionalProjectSelect-${baseProjectId}`);
                    if (select && select.value) {
                        const optionalProjectId = parseInt(select.value);
                        self.linkOptionalProject(baseProjectId, optionalProjectId);
                    }
                }

                // Unlink optional project
                const unlinkOptionalBtn = e.target.closest('[data-action="unlink-optional"]');
                if (unlinkOptionalBtn) {
                    const baseProjectId = parseInt(unlinkOptionalBtn.dataset.baseProjectId);
                    const optionalProjectId = parseInt(unlinkOptionalBtn.dataset.optionalProjectId);
                    self.unlinkOptionalProject(baseProjectId, optionalProjectId);
                }

                // Link optional from page
                const linkFromPageBtn = e.target.closest('[data-action="link-optional-from-page"]');
                if (linkFromPageBtn) {
                    const optionalProjectId = parseInt(linkFromPageBtn.dataset.optionalProjectId);
                    const select = document.getElementById(`linkBaseSelect-${optionalProjectId}`);
                    if (select && select.value) {
                        const baseProjectId = parseInt(select.value);
                        self.linkOptionalProject(baseProjectId, optionalProjectId);
                    }
                }

                // Toggle project optional
                const toggleOptionalBtn = e.target.closest('[data-action="toggle-project-optional"]');
                if (toggleOptionalBtn) {
                    const projectId = parseInt(toggleOptionalBtn.dataset.projectId);
                    const isOptional = toggleOptionalBtn.dataset.isOptional === 'true';
                    self.toggleProjectOptional(projectId, isOptional);
                }

                // ====== BOM Actions ======

                // Handle create variant (new SKU based on existing BOM)
                const createVariantBtn = e.target.closest('[data-action="create-variant"]');
                if (createVariantBtn) {
                    const bomId = parseInt(createVariantBtn.dataset.bomId);
                    const projectName = createVariantBtn.dataset.projectName || '';
                    const sku = createVariantBtn.dataset.sku || '';
                    const bomName = createVariantBtn.dataset.bomName || '';
                    const variantGroup = createVariantBtn.dataset.variantGroup || '';
                    self.showCreateVariantModal({
                        bomId,
                        projectName,
                        sku,
                        bomName,
                        variantGroup
                    });
                }

                // Handle create revision
                const createRevisionBtn = e.target.closest('[data-action="create-revision"]');
                if (createRevisionBtn) {
                    const bomId = parseInt(createRevisionBtn.dataset.bomId);
                    self.showCreateRevisionModal(bomId);
                }

                // Handle close revision modal
                const closeRevisionModalBtn = e.target.closest('[data-action="close-revision-modal"]');
                if (closeRevisionModalBtn) {
                    self.closeRevisionModal();
                }

                // Handle confirm revision creation
                const confirmRevisionBtn = e.target.closest('[data-action="confirm-create-revision"]');
                if (confirmRevisionBtn) {
                    const bomId = parseInt(confirmRevisionBtn.dataset.bomId);
                    self.createRevision(bomId);
                }

                // Handle close variant modal
                const closeVariantModalBtn = e.target.closest('[data-action="close-variant-modal"]');
                if (closeVariantModalBtn) {
                    self.closeVariantModal();
                }

                // Handle confirm variant creation
                const confirmVariantBtn = e.target.closest('[data-action="confirm-create-variant"]');
                if (confirmVariantBtn) {
                    const bomId = parseInt(confirmVariantBtn.dataset.bomId);
                    self.createVariant(bomId);
                }

                // Handle export BOM
                const exportBtn = e.target.closest('[data-action="export-bom"]');
                if (exportBtn) {
                    const bomId = parseInt(exportBtn.dataset.bomId);
                    const bomName = exportBtn.dataset.bomName;
                    self.exportBOM(bomId, bomName);
                }

                // Handle compare BOMs
                const compareBOMsBtn = e.target.closest('[data-action="compare-boms"]');
                if (compareBOMsBtn) {
                    self.showBOMCompareModal();
                }

                // ====== Component Actions ======

                // Handle create component
                const createComponentBtn = e.target.closest('[data-action="create-component"]');
                if (createComponentBtn) {
                    self.showCreateComponentModal();
                }

                // Handle edit component
                const editComponentBtn = e.target.closest('[data-action="edit-component"]');
                if (editComponentBtn) {
                    const componentId = parseInt(editComponentBtn.dataset.componentId);
                    self.showEditComponentModal(componentId);
                }

                // Handle close component modal
                const closeComponentModalBtn = e.target.closest('[data-action="close-component-modal"]');
                if (closeComponentModalBtn) {
                    self.closeComponentModal();
                }

                // Handle save component
                const saveComponentBtn = e.target.closest('[data-action="save-component"]');
                if (saveComponentBtn) {
                    self.saveComponent();
                }
            });
        } catch (error) {
            this.logError('Initialization error', error);
            this.showError('Failed to initialize application');
        }
    },
    
    async loadAllData() {
        try {
            const [bomsResp, projectsResp, assembliesResp, componentsResp] = await Promise.all([
                API.listBOMs(),
                API.listProjects(),
                API.listAssemblies(),
                API.listComponents({ source: 'bommer' }) // Load only Bommer components by default
            ]);
            
            this.data.boms = bomsResp.data || [];
            this.data.projects = projectsResp.data || [];
            this.data.assemblies = assembliesResp.data || [];
            this.data.components = componentsResp.data || [];
        } catch (error) {
            this.logError('Error loading data', error);
        }
    },
    
    async checkAndResumeAutosave() {
        const draftKey = `bom_draft_${window.userId || 'anonymous'}_new`;
        try {
            const draftData = localStorage.getItem(draftKey);
            if (draftData) {
                const parsed = JSON.parse(draftData);
                const age = Date.now() - (parsed.timestamp || 0);
                
                // Only prompt if less than 24 hours old
                if (age < 24 * 60 * 60 * 1000) {
                    const bomId = parsed.bomId || null;
                    const context = bomId ? `editing BOM #${bomId}` : 'creating a new BOM';
                    
                    const resume = confirm(
                        `You have an unsaved draft from ${context}.\n\n` +
                        `Would you like to resume where you left off?`
                    );
                    
                    if (resume) {
                        // Navigate to appropriate mode - single call, no timeout to avoid race
                        if (bomId) {
                            await this.navigateTo(`boms/${bomId}/edit`);
                        } else {
                            await this.navigateTo('boms/create');
                        }
                    } else {
                        // User declined - clear the autosave
                        localStorage.removeItem(draftKey);
                    }
                } else {
                    // Expired - clear it
                    localStorage.removeItem(draftKey);
                }
            }
        } catch (error) {
            console.warn('Failed to check autosave on init:', error);
        }
    },
    
    async loadBOMCreateApp(bomId = null, bomData = null, projectId = null) {
        // Clean up group management context flag if present
        localStorage.removeItem('bomCreationContext');
        
        // Always reinitialize to ensure fresh state
        if (window.bomApp) {
            // Clean up existing instance
            delete window.bomApp;
        }
        
        // Check for conflicting autosave data ONLY if not already handled by checkAndResumeAutosave
        const draftKey = `bom_draft_${window.userId || 'anonymous'}_new`;
        try {
            const draftData = localStorage.getItem(draftKey);
            if (draftData) {
                const parsed = JSON.parse(draftData);
                const draftBomId = parsed.bomId || null;
                const targetBomId = bomId || null;
                
                // Check if autosave is for a different context
                if (draftBomId !== targetBomId) {
                    const draftContext = draftBomId ? `editing BOM #${draftBomId}` : 'creating a new BOM';
                    const targetContext = targetBomId ? `editing BOM #${targetBomId}` : 'creating a new BOM';
                    
                    const proceed = confirm(
                        `You have unsaved changes from ${draftContext}.\n\n` +
                        `Do you want to discard those changes and continue ${targetContext}?\n\n` +
                        `Click OK to discard, Cancel to go back and save first.`
                    );
                    
                    if (!proceed) {
                        // User wants to save first - redirect WITHOUT timeout to avoid loops
                        if (draftBomId) {
                            await this.navigateTo(`boms/${draftBomId}/edit`);
                        } else {
                            await this.navigateTo('boms/create');
                        }
                        return;
                    } else {
                        // User chose to discard - clear the autosave
                        localStorage.removeItem(draftKey);
                    }
                }
            }
        } catch (error) {
            console.warn('Failed to check autosave conflict:', error);
        }
        
        // Check if already loaded
        if (window.BOMCreateApp) {
            // Re-initialize with optional edit data
            window.bomApp = new window.BOMCreateApp(bomId, bomData, projectId);
            // Clean up edit data after creating instance
            if (window.__bomEditData) {
                delete window.__bomEditData;
            }
            return;
        }
        
        // Dynamically import the BOM creation module
        try {
            // Store data temporarily for the module to pick up
            if (bomId && bomData) {
                window.__bomEditData = { bomId, bomData };
            } else if (projectId) {
                window.__bomEditData = { projectId };
            }
            
            const module = await import('./public/js/bom-create.js?v=2026012504');
            
            // Wait for next tick to ensure module initialization completed
            await new Promise(resolve => setTimeout(resolve, 0));
            
            // Clean up edit data after module initialization
            if (window.__bomEditData) {
                delete window.__bomEditData;
            }
        } catch (error) {
            this.logError('Failed to load BOM creation module', error);
            document.getElementById('bom-create-app').innerHTML = `
                <div class="content-body">
                    <div class="card">
                        <h2>Error Loading BOM Creator</h2>
                        <p>${error.message}</p>
                    </div>
                </div>
            `;
        }
    },
    
    async navigateTo(route) {
        console.log('[navigateTo] Original route:', route);
        
        // Handle query parameters if any
        let projectId = null;
        let queryParams = null;
        if (route.includes('?')) {
            const urlParts = route.split('?');
            route = urlParts[0];
            queryParams = new URLSearchParams(urlParts[1]);
            projectId = queryParams.get('projectId');
            console.log('[navigateTo] Extracted route:', route);
            console.log('[navigateTo] Query params:', Object.fromEntries(queryParams));
        }

        this.currentRoute = route;
        
        try {
            const content = document.getElementById('appContent');
            if (!content) return;

            content.innerHTML = '<div class="content-body"><div class="loading-center"><div class="spinner"></div><p>Loading...</p></div></div>';
            
            // Update active nav item in header
            document.querySelectorAll('.header-nav-item').forEach(item => {
                item.classList.remove('active');
                const itemRoute = item.dataset.route;
                if (itemRoute && (route === itemRoute || route.startsWith(itemRoute + '/'))) {
                    item.classList.add('active');
                }
            });

            if (route === 'dashboard') {
                await this.loadAllData();
                content.innerHTML = await Pages.renderDashboard(this.data);
            } else if (route === 'boms') {
                const bomsResp = await API.listBOMs();
                this.data.boms = bomsResp.data || [];
                content.innerHTML = await Pages.renderBOMList(this.data.boms);
            } else if (route.startsWith('boms/')) {
                const parts = route.split('/');
                if (parts[1] === 'create') {
                    content.innerHTML = Pages.renderBOMCreate();
                    await this.loadBOMCreateApp(null, null, projectId);
                } else if (parts[1] === 'compare') {
                    const idsParam = queryParams ? queryParams.get('ids') : null;
                    content.innerHTML = Pages.renderBOMCompare(idsParam);
                } else if (parts[1] === 'matrix') {
                    // Get scope and id from query params
                    const scope = queryParams ? queryParams.get('scope') : null;
                    const id = queryParams ? queryParams.get('id') : null;
                    content.innerHTML = Pages.renderBOMMatrix(scope, id);
                } else if (parts[2] === 'edit') {
                    const bomId = parseInt(parts[1]);
                    const bomResp = await API.getBOM(bomId);
                    if (bomResp.data.current_status !== 'draft') {
                        content.innerHTML = `<div class="content-body"><div class="card"><h2>Cannot Edit BOM</h2><p>Only draft BOMs can be edited. This BOM has status: ${bomResp.data.current_status}</p><button class="btn btn-primary" onclick="navigateTo('boms/${bomId}')">Back to BOM</button></div></div>`;
                    } else {
                        content.innerHTML = Pages.renderBOMCreate();
                        await this.loadBOMCreateApp(bomId, bomResp.data);
                    }
                } else {
                    const bomId = parseInt(parts[1]);
                    const bomResp = await API.getBOM(bomId);
                    content.innerHTML = Pages.renderBOMDetail(bomResp.data);
                }
            } else if (route === 'projects') {
                const projectsResp = await API.listProjects();
                this.data.projects = projectsResp.data || [];
                content.innerHTML = await Pages.renderProjects(this.data.projects);
            } else if (route === 'optionals') {
                const [optionalsResp, projectsResp] = await Promise.all([
                    API.listAllOptionals(),
                    API.listProjectsWithOptionals()
                ]);
                content.innerHTML = await Pages.renderOptionals(optionalsResp.data || [], projectsResp.data || []);
            } else if (route.startsWith('projects/')) {
                const projectId = parseInt(route.split('/')[1]);
                const projectResp = await API.getProjectWithOptionals(projectId);
                content.innerHTML = Pages.renderProjectDetail(projectResp.data);
            } else if (route === 'assemblies') {
                const assembliesResp = await API.listAssemblies();
                this.data.assemblies = assembliesResp.data || [];
                content.innerHTML = await Pages.renderAssemblies(this.data.assemblies);
            } else if (route.startsWith('assemblies/')) {
                const assemblyId = parseInt(route.split('/')[1]);
                const assemblyResp = await API.getAssembly(assemblyId);
                content.innerHTML = Pages.renderAssemblyDetail(assemblyResp.data);
            } else if (route === 'components') {
                // Check for source filter in URL hash (e.g., #components?source=all)
                const urlParams = window.location.hash.includes('?') ? 
                    new URLSearchParams(window.location.hash.split('?')[1]) : new URLSearchParams();
                const sourceFilter = urlParams.get('source') || 'bommer'; // Default to bommer only
                
                const componentsResp = await API.listComponents({ source: sourceFilter });
                this.data.components = componentsResp.data || [];
                content.innerHTML = await Pages.renderComponents(this.data.components, sourceFilter);
            } else if (route.startsWith('components/')) {
                const componentId = parseInt(route.split('/')[1]);
                const componentResp = await API.getComponent(componentId);
                content.innerHTML = Pages.renderComponentDetail(componentResp.data);
            } else if (route === 'users') {
                content.innerHTML = Pages.renderUsers();
            } else if (route === 'groups') {
                const groupsResp = await API.listComponentGroups(true);
                content.innerHTML = await Pages.renderGroups(groupsResp.data || []);
            } else if (route === 'audit') {
                const logsResp = await API.listAuditLogs({ limit: 100 });
                content.innerHTML = Pages.renderAuditLog(logsResp.data || []);
            } else if (route === 'account') {
                content.innerHTML = Pages.renderAccount();
            } else {
                content.innerHTML = '<div class="content-body"><h1>Page Not Found</h1></div>';
            }
        } catch (error) {
            this.logError('Navigation error', error);
            const content = document.getElementById('appContent');
            if (content) {
                content.innerHTML = `<div class="content-body"><div class="card"><h2>Error</h2><p>${error.message || 'Failed to load page'}</p></div></div>`;
            }
        }
        
        const contentEl = document.getElementById('appContent');
        if (contentEl) contentEl.scrollTop = 0;
    },
    
    async linkOptionalProject(baseProjectId, optionalProjectId) {
        if (!optionalProjectId) {
            alert('Please select a project to link as optional.');
            return;
        }

        try {
            await API.linkProjectOptional(baseProjectId, optionalProjectId);
            if (this.currentRoute === 'optionals') {
                this.navigateTo('optionals');
            } else {
                const projectResp = await API.getProjectWithOptionals(baseProjectId);
                const content = document.getElementById('appContent');
                content.innerHTML = Pages.renderProjectDetail(projectResp.data);
            }
        } catch (error) {
            this.logError('Link optional project error', error);
            this.showError(error.message || 'Failed to link optional project');
        }
    },

    async unlinkOptionalProject(baseProjectId, optionalProjectId) {
        try {
            await API.unlinkProjectOptional(baseProjectId, optionalProjectId);
            if (this.currentRoute === 'optionals') {
                this.navigateTo('optionals');
            } else {
                const projectResp = await API.getProjectWithOptionals(baseProjectId);
                const content = document.getElementById('appContent');
                content.innerHTML = Pages.renderProjectDetail(projectResp.data);
            }
        } catch (error) {
            this.logError('Unlink optional project error', error);
            this.showError(error.message || 'Failed to unlink optional project');
        }
    },

    async toggleProjectOptional(projectId, isOptional) {
        try {
            await API.updateProject({ id: projectId, is_optional: isOptional ? 1 : 0 });
            this.navigateTo('optionals');
        } catch (error) {
            this.logError('Toggle project optional error', error);
            this.showError(error.message || 'Failed to update project');
        }
    },

    async performSearch(query) {
        if (!query.trim()) return;
        
        try {
            const [bomsResp, projectsResp, assembliesResp, componentsResp, optionalsResp] = await Promise.all([
                API.listBOMs({ search: query }),
                API.listProjects({ search: query }),
                API.listAssemblies({ search: query }),
                API.listComponents({ search: query, source: 'all', limit: 50 }), // Search both sources, limit results
                API.listAllOptionals({ search: query })
            ]);
            
            const results = {
                boms: bomsResp.data || [],
                projects: projectsResp.data || [],
                assemblies: assembliesResp.data || [],
                components: componentsResp.data || [],
                optionals: optionalsResp.data || []
            };
            
            document.getElementById('appContent').innerHTML = Pages.renderSearchResults(query, results);
        } catch (error) {
            this.logError('Search error', error);
            this.showError('Search failed');
        }
    },
    
    showError(message) {
        alert(message);
    },

    // Show Change Status Modal
    showChangeStatusModal(bomId, currentStatus) {
        const availableStatuses = this.getAvailableStatusTransitions(currentStatus);
        
        if (availableStatuses.length === 0) {
            alert(`No status transitions available from "${currentStatus}".`);
            return;
        }

        const modal = document.createElement('div');
        modal.className = 'modal modal-open';
        modal.innerHTML = `
            <div class="modal-dialog" role="dialog" style="max-width: 600px;">
                <div class="modal-content">
                    <button class="close-btn" data-action="close-status-modal" aria-label="Close">
                        <clr-icon shape="close"></clr-icon>
                    </button>
                    <div class="modal-header">
                        <h3 class="modal-title">Change BOM Status</h3>
                    </div>
                    <div class="modal-body">
                        <p>Current Status: <span class="badge badge-${Pages.getStatusBadgeClass(currentStatus)}">${currentStatus}</span></p>
                        
                        <div class="clr-form-control" style="margin-top: 1.5rem;">
                            <label class="clr-control-label" for="newStatus">New Status</label>
                            <select id="newStatus" class="clr-select" required>
                                <option value="">Select new status...</option>
                                ${availableStatuses.map(status => `
                                    <option value="${status.value}">${status.label} - ${status.description}</option>
                                `).join('')}
                            </select>
                        </div>
                        
                        <div style="margin-top: 1.5rem; padding: 1rem; background-color: var(--clr-app-bg); border-radius: 4px; border: 1px solid var(--clr-border);">
                            <h4 style="margin: 0 0 0.75rem 0; font-size: 0.875rem; font-weight: 600; color: var(--clr-text-main);">Status Workflow Legend</h4>
                            <div style="font-size: 0.8125rem; color: var(--clr-text-secondary); line-height: 1.6;">
                                <div style="display: flex; align-items: center; margin-bottom: 0.5rem;">
                                    <span style="color: #62d077; margin-right: 0.5rem;">✅</span>
                                    <span><strong>draft</strong> → <strong>approved</strong> (Mark as ready for production)</span>
                                </div>
                                <div style="display: flex; align-items: center; margin-bottom: 0.5rem;">
                                    <span style="color: #62d077; margin-right: 0.5rem;">✅</span>
                                    <span><strong>draft</strong> → <strong>invalidated</strong> (Cancel/mark as invalid)</span>
                                </div>
                                <div style="display: flex; align-items: center; margin-bottom: 0.5rem;">
                                    <span style="color: #62d077; margin-right: 0.5rem;">✅</span>
                                    <span><strong>approved</strong> → <strong>obsolete</strong> (Replace with newer revision)</span>
                                </div>
                                <div style="display: flex; align-items: center; margin-bottom: 0.5rem;">
                                    <span style="color: #ff5630; margin-right: 0.5rem;">❌</span>
                                    <span><strong>obsolete</strong> → (none) <em style="color: var(--clr-text-muted);">(Terminal state)</em></span>
                                </div>
                                <div style="display: flex; align-items: center;">
                                    <span style="color: #ff5630; margin-right: 0.5rem;">❌</span>
                                    <span><strong>invalidated</strong> → (none) <em style="color: var(--clr-text-muted);">(Terminal state)</em></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" data-action="close-status-modal">Cancel</button>
                        <button class="btn btn-primary" data-action="confirm-status-change" data-bom-id="${bomId}">Change Status</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        this.currentModal = modal;
    },

    // Close Status Modal
    closeStatusModal() {
        if (this.currentModal) {
            this.currentModal.remove();
            this.currentModal = null;
        }
    },

    // Get Available Status Transitions
    getAvailableStatusTransitions(currentStatus) {
        const transitions = {
            'draft': [
                { value: 'approved', label: 'Approved', description: 'Mark as approved and ready for production' },
                { value: 'invalidated', label: 'Invalidated', description: 'Mark as invalid/cancelled' }
            ],
            'approved': [
                { value: 'obsolete', label: 'Obsolete', description: 'Mark as outdated/replaced by newer revision' }
            ],
            'obsolete': [],
            'invalidated': []
        };
        
        return transitions[currentStatus] || [];
    },

    // Change BOM Status
    async changeStatus(bomId, newStatus) {
        if (!newStatus) {
            alert('Please select a new status.');
            return;
        }

        try {
            const response = await API.updateBOM(bomId, { status: newStatus });
            
            if (response.success) {
                this.closeStatusModal();
                
                // Show success message
                const successMsg = document.createElement('div');
                successMsg.className = 'alert alert-success';
                successMsg.style.cssText = 'position: fixed; top: 80px; right: 20px; z-index: 10000; padding: 1rem 1.5rem; background: #3c8500; color: white; border-radius: 4px; box-shadow: 0 4px 8px rgba(0,0,0,0.3);';
                successMsg.innerHTML = `<clr-icon shape="check-circle"></clr-icon> Status updated successfully!`;
                document.body.appendChild(successMsg);
                
                setTimeout(() => successMsg.remove(), 3000);
                
                // Reload BOM detail to show new status
                this.navigateTo(`boms/${bomId}`);
            } else {
                alert(`Failed to update status: ${response.error || response.message || 'Unknown error'}`);
            }
        } catch (error) {
            this.logError('Status change error', error);
            alert(`Failed to update BOM status. Error: ${error.message || 'Please try again.'}`);
        }
    },

    // ========== BOM Variant Management ==========
    
    showCreateVariantModal(meta) {
        const { bomId, projectName, sku, bomName, variantGroup } = meta || {};
        const safeProject = projectName || '';
        const safeSku = sku || '';
        const safeName = bomName || '';
        const safeVariantGroup = variantGroup || safeSku;

        const modal = document.createElement('div');
        modal.className = 'modal modal-open';
        modal.innerHTML = `
            <div class="modal-dialog" role="dialog" style="max-width: 650px;">
                <div class="modal-content">
                    <button class="close-btn" data-action="close-variant-modal" aria-label="Close">
                        <clr-icon shape="close"></clr-icon>
                    </button>
                    <div class="modal-header">
                        <h3 class="modal-title">Create BOM Variant (New SKU)</h3>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted" style="margin-bottom: 1rem;">
                            This will create a new BOM with a different SKU in the same project by cloning the
                            current revision of <strong>${safeName}</strong> (<strong>${safeSku}</strong>).
                            The new BOM will start at revision <strong>R1</strong> in <strong>draft</strong> status.
                        </p>
                        ${safeProject ? `
                        <p class="text-muted" style="margin-bottom: 1rem;">
                            Project: <strong>${safeProject}</strong>
                        </p>` : ''}
                        <div class="clr-form-control">
                            <label class="clr-control-label required" for="variantSku">New SKU *</label>
                            <input id="variantSku" type="text" class="clr-input" placeholder="Enter new SKU code" required>
                            <span class="clr-subtext">Must be unique; cannot match an existing SKU.</span>
                        </div>
                        <div class="clr-form-control">
                            <label class="clr-control-label" for="variantName">BOM Name (optional)</label>
                            <input id="variantName" type="text" class="clr-input" placeholder="${safeName ? 'Defaults to: ' + safeName.replace(/"/g, '&quot;') : ''}">
                        </div>
                        <div class="clr-form-control">
                            <label class="clr-control-label" for="variantGroup">Variant Group</label>
                            <input id="variantGroup" type="text" class="clr-input" value="${safeVariantGroup.replace(/"/g, '&quot;')}" placeholder="e.g., base product family code">
                            <span class="clr-subtext">Used to group related SKUs as variants of the same product within this project.</span>
                        </div>
                        <div class="clr-form-control">
                            <label class="clr-control-label required" for="variantNotes">Reason for Variant *</label>
                            <textarea 
                                id="variantNotes"
                                class="clr-textarea"
                                rows="4"
                                placeholder="e.g., Cost-down version without component X, Regional variant, Optional feature set..."
                                required
                            ></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" data-action="close-variant-modal">Cancel</button>
                        <button class="btn btn-primary" data-action="confirm-create-variant" data-bom-id="${bomId}">
                            Create Variant
                        </button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        this.currentModal = modal;
        setTimeout(() => document.getElementById('variantSku')?.focus(), 100);
    },

    closeVariantModal() {
        if (this.currentModal) {
            this.currentModal.remove();
            this.currentModal = null;
        }
    },

    async createVariant(bomId) {
        const skuInput = document.getElementById('variantSku');
        const nameInput = document.getElementById('variantName');
        const groupInput = document.getElementById('variantGroup');
        const notesInput = document.getElementById('variantNotes');

        const sku = skuInput?.value?.trim();
        const name = nameInput?.value?.trim();
        const variantGroup = groupInput?.value?.trim();
        const notes = notesInput?.value?.trim();

        if (!sku) {
            alert('Please enter a new SKU for this variant.');
            if (skuInput) skuInput.focus();
            return;
        }

        if (!notes) {
            alert('Please provide a reason for creating this variant.');
            if (notesInput) notesInput.focus();
            return;
        }

        const confirmBtn = document.querySelector('[data-action="confirm-create-variant"]');
        const cancelBtn = document.querySelector('[data-action="close-variant-modal"]');

        if (confirmBtn) {
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<span class="spinner spinner-inline"></span> Creating...';
        }
        if (cancelBtn) {
            cancelBtn.disabled = true;
        }

        try {
            const payload = {
                source_bom_id: bomId,
                sku,
                notes
            };
            if (name) payload.name = name;
            if (variantGroup) payload.variant_group = variantGroup;

            const response = await API.createBOMVariant(payload);

            if (response.success && response.data && response.data.id) {
                this.closeVariantModal();

                const newBomId = response.data.id;

                const successMsg = document.createElement('div');
                successMsg.className = 'alert alert-success';
                successMsg.style.cssText = 'position: fixed; top: 80px; right: 20px; z-index: 10000; padding: 1rem 1.5rem; background: #3c8500; color: white; border-radius: 4px; box-shadow: 0 4px 8px rgba(0,0,0,0.3);';
                successMsg.innerHTML = '<clr-icon shape="check-circle"></clr-icon> Variant BOM created successfully.';
                document.body.appendChild(successMsg);
                setTimeout(() => successMsg.remove(), 3000);

                this.navigateTo(`boms/${newBomId}`);
            } else {
                alert(`Failed to create variant: ${response.error || response.message || 'Unknown error'}`);
                if (confirmBtn) {
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = 'Create Variant';
                }
                if (cancelBtn) cancelBtn.disabled = false;
            }
        } catch (error) {
            this.logError('Create variant error', error);
            alert(`Failed to create variant. Error: ${error.message || 'Please try again.'}`);
            if (confirmBtn) {
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = 'Create Variant';
            }
            if (cancelBtn) cancelBtn.disabled = false;
        }
    },

    // ========== Revision Management Methods ==========
    
    showCreateRevisionModal(bomId) {
        const modal = document.createElement('div');
        modal.className = 'modal modal-open';
        modal.innerHTML = `
            <div class="modal-dialog" role="dialog" style="max-width: 600px;">
                <div class="modal-content">
                    <button class="close-btn" data-action="close-revision-modal" aria-label="Close">
                        <clr-icon shape="close"></clr-icon>
                    </button>
                    <div class="modal-header">
                        <h3 class="modal-title">Create New Revision</h3>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted" style="margin-bottom: 1rem;">
                            This will create a new revision by cloning the current BOM structure. 
                            The new revision will be in <strong>draft</strong> status.
                        </p>
                        
                        <div class="clr-form-control">
                            <label class="clr-control-label required" for="revisionNotes">Reason for Revision *</label>
                            <textarea 
                                id="revisionNotes" 
                                class="clr-textarea" 
                                rows="4" 
                                placeholder="e.g., Updated component specifications, Cost optimization changes, Design improvements..."
                                required
                            ></textarea>
                            <span class="clr-subtext">Please provide a clear reason for creating this new revision.</span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" data-action="close-revision-modal">Cancel</button>
                        <button class="btn btn-primary" data-action="confirm-create-revision" data-bom-id="${bomId}">Create Revision</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        this.currentModal = modal;
        
        // Focus on notes field
        setTimeout(() => document.getElementById('revisionNotes')?.focus(), 100);
    },

    closeRevisionModal() {
        if (this.currentModal) {
            this.currentModal.remove();
            this.currentModal = null;
        }
    },

    async createRevision(bomId) {
        const notes = document.getElementById('revisionNotes')?.value?.trim();
        
        if (!notes) {
            alert('Please provide a reason for creating this revision.');
            return;
        }

        const confirmBtn = document.querySelector('[data-action="confirm-create-revision"]');
        const cancelBtn = document.querySelector('[data-action="close-revision-modal"]');
        
        if (confirmBtn) {
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<span class="spinner spinner-inline"></span> Creating...';
        }
        if (cancelBtn) cancelBtn.disabled = true;

        try {
            const response = await API.post('boms.php?action=create_revision', {
                bom_id: bomId,
                notes: notes
            });
            
            if (response.success) {
                this.closeRevisionModal();
                
                // Show success message
                const successMsg = document.createElement('div');
                successMsg.className = 'alert alert-success';
                successMsg.style.cssText = 'position: fixed; top: 80px; right: 20px; z-index: 10000; padding: 1rem 1.5rem; background: #3c8500; color: white; border-radius: 4px; box-shadow: 0 4px 8px rgba(0,0,0,0.3);';
                successMsg.innerHTML = `<clr-icon shape="check-circle"></clr-icon> Revision ${response.data.revision_number} created successfully!`;
                document.body.appendChild(successMsg);
                
                setTimeout(() => successMsg.remove(), 3000);
                
                // Reload BOM detail to show new revision
                this.navigateTo(`boms/${bomId}`);
            } else {
                alert(`Failed to create revision: ${response.error || response.message || 'Unknown error'}`);
                if (confirmBtn) {
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = 'Create Revision';
                }
                if (cancelBtn) cancelBtn.disabled = false;
            }
        } catch (error) {
            this.logError('Create revision error', error);
            alert(`Failed to create revision. Error: ${error.message || 'Please try again.'}`);
            if (confirmBtn) {
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = 'Create Revision';
            }
            if (cancelBtn) cancelBtn.disabled = false;
        }
    },

    async exportBOM(bomId, bomName) {
        // Show format selection modal
        const modal = document.createElement('div');
        modal.className = 'modal modal-open';
        modal.innerHTML = `
            <div class="modal-dialog" role="dialog" style="max-width: 500px;">
                <div class="modal-content">
                    <button class="close-btn" data-action="close-export-modal" aria-label="Close">
                        <clr-icon shape="close"></clr-icon>
                    </button>
                    <div class="modal-header">
                        <h3 class="modal-title">Export BOM</h3>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted" style="margin-bottom: 1rem;">
                            Select the export format for <strong>${bomName}</strong>
                        </p>
                        
                        <div class="clr-form-control">
                            <label class="clr-control-label">Export Format</label>
                            <div class="clr-radio-wrapper" style="margin-bottom: 0.5rem;">
                                <input type="radio" id="formatCSV" name="exportFormat" value="csv" class="clr-radio" checked>
                                <label for="formatCSV" style="cursor: pointer;">
                                    <strong>CSV</strong> - Comma-separated values (universal compatibility)
                                </label>
                            </div>
                            <div class="clr-radio-wrapper">
                                <input type="radio" id="formatXLS" name="exportFormat" value="xls" class="clr-radio">
                                <label for="formatXLS" style="cursor: pointer;">
                                    <strong>XLS</strong> - Excel spreadsheet (opens directly in Excel)
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" data-action="close-export-modal">Cancel</button>
                        <button class="btn btn-primary" data-action="confirm-export" data-bom-id="${bomId}" data-bom-name="${bomName}">
                            <clr-icon shape="download"></clr-icon> Export
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        this.currentModal = modal;
        
        // Handle close
        modal.querySelectorAll('[data-action="close-export-modal"]').forEach(btn => {
            btn.addEventListener('click', () => {
                modal.remove();
                this.currentModal = null;
            });
        });
        
        // Handle export
        const confirmBtn = modal.querySelector('[data-action="confirm-export"]');
        confirmBtn.addEventListener('click', () => {
            const format = modal.querySelector('input[name="exportFormat"]:checked').value;
            this.performExport(bomId, bomName, format);
            modal.remove();
            this.currentModal = null;
        });
    },

    async performExport(bomId, bomName, format) {
        try {
            // Create download link
            const url = `/api/boms.php?action=export&bom_id=${bomId}&format=${format}`;
            
            // Show loading message
            const loadingMsg = document.createElement('div');
            loadingMsg.className = 'alert alert-info';
            loadingMsg.style.cssText = 'position: fixed; top: 80px; right: 20px; z-index: 10000; padding: 1rem 1.5rem; background: #0072a3; color: white; border-radius: 4px; box-shadow: 0 4px 8px rgba(0,0,0,0.3);';
            loadingMsg.innerHTML = `<clr-icon shape="download-cloud"></clr-icon> Preparing ${format.toUpperCase()} export for ${bomName}...`;
            document.body.appendChild(loadingMsg);
            
            // Create hidden anchor element for download (avoids X-Frame-Options issue)
            const link = document.createElement('a');
            link.href = url;
            link.download = ''; // Let server set the filename
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            
            // Remove loading message after delay
            setTimeout(() => {
                loadingMsg.remove();
                
                // Show success message
                const successMsg = document.createElement('div');
                successMsg.className = 'alert alert-success';
                successMsg.style.cssText = 'position: fixed; top: 80px; right: 20px; z-index: 10000; padding: 1rem 1.5rem; background: #3c8500; color: white; border-radius: 4px; box-shadow: 0 4px 8px rgba(0,0,0,0.3);';
                successMsg.innerHTML = `<clr-icon shape="check-circle"></clr-icon> BOM exported as ${format.toUpperCase()} successfully!`;
                document.body.appendChild(successMsg);
                
                setTimeout(() => {
                    successMsg.remove();
                    link.remove();
                }, 3000);
            }, 1000);
        } catch (error) {
            this.logError('Export BOM error', error);
            alert(`Failed to export BOM. Error: ${error.message || 'Please try again.'}`);
        }
    },

    // ========== BOM Comparison Modal Methods ==========
    
    async showBOMCompareModal() {
        try {
            // Load all BOMs if not already loaded
            if (!this.data.boms || this.data.boms.length === 0) {
                const bomsResp = await API.listBOMs();
                this.data.boms = bomsResp.data || [];
            }
            
            const boms = this.data.boms;
            
            if (boms.length < 2) {
                alert('At least 2 BOMs are required for comparison.');
                return;
            }
            
            // Get unique project list for filter
            const projects = {};
            boms.forEach(bom => {
                if (bom.project_name && !projects[bom.project_id]) {
                    projects[bom.project_id] = bom.project_name;
                }
            });
            
            const modal = document.createElement('div');
            modal.className = 'modal modal-open';
            modal.innerHTML = `
                <div class="modal-dialog" role="dialog" style="max-width: 900px; max-height: 90vh;">
                    <div class="modal-content" style="display: flex; flex-direction: column; height: 100%;">
                        <button class="close-btn" data-action="close-compare-modal" aria-label="Close">
                            <clr-icon shape="close"></clr-icon>
                        </button>
                        <div class="modal-header">
                            <h3 class="modal-title">Select BOMs to Compare</h3>
                            <p class="text-muted" style="margin: 0.5rem 0 0 0; font-size: 0.85rem;">Select 2-5 BOMs for comparison</p>
                        </div>
                        <div class="modal-body" style="flex: 1; overflow: hidden; display: flex; flex-direction: column;">
                            <!-- Filters -->
                            <div style="display: flex; gap: 0.75rem; margin-bottom: 1rem; flex-wrap: wrap;">
                                <input 
                                    type="search" 
                                    id="bomSearchInput" 
                                    class="clr-input" 
                                    placeholder="Search SKU or name..." 
                                    style="flex: 1; min-width: 200px;"
                                />
                                <select id="bomProjectFilter" class="clr-select" style="min-width: 180px;">
                                    <option value="">All Projects</option>
                                    ${Object.entries(projects).map(([id, name]) => `
                                        <option value="${id}">${name}</option>
                                    `).join('')}
                                </select>
                                <select id="bomStatusFilter" class="clr-select" style="min-width: 140px;">
                                    <option value="">All Statuses</option>
                                    <option value="draft">Draft</option>
                                    <option value="approved">Approved</option>
                                    <option value="obsolete">Obsolete</option>
                                    <option value="invalidated">Invalidated</option>
                                </select>
                            </div>
                            
                            <!-- BOM Table -->
                            <div style="flex: 1; overflow-y: auto; border: 1px solid rgba(255,255,255,0.1); border-radius: 4px;">
                                <table class="table table-compact" style="margin: 0;">
                                    <thead style="position: sticky; top: 0; background: #21333b; z-index: 1;">
                                        <tr>
                                            <th style="width: 50px; text-align: center;">
                                                <input type="checkbox" id="selectAllBOMs" class="clr-checkbox" style="margin: 0;" />
                                            </th>
                                            <th>SKU</th>
                                            <th>Name</th>
                                            <th>Project</th>
                                            <th style="width: 80px;">Rev</th>
                                            <th style="width: 100px;">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="bomTableBody">
                                        ${this.renderBOMCompareRows(boms)}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer" style="display: flex; justify-content: space-between; align-items: center;">
                            <span id="bomSelectionCount" class="text-muted">0 of 5 selected</span>
                            <div>
                                <button class="btn btn-secondary" data-action="close-compare-modal">Cancel</button>
                                <button class="btn btn-primary" id="compareBomsBtn" disabled>Compare</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            this.currentModal = modal;
            this.selectedBOMIds = [];
            
            // Setup event listeners
            this.setupBOMCompareModalListeners(modal, boms);
            
        } catch (error) {
            this.logError('Failed to show BOM compare modal', error);
            alert('Failed to load BOMs for comparison');
        }
    },
    
    renderBOMCompareRows(boms) {
        return boms.map(bom => `
            <tr data-bom-id="${bom.id}" 
                data-project-id="${bom.project_id}" 
                data-status="${bom.current_status || 'draft'}" 
                data-sku="${bom.sku}" 
                data-name="${bom.name}">
                <td style="text-align: center;">
                    <input type="checkbox" class="bom-checkbox clr-checkbox" value="${bom.id}" style="margin: 0;" />
                </td>
                <td><strong>${bom.sku}</strong></td>
                <td>${bom.name}</td>
                <td>${bom.project_name || bom.project_code || '-'}</td>
                <td>R${bom.current_revision}</td>
                <td><span class="badge badge-${Pages.getStatusBadgeClass(bom.current_status)}">${bom.current_status || 'draft'}</span></td>
            </tr>
        `).join('');
    },
    
    setupBOMCompareModalListeners(modal, allBoms) {
        const searchInput = modal.querySelector('#bomSearchInput');
        const projectFilter = modal.querySelector('#bomProjectFilter');
        const statusFilter = modal.querySelector('#bomStatusFilter');
        const selectAllCheckbox = modal.querySelector('#selectAllBOMs');
        const tableBody = modal.querySelector('#bomTableBody');
        const compareBtn = modal.querySelector('#compareBomsBtn');
        const selectionCount = modal.querySelector('#bomSelectionCount');
        
        // Filter BOMs
        const filterBOMs = () => {
            const searchTerm = searchInput.value.toLowerCase();
            const projectId = projectFilter.value;
            const status = statusFilter.value;
            
            const rows = tableBody.querySelectorAll('tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const rowProjectId = row.dataset.projectId;
                const rowStatus = row.dataset.status;
                const rowSku = row.dataset.sku.toLowerCase();
                const rowName = row.dataset.name.toLowerCase();
                
                const matchesSearch = !searchTerm || rowSku.includes(searchTerm) || rowName.includes(searchTerm);
                const matchesProject = !projectId || rowProjectId === projectId;
                const matchesStatus = !status || rowStatus === status;
                
                if (matchesSearch && matchesProject && matchesStatus) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
        };
        
        searchInput.addEventListener('input', filterBOMs);
        projectFilter.addEventListener('change', filterBOMs);
        statusFilter.addEventListener('change', filterBOMs);
        
        // Update selection count and button state
        const updateSelectionUI = () => {
            const count = this.selectedBOMIds.length;
            selectionCount.textContent = `${count} of 5 selected`;
            compareBtn.disabled = count < 2 || count > 5;
            
            // Update select all checkbox
            const visibleCheckboxes = Array.from(tableBody.querySelectorAll('tr:not([style*="display: none"]) .bom-checkbox'));
            const visibleCheckedCount = visibleCheckboxes.filter(cb => cb.checked).length;
            selectAllCheckbox.checked = visibleCheckboxes.length > 0 && visibleCheckedCount === visibleCheckboxes.length;
            selectAllCheckbox.indeterminate = visibleCheckedCount > 0 && visibleCheckedCount < visibleCheckboxes.length;
        };
        
        // Handle individual checkbox change
        tableBody.addEventListener('change', (e) => {
            if (e.target.classList.contains('bom-checkbox')) {
                const bomId = parseInt(e.target.value);
                
                if (e.target.checked) {
                    if (this.selectedBOMIds.length < 5) {
                        this.selectedBOMIds.push(bomId);
                    } else {
                        e.target.checked = false;
                        alert('Maximum 5 BOMs can be selected for comparison.');
                    }
                } else {
                    const index = this.selectedBOMIds.indexOf(bomId);
                    if (index > -1) {
                        this.selectedBOMIds.splice(index, 1);
                    }
                }
                
                updateSelectionUI();
            }
        });
        
        // Handle select all
        selectAllCheckbox.addEventListener('change', (e) => {
            const visibleCheckboxes = Array.from(tableBody.querySelectorAll('tr:not([style*="display: none"]) .bom-checkbox'));
            
            if (e.target.checked) {
                // Select visible unchecked items up to limit
                visibleCheckboxes.forEach(checkbox => {
                    if (!checkbox.checked && this.selectedBOMIds.length < 5) {
                        checkbox.checked = true;
                        this.selectedBOMIds.push(parseInt(checkbox.value));
                    }
                });
                
                if (this.selectedBOMIds.length >= 5) {
                    alert('Maximum 5 BOMs can be selected. Some items were not selected.');
                }
            } else {
                // Deselect visible checked items
                visibleCheckboxes.forEach(checkbox => {
                    if (checkbox.checked) {
                        checkbox.checked = false;
                        const index = this.selectedBOMIds.indexOf(parseInt(checkbox.value));
                        if (index > -1) {
                            this.selectedBOMIds.splice(index, 1);
                        }
                    }
                });
            }
            
            updateSelectionUI();
        });
        
        // Handle compare button
        compareBtn.addEventListener('click', () => {
            if (this.selectedBOMIds.length >= 2 && this.selectedBOMIds.length <= 5) {
                const idsParam = this.selectedBOMIds.join(',');
                this.closeBOMCompareModal();
                this.navigateTo(`boms/compare?ids=${idsParam}`);
            }
        });
        
        // Handle close
        modal.querySelectorAll('[data-action="close-compare-modal"]').forEach(btn => {
            btn.addEventListener('click', () => this.closeBOMCompareModal());
        });
        
        // Handle ESC key
        const handleEsc = (e) => {
            if (e.key === 'Escape') {
                this.closeBOMCompareModal();
                document.removeEventListener('keydown', handleEsc);
            }
        };
        document.addEventListener('keydown', handleEsc);
        
        // Focus search input
        setTimeout(() => searchInput.focus(), 100);
    },
    
    closeBOMCompareModal() {
        if (this.currentModal) {
            this.currentModal.remove();
            this.currentModal = null;
        }
        this.selectedBOMIds = [];
    },

    // ========== Group Management Methods ==========
    
    showCreateGroupModal() {
        this.currentGroupId = null;
        const modal = document.createElement('div');
        modal.className = 'modal modal-open';
        modal.innerHTML = `
            <div class="modal-dialog" role="dialog" style="max-width: 500px;">
                <div class="modal-content">
                    <button class="close-btn" data-action="close-group-modal" aria-label="Close">
                        <clr-icon shape="close"></clr-icon>
                    </button>
                    <div class="modal-header">
                        <h3 class="modal-title">Create Component Group</h3>
                    </div>
                    <div class="modal-body">
                        <div class="clr-form-control" style="margin-bottom: 1rem;">
                            <label class="clr-control-label" for="groupName">Name *</label>
                            <input type="text" id="groupName" class="clr-input" required placeholder="e.g., Power Supply">
                        </div>
                        
                        <div class="clr-form-control" style="margin-bottom: 1rem;">
                            <label class="clr-control-label" for="groupDescription">Description</label>
                            <textarea id="groupDescription" class="clr-textarea" rows="3" placeholder="Optional description"></textarea>
                        </div>
                        
                        <div class="clr-form-control" style="margin-bottom: 1rem;">
                            <label class="clr-control-label" for="groupDisplayOrder">Display Order</label>
                            <input type="number" id="groupDisplayOrder" class="clr-input" value="999" min="0">
                        </div>
                        
                        <div class="clr-form-control">
                            <label class="clr-control-label">
                                <input type="checkbox" id="groupIsActive" checked>
                                <span>Active</span>
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" data-action="close-group-modal">Cancel</button>
                        <button class="btn btn-primary" data-action="save-group">Create Group</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        this.currentModal = modal;
        
        // Focus on name field
        setTimeout(() => document.getElementById('groupName')?.focus(), 100);
    },
    
    async showEditGroupModal(groupId) {
        this.currentGroupId = groupId;
        
        try {
            const response = await API.getComponentGroup(groupId);
            const group = response.data;
            
            const modal = document.createElement('div');
            modal.className = 'modal modal-open';
            modal.innerHTML = `
                <div class="modal-dialog" role="dialog" style="max-width: 500px;">
                    <div class="modal-content">
                        <button class="close-btn" data-action="close-group-modal" aria-label="Close">
                            <clr-icon shape="close"></clr-icon>
                        </button>
                        <div class="modal-header">
                            <h3 class="modal-title">Edit Component Group</h3>
                        </div>
                        <div class="modal-body">
                            <div class="clr-form-control" style="margin-bottom: 1rem;">
                                <label class="clr-control-label" for="groupName">Name *</label>
                                <input type="text" id="groupName" class="clr-input" required value="${group.name}">
                            </div>
                            
                            <div class="clr-form-control" style="margin-bottom: 1rem;">
                                <label class="clr-control-label" for="groupDescription">Description</label>
                                <textarea id="groupDescription" class="clr-textarea" rows="3">${group.description || ''}</textarea>
                            </div>
                            
                            <div class="clr-form-control" style="margin-bottom: 1rem;">
                                <label class="clr-control-label" for="groupDisplayOrder">Display Order</label>
                                <input type="number" id="groupDisplayOrder" class="clr-input" value="${group.display_order}" min="0">
                            </div>
                            
                            <div class="clr-form-control">
                                <label class="clr-control-label">
                                    <input type="checkbox" id="groupIsActive" ${group.is_active ? 'checked' : ''}>
                                    <span>Active</span>
                                </label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-secondary" data-action="close-group-modal">Cancel</button>
                            <button class="btn btn-primary" data-action="save-group">Update Group</button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            this.currentModal = modal;
            
            // Focus on name field
            setTimeout(() => document.getElementById('groupName')?.focus(), 100);
        } catch (error) {
            this.logError('Failed to load group', error);
            alert('Failed to load group details');
        }
    },
    
    closeGroupModal() {
        if (this.currentModal) {
            this.currentModal.remove();
            this.currentModal = null;
        }
        this.currentGroupId = null;
    },
    
    async saveGroup() {
        const name = document.getElementById('groupName')?.value.trim();
        const description = document.getElementById('groupDescription')?.value.trim();
        const displayOrder = parseInt(document.getElementById('groupDisplayOrder')?.value) || 999;
        const isActive = document.getElementById('groupIsActive')?.checked ? 1 : 0;
        
        if (!name) {
            alert('Group name is required');
            return;
        }
        
        const data = {
            name,
            description: description || null,
            display_order: displayOrder,
            is_active: isActive
        };
        
        try {
            let response;
            if (this.currentGroupId) {
                // Update existing group
                data.id = this.currentGroupId;
                response = await API.updateComponentGroup(data);
            } else {
                // Create new group
                response = await API.createComponentGroup(data);
            }
            
            if (response.success) {
                this.closeGroupModal();
                
                // Show success message
                const successMsg = document.createElement('div');
                successMsg.className = 'alert alert-success';
                successMsg.style.cssText = 'position: fixed; top: 80px; right: 20px; z-index: 10000; padding: 1rem 1.5rem; background: #3c8500; color: white; border-radius: 4px; box-shadow: 0 4px 8px rgba(0,0,0,0.3);';
                successMsg.innerHTML = `<clr-icon shape="check-circle"></clr-icon> Group ${this.currentGroupId ? 'updated' : 'created'} successfully!`;
                document.body.appendChild(successMsg);
                
                setTimeout(() => successMsg.remove(), 3000);
                
                // Reload groups page
                this.navigateTo('groups');
            } else {
                alert(`Failed to save group: ${response.error || response.message || 'Unknown error'}`);
            }
        } catch (error) {
            this.logError('Save group error', error);
            alert(`Failed to save group. Error: ${error.message || 'Please try again.'}`);
        }
    },
    
    async toggleGroupStatus(groupId, currentIsActive) {
        const action = currentIsActive ? 'deactivate' : 'activate';
        
        if (!confirm(`Are you sure you want to ${action} this group?`)) {
            return;
        }
        
        try {
            const response = await API.updateComponentGroup({
                id: groupId,
                is_active: currentIsActive ? 0 : 1
            });
            
            if (response.success) {
                // Show success message
                const successMsg = document.createElement('div');
                successMsg.className = 'alert alert-success';
                successMsg.style.cssText = 'position: fixed; top: 80px; right: 20px; z-index: 10000; padding: 1rem 1.5rem; background: #3c8500; color: white; border-radius: 4px; box-shadow: 0 4px 8px rgba(0,0,0,0.3);';
                successMsg.innerHTML = `<clr-icon shape="check-circle"></clr-icon> Group ${action}d successfully!`;
                document.body.appendChild(successMsg);
                
                setTimeout(() => successMsg.remove(), 3000);
                
                // Reload groups page
                this.navigateTo('groups');
            } else {
                alert(`Failed to ${action} group: ${response.error || response.message || 'Unknown error'}`);
            }
        } catch (error) {
            this.logError('Toggle group status error', error);
            alert(`Failed to ${action} group. Error: ${error.message || 'Please try again.'}`);
        }
    },

    // ========== Component Management Methods ==========

    showCreateComponentModal() {
        this.currentComponentId = null; // null means creating new component
        this.componentEditContext = null;
        
        const modal = document.createElement('div');
        modal.className = 'modal modal-open';
        modal.innerHTML = `
            <div class="modal-dialog" role="dialog" style="max-width: 600px;">
                <div class="modal-content">
                    <button class="close-btn" data-action="close-component-modal" aria-label="Close">
                        <clr-icon shape="close"></clr-icon>
                    </button>
                    <div class="modal-header">
                        <h3 class="modal-title">Create New Component</h3>
                    </div>
                    <div class="modal-body">
                        <div class="clr-form-control" style="margin-bottom: 1rem;">
                            <label class="clr-control-label" for="componentPartNumber">Part Number *</label>
                            <input type="text" id="componentPartNumber" class="clr-input" required placeholder="e.g., RES-0805-10K" style="font-size: 14px;">
                            <span class="clr-subtext">Unique identifier for this component</span>
                        </div>
                        
                        <div class="clr-form-control" style="margin-bottom: 1rem;">
                            <label class="clr-control-label" for="componentName">Name *</label>
                            <input type="text" id="componentName" class="clr-input" required placeholder="e.g., 10K Resistor" style="font-size: 14px;">
                        </div>
                        
                        <div class="clr-form-control" style="margin-bottom: 1rem;">
                            <label class="clr-control-label" for="componentDescription">Description</label>
                            <textarea id="componentDescription" class="clr-textarea" rows="2" placeholder="Component description" style="font-size: 14px;"></textarea>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                            <div class="clr-form-control">
                                <label class="clr-control-label" for="componentCategory">Category</label>
                                <input type="text" id="componentCategory" class="clr-input" placeholder="e.g., Resistors" style="font-size: 14px;">
                            </div>
                            
                            <div class="clr-form-control">
                                <label class="clr-control-label" for="componentManufacturer">Manufacturer</label>
                                <input type="text" id="componentManufacturer" class="clr-input" placeholder="e.g., Vishay" style="font-size: 14px;">
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                            <div class="clr-form-control">
                                <label class="clr-control-label" for="componentMPN">MPN</label>
                                <input type="text" id="componentMPN" class="clr-input" placeholder="Manufacturer Part Number" style="font-size: 14px;">
                            </div>
                            
                            <div class="clr-form-control">
                                <label class="clr-control-label" for="componentSupplier">Supplier</label>
                                <input type="text" id="componentSupplier" class="clr-input" placeholder="e.g., Digi-Key" style="font-size: 14px;">
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                            <div class="clr-form-control">
                                <label class="clr-control-label" for="componentUnitCost">Unit Cost *</label>
                                <input type="number" id="componentUnitCost" class="clr-input" step="0.0001" min="0" value="0" required style="font-size: 14px;">
                            </div>
                            
                            <div class="clr-form-control">
                                <label class="clr-control-label" for="componentStockLevel">Stock Level</label>
                                <input type="number" id="componentStockLevel" class="clr-input" min="0" value="0" style="font-size: 14px;">
                            </div>
                            
                            <div class="clr-form-control">
                                <label class="clr-control-label" for="componentMinStock">Min Stock</label>
                                <input type="number" id="componentMinStock" class="clr-input" min="0" value="0" style="font-size: 14px;">
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                            <div class="clr-form-control">
                                <label class="clr-control-label" for="componentLeadTime">Lead Time (days)</label>
                                <input type="number" id="componentLeadTime" class="clr-input" min="0" value="0" style="font-size: 14px;">
                            </div>
                            
                            <div class="clr-form-control">
                                <label class="clr-control-label" for="componentStatus">Status *</label>
                                <div class="clr-select-wrapper">
                                    <select id="componentStatus" class="clr-select" required style="font-size: 14px;">
                                        <option value="active" selected>Active</option>
                                        <option value="obsolete">Obsolete</option>
                                        <option value="banned">Banned</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="clr-form-control">
                            <label class="clr-control-label" for="componentNotes">Notes</label>
                            <textarea id="componentNotes" class="clr-textarea" rows="2" placeholder="Additional notes" style="font-size: 14px;"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" data-action="close-component-modal">Cancel</button>
                        <button class="btn btn-primary" data-action="save-component" id="saveComponentBtn">Create Component</button>
                    </div>
                </div>
            </div>
        `;
                    
        document.body.appendChild(modal);
        this.currentModal = modal;
                    
        // Enable save button validation
        const saveBtn = modal.querySelector('#saveComponentBtn');
        const partNumberInput = document.getElementById('componentPartNumber');
        const nameInput = document.getElementById('componentName');
        
        const checkValidity = () => {
            const partNumber = partNumberInput.value.trim();
            const name = nameInput.value.trim();
            saveBtn.disabled = !partNumber || !name;
        };
        
        partNumberInput.addEventListener('input', checkValidity);
        nameInput.addEventListener('input', checkValidity);
        
        // Initially disable save button
        saveBtn.disabled = true;
        
        // Focus on part number field
        setTimeout(() => partNumberInput?.focus(), 100);
    },

    async showEditComponentModal(componentId, context = null) {
        this.currentComponentId = componentId;
        this.componentEditContext = context; // Store context (e.g., 'bom-create') for post-save refresh
        
        try {
            const response = await API.getComponent(componentId);
            const component = response.data;
            
            // Check if component is from ERP (read-only)
            if (component.source === 'erp') {
                alert('ERP components are read-only and cannot be edited.');
                return;
            }
            
            const modal = document.createElement('div');
            modal.className = 'modal modal-open';
            modal.innerHTML = `
                <div class="modal-dialog" role="dialog" style="max-width: 600px;">
                    <div class="modal-content">
                        <button class="close-btn" data-action="close-component-modal" aria-label="Close">
                            <clr-icon shape="close"></clr-icon>
                        </button>
                        <div class="modal-header">
                            <h3 class="modal-title">Edit Component</h3>
                        </div>
                        <div class="modal-body">
                            <div class="clr-form-control" style="margin-bottom: 1rem;">
                                <label class="clr-control-label" for="componentPartNumber">Part Number *</label>
                                <input type="text" id="componentPartNumber" class="clr-input" value="${component.part_number}" disabled title="Part number cannot be changed">
                                <span class="clr-subtext">Part number cannot be changed after creation</span>
                            </div>
                            
                            <div class="clr-form-control" style="margin-bottom: 1rem;">
                                <label class="clr-control-label" for="componentName">Name *</label>
                                <input type="text" id="componentName" class="clr-input" required value="${component.name}" style="font-size: 14px;">
                            </div>
                            
                            <div class="clr-form-control" style="margin-bottom: 1rem;">
                                <label class="clr-control-label" for="componentDescription">Description</label>
                                <textarea id="componentDescription" class="clr-textarea" rows="2" style="font-size: 14px;">${component.description || ''}</textarea>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                                <div class="clr-form-control">
                                    <label class="clr-control-label" for="componentCategory">Category</label>
                                    <input type="text" id="componentCategory" class="clr-input" value="${component.category || ''}" style="font-size: 14px;">
                                </div>
                                
                                <div class="clr-form-control">
                                    <label class="clr-control-label" for="componentManufacturer">Manufacturer</label>
                                    <input type="text" id="componentManufacturer" class="clr-input" value="${component.manufacturer || ''}" style="font-size: 14px;">
                                </div>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                                <div class="clr-form-control">
                                    <label class="clr-control-label" for="componentMPN">MPN</label>
                                    <input type="text" id="componentMPN" class="clr-input" value="${component.mpn || ''}" style="font-size: 14px;">
                                </div>
                                
                                <div class="clr-form-control">
                                    <label class="clr-control-label" for="componentSupplier">Supplier</label>
                                    <input type="text" id="componentSupplier" class="clr-input" value="${component.supplier || ''}" style="font-size: 14px;">
                                </div>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                                <div class="clr-form-control">
                                    <label class="clr-control-label" for="componentUnitCost">Unit Cost *</label>
                                    <input type="number" id="componentUnitCost" class="clr-input" step="0.0001" min="0" value="${component.unit_cost || 0}" required style="font-size: 14px;">
                                </div>
                                
                                <div class="clr-form-control">
                                    <label class="clr-control-label" for="componentStockLevel">Stock Level</label>
                                    <input type="number" id="componentStockLevel" class="clr-input" min="0" value="${component.stock_level || 0}" style="font-size: 14px;">
                                </div>
                                
                                <div class="clr-form-control">
                                    <label class="clr-control-label" for="componentMinStock">Min Stock</label>
                                    <input type="number" id="componentMinStock" class="clr-input" min="0" value="${component.min_stock || 0}" style="font-size: 14px;">
                                </div>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                                <div class="clr-form-control">
                                    <label class="clr-control-label" for="componentLeadTime">Lead Time (days)</label>
                                    <input type="number" id="componentLeadTime" class="clr-input" min="0" value="${component.lead_time_days || 0}" style="font-size: 14px;">
                                </div>
                                
                                <div class="clr-form-control">
                                    <label class="clr-control-label" for="componentStatus">Status *</label>
                                    <div class="clr-select-wrapper">
                                        <select id="componentStatus" class="clr-select" required style="font-size: 14px;">
                                            <option value="active" ${component.status === 'active' ? 'selected' : ''}>Active</option>
                                            <option value="obsolete" ${component.status === 'obsolete' ? 'selected' : ''}>Obsolete</option>
                                            <option value="banned" ${component.status === 'banned' ? 'selected' : ''}>Banned</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="clr-form-control">
                                <label class="clr-control-label" for="componentNotes">Notes</label>
                                <textarea id="componentNotes" class="clr-textarea" rows="2" style="font-size: 14px;">${component.notes || ''}</textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-secondary" data-action="close-component-modal">Cancel</button>
                            <button class="btn btn-primary" data-action="save-component" id="saveComponentBtn">Save Changes</button>
                        </div>
                    </div>
                </div>
            `;
                        
            document.body.appendChild(modal);
            this.currentModal = modal;
                        
            // Enable save button change detection
            const saveBtn = modal.querySelector('#saveComponentBtn');
            const inputs = modal.querySelectorAll('input:not([disabled]), textarea, select');
            const originalValues = {
                name: component.name,
                description: component.description || '',
                category: component.category || '',
                manufacturer: component.manufacturer || '',
                mpn: component.mpn || '',
                supplier: component.supplier || '',
                unit_cost: component.unit_cost || 0,
                stock_level: component.stock_level || 0,
                min_stock: component.min_stock || 0,
                lead_time_days: component.lead_time_days || 0,
                status: component.status,
                notes: component.notes || ''
            };
            
            const checkChanges = () => {
                const hasChanges = 
                    document.getElementById('componentName').value !== originalValues.name ||
                    document.getElementById('componentDescription').value !== originalValues.description ||
                    document.getElementById('componentCategory').value !== originalValues.category ||
                    document.getElementById('componentManufacturer').value !== originalValues.manufacturer ||
                    document.getElementById('componentMPN').value !== originalValues.mpn ||
                    document.getElementById('componentSupplier').value !== originalValues.supplier ||
                    parseFloat(document.getElementById('componentUnitCost').value) !== parseFloat(originalValues.unit_cost) ||
                    parseInt(document.getElementById('componentStockLevel').value) !== parseInt(originalValues.stock_level) ||
                    parseInt(document.getElementById('componentMinStock').value) !== parseInt(originalValues.min_stock) ||
                    parseInt(document.getElementById('componentLeadTime').value) !== parseInt(originalValues.lead_time_days) ||
                    document.getElementById('componentStatus').value !== originalValues.status ||
                    document.getElementById('componentNotes').value !== originalValues.notes;
                
                saveBtn.disabled = !hasChanges;
            };
            
            inputs.forEach(input => {
                input.addEventListener('input', checkChanges);
                input.addEventListener('change', checkChanges);
            });
            
            // Initially disable save button
            saveBtn.disabled = true;
            
            // Focus on name field
            setTimeout(() => document.getElementById('componentName')?.focus(), 100);
        } catch (error) {
            this.logError('Failed to load component', error);
            alert('Failed to load component details');
        }
    },
    
    closeComponentModal() {
        if (this.currentModal) {
            this.currentModal.remove();
            this.currentModal = null;
        }
        this.currentComponentId = null;
        this.componentEditContext = null;
    },
    
    async saveComponent() {
        const partNumber = document.getElementById('componentPartNumber')?.value.trim();
        const name = document.getElementById('componentName')?.value.trim();
        const description = document.getElementById('componentDescription')?.value.trim();
        const category = document.getElementById('componentCategory')?.value.trim();
        const manufacturer = document.getElementById('componentManufacturer')?.value.trim();
        const mpn = document.getElementById('componentMPN')?.value.trim();
        const supplier = document.getElementById('componentSupplier')?.value.trim();
        const unitCost = parseFloat(document.getElementById('componentUnitCost')?.value) || 0;
        const stockLevel = parseInt(document.getElementById('componentStockLevel')?.value) || 0;
        const minStock = parseInt(document.getElementById('componentMinStock')?.value) || 0;
        const leadTime = parseInt(document.getElementById('componentLeadTime')?.value) || 0;
        const status = document.getElementById('componentStatus')?.value;
        const notes = document.getElementById('componentNotes')?.value.trim();
        
        // Validation
        if (!name) {
            alert('Component name is required');
            return;
        }
        
        // For new components, part number is required
        if (!this.currentComponentId && !partNumber) {
            alert('Part number is required');
            return;
        }
        
        if (unitCost < 0) {
            alert('Unit cost cannot be negative');
            return;
        }
        
        const data = {
            name,
            description: description || null,
            category: category || null,
            manufacturer: manufacturer || null,
            mpn: mpn || null,
            supplier: supplier || null,
            unit_cost: unitCost,
            stock_level: stockLevel,
            min_stock: minStock,
            lead_time_days: leadTime,
            status,
            notes: notes || null
        };
        
        // Add part_number for new components, add id for updates
        if (this.currentComponentId) {
            data.id = this.currentComponentId;
        } else {
            data.part_number = partNumber;
        }
        
        // Disable save button during save
        const saveBtn = document.getElementById('saveComponentBtn');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<clr-icon shape="sync"></clr-icon><span>Saving...</span>';
        }
        
        try {
            // Use createComponent for new, updateComponent for existing
            const response = this.currentComponentId 
                ? await API.updateComponent(data)
                : await API.createComponent(data);
            
            if (response.success) {
                // Store context before closing modal (closeComponentModal clears it)
                const editContext = this.componentEditContext;
                const componentId = this.currentComponentId;
                const isCreating = !componentId;
                
                this.closeComponentModal();
                
                // Show success message
                const successMsg = document.createElement('div');
                successMsg.className = 'alert alert-success';
                successMsg.style.cssText = 'position: fixed; top: 80px; right: 20px; z-index: 10000; padding: 1rem 1.5rem; background: #3c8500; color: white; border-radius: 4px; box-shadow: 0 4px 8px rgba(0,0,0,0.3);';
                successMsg.innerHTML = `<clr-icon shape="check-circle"></clr-icon> Component ${isCreating ? 'created' : 'updated'} successfully!`;
                document.body.appendChild(successMsg);
                
                setTimeout(() => successMsg.remove(), 3000);
                
                // Refresh data based on context
                if (editContext === 'bom-create' && window.bomApp) {
                    // Reload components in BOM creator for immediate updates
                    const componentsResp = await fetch(`/api/components.php?_t=${Date.now()}`, {
                        method: 'GET',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        credentials: 'same-origin',
                        cache: 'no-store'
                    }).then(r => r.json());
                    
                    if (componentsResp.success && window.bomApp && window.bomApp.state) {
                        window.bomApp.state.components = componentsResp.data || [];
                        window.bomApp.render();
                    }
                } else {
                    // Reload current page if on components detail
                    if (this.currentRoute.startsWith('components/')) {
                        this.navigateTo(this.currentRoute);
                    } else if (this.currentRoute === 'components') {
                        this.navigateTo('components');
                    }
                }
            } else {
                const action = this.currentComponentId ? 'update' : 'create';
                alert(`Failed to ${action} component: ${response.error || response.message || 'Unknown error'}`);
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = this.currentComponentId ? '<span>Save Changes</span>' : '<span>Create Component</span>';
                }
            }
        } catch (error) {
            this.logError('Save component error', error);
            const action = this.currentComponentId ? 'update' : 'create';
            alert(`Failed to ${action} component. Error: ${error.message || 'Please try again.'}`);
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = this.currentComponentId ? '<span>Save Changes</span>' : '<span>Create Component</span>';
            }
        }
    },

    // ========== Project Management Methods ==========

    async editAssembly(assemblyId) {
        try {
            // Fetch full assembly data including projects
            const assemblyResp = await API.getAssembly(assemblyId);
            if (assemblyResp.success && assemblyResp.data) {
                this.showAssemblyModal(assemblyResp.data);
            } else {
                alert('Failed to load assembly data');
            }
        } catch (error) {
            this.logError('Failed to load assembly for editing', error);
            alert('Failed to load assembly. Please try again.');
        }
    },

    showAssemblyModal(assembly = null) {
        const isEdit = !!assembly;
        const modal = document.createElement('div');
        modal.className = 'modal modal-open';
        modal.innerHTML = `
            <div class="modal-dialog" role="dialog" style="max-width: 800px;">
                <div class="modal-content">
                    <button class="close-btn" data-action="close-assembly-modal" aria-label="Close">
                        <clr-icon shape="close"></clr-icon>
                    </button>
                    <div class="modal-header">
                        <h3 class="modal-title">${isEdit ? 'Edit Assembly' : 'Create New Assembly'}</h3>
                    </div>
                    <div class="modal-body">
                        ${isEdit ? `<input type="hidden" id="assemblyId" value="${assembly.id}">` : ''}
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                            <div class="clr-form-control">
                                <label class="clr-control-label" for="assemblyCode">Assembly Code *</label>
                                <input type="text" id="assemblyCode" class="clr-input" required placeholder="e.g., ASM-2026-001" style="width: 100%;" value="${isEdit ? assembly.code : ''}">
                            </div>
                            
                            <div class="clr-form-control">
                                <label class="clr-control-label" for="assemblyCategory">Category</label>
                                <input type="text" id="assemblyCategory" class="clr-input" placeholder="e.g., Electronics" style="width: 100%;" value="${isEdit ? assembly.category || '' : ''}">
                            </div>
                        </div>
                        
                        <div class="clr-form-control" style="margin-bottom: 1rem;">
                            <label class="clr-control-label" for="assemblyName">Assembly Name *</label>
                            <input type="text" id="assemblyName" class="clr-input" required placeholder="e.g., Main Control Panel" style="width: 100%;" value="${isEdit ? assembly.name : ''}">
                        </div>
                        
                        <div class="clr-form-control" style="margin-bottom: 1.5rem;">
                            <label class="clr-control-label" for="assemblyDescription">Description</label>
                            <textarea id="assemblyDescription" class="clr-textarea" rows="2" placeholder="Assembly details and purpose" style="width: 100%;">${isEdit ? assembly.description || '' : ''}</textarea>
                        </div>

                        <div class="clr-form-control" style="margin-bottom: 1rem;">
                            <label class="clr-control-label" for="assemblyProjectsFilter" style="font-weight: 600; font-size: 1rem;">Select BOMs (SKUs) for this Assembly</label>
                            <div class="text-muted" style="margin-bottom: 0.5rem;">Choose specific BOMs from projects. Projects will be automatically included based on selected BOMs.</div>
                            <input type="text" id="assemblyProjectsFilter" class="clr-input" placeholder="Search projects or BOMs..." style="width: 100%; margin-bottom: 0.75rem;">
                            <div id="assemblyProjectsList">
                                <p class="text-muted">Loading projects and BOMs...</p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" data-action="close-assembly-modal">Cancel</button>
                        <button class="btn btn-primary" data-action="save-assembly" ${isEdit ? 'disabled' : ''}>${isEdit ? 'Save Changes' : 'Create Assembly'}</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        this.currentModal = modal;
        
        // Store original values for change detection (in edit mode)
        if (isEdit) {
            this.originalAssemblyData = {
                code: assembly.code || '',
                name: assembly.name || '',
                category: assembly.category || '',
                description: assembly.description || '',
                projectIds: assembly.projects ? assembly.projects.map(p => p.id).sort((a, b) => a - b) : [],
                bomIds: assembly.selected_bom_ids ? assembly.selected_bom_ids.sort((a, b) => a - b) : []
            };
            
            // Setup change detection after projects are loaded
            this.setupAssemblyChangeDetection();
        }
        
        // Initialize projects selector (async)
        this.initializeAssemblyProjectsSelector(assembly);

        // Focus on code field
        setTimeout(() => document.getElementById('assemblyCode')?.focus(), 100);
    },

    closeAssemblyModal() {
        if (this.currentModal) {
            this.currentModal.remove();
            this.currentModal = null;
        }
        // Clean up original data and change detection function
        this.originalAssemblyData = null;
        this.checkAssemblyChanges = null;
    },

    setupAssemblyChangeDetection() {
        const modal = this.currentModal;
        if (!modal) return;

        const checkForChanges = () => {
            const saveBtn = modal.querySelector('[data-action="save-assembly"]');
            if (!saveBtn || !this.originalAssemblyData) return;

            const currentCode = document.getElementById('assemblyCode')?.value.trim() || '';
            const currentName = document.getElementById('assemblyName')?.value.trim() || '';
            const currentCategory = document.getElementById('assemblyCategory')?.value.trim() || '';
            const currentDescription = document.getElementById('assemblyDescription')?.value.trim() || '';
            
            const selectedProjectCheckboxes = modal.querySelectorAll('.assembly-project-checkbox:checked');
            const currentProjectIds = Array.from(selectedProjectCheckboxes)
                .map(cb => parseInt(cb.dataset.projectId, 10))
                .filter(id => !isNaN(id))
                .sort((a, b) => a - b);
            
            const selectedBOMCheckboxes = modal.querySelectorAll('.assembly-bom-checkbox:checked');
            const currentBomIds = Array.from(selectedBOMCheckboxes)
                .map(cb => parseInt(cb.dataset.bomId, 10))
                .filter(id => !isNaN(id))
                .sort((a, b) => a - b);

            // Check if any field has changed
            const hasChanges = (
                currentCode !== this.originalAssemblyData.code ||
                currentName !== this.originalAssemblyData.name ||
                currentCategory !== this.originalAssemblyData.category ||
                currentDescription !== this.originalAssemblyData.description ||
                JSON.stringify(currentProjectIds) !== JSON.stringify(this.originalAssemblyData.projectIds) ||
                JSON.stringify(currentBomIds) !== JSON.stringify(this.originalAssemblyData.bomIds || [])
            );

            saveBtn.disabled = !hasChanges;
        };

        // Add change listeners to text inputs
        const codeInput = document.getElementById('assemblyCode');
        const nameInput = document.getElementById('assemblyName');
        const categoryInput = document.getElementById('assemblyCategory');
        const descriptionInput = document.getElementById('assemblyDescription');

        if (codeInput) codeInput.addEventListener('input', checkForChanges);
        if (nameInput) nameInput.addEventListener('input', checkForChanges);
        if (categoryInput) categoryInput.addEventListener('input', checkForChanges);
        if (descriptionInput) descriptionInput.addEventListener('input', checkForChanges);

        // Add change listeners to checkboxes (will be set up after projects are loaded)
        // Store the checkForChanges function for later use
        this.checkAssemblyChanges = checkForChanges;
    },

    async saveAssembly() {
        const id = document.getElementById('assemblyId')?.value;
        const modal = this.currentModal;
        if (!modal) return;

        const saveBtn = modal.querySelector('[data-action="save-assembly"]');
        const cancelButtons = modal.querySelectorAll('[data-action="close-assembly-modal"], .close-btn');

        const code = document.getElementById('assemblyCode')?.value.trim();
        const name = document.getElementById('assemblyName')?.value.trim();
        const category = document.getElementById('assemblyCategory')?.value.trim();
        const description = document.getElementById('assemblyDescription')?.value.trim();
        
        if (!code || !name) {
            alert('Assembly Code and Name are required');
            return;
        }

        const selectedProjectCheckboxes = modal.querySelectorAll('.assembly-project-checkbox:checked');
        const projectIds = Array.from(selectedProjectCheckboxes)
            .map(cb => parseInt(cb.dataset.projectId, 10))
            .filter(idVal => !isNaN(idVal));
        
        const selectedBOMCheckboxes = modal.querySelectorAll('.assembly-bom-checkbox:checked');
        const bomIds = Array.from(selectedBOMCheckboxes)
            .map(cb => parseInt(cb.dataset.bomId, 10))
            .filter(idVal => !isNaN(idVal));
        
        // Disable buttons during submission
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.innerHTML = id ? 'Saving...' : 'Creating...';
        }
        cancelButtons.forEach(btn => btn.disabled = true);

        const data = {
            id: id ? parseInt(id) : undefined,
            code,
            name,
            category: category || null,
            description: description || null,
            project_ids: projectIds,
            bom_ids: bomIds
        };
        
        try {
            const response = id ? await API.updateAssembly(data) : await API.createAssembly(data);
            
            if (response.success) {
                this.closeAssemblyModal();
                
                // Show success message
                const successMsg = document.createElement('div');
                successMsg.className = 'alert alert-success';
                successMsg.style.cssText = 'position: fixed; top: 80px; right: 20px; z-index: 10000; padding: 1rem 1.5rem; background: #3c8500; color: white; border-radius: 4px; box-shadow: 0 4px 8px rgba(0,0,0,0.3);';
                successMsg.innerHTML = `<clr-icon shape="check-circle"></clr-icon> Assembly ${id ? 'updated' : 'created'} successfully!`;
                document.body.appendChild(successMsg);
                
                setTimeout(() => successMsg.remove(), 3000);
                
                // Reload data and navigate
                await this.loadAllData();
                this.navigateTo('assemblies');
            } else {
                alert(`Failed to ${id ? 'update' : 'create'} assembly: ${response.error || response.message || 'Unknown error'}`);
                // Re-enable buttons on failure
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = id ? 'Save Changes' : 'Create Assembly';
                }
                cancelButtons.forEach(btn => btn.disabled = false);
            }
        } catch (error) {
            this.logError('Save assembly error', error);
            alert(`Failed to save assembly. Error: ${error.message || 'Please try again.'}`);
            // Re-enable buttons on failure
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = id ? 'Save Changes' : 'Create Assembly';
            }
            cancelButtons.forEach(btn => btn.disabled = false);
        }
    },

    async initializeAssemblyProjectsSelector(assembly) {
        const modal = this.currentModal;
        if (!modal) return;

        const listContainer = modal.querySelector('#assemblyProjectsList');
        const filterInput = modal.querySelector('#assemblyProjectsFilter');

        if (!listContainer) {
            return;
        }

        try {
            // Fetch projects
            let projects = (this.data && Array.isArray(this.data.projects) && this.data.projects.length)
                ? this.data.projects
                : null;

            if (!projects) {
                const resp = await API.listProjects();
                projects = resp.data || [];
                if (!this.data) {
                    this.data = {};
                }
                this.data.projects = projects;
            }
            
            // Fetch all BOMs
            const bomsResp = await API.listBOMs();
            const allBOMs = bomsResp.data || [];
            
            // Group BOMs by project
            const bomsByProject = {};
            allBOMs.forEach(bom => {
                if (!bomsByProject[bom.project_id]) {
                    bomsByProject[bom.project_id] = [];
                }
                bomsByProject[bom.project_id].push(bom);
            });

            const selectedProjectIds = assembly && Array.isArray(assembly.projects)
                ? assembly.projects.map(p => p.id)
                : [];
            
            const selectedBOMIds = assembly && Array.isArray(assembly.selected_bom_ids)
                ? assembly.selected_bom_ids
                : [];

            const renderList = (items) => {
                if (!items || items.length === 0) {
                    listContainer.innerHTML = '<p class="text-muted">No projects available.</p>';
                    return;
                }
                
                const projectsContainer = document.createElement('div');
                projectsContainer.className = 'assembly-projects-list';
                projectsContainer.style.cssText = 'max-height: 420px; overflow-y: auto; border: 1px solid #3b3b3b; border-radius: 4px; padding: 0.5rem;';
                
                items.forEach((p) => {
                    const projectBOMs = bomsByProject[p.id] || [];
                    const projectIsChecked = selectedProjectIds.includes(p.id);
                    const statusBadgeClass = Pages.getStatusBadgeClass(p.status);
                    
                    // Create project container
                    const projectDiv = document.createElement('div');
                    projectDiv.className = 'assembly-project-container';
                    projectDiv.style.cssText = 'margin-bottom: 0.75rem; border: 1px solid rgba(255,255,255,0.1); border-radius: 4px; overflow: hidden;';
                    
                    // Create project header
                    const projectHeader = document.createElement('label');
                    projectHeader.className = 'assembly-project-item';
                    projectHeader.style.cssText = 'display: flex; align-items: center; padding: 0.5rem 0.75rem; gap: 1rem; cursor: pointer; background: rgba(255,255,255,0.02); transition: background-color 0.2s;';
                    projectHeader.addEventListener('mouseenter', () => projectHeader.style.backgroundColor = 'rgba(255,255,255,0.05)');
                    projectHeader.addEventListener('mouseleave', () => projectHeader.style.backgroundColor = 'rgba(255,255,255,0.02)');
                    
                    // Project checkbox
                    const projectCheckbox = document.createElement('input');
                    projectCheckbox.type = 'checkbox';
                    projectCheckbox.className = 'assembly-project-checkbox';
                    projectCheckbox.dataset.projectId = p.id;
                    projectCheckbox.checked = projectIsChecked;
                    projectCheckbox.style.cssText = 'width: 18px; height: 18px; flex-shrink: 0; cursor: pointer;';
                    
                    // When project checkbox changes, check/uncheck all its BOMs
                    projectCheckbox.addEventListener('change', (e) => {
                        const bomCheckboxes = projectDiv.querySelectorAll('.assembly-bom-checkbox');
                        bomCheckboxes.forEach(cb => cb.checked = e.target.checked);
                        if (this.checkAssemblyChanges) this.checkAssemblyChanges();
                    });
                    
                    // Project content
                    const projectContent = document.createElement('div');
                    projectContent.style.cssText = 'flex: 1; min-width: 0;';
                    
                    const projectTitle = document.createElement('div');
                    projectTitle.style.cssText = 'margin-bottom: 0.25rem; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;';
                    
                    const codeStrong = document.createElement('strong');
                    codeStrong.style.fontSize = '0.95rem';
                    codeStrong.textContent = p.code;
                    
                    const separator = document.createElement('span');
                    separator.style.cssText = 'color: #999; font-weight: 300;';
                    separator.textContent = '—';
                    
                    const nameSpan = document.createElement('span');
                    nameSpan.style.cssText = 'flex: 1; min-width: 0;';
                    nameSpan.textContent = p.name;
                    
                    projectTitle.appendChild(codeStrong);
                    projectTitle.appendChild(separator);
                    projectTitle.appendChild(nameSpan);
                    
                    const projectStatus = document.createElement('div');
                    projectStatus.style.cssText = 'font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem;';
                    
                    const statusLabel = document.createElement('span');
                    statusLabel.className = 'text-muted';
                    statusLabel.textContent = 'Status:';
                    
                    const statusBadge = document.createElement('span');
                    statusBadge.className = `badge badge-${statusBadgeClass}`;
                    statusBadge.style.cssText = 'font-size: 0.75rem; padding: 0.15rem 0.5rem;';
                    statusBadge.textContent = p.status || '-';
                    
                    const bomCount = document.createElement('span');
                    bomCount.className = 'text-muted';
                    bomCount.style.marginLeft = 'auto';
                    bomCount.textContent = `${projectBOMs.length} BOM${projectBOMs.length !== 1 ? 's' : ''}`;
                    
                    projectStatus.appendChild(statusLabel);
                    projectStatus.appendChild(statusBadge);
                    projectStatus.appendChild(bomCount);
                    
                    projectContent.appendChild(projectTitle);
                    projectContent.appendChild(projectStatus);
                    
                    projectHeader.appendChild(projectCheckbox);
                    projectHeader.appendChild(projectContent);
                    
                    projectDiv.appendChild(projectHeader);
                    
                    // Add BOMs list if project has BOMs
                    if (projectBOMs.length > 0) {
                        const bomsContainer = document.createElement('div');
                        bomsContainer.style.cssText = 'padding: 0.25rem 0.5rem 0.5rem 2.75rem; background: rgba(0,0,0,0.2);';
                        
                        projectBOMs.forEach(bom => {
                            const bomLabel = document.createElement('label');
                            bomLabel.className = 'assembly-bom-item';
                            bomLabel.style.cssText = 'display: flex; align-items: center; padding: 0.35rem 0.5rem; gap: 0.75rem; cursor: pointer; border-radius: 3px; transition: background-color 0.15s;';
                            bomLabel.addEventListener('mouseenter', () => bomLabel.style.backgroundColor = 'rgba(255,255,255,0.03)');
                            bomLabel.addEventListener('mouseleave', () => bomLabel.style.backgroundColor = 'transparent');
                            
                            const bomCheckbox = document.createElement('input');
                            bomCheckbox.type = 'checkbox';
                            bomCheckbox.className = 'assembly-bom-checkbox';
                            bomCheckbox.dataset.bomId = bom.id;
                            bomCheckbox.dataset.projectId = p.id;
                            bomCheckbox.checked = selectedBOMIds.includes(bom.id);
                            bomCheckbox.style.cssText = 'width: 16px; height: 16px; flex-shrink: 0; cursor: pointer;';
                            
                            // When BOM checkbox changes, update project checkbox
                            bomCheckbox.addEventListener('change', () => {
                                const allBOMCheckboxes = projectDiv.querySelectorAll('.assembly-bom-checkbox');
                                const checkedBOMs = Array.from(allBOMCheckboxes).filter(cb => cb.checked);
                                projectCheckbox.checked = checkedBOMs.length > 0;
                                if (this.checkAssemblyChanges) this.checkAssemblyChanges();
                            });
                            
                            const bomContent = document.createElement('div');
                            bomContent.style.cssText = 'flex: 1; min-width: 0; font-size: 0.9rem;';
                            
                            const bomTitle = document.createElement('div');
                            bomTitle.style.cssText = 'display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.15rem;';
                            
                            const skuCode = document.createElement('strong');
                            skuCode.style.fontSize = '0.875rem';
                            skuCode.style.color = '#6ccff6';
                            skuCode.textContent = bom.sku;
                            
                            const bomName = document.createElement('span');
                            bomName.style.cssText = 'flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;';
                            bomName.textContent = bom.name;
                            
                            bomTitle.appendChild(skuCode);
                            bomTitle.appendChild(bomName);
                            
                            const bomMeta = document.createElement('div');
                            bomMeta.style.cssText = 'font-size: 0.8rem; color: rgba(255,255,255,0.5);';
                            bomMeta.textContent = `Rev ${bom.current_revision} • ${bom.current_status || 'draft'}`;
                            
                            bomContent.appendChild(bomTitle);
                            bomContent.appendChild(bomMeta);
                            
                            bomLabel.appendChild(bomCheckbox);
                            bomLabel.appendChild(bomContent);
                            
                            bomsContainer.appendChild(bomLabel);
                        });
                        
                        projectDiv.appendChild(bomsContainer);
                    }
                    
                    projectsContainer.appendChild(projectDiv);
                });
                
                listContainer.innerHTML = '';
                listContainer.appendChild(projectsContainer);
            };

            renderList(projects);

            if (filterInput) {
                filterInput.addEventListener('input', () => {
                    const term = filterInput.value.trim().toLowerCase();
                    if (!term) {
                        renderList(projects);
                        return;
                    }
                    const filtered = projects.filter(p => {
                        const code = (p.code || '').toLowerCase();
                        const name = (p.name || '').toLowerCase();
                        const projectBOMs = bomsByProject[p.id] || [];
                        const hasBOMMatch = projectBOMs.some(bom => {
                            return (bom.sku || '').toLowerCase().includes(term) || 
                                   (bom.name || '').toLowerCase().includes(term);
                        });
                        return code.includes(term) || name.includes(term) || hasBOMMatch;
                    });
                    renderList(filtered);
                });
            }
        } catch (error) {
            this.logError('Load assembly projects selector error', error);
            if (listContainer) {
                listContainer.innerHTML = '<p class="text-danger">Failed to load projects and BOMs.</p>';
            }
        }
    },

    showProjectModal(project = null) {
        const isEdit = !!project;
        const modal = document.createElement('div');
        modal.className = 'modal modal-open';
        modal.innerHTML = `
            <div class="modal-dialog" role="dialog" style="max-width: 600px;">
                <div class="modal-content">
                    <button class="close-btn" data-action="close-project-modal" aria-label="Close">
                        <clr-icon shape="close"></clr-icon>
                    </button>
                    <div class="modal-header">
                        <h3 class="modal-title">${isEdit ? 'Edit Project' : 'Create New Project'}</h3>
                    </div>
                    <div class="modal-body">
                        ${isEdit ? `<input type="hidden" id="projectId" value="${project.id}">` : ''}
                        <div class="clr-form-control" style="margin-bottom: 1rem;">
                            <label class="clr-control-label" for="projectCode">Project Code *</label>
                            <input type="text" id="projectCode" class="clr-input" required placeholder="e.g., PRJ-2026-001" style="width: 100%;" value="${isEdit ? project.code : ''}">
                        </div>
                        
                        <div class="clr-form-control" style="margin-bottom: 1rem;">
                            <label class="clr-control-label" for="projectName">Project Name *</label>
                            <input type="text" id="projectName" class="clr-input" required placeholder="e.g., Next Gen Controller" style="width: 100%;" value="${isEdit ? project.name : ''}">
                        </div>
                        
                        <div class="clr-form-control" style="margin-bottom: 1rem;">
                            <label class="clr-control-label" for="projectDescription">Description</label>
                            <textarea id="projectDescription" class="clr-textarea" rows="3" placeholder="Project overview and objectives" style="width: 100%;">${isEdit ? project.description || '' : ''}</textarea>
                        </div>

                        <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
                            <div class="clr-form-control" style="flex: 1;">
                                <label class="clr-control-label" for="projectStatus">Status</label>
                                <select id="projectStatus" class="clr-select" style="width: 100%;">
                                    <option value="planning" ${isEdit && project.status === 'planning' ? 'selected' : ''}>Planning</option>
                                    <option value="active" ${(!isEdit || project.status === 'active') ? 'selected' : ''}>Active</option>
                                    <option value="on-hold" ${isEdit && project.status === 'on-hold' ? 'selected' : ''}>On Hold</option>
                                    <option value="completed" ${isEdit && project.status === 'completed' ? 'selected' : ''}>Completed</option>
                                    <option value="cancelled" ${isEdit && project.status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                                </select>
                            </div>
                            <div class="clr-form-control" style="flex: 1;">
                                <label class="clr-control-label" for="projectPriority">Priority</label>
                                <select id="projectPriority" class="clr-select" style="width: 100%;">
                                    <option value="low" ${isEdit && project.priority === 'low' ? 'selected' : ''}>Low</option>
                                    <option value="medium" ${(!isEdit || project.priority === 'medium') ? 'selected' : ''}>Medium</option>
                                    <option value="high" ${isEdit && project.priority === 'high' ? 'selected' : ''}>High</option>
                                    <option value="urgent" ${isEdit && project.priority === 'urgent' ? 'selected' : ''}>Urgent</option>
                                </select>
                            </div>
                        </div>

                        <div class="clr-form-control">
                            <label class="clr-control-label">
                                <input type="checkbox" id="projectIsOptional" ${isEdit && project.is_optional ? 'checked' : ''}>
                                <span>Mark as Optional Project</span>
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" data-action="close-project-modal">Cancel</button>
                        <button class="btn btn-primary" data-action="save-project" ${isEdit ? 'disabled' : ''}>${isEdit ? 'Save Changes' : 'Create Project'}</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        this.currentModal = modal;
        
        if (isEdit) {
            const saveBtn = modal.querySelector('[data-action="save-project"]');
            const inputs = modal.querySelectorAll('input, textarea, select');
            const originalValues = {
                code: project.code,
                name: project.name,
                description: project.description || '',
                status: project.status,
                priority: project.priority,
                is_optional: !!project.is_optional
            };

            const checkChanges = () => {
                const currentValues = {
                    code: document.getElementById('projectCode').value.trim(),
                    name: document.getElementById('projectName').value.trim(),
                    description: document.getElementById('projectDescription').value.trim(),
                    status: document.getElementById('projectStatus').value,
                    priority: document.getElementById('projectPriority').value,
                    is_optional: document.getElementById('projectIsOptional').checked
                };

                const hasChanges = JSON.stringify(originalValues) !== JSON.stringify(currentValues);
                saveBtn.disabled = !hasChanges;
            };

            inputs.forEach(input => {
                input.addEventListener('input', checkChanges);
                input.addEventListener('change', checkChanges);
            });
        }

        // Focus on code field
        setTimeout(() => document.getElementById('projectCode')?.focus(), 100);
    },

    closeProjectModal() {
        if (this.currentModal) {
            this.currentModal.remove();
            this.currentModal = null;
        }
    },

    async saveProject() {
        const id = document.getElementById('projectId')?.value;
        const modal = this.currentModal;
        if (!modal) return;

        const saveBtn = modal.querySelector('[data-action="save-project"]');
        const cancelButtons = modal.querySelectorAll('[data-action="close-project-modal"], .close-btn');

        const code = document.getElementById('projectCode')?.value.trim();
        const name = document.getElementById('projectName')?.value.trim();
        const description = document.getElementById('projectDescription')?.value.trim();
        const status = document.getElementById('projectStatus')?.value;
        const priority = document.getElementById('projectPriority')?.value;
        const isOptional = document.getElementById('projectIsOptional')?.checked ? 1 : 0;
        
        if (!code || !name) {
            alert('Project Code and Name are required');
            return;
        }
        
        // Disable buttons during submission
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.innerHTML = id ? 'Saving...' : 'Creating...';
        }
        cancelButtons.forEach(btn => btn.disabled = true);

        const data = {
            id: id ? parseInt(id) : undefined,
            code,
            name,
            description: description || null,
            status,
            priority,
            is_optional: isOptional
        };
        
        try {
            const response = id ? await API.updateProject(data) : await API.createProject(data);
            
            if (response.success) {
                this.closeProjectModal();
                
                // Show success message
                const successMsg = document.createElement('div');
                successMsg.className = 'alert alert-success';
                successMsg.style.cssText = 'position: fixed; top: 80px; right: 20px; z-index: 10000; padding: 1rem 1.5rem; background: #3c8500; color: white; border-radius: 4px; box-shadow: 0 4px 8px rgba(0,0,0,0.3);';
                successMsg.innerHTML = `<clr-icon shape="check-circle"></clr-icon> Project ${id ? 'updated' : 'created'} successfully!`;
                document.body.appendChild(successMsg);
                
                setTimeout(() => successMsg.remove(), 3000);
                
                // Reload data and navigate
                await this.loadAllData();
                
                if (id) {
                    // If we were on the project detail page, stay there and refresh
                    if (this.currentRoute.startsWith('projects/')) {
                        this.navigateTo(this.currentRoute);
                    } else {
                        this.navigateTo('projects');
                    }
                } else {
                    this.navigateTo('projects');
                }
            } else {
                alert(`Failed to ${id ? 'update' : 'create'} project: ${response.error || response.message || 'Unknown error'}`);
                // Re-enable buttons on failure
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = id ? 'Save Changes' : 'Create Project';
                }
                cancelButtons.forEach(btn => btn.disabled = false);
            }
        } catch (error) {
            this.logError('Save project error', error);
            alert(`Failed to save project. Error: ${error.message || 'Please try again.'}`);
            // Re-enable buttons on failure
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = id ? 'Save Changes' : 'Create Project';
            }
            cancelButtons.forEach(btn => btn.disabled = false);
        }
    },

    // ========== devapp Integration Methods ==========

    async showDevappSyncModal() {
        try {
            // Fetch devapp projects
            const response = await API.get('projects.php?action=list_devapp_projects');
            
            if (!response.success) {
                alert('Failed to fetch devapp projects: ' + (response.error || 'Unknown error'));
                return;
            }

            const devappProjects = response.data || [];
            
            if (devappProjects.length === 0) {
                alert('No projects found in devapp.');
                return;
            }

            // Filter out projects that already exist in bommer
            const existingCodes = this.data.projects.map(p => p.code);
            const newProjects = devappProjects.filter(p => !existingCodes.includes(p.generated_code));

            const modal = document.createElement('div');
            modal.className = 'modal modal-open';
            modal.innerHTML = `
                <div class="modal-dialog" role="dialog" style="max-width: 900px;">
                    <div class="modal-content">
                        <button class="close-btn" data-action="close-devapp-modal" aria-label="Close">
                            <clr-icon shape="close"></clr-icon>
                        </button>
                        <div class="modal-header">
                            <h3 class="modal-title">Sync Projects from devapp</h3>
                        </div>
                        <div class="modal-body">
                            <p>Found <strong>${devappProjects.length}</strong> projects in devapp.</p>
                            ${newProjects.length > 0 ? `
                                <p><strong>${newProjects.length}</strong> new projects available to import.</p>
                                <div style="max-height: 400px; overflow-y: auto; margin-top: 1rem;">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th><input type="checkbox" id="selectAllDevapp" checked></th>
                                                <th>Generated Code</th>
                                                <th>Name</th>
                                                <th>Status</th>
                                                <th>Created By</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${newProjects.map(p => `
                                                <tr>
                                                    <td><input type="checkbox" class="devapp-project-checkbox" value="${p.id}" checked></td>
                                                    <td><strong>${p.generated_code}</strong></td>
                                                    <td>${p.name}</td>
                                                    <td>${p.status}</td>
                                                    <td>User #${p.created_by_user_id}</td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                            ` : `
                                <p style="color: #666;">All devapp projects are already imported into bommer.</p>
                            `}
                            ${devappProjects.length > newProjects.length ? `
                                <p style="margin-top: 1rem; color: #666;"><em>${devappProjects.length - newProjects.length} projects already exist in bommer and will be skipped.</em></p>
                            ` : ''}
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-secondary" data-action="close-devapp-modal">Cancel</button>
                            ${newProjects.length > 0 ? `
                                <button class="btn btn-primary" data-action="import-devapp-projects">Import Selected Projects</button>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            this.currentModal = modal;

            // Handle select all checkbox
            const selectAll = modal.querySelector('#selectAllDevapp');
            if (selectAll) {
                selectAll.addEventListener('change', (e) => {
                    modal.querySelectorAll('.devapp-project-checkbox').forEach(cb => {
                        cb.checked = e.target.checked;
                    });
                });
            }

            // Handle close modal
            modal.querySelectorAll('[data-action="close-devapp-modal"]').forEach(btn => {
                btn.addEventListener('click', () => {
                    this.closeDevappModal();
                });
            });
        } catch (error) {
            this.logError('Show devapp sync modal error', error);
            alert('Failed to load devapp projects: ' + error.message);
        }
    },

    closeDevappModal() {
        if (this.currentModal) {
            this.currentModal.remove();
            this.currentModal = null;
        }
    },

    async importDevappProjects() {
        const modal = this.currentModal;
        if (!modal) return;

        const checkboxes = modal.querySelectorAll('.devapp-project-checkbox:checked');
        const selectedIds = Array.from(checkboxes).map(cb => parseInt(cb.value));

        if (selectedIds.length === 0) {
            alert('Please select at least one project to import.');
            return;
        }

        const importBtn = modal.querySelector('[data-action="import-devapp-projects"]');
        const cancelBtn = modal.querySelector('[data-action="close-devapp-modal"]');

        if (importBtn) {
            importBtn.disabled = true;
            importBtn.textContent = `Importing ${selectedIds.length} project(s)...`;
        }
        if (cancelBtn) cancelBtn.disabled = true;

        try {
            const response = await API.post('projects.php?action=import_from_devapp', {
                project_ids: selectedIds
            });

            if (response.success) {
                this.closeDevappModal();

                // Show success message
                const successMsg = document.createElement('div');
                successMsg.className = 'alert alert-success';
                successMsg.style.cssText = 'position: fixed; top: 80px; right: 20px; z-index: 10000; padding: 1rem 1.5rem; background: #3c8500; color: white; border-radius: 4px; box-shadow: 0 4px 8px rgba(0,0,0,0.3);';
                successMsg.innerHTML = `<clr-icon shape="check-circle"></clr-icon> Successfully imported ${response.data.imported} project(s) from devapp!`;
                document.body.appendChild(successMsg);

                setTimeout(() => successMsg.remove(), 3000);

                // Reload data and refresh view
                await this.loadAllData();
                this.navigateTo('projects');
            } else {
                alert('Failed to import projects: ' + (response.error || 'Unknown error'));
                if (importBtn) {
                    importBtn.disabled = false;
                    importBtn.textContent = 'Import Selected Projects';
                }
                if (cancelBtn) cancelBtn.disabled = false;
            }
        } catch (error) {
            this.logError('Import devapp projects error', error);
            alert('Failed to import projects: ' + error.message);
            if (importBtn) {
                importBtn.disabled = false;
                importBtn.textContent = 'Import Selected Projects';
            }
            if (cancelBtn) cancelBtn.disabled = false;
        }
    }
};

// Page Rendering Functions
const Pages = {
    // Dashboard
    async renderDashboard(data) {
        const totalBOMs = data.boms.length;
        const activeBOMs = data.boms.filter(b => b.current_status === 'approved').length;
        const totalProjects = data.projects.length;
        const activeProjects = data.projects.filter(p => p.status === 'active').length;
        const totalComponents = data.components.length;
        const lowStockComponents = data.components.filter(c => c.stock_level < c.min_stock).length;
        
        const logsResp = await API.listAuditLogs({ limit: 10 });
        const recentActivity = logsResp.data || [];
        
        return `
            <div class="content-header">
                <h1>Dashboard</h1>
                <p class="text-muted">Welcome to Bommer BOM Management System</p>
            </div>
            <div class="content-body">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-label">Total BOMs</div>
                        <div class="stat-value">${totalBOMs}</div>
                        <div class="text-muted stat-detail">
                            ${activeBOMs} approved
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Total Projects</div>
                        <div class="stat-value">${totalProjects}</div>
                        <div class="text-muted stat-detail">
                            ${activeProjects} active
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Total Components</div>
                        <div class="stat-value">${totalComponents}</div>
                        <div class="text-muted stat-detail">
                            ${lowStockComponents} low stock
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Assemblies</div>
                        <div class="stat-value">${data.assemblies.length}</div>
                    </div>
                </div>

                <div class="card">
                    <h2 class="card-title">Recent Activity</h2>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Entity</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${recentActivity.map(log => `
                                <tr>
                                    <td>${new Date(log.created_at).toLocaleString()}</td>
                                    <td>${log.full_name || log.username}</td>
                                    <td><span class="badge badge-blue">${log.action}</span></td>
                                    <td>${log.entity_type} #${log.entity_id}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    },

    // BOM List
    async renderBOMList(boms) {
        return `
            <div class="content-header">
                <h1>BOMs</h1>
                <p class="text-muted">Manage all Bill of Materials</p>
                <div class="content-header-actions">
                    <button class="btn btn-primary" onclick="navigateTo('boms/create')">+ Create New BOM</button>
                    <button class="btn" data-action="compare-boms">Compare BOMs</button>
                </div>
            </div>
            <div class="content-body">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Project</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>SKU</th>
                            <th>Revision</th>
                            <th>Status</th>
                            <th>Total Cost</th>
                            <th>Last Modified</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${boms.map(bom => {
                            // Ensure total_cost is a number and format with 3 decimal places
                            const totalCost = typeof bom.total_cost === 'number' ? bom.total_cost : parseFloat(bom.total_cost || 0);
                            return `
                            <tr onclick="navigateTo('boms/${bom.id}')">
                                <td>${bom.project_name || bom.project_code}</td>
                                <td>${bom.name}</td>
                                <td>${bom.description || '<span class="text-muted">—</span>'}</td>
                                <td><strong>${bom.sku}</strong></td>
                                <td>R${bom.current_revision}</td>
                                <td><span class="badge badge-${this.getStatusBadgeClass(bom.current_status)}">${bom.current_status || 'draft'}</span></td>
                                <td>¥${totalCost.toFixed(3)}</td>
                                <td>${new Date(bom.updated_at).toLocaleDateString()}</td>
                            </tr>
                        `}).join('')}
                    </tbody>
                </table>
            </div>
        `;
    },

    // BOM Detail
    renderBOMDetail(bom) {
        let totalCost = 0;
        let totalItems = 0;
        
        if (bom.groups) {
            bom.groups.forEach(group => {
                if (group.items) {
                    group.items.forEach(item => {
                        // Round each line item to 2 decimal places before summing to avoid floating point drift
                        const lineTotal = Math.round((parseFloat(item.quantity) * parseFloat(item.unit_cost || 0)) * 100) / 100;
                        totalCost = Math.round((totalCost + lineTotal) * 100) / 100;
                        totalItems++;
                    });
                }
            });
        }
        
        // Determine Edit button behavior based on status
        const status = bom.current_status || 'draft';
        let editButtonHtml = '';
        
        if (status === 'draft') {
            editButtonHtml = `<button class="btn" onclick="navigateTo('boms/${bom.id}/edit')">Edit BOM</button>`;
        } else if (status === 'approved') {
            editButtonHtml = `<button class="btn" onclick="alert('This BOM is approved. To make changes, create a new revision.')">Edit BOM</button>`;
        } else if (status === 'obsolete') {
            editButtonHtml = `<button class="btn" disabled title="This revision is obsolete">Edit BOM</button>`;
        } else if (status === 'invalidated') {
            editButtonHtml = `<button class="btn" disabled title="This BOM has been invalidated">Edit BOM</button>`;
        }
        
        return `
            <div class="content-header">
                <h1>${bom.name}</h1>
                <p class="text-muted">SKU: ${bom.sku} | Revision ${bom.current_revision} | Project: ${bom.project_name}</p>
                <div class="content-header-actions">
                    ${editButtonHtml}
                    <button class="btn" data-action="change-status" data-bom-id="${bom.id}" data-current-status="${bom.current_status || 'draft'}">Change Status</button>
                    <button class="btn" data-action="create-revision" data-bom-id="${bom.id}">Create Revision</button>
                    <button class="btn" data-action="create-variant" data-bom-id="${bom.id}" data-project-name="${bom.project_name || ''}" data-bom-name="${bom.name}" data-sku="${bom.sku}" data-variant-group="${bom.variant_group || ''}">Create Variant (New SKU)</button>
                    <button class="btn btn-primary" data-action="export-bom" data-bom-id="${bom.id}" data-bom-name="${bom.name}">Export</button>
                </div>
            </div>
            <div class="content-body">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-label">Status</div>
                        <div><span class="badge badge-${this.getStatusBadgeClass(bom.current_status)}">${bom.current_status || 'draft'}</span></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Total Items</div>
                        <div class="stat-value stat-value-medium">${totalItems}</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Total Cost</div>
                        <div class="stat-value stat-value-medium">¥${totalCost.toFixed(3)}</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Groups</div>
                        <div class="stat-value stat-value-medium">${bom.groups?.length || 0}</div>
                    </div>
                </div>

                ${bom.groups && bom.groups.length > 0 ? bom.groups.map(group => {
                    // Calculate group total with proper decimal precision
                    const groupTotal = group.items ? group.items.reduce((sum, item) => {
                        const lineTotal = Math.round((parseFloat(item.quantity) * parseFloat(item.unit_cost || 0)) * 100) / 100;
                        return Math.round((sum + lineTotal) * 100) / 100;
                    }, 0) : 0;
                    
                    return `
                    <div class="card">
                        <div class="card-header-with-total">
                            <h2 class="card-title">${group.name}</h2>
                            <span class="card-group-total">¥${groupTotal.toFixed(3)}</span>
                        </div>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th class="col-w-40">#</th>
                                    <th>Part Number</th>
                                    <th>Component Name</th>
                                    <th>Description</th>
                                    <th>Qty</th>
                                    <th>Unit Cost</th>
                                    <th>Total Cost</th>
                                    <th>Ref Designator</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${group.items && group.items.length > 0 ? group.items.map((item, idx) => `
                                    <tr>
                                        <td>${idx + 1}</td>
                                        <td><strong>${item.part_number}</strong></td>
                                        <td>${item.component_name}</td>
                                        <td class="text-muted">${item.description || '-'}</td>
                                        <td>${item.quantity}</td>
                                        <td>¥${parseFloat(item.unit_cost || 0).toFixed(3)}</td>
                                        <td>¥${(Math.round((parseFloat(item.quantity) * parseFloat(item.unit_cost || 0)) * 100) / 100).toFixed(3)}</td>
                                        <td>${item.reference_designator || '-'}</td>
                                    </tr>
                                `).join('') : '<tr><td colspan="8">No items in this group</td></tr>'}
                            </tbody>
                        </table>
                    </div>
                `}).join('') : '<div class="card"><p>No groups defined for this BOM</p></div>'}
            </div>
        `;
    },

    // Projects List
    async renderProjects(projects) {
        if (!projects || projects.length === 0) {
            return `
                <div class="content-header"><h1>Projects</h1></div>
                <div class="content-body"><div class="card"><p>No projects found.</p></div></div>
            `;
        }
        return `
            <div class="content-header">
                <h1>Projects</h1>
                <p class="text-muted">Manage all projects</p>
                <div class="content-header-actions">
                    <button class="btn" data-action="sync-devapp-projects"><clr-icon shape="sync"></clr-icon> Sync from devapp</button>
                    <button class="btn btn-primary" data-action="create-project">+ Create New Project</button>
                </div>
            </div>
            <div class="content-body">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>BOMs</th>
                            <th>Owner</th>
                            <th>Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${projects.map(project => `
                            <tr onclick="navigateTo('projects/${project.id}')">
                                <td><strong>${project.code}</strong></td>
                                <td>${project.name}</td>
                                <td><span class="badge badge-${this.getStatusBadgeClass(project.status)}">${project.status}</span></td>
                                <td><span class="badge badge-${this.getPriorityBadgeClass(project.priority)}">${project.priority}</span></td>
                                <td>${project.bom_count || 0}</td>
                                <td>${project.owner_name || '-'}</td>
                                <td>${new Date(project.updated_at).toLocaleDateString()}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    },

    // Project Detail  
    renderProjectDetail(project) {
        const allProjects = (AppRouter && AppRouter.data && Array.isArray(AppRouter.data.projects)) ? AppRouter.data.projects : [];
        const linkedOptionalIds = project.optionals ? project.optionals.map(o => o.id) : [];
        const availableOptionals = allProjects.filter(p => p.id !== project.id && !linkedOptionalIds.includes(p.id));

        return `
            <div class="content-header">
                <h1>${project.name}</h1>
                <p class="text-muted">Code: ${project.code} | Owner: ${project.owner_name}</p>
                <div class="content-header-actions">
                    <button class="btn" data-action="edit-project" data-project-id="${project.id}">Edit Project</button>
                    ${project.boms && project.boms.length >= 2 ? `<button class="btn" onclick="navigateTo('boms/matrix?scope=project&id=${project.id}')">View Matrix</button>` : ''}
                    <button class="btn btn-primary" data-action="add-bom" data-project-id="${project.id}">+ Add BOM</button>
                </div>
            </div>
            <div class="content-body">
                <div class="card">
                    <h2 class="card-title">Project Information</h2>
                    <p><strong>Description:</strong> ${project.description || 'No description'}</p>
                    <p><strong>Status:</strong> <span class="badge badge-${this.getStatusBadgeClass(project.status)}">${project.status}</span></p>
                    <p><strong>Priority:</strong> <span class="badge badge-${this.getPriorityBadgeClass(project.priority)}">${project.priority}</span></p>
                </div>

                <div class="card">
                    <h2 class="card-title">BOMs</h2>
                    ${project.boms && project.boms.length > 0 ? `
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>SKU</th>
                                    <th>Name</th>
                                    <th>Revision</th>
                                    <th>Status</th>
                                    <th>Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${project.boms.map(bom => `
                                    <tr onclick="navigateTo('boms/${bom.id}')">
                                        <td><strong>${bom.sku}</strong></td>
                                        <td>${bom.name}</td>
                                        <td>R${bom.current_revision}</td>
                                        <td><span class="badge badge-${this.getStatusBadgeClass(bom.current_status)}">${bom.current_status || 'draft'}</span></td>
                                        <td>${new Date(bom.updated_at).toLocaleDateString()}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    ` : '<p>No BOMs in this project</p>'}
                </div>

                <div class="card">
                    <h2 class="card-title">Optionals</h2>
                    ${project.optionals && project.optionals.length > 0 ? `
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Default</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${project.optionals.map(optionalProject => `
                                    <tr>
                                        <td><strong>${optionalProject.code}</strong></td>
                                        <td>${optionalProject.name}</td>
                                        <td>${optionalProject.optional_description || optionalProject.description || '-'}</td>
                                        <td>${optionalProject.is_default ? 'Yes' : 'No'}</td>
                                        <td>
                                            <button class="btn btn-sm" data-action="unlink-optional" data-base-project-id="${project.id}" data-optional-project-id="${optionalProject.id}">Remove</button>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    ` : '<p>No optionals linked to this project</p>'}

                    <div class="form-inline" style="margin-top: 1rem; gap: 0.5rem; align-items: center;">
                        <label for="optionalProjectSelect-${project.id}"><strong>Link optional project:</strong></label>
                        <select id="optionalProjectSelect-${project.id}" class="clr-select" style="min-width: 240px;">
                            <option value="">Select project...</option>
                            ${availableOptionals.map(p => `
                                <option value="${p.id}">${p.code} - ${p.name}</option>
                            `).join('')}
                        </select>
                        <button class="btn btn-primary btn-sm" data-action="link-optional" data-base-project-id="${project.id}">Link Optional</button>
                    </div>
                </div>
            </div>
        `;
    },

    // Optionals Management Page
    async renderOptionals(optionals, allProjects) {
        const activeOptionals = optionals;
        const projectsWithLinks = allProjects.filter(p => p.optionals && p.optionals.length > 0);
        const nonOptionals = allProjects.filter(p => !p.is_optional);
        
        return `
            <div class="content-header">
                <h1>Project Optionals</h1>
                <p class="text-muted">Manage reusable optional projects and their links to base products</p>
            </div>
            <div class="content-body">
                <!-- NEW SECTION: Project Overview -->
                <div class="card">
                    <h2 class="card-title">Project Relationships (Base Products & Their Optionals)</h2>
                    <p class="text-muted" style="margin-bottom: 1rem;">Overview of which projects currently have optionals linked to them.</p>
                    ${projectsWithLinks.length > 0 ? `
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Base Project</th>
                                    <th>Status</th>
                                    <th>Linked Optionals</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${projectsWithLinks.map(p => `
                                    <tr>
                                        <td>
                                            <a href="javascript:void(0)" onclick="navigateTo('projects/${p.id}')" style="text-decoration:none">
                                                <strong>${p.code}</strong><br>
                                                <span class="text-muted">${p.name}</span>
                                            </a>
                                        </td>
                                        <td><span class="badge badge-${this.getStatusBadgeClass(p.status)}">${p.status}</span></td>
                                        <td>
                                            <div style="display: flex; flex-wrap: wrap; gap: 4px;">
                                                ${p.optionals.map(opt => `
                                                    <span class="badge badge-purple" title="${opt.optional_project_name}">
                                                        ${opt.optional_project_code}
                                                        <clr-icon shape="close" size="12" style="cursor:pointer; margin-left:4px" 
                                                            data-action="unlink-optional" 
                                                            data-base-project-id="${p.id}" 
                                                            data-optional-project-id="${opt.optional_project_id}"
                                                            title="Remove link"></clr-icon>
                                                    </span>
                                                `).join('')}
                                            </div>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-link" onclick="navigateTo('projects/${p.id}')">Manage All</button>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    ` : '<p class="text-muted">No projects currently have optionals linked.</p>'}
                </div>

                <div class="card">
                    <h2 class="card-title">Active Optionals (Optional-centric View)</h2>
                    <p class="text-muted" style="margin-bottom: 1rem;">Projects marked as "Optional" and which base products they are available for.</p>
                    ${activeOptionals.length > 0 ? `
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Optional Project</th>
                                    <th>Category</th>
                                    <th>BOMs</th>
                                    <th>Available For (Base Projects)</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${activeOptionals.map(opt => `
                                    <tr>
                                        <td>
                                            <a href="javascript:void(0)" onclick="navigateTo('projects/${opt.id}')" style="text-decoration:none">
                                                <strong>${opt.code}</strong><br>
                                                <span class="text-muted">${opt.name}</span>
                                            </a>
                                        </td>
                                        <td>${opt.optional_category || '-'}</td>
                                        <td>${opt.bom_count}</td>
                                        <td>
                                            <div class="links-container" style="display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 8px;">
                                                ${opt.links && opt.links.length > 0 ? opt.links.map(link => `
                                                    <span class="badge badge-blue" style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px;">
                                                        ${link.base_project_code}
                                                        <clr-icon shape="close" size="12" style="cursor:pointer" 
                                                            data-action="unlink-optional" 
                                                            data-base-project-id="${link.base_project_id}" 
                                                            data-optional-project-id="${opt.id}"
                                                            title="Remove link"></clr-icon>
                                                    </span>
                                                `).join('') : '<span class="text-muted">Not linked to any projects</span>'}
                                            </div>
                                            <div class="form-inline" style="display: flex; align-items: center; gap: 8px;">
                                                <select id="linkBaseSelect-${opt.id}" class="clr-select clr-select-sm" style="min-width: 150px;">
                                                    <option value="">Link to project...</option>
                                                    ${allProjects.filter(p => p.id !== opt.id && !(opt.links && opt.links.some(l => l.base_project_id == p.id))).map(p => `
                                                        <option value="${p.id}">${p.code} - ${p.name}</option>
                                                    `).join('')}
                                                </select>
                                                <button class="btn btn-sm btn-link" data-action="link-optional-from-page" data-optional-project-id="${opt.id}">Link</button>
                                            </div>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-danger" data-action="toggle-project-optional" data-project-id="${opt.id}" data-is-optional="false">Disable Optional</button>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    ` : '<p class="text-muted">No projects marked as optional. See below to enable.</p>'}
                </div>

                <div class="card">
                    <h2 class="card-title">Available Projects (to mark as Optional)</h2>
                    ${nonOptionals.length > 0 ? `
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Project Code</th>
                                    <th>Name</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${nonOptionals.map(p => `
                                    <tr>
                                        <td><strong>${p.code}</strong></td>
                                        <td>${p.name}</td>
                                        <td><span class="badge badge-${this.getStatusBadgeClass(p.status)}">${p.status}</span></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" data-action="toggle-project-optional" data-project-id="${p.id}" data-is-optional="true">Mark as Optional</button>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    ` : '<p class="text-muted">All projects are already marked as optional.</p>'}
                </div>
            </div>
        `;
    },

    // Assemblies List
    async renderAssemblies(assemblies) {
        if (!assemblies || assemblies.length === 0) {
            return `
                <div class="content-header"><h1>Assemblies</h1></div>
                <div class="content-body"><div class="card"><p>No assemblies found.</p></div></div>
            `;
        }
        return `
            <div class="content-header">
                <h1>Assemblies</h1>
                <p class="text-muted">Manage assemblies</p>
                <div class="content-header-actions">
                    <button class="btn btn-primary" data-action="create-assembly">+ Create Assembly</button>
                </div>
            </div>
            <div class="content-body">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Projects</th>
                            <th>Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${assemblies.map(assembly => `
                            <tr onclick="navigateTo('assemblies/${assembly.id}')">
                                <td><strong>${assembly.code}</strong></td>
                                <td>${assembly.name}</td>
                                <td>${assembly.category || '-'}</td>
                                <td>${assembly.project_count || 0}</td>
                                <td>${new Date(assembly.updated_at).toLocaleDateString()}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    },

    renderAssemblyDetail(assembly) {
        const bomCount = assembly.boms ? assembly.boms.length : 0;
        
        return `
            <div class="content-header">
                <h1>${assembly.name}</h1>
                <p class="text-muted">Code: ${assembly.code}</p>
                <div class="content-header-actions">
                    <button class="btn" data-action="edit-assembly" data-assembly-id="${assembly.id}">Edit Assembly</button>
                    ${bomCount >= 2 ? `<button class="btn" onclick="navigateTo('boms/matrix?scope=assembly&id=${assembly.id}')">View Matrix</button>` : ''}
                </div>
            </div>
            <div class="content-body">
                <div class="card">
                    <h2 class="card-title">Assembly Information</h2>
                    <p><strong>Description:</strong> ${assembly.description || 'No description'}</p>
                    <p><strong>Category:</strong> ${assembly.category || '-'}</p>
                </div>

                <div class="card">
                    <h2 class="card-title">Projects (${assembly.projects ? assembly.projects.length : 0})</h2>
                    ${assembly.projects && assembly.projects.length > 0 ? `
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Status</th>
                                    <th>Optionals</th>
                                    <th>Added</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${assembly.projects.map(project => `
                                    <tr onclick="navigateTo('projects/${project.id}')">
                                        <td><strong>${project.code}</strong></td>
                                        <td>${project.name}</td>
                                        <td><span class="badge badge-${this.getStatusBadgeClass(project.status)}">${project.status}</span></td>
                                        <td>${project.optionals && project.optionals.length > 0 ? project.optionals.map(o => o.code).join(', ') : '-'}</td>
                                        <td>${new Date(project.added_at).toLocaleDateString()}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    ` : '<p>No projects in this assembly</p>'}
                </div>

                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <h2 class="card-title" style="margin: 0;">BOMs (${bomCount})</h2>
                        ${bomCount > 0 ? `
                            <input 
                                type="search" 
                                id="assemblyBOMSearch" 
                                class="clr-input" 
                                placeholder="Filter by SKU or name..." 
                                style="width: 300px;"
                                onkeyup="AppRouter.filterAssemblyBOMs()"
                            />
                        ` : ''}
                    </div>
                    ${assembly.boms && assembly.boms.length > 0 ? `
                        <table class="data-table" id="assemblyBOMTable">
                            <thead>
                                <tr>
                                    <th>SKU</th>
                                    <th>Name</th>
                                    <th>Project</th>
                                    <th>Revision</th>
                                    <th>Status</th>
                                    <th>Last Modified</th>
                                </tr>
                            </thead>
                            <tbody id="assemblyBOMTableBody">
                                ${assembly.boms.map(bom => `
                                    <tr onclick="navigateTo('boms/${bom.id}')" 
                                        data-sku="${bom.sku.toLowerCase()}" 
                                        data-name="${(bom.name || '').toLowerCase()}">
                                        <td><strong>${bom.sku}</strong></td>
                                        <td>${bom.name}</td>
                                        <td>${bom.project_name || '-'}</td>
                                        <td>R${bom.current_revision}</td>
                                        <td><span class="badge badge-${this.getStatusBadgeClass(bom.current_status)}">${bom.current_status || 'draft'}</span></td>
                                        <td>${new Date(bom.updated_at).toLocaleDateString()}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    ` : '<p>No BOMs in this assembly</p>'}
                </div>
            </div>
        `;
    },

    filterAssemblyBOMs() {
        const searchInput = document.getElementById('assemblyBOMSearch');
        const tbody = document.getElementById('assemblyBOMTableBody');
        
        if (!searchInput || !tbody) return;
        
        const filter = searchInput.value.toLowerCase().trim();
        const rows = tbody.getElementsByTagName('tr');
        
        let visibleCount = 0;
        
        for (let i = 0; i < rows.length; i++) {
            const row = rows[i];
            const sku = row.getAttribute('data-sku') || '';
            const name = row.getAttribute('data-name') || '';
            
            if (sku.includes(filter) || name.includes(filter)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        }
        
        // Update card title with filter count
        const cardTitle = document.querySelector('.card h2.card-title');
        if (cardTitle && filter) {
            const totalCount = rows.length;
            cardTitle.textContent = `BOMs (${visibleCount} of ${totalCount})`;
        } else if (cardTitle) {
            cardTitle.textContent = `BOMs (${rows.length})`;
        }
    },

    async renderComponents(components, sourceFilter = 'bommer') {
        const bommerCount = components.filter(c => c.source === 'bommer').length;
        const erpCount = components.filter(c => c.source === 'erp').length;
        const totalCount = components.length;
        
        return `
            <div class="content-header">
                <h1>Components</h1>
                <p class="text-muted">Manage component library (${bommerCount} Bommer${erpCount > 0 ? `, ${erpCount} ERP` : ''})</p>
                <div class="content-header-actions">
                    <div class="btn-group" style="margin-right: 1rem;">
                        <button class="btn ${sourceFilter === 'bommer' ? 'btn-primary' : 'btn-secondary'}" 
                                onclick="navigateTo('components?source=bommer')">
                            <clr-icon shape="storage"></clr-icon>
                            <span>Bommer Only</span>
                        </button>
                        <button class="btn ${sourceFilter === 'all' ? 'btn-primary' : 'btn-secondary'}" 
                                onclick="navigateTo('components?source=all')">
                            <clr-icon shape="organization"></clr-icon>
                            <span>All Sources</span>
                        </button>
                    </div>
                    <button class="btn btn-primary" data-action="create-component">+ Add Component</button>
                </div>
            </div>
            <div class="content-body">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Part Number</th>
                            <th>Name</th>
                            <th>Source</th>
                            <th>Category</th>
                            <th>Manufacturer</th>
                            <th>MPN</th>
                            <th>Unit Cost</th>
                            <th>Where Used</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${components.map(comp => {
                            const source = comp.source || 'bommer';
                            const sourceLabel = source === 'erp' ? 'ERP' : 'Bommer';
                            const sourceBadge = source === 'erp' ? 'info' : 'success';
                            const sourceIcon = source === 'erp' ? 'building' : 'storage';
                            return `
                            <tr onclick="navigateTo('components/${comp.id}')">
                                <td><strong>${comp.part_number}</strong></td>
                                <td>${comp.name}</td>
                                <td>
                                    <span class="badge badge-${sourceBadge}" style="display: inline-flex; align-items: center; gap: 4px;">
                                        <clr-icon shape="${sourceIcon}"></clr-icon>
                                        <span>${sourceLabel}</span>
                                    </span>
                                </td>
                                <td>${comp.category || '-'}</td>
                                <td>${comp.manufacturer || '-'}</td>
                                <td>${comp.mpn || '-'}</td>
                                <td>¥${parseFloat(comp.unit_cost || 0).toFixed(3)}</td>
                                <td class="text-center">${comp.where_used_count || 0} ${(comp.where_used_count || 0) === 1 ? 'BOM' : 'BOMs'}</td>
                                <td><span class="badge badge-${this.getStatusBadgeClass(comp.status)}">${comp.status}</span></td>
                            </tr>
                        `}).join('')}
                    </tbody>
                </table>
            </div>
        `;
    },

    renderComponentDetail(component) {
        const isEditable = component.source !== 'erp';
        const sourceLabel = component.source === 'erp' ? 'ERP' : 'Bommer';
        
        return `
            <div class="content-header">
                <h1>${component.name}</h1>
                <p class="text-muted">Part Number: ${component.part_number} ${component.source ? `<span class="badge badge-${component.source === 'erp' ? 'info' : 'success'}" style="margin-left: 0.5rem;">${sourceLabel}</span>` : ''}</p>
                <div class="content-header-actions">
                    ${isEditable ? `
                        <button class="btn btn-primary" data-action="edit-component" data-component-id="${component.id}">
                            <clr-icon shape="pencil"></clr-icon>
                            <span>Edit Component</span>
                        </button>
                    ` : `
                        <button class="btn" disabled title="ERP components are read-only">
                            <clr-icon shape="lock"></clr-icon>
                            <span>Read-Only (ERP)</span>
                        </button>
                    `}
                </div>
            </div>
            <div class="content-body">
                <div class="card">
                    <h2 class="card-title">Component Information</h2>
                    <p><strong>Description:</strong> ${component.description || '-'}</p>
                    <p><strong>Category:</strong> ${component.category || '-'}</p>
                    <p><strong>Manufacturer:</strong> ${component.manufacturer || '-'}</p>
                    <p><strong>MPN:</strong> ${component.mpn || '-'}</p>
                    <p><strong>Supplier:</strong> ${component.supplier || '-'}</p>
                    <p><strong>Unit Cost:</strong> ¥${parseFloat(component.unit_cost || 0).toFixed(3)}</p>
                    <p><strong>Stock Level:</strong> ${component.stock_level}</p>
                    <p><strong>Min Stock:</strong> ${component.min_stock}</p>
                    <p><strong>Lead Time:</strong> ${component.lead_time_days} days</p>
                    <p><strong>Status:</strong> <span class="badge badge-${this.getStatusBadgeClass(component.status)}">${component.status}</span></p>
                    ${component.notes ? `<p><strong>Notes:</strong> ${component.notes}</p>` : ''}
                </div>

                <div class="card">
                    <h2 class="card-title">Where Used</h2>
                    ${component.used_in_boms && component.used_in_boms.length > 0 ? `
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>BOM SKU</th>
                                    <th>BOM Name</th>
                                    <th>Project</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${component.used_in_boms.map(bom => `
                                    <tr onclick="navigateTo('boms/${bom.id}')">
                                        <td><strong>${bom.sku}</strong></td>
                                        <td>${bom.name}</td>
                                        <td>${bom.project_name}</td>
                                        <td><span class="badge badge-${this.getStatusBadgeClass(bom.bom_status)}">${bom.bom_status}</span></td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    ` : '<p>This component is not used in any BOMs</p>'}
                </div>
            </div>
        `;
    },

    renderAuditLog(logs) {
        return `
            <div class="content-header">
                <h1>Audit Log</h1>
                <p class="text-muted">System activity history</p>
            </div>
            <div class="content-body">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Entity Type</th>
                            <th>Entity ID</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${logs.map(log => `
                            <tr>
                                <td>${new Date(log.created_at).toLocaleString()}</td>
                                <td>${log.full_name || log.username}</td>
                                <td><span class="badge badge-blue">${log.action}</span></td>
                                <td>${log.entity_type}</td>
                                <td>#${log.entity_id}</td>
                                <td class="text-muted">${log.ip_address || '-'}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    },

    renderUsers() {
        return '<iframe src="/admin/admin-users.php" class="iframe-fullheight"></iframe>';
    },

    renderGroups(groups) {
        // Check if we came from BOM creation context (using localStorage for cross-page persistence)
        const fromBOMContext = localStorage.getItem('bomCreationContext') === 'true';
        
        return `
            <div class="content-header">
                <h1>Component Groups</h1>
                <p class="text-muted">Manage BOM component group templates (Admin only)</p>
                <div class="content-header-actions">
                    ${fromBOMContext ? `
                        <button class="btn btn-secondary" data-action="back-to-bom-creation">
                            <clr-icon shape="arrow-left"></clr-icon>
                            <span>Back to BOM Creation</span>
                        </button>
                    ` : ''}
                    <button class="btn btn-primary" data-action="create-group">+ Create New Group</button>
                </div>
            </div>
            <div class="content-body">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Display Order</th>
                            <th>Status</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${groups.map(group => `
                            <tr>
                                <td><strong>${group.name}</strong></td>
                                <td>${group.description || '<span class="text-muted">—</span>'}</td>
                                <td>${group.display_order}</td>
                                <td><span class="badge badge-${group.is_active ? 'success' : 'danger'}">${group.is_active ? 'Active' : 'Inactive'}</span></td>
                                <td>${group.created_by_name || group.created_by_username}</td>
                                <td>
                                    <button class="btn btn-sm" data-action="edit-group" data-group-id="${group.id}" title="Edit">
                                        <clr-icon shape="pencil"></clr-icon>
                                    </button>
                                    <button class="btn btn-sm" data-action="toggle-group" data-group-id="${group.id}" data-is-active="${group.is_active}" title="${group.is_active ? 'Deactivate' : 'Activate'}">
                                        <clr-icon shape="${group.is_active ? 'ban' : 'check'}"></clr-icon>
                                    </button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    },

    renderAccount() {
        return `
            <div class="content-header">
                <h1>Account Settings</h1>
            </div>
            <div class="content-body">
                <div class="card">
                    <h2 class="card-title">Profile Information</h2>
                    <p>Account settings will be implemented here.</p>
                </div>
            </div>
        `;
    },

    renderBOMCreate() {
        // Return container for BOM creation app
        return `
            <div id="bom-create-app" class="bom-app-container">
                <!-- Loading state -->
                <div class="loading-overlay" id="loadingOverlay">
                    <div class="spinner"></div>
                    <p>Loading BOM Creator...</p>
                </div>
            </div>
        `;
    },

    renderBOMCompare(idsParam = '') {
        console.log('[renderBOMCompare] idsParam:', idsParam);
        const iframeSrc = idsParam 
            ? `comparison.html?ids=${idsParam}`
            : 'comparison.html';
        console.log('[renderBOMCompare] iframe src:', iframeSrc);
        return `<iframe src="${iframeSrc}" class="iframe-content"></iframe>`;
    },

    renderBOMMatrix(scope, id) {
        if (!scope || !id) {
            // No scope/id provided, show error message
            return `
                <div class="content-header">
                    <h1>BOM Configuration Matrix</h1>
                </div>
                <div class="content-body">
                    <div class="card">
                        <h2>Missing Parameters</h2>
                        <p>Matrix view requires a scope (project or assembly) and ID.</p>
                        <p>Please navigate to a Project or Assembly and click "View Matrix" from there.</p>
                        <div style="margin-top: 1rem;">
                            <button class="btn btn-primary" onclick="navigateTo('projects')">Go to Projects</button>
                            <button class="btn" onclick="navigateTo('assemblies')">Go to Assemblies</button>
                        </div>
                    </div>
                </div>
            `;
        }
        
        const iframeSrc = `matrix.html?scope=${scope}&id=${id}`;
        return `<iframe src="${iframeSrc}" class="iframe-content"></iframe>`;
    },

    renderSearchResults(query, results) {
        const totalResults = results.boms.length + results.projects.length + results.assemblies.length + results.components.length + results.optionals.length;
        
        return `
            <div class="content-header">
                <h1>Search Results</h1>
                <p class="text-muted">Found ${totalResults} results for "${query}"</p>
            </div>
            <div class="content-body">
                ${results.boms.length > 0 ? `
                    <div class="card">
                        <h2 class="card-title">BOMs (${results.boms.length})</h2>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>SKU</th>
                                    <th>Name</th>
                                    <th>Project</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${results.boms.map(bom => `
                                    <tr onclick="navigateTo('boms/${bom.id}')">
                                        <td><strong>${bom.sku}</strong></td>
                                        <td>${bom.name}</td>
                                        <td>${bom.project_name}</td>
                                        <td><span class="badge badge-${this.getStatusBadgeClass(bom.current_status)}">${bom.current_status}</span></td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                ` : ''}

                ${results.projects.length > 0 ? `
                    <div class="card">
                        <h2 class="card-title">Projects (${results.projects.length})</h2>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${results.projects.map(project => `
                                    <tr onclick="navigateTo('projects/${project.id}')">
                                        <td><strong>${project.code}</strong></td>
                                        <td>${project.name}</td>
                                        <td><span class="badge badge-${this.getStatusBadgeClass(project.status)}">${project.status}</span></td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                ` : ''}

                ${results.assemblies.length > 0 ? `
                    <div class="card">
                        <h2 class="card-title">Assemblies (${results.assemblies.length})</h2>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>Projects</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${results.assemblies.map(assembly => `
                                    <tr onclick="navigateTo('assemblies/${assembly.id}')">
                                        <td><strong>${assembly.code}</strong></td>
                                        <td>${assembly.name}</td>
                                        <td>${assembly.category || '-'}</td>
                                        <td>${assembly.project_count || 0}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                ` : ''}

                ${results.components.length > 0 ? `
                    <div class="card">
                        <h2 class="card-title">Components (${results.components.length})</h2>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Part Number</th>
                                    <th>Name</th>
                                    <th>Source</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${results.components.map(component => {
                                    const source = component.source || 'bommer';
                                    const sourceLabel = source === 'erp' ? 'ERP' : 'Bommer';
                                    const sourceBadge = source === 'erp' ? 'info' : 'success';
                                    const sourceIcon = source === 'erp' ? 'building' : 'storage';
                                    return `
                                    <tr onclick="navigateTo('components/${component.id}')">
                                        <td><strong>${component.part_number}</strong></td>
                                        <td>${component.name}</td>
                                        <td>
                                            <span class="badge badge-${sourceBadge}" style="display: inline-flex; align-items: center; gap: 4px;">
                                                <clr-icon shape="${sourceIcon}"></clr-icon>
                                                <span>${sourceLabel}</span>
                                            </span>
                                        </td>
                                        <td><span class="badge badge-${this.getStatusBadgeClass(component.status)}">${component.status}</span></td>
                                    </tr>
                                `}).join('')}
                            </tbody>
                        </table>
                    </div>
                ` : ''}

                ${results.optionals.length > 0 ? `
                    <div class="card">
                        <h2 class="card-title">Optional Projects (${results.optionals.length})</h2>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>Linked To</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${results.optionals.map(optional => `
                                    <tr onclick="navigateTo('projects/${optional.id}')">
                                        <td><strong>${optional.code}</strong></td>
                                        <td>${optional.name}</td>
                                        <td>${optional.optional_category || '-'}</td>
                                        <td>${(optional.links || []).length} base project(s)</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                ` : ''}

                ${totalResults === 0 ? '<div class="card"><p>No results found</p></div>' : ''}
            </div>
        `;
    },

    // Utility functions
    getStatusBadgeClass(status) {
        const statusMap = {
            'approved': 'success',
            'active': 'success',
            'draft': 'warning',
            'planning': 'blue',
            'on_hold': 'warning',
            'obsolete': 'danger',
            'invalidated': 'danger',
            'cancelled': 'danger',
            'completed': 'success',
            'banned': 'danger'
        };
        return statusMap[status] || 'blue';
    },

    getPriorityBadgeClass(priority) {
        const priorityMap = {
            'critical': 'danger',
            'high': 'warning',
            'medium': 'blue',
            'low': 'light-blue'
        };
        return priorityMap[priority] || 'blue';
    }
};

// Global navigation function
function navigateTo(route) {
    AppRouter.navigateTo(route);
}

// Logout function
function logout() {
    window.location.href = '/auth/logout.php';
}

// Make router globally available
const router = AppRouter;
window.router = router;

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    AppRouter.init();
});
