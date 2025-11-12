/**
 * ElektraWeb VB Desktop Application - JavaScript Components
 * Interactive behaviors for VB-style UI components
 */

(function() {
    'use strict';

    // ============================================
    // 1. SIDEBAR TOGGLE
    // ============================================
    function initSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const toggleBtn = document.querySelector('.sidebar-toggle button');
        const sidebarItems = document.querySelectorAll('.sidebar-menu-item');

        if (toggleBtn && sidebar) {
            toggleBtn.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                
                // Save state to localStorage
                const isCollapsed = sidebar.classList.contains('collapsed');
                localStorage.setItem('sidebarCollapsed', isCollapsed);
                
                // Update button icon
                const icon = this.querySelector('i');
                if (icon) {
                    icon.className = isCollapsed ? 'fas fa-chevron-right' : 'fas fa-chevron-left';
                }
            });

            // Restore sidebar state from localStorage
            const savedState = localStorage.getItem('sidebarCollapsed');
            if (savedState === 'true') {
                sidebar.classList.add('collapsed');
                const icon = toggleBtn.querySelector('i');
                if (icon) {
                    icon.className = 'fas fa-chevron-right';
                }
            }
        }

        // Active menu item highlighting
        sidebarItems.forEach(item => {
            item.addEventListener('click', function(e) {
                // Remove active from all items
                sidebarItems.forEach(i => i.classList.remove('active'));
                // Add active to clicked item
                this.classList.add('active');
            });
        });

        // Auto-highlight current page
        const currentPath = window.location.pathname;
        sidebarItems.forEach(item => {
            const link = item.getAttribute('href') || item.dataset.href;
            if (link && currentPath.includes(link)) {
                item.classList.add('active');
            }
        });
    }

    // ============================================
    // 2. SIDEBAR SEARCH
    // ============================================
    function initSidebarSearch() {
        const searchInput = document.querySelector('.sidebar-search input');
        const menuItems = document.querySelectorAll('.sidebar-menu-item');

        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();

                menuItems.forEach(item => {
                    const text = item.textContent.toLowerCase();
                    const matches = text.includes(searchTerm);
                    item.style.display = matches ? 'flex' : 'none';
                });

                // Show/hide menu headers
                const headers = document.querySelectorAll('.sidebar-menu-header');
                headers.forEach(header => {
                    const nextItems = [];
                    let sibling = header.nextElementSibling;
                    while (sibling && !sibling.classList.contains('sidebar-menu-header')) {
                        if (sibling.classList.contains('sidebar-menu-item')) {
                            nextItems.push(sibling);
                        }
                        sibling = sibling.nextElementSibling;
                    }
                    const hasVisibleItems = nextItems.some(item => item.style.display !== 'none');
                    header.style.display = hasVisibleItems ? 'block' : 'none';
                });
            });
        }
    }

    // ============================================
    // 3. DATAGRID ROW SELECTION
    // ============================================
    function initDataGrid() {
        const dataGridRows = document.querySelectorAll('.vb-datagrid tbody tr');

        dataGridRows.forEach(row => {
            row.addEventListener('click', function(e) {
                // Don't select if clicking on a button or link
                if (e.target.tagName === 'BUTTON' || e.target.tagName === 'A') {
                    return;
                }

                // Toggle selection
                const isSelected = this.classList.contains('selected');
                
                // Remove selection from all rows (single select mode)
                dataGridRows.forEach(r => r.classList.remove('selected'));
                
                // Add selection to clicked row
                if (!isSelected) {
                    this.classList.add('selected');
                }
            });

            // Double-click to edit/view
            row.addEventListener('dblclick', function() {
                const rowId = this.dataset.id;
                if (rowId) {
                    console.log('Double-click on row:', rowId);
                    // Add your edit/view logic here
                }
            });
        });

        // Header checkbox for select all
        const headerCheckbox = document.querySelector('.vb-datagrid thead input[type="checkbox"]');
        const rowCheckboxes = document.querySelectorAll('.vb-datagrid tbody input[type="checkbox"]');

        if (headerCheckbox) {
            headerCheckbox.addEventListener('change', function() {
                const isChecked = this.checked;
                rowCheckboxes.forEach(checkbox => {
                    checkbox.checked = isChecked;
                    const row = checkbox.closest('tr');
                    if (row) {
                        row.classList.toggle('selected', isChecked);
                    }
                });
            });
        }

        // Individual row checkboxes
        rowCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const row = this.closest('tr');
                if (row) {
                    row.classList.toggle('selected', this.checked);
                }

                // Update header checkbox state
                if (headerCheckbox) {
                    const allChecked = Array.from(rowCheckboxes).every(cb => cb.checked);
                    const someChecked = Array.from(rowCheckboxes).some(cb => cb.checked);
                    headerCheckbox.checked = allChecked;
                    headerCheckbox.indeterminate = someChecked && !allChecked;
                }
            });
        });
    }

    // ============================================
    // 4. DATAGRID SORTING
    // ============================================
    function initDataGridSorting() {
        const headers = document.querySelectorAll('.vb-datagrid th[data-sortable]');
        
        headers.forEach(header => {
            header.style.cursor = 'pointer';
            header.innerHTML += ' <i class="fas fa-sort" style="opacity: 0.3; font-size: 12px;"></i>';

            header.addEventListener('click', function() {
                const table = this.closest('table');
                const tbody = table.querySelector('tbody');
                const rows = Array.from(tbody.querySelectorAll('tr'));
                const columnIndex = this.cellIndex;
                const currentSort = this.dataset.sort || 'none';
                
                // Reset all headers
                headers.forEach(h => {
                    h.dataset.sort = 'none';
                    const icon = h.querySelector('i');
                    if (icon) icon.className = 'fas fa-sort';
                });

                // Determine new sort direction
                let newSort = 'asc';
                if (currentSort === 'asc') {
                    newSort = 'desc';
                }
                this.dataset.sort = newSort;

                // Update icon
                const icon = this.querySelector('i');
                if (icon) {
                    icon.className = newSort === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down';
                }

                // Sort rows
                rows.sort((a, b) => {
                    const aValue = a.cells[columnIndex].textContent.trim();
                    const bValue = b.cells[columnIndex].textContent.trim();

                    // Try to parse as number
                    const aNum = parseFloat(aValue.replace(/[^0-9.-]/g, ''));
                    const bNum = parseFloat(bValue.replace(/[^0-9.-]/g, ''));

                    if (!isNaN(aNum) && !isNaN(bNum)) {
                        return newSort === 'asc' ? aNum - bNum : bNum - aNum;
                    }

                    // String comparison
                    return newSort === 'asc' 
                        ? aValue.localeCompare(bValue, 'tr')
                        : bValue.localeCompare(aValue, 'tr');
                });

                // Reorder rows
                rows.forEach(row => tbody.appendChild(row));
            });
        });
    }

    // ============================================
    // 5. ROOM RACK INTERACTIONS
    // ============================================
    function initRoomRack() {
        const roomCards = document.querySelectorAll('.room-card');

        roomCards.forEach(card => {
            card.addEventListener('click', function() {
                // Toggle selection
                const isSelected = this.classList.contains('selected');
                
                // Remove selection from all cards (single select mode)
                roomCards.forEach(c => c.classList.remove('selected'));
                
                // Add selection to clicked card
                if (!isSelected) {
                    this.classList.add('selected');
                }

                // Get room data
                const roomNumber = this.dataset.roomNumber;
                const roomStatus = this.dataset.roomStatus;
                console.log('Room selected:', roomNumber, roomStatus);
            });

            // Double-click to open details
            card.addEventListener('dblclick', function() {
                const roomNumber = this.dataset.roomNumber;
                console.log('Open room details:', roomNumber);
                // Add your room details modal logic here
            });
        });
    }

    // ============================================
    // 6. FILTERS BAR
    // ============================================
    function initFilters() {
        const filterInputs = document.querySelectorAll('.filters-bar input, .filters-bar select');
        const clearBtn = document.querySelector('[data-action="clear-filters"]');
        const applyBtn = document.querySelector('[data-action="apply-filters"]');

        // Auto-apply on change (optional)
        filterInputs.forEach(input => {
            input.addEventListener('change', function() {
                // Add your filter logic here
                console.log('Filter changed:', this.name, this.value);
            });
        });

        // Clear filters button
        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                filterInputs.forEach(input => {
                    if (input.type === 'checkbox') {
                        input.checked = false;
                    } else {
                        input.value = '';
                    }
                });
                console.log('Filters cleared');
            });
        }

        // Apply filters button
        if (applyBtn) {
            applyBtn.addEventListener('click', function() {
                const filters = {};
                filterInputs.forEach(input => {
                    if (input.value) {
                        filters[input.name] = input.value;
                    }
                });
                console.log('Filters applied:', filters);
                // Add your filter application logic here
            });
        }
    }

    // ============================================
    // 7. TOOLBAR ACTIONS
    // ============================================
    function initToolbarActions() {
        // Print button
        const printBtn = document.querySelector('[data-action="print"]');
        if (printBtn) {
            printBtn.addEventListener('click', function() {
                window.print();
            });
        }

        // Export button
        const exportBtn = document.querySelector('[data-action="export"]');
        if (exportBtn) {
            exportBtn.addEventListener('click', function() {
                console.log('Export data');
                // Add your export logic here
            });
        }

        // Refresh button
        const refreshBtn = document.querySelector('[data-action="refresh"]');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function() {
                location.reload();
            });
        }
    }

    // ============================================
    // 8. MODULE ICONS (Top Bar)
    // ============================================
    function initModuleIcons() {
        const moduleIcons = document.querySelectorAll('.module-icon');
        
        moduleIcons.forEach(icon => {
            icon.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Remove active from all
                moduleIcons.forEach(i => i.classList.remove('active'));
                
                // Add active to clicked
                this.classList.add('active');
                
                // Navigate or show module
                const moduleName = this.dataset.module;
                console.log('Module selected:', moduleName);
                
                // Optionally navigate
                const href = this.getAttribute('href');
                if (href && href !== '#') {
                    window.location.href = href;
                }
            });
        });
    }

    // ============================================
    // 9. USER MENU DROPDOWN
    // ============================================
    function initUserMenu() {
        const userMenu = document.querySelector('.user-menu');
        const dropdown = document.querySelector('.user-menu-dropdown');

        if (userMenu && dropdown) {
            userMenu.addEventListener('click', function(e) {
                e.stopPropagation();
                dropdown.classList.toggle('show');
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function() {
                dropdown.classList.remove('show');
            });
        }
    }

    // ============================================
    // 10. TOOLTIPS (Simple implementation)
    // ============================================
    function initTooltips() {
        const tooltipElements = document.querySelectorAll('[data-tooltip]');
        
        tooltipElements.forEach(element => {
            element.addEventListener('mouseenter', function() {
                const tooltipText = this.dataset.tooltip;
                const tooltip = document.createElement('div');
                tooltip.className = 'vb-tooltip';
                tooltip.textContent = tooltipText;
                document.body.appendChild(tooltip);

                const rect = this.getBoundingClientRect();
                tooltip.style.position = 'fixed';
                tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
                tooltip.style.top = rect.bottom + 8 + 'px';
                tooltip.style.background = '#333';
                tooltip.style.color = 'white';
                tooltip.style.padding = '6px 10px';
                tooltip.style.borderRadius = '3px';
                tooltip.style.fontSize = '12px';
                tooltip.style.zIndex = '10000';
                tooltip.style.pointerEvents = 'none';

                this._tooltip = tooltip;
            });

            element.addEventListener('mouseleave', function() {
                if (this._tooltip) {
                    this._tooltip.remove();
                    this._tooltip = null;
                }
            });
        });
    }

    // ============================================
    // 11. FORM VALIDATION (VB-style)
    // ============================================
    function initFormValidation() {
        const forms = document.querySelectorAll('form[data-validate]');
        
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const requiredFields = this.querySelectorAll('[required]');
                let isValid = true;

                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        isValid = false;
                        field.style.borderColor = 'var(--button-danger)';
                        field.style.boxShadow = '0 0 0 2px rgba(231, 76, 60, 0.2)';
                    } else {
                        field.style.borderColor = '';
                        field.style.boxShadow = '';
                    }
                });

                if (!isValid) {
                    e.preventDefault();
                    alert('Lütfen tüm zorunlu alanları doldurun.');
                }
            });
        });
    }

    // ============================================
    // 12. STATUS BAR UPDATES
    // ============================================
    function initStatusBar() {
        const statusBar = document.querySelector('.statusbar');
        
        if (statusBar) {
            // Update time every second
            const timeElement = document.querySelector('[data-status="time"]');
            if (timeElement) {
                setInterval(function() {
                    const now = new Date();
                    const timeString = now.toLocaleTimeString('tr-TR', {
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit'
                    });
                    timeElement.textContent = timeString;
                }, 1000);
            }

            // Update connection status
            const connectionElement = document.querySelector('[data-status="connection"]');
            if (connectionElement) {
                window.addEventListener('online', function() {
                    connectionElement.textContent = 'Bağlı';
                    connectionElement.style.color = 'var(--status-clean)';
                });

                window.addEventListener('offline', function() {
                    connectionElement.textContent = 'Bağlantı Yok';
                    connectionElement.style.color = 'var(--button-danger)';
                });
            }
        }
    }

    // ============================================
    // 13. KEYBOARD SHORTCUTS
    // ============================================
    function initKeyboardShortcuts() {
        document.addEventListener('keydown', function(e) {
            // Ctrl+F: Focus search
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                const search = document.querySelector('.sidebar-search input');
                if (search) search.focus();
            }

            // Ctrl+N: New record
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                const newBtn = document.querySelector('[data-action="new"]');
                if (newBtn) newBtn.click();
            }

            // Ctrl+S: Save
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                const saveBtn = document.querySelector('[data-action="save"]');
                if (saveBtn) saveBtn.click();
            }

            // Ctrl+P: Print
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                window.print();
            }

            // F5: Refresh
            if (e.key === 'F5' && !e.ctrlKey) {
                // Allow normal F5 behavior
            }

            // Escape: Close modals/clear selection
            if (e.key === 'Escape') {
                document.querySelectorAll('.selected').forEach(el => {
                    el.classList.remove('selected');
                });
            }
        });
    }

    // ============================================
    // INITIALIZE ALL COMPONENTS
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🚀 ElektraWeb VB Components initialized');
        
        initSidebar();
        initSidebarSearch();
        initDataGrid();
        initDataGridSorting();
        initRoomRack();
        initFilters();
        initToolbarActions();
        initModuleIcons();
        initUserMenu();
        initTooltips();
        initFormValidation();
        initStatusBar();
        initKeyboardShortcuts();
    });

    // ============================================
    // UTILITY FUNCTIONS (Global)
    // ============================================
    window.VBComponents = {
        // Show notification
        notify: function(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = 'vb-notification ' + type;
            notification.textContent = message;
            notification.style.position = 'fixed';
            notification.style.top = '20px';
            notification.style.right = '20px';
            notification.style.padding = '12px 20px';
            notification.style.background = type === 'success' ? 'var(--button-success)' : 
                                          type === 'error' ? 'var(--button-danger)' : 
                                          'var(--button-primary)';
            notification.style.color = 'white';
            notification.style.borderRadius = 'var(--border-radius-small)';
            notification.style.boxShadow = 'var(--shadow-medium)';
            notification.style.zIndex = '10000';
            document.body.appendChild(notification);

            setTimeout(function() {
                notification.style.opacity = '0';
                notification.style.transition = 'opacity 0.3s';
                setTimeout(function() {
                    notification.remove();
                }, 300);
            }, 3000);
        },

        // Confirm dialog (VB MessageBox style)
        confirm: function(message, callback) {
            if (confirm(message)) {
                callback();
            }
        },

        // Loading overlay
        showLoading: function() {
            const overlay = document.createElement('div');
            overlay.id = 'vb-loading-overlay';
            overlay.style.position = 'fixed';
            overlay.style.top = '0';
            overlay.style.left = '0';
            overlay.style.width = '100%';
            overlay.style.height = '100%';
            overlay.style.background = 'rgba(0, 0, 0, 0.5)';
            overlay.style.display = 'flex';
            overlay.style.alignItems = 'center';
            overlay.style.justifyContent = 'center';
            overlay.style.zIndex = '10000';
            overlay.innerHTML = '<div style="background: white; padding: 30px; border-radius: 4px; text-align: center;"><i class="fas fa-spinner fa-spin" style="font-size: 32px; color: var(--button-primary);"></i><p style="margin-top: 12px;">Yükleniyor...</p></div>';
            document.body.appendChild(overlay);
        },

        hideLoading: function() {
            const overlay = document.getElementById('vb-loading-overlay');
            if (overlay) {
                overlay.remove();
            }
        }
    };

})();


