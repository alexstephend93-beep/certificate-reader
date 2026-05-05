// public/assets/js/database.js

// Global variables
let currentDbId = null;
let currentTable = null;
let lastQueryResults = null;      // For display (max 100 rows)
let lastQueryResultsFull = null;  // For export (all rows)
let activeConnectionsRefreshInterval = null;
let runningQueriesRefreshInterval = null;
let currentOpenModal = null; // ADDED: Track currently open modal
let isSwitchingDatabase = false;
let pendingDbSwitch = null;
let isInitialized = false;
let isTestingConnection = false;
let testTimeoutId = null;
let lastExecutedQuery = '';
let lastExecutionTime = 0;

// SSH DB Import state
let sshImportCancelled = false;
let sshImportProgress = {
    current: 0,
    total: 0,
    imported: 0,
    skipped: 0,
    errors: []
};
let sshImportServers = []; // List of servers to process

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeDatabaseManager();
    setupKeyboardShortcuts();
    setupModalCleanup(); // ADDED: Setup modal cleanup
});

function initializeDatabaseManager() {
    if (isInitialized) return;
    isInitialized = true;
    
    // Auto-select default database if present
    const defaultDb = document.querySelector('#dbConnection option[selected]');
    if (defaultDb && defaultDb.value) {
        currentDbId = defaultDb.value;
        // Load data without triggering change event
        loadTablesData(currentDbId);
        testConnectionData(currentDbId);
        updateActiveConnectionsCount();
        // Fetch phpMyAdmin info for default
        fetchPhpMyAdminInfo(currentDbId);
    }
    
    // Initialize Select2 on database dropdown
    const $dbSelect = $('#dbConnection');
    if ($dbSelect.length && typeof $ !== 'undefined' && $.fn.select2) {
        $dbSelect.select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: '-- Select Database --',
            allowClear: false,
            minimumResultsForSearch: 0, // Always show search
            dropdownAutoWidth: true,
            language: {
                noResults: function() { return "No databases found"; },
                searching: function() { return "Searching..."; }
            }
        });
        
        // Attach change handler
        $dbSelect.on('change', function(e) {
            e.stopPropagation();
            switchDatabase();
            fetchPhpMyAdminInfo($(this).val());
        });
    } else {
        // Fallback to plain select
        const dbConnection = document.getElementById('dbConnection');
        if (dbConnection && !dbConnection.hasAttribute('data-listener-attached')) {
            dbConnection.setAttribute('data-listener-attached', 'true');
            dbConnection.addEventListener('change', function(e) {
                e.stopPropagation();
                switchDatabase();
                fetchPhpMyAdminInfo(dbConnection.value);
            });
        }
    }
    
    // Load SSH DB import pending count for badge
    fetchSshImportPendingCount();
}

/**
 * Fetch phpMyAdmin info for selected database credential
 */
function fetchPhpMyAdminInfo(credentialId) {
    if (!credentialId) {
        document.getElementById('phpmyadminInfo').style.display = 'none';
        return;
    }
    
    fetch(`/ssh/phpmyadmin-info/${credentialId}`)
        .then(r => r.json())
        .then(data => {
            const container = document.getElementById('phpmyadminInfo');
            const content = document.getElementById('phpmyadminContent');
            
            if (!data.success) {
                container.style.display = 'none';
                return;
            }
            
            // Build content
            let html = '';
            
            // phpMyAdmin URL(s)
            if (data.domain_url) {
                html += `
                    <div class="mb-2">
                        <a href="${data.domain_url}" target="_blank" class="btn btn-sm btn-outline-primary w-100" id="phpmyadminUrlBtn">
                            <i class="bi bi-box-arrow-up-right me-1"></i>Open phpMyAdmin (HTTPS)
                        </a>
                        <small class="text-muted d-block mt-1">URL: <code>${data.domain_url}</code></small>
                    </div>
                `;
            } else if (data.possible_urls && data.possible_urls.length > 0) {
                // Show first URL as primary
                const primaryUrl = data.possible_urls[0];
                html += `
                    <div class="mb-2">
                        <a href="${primaryUrl}" target="_blank" class="btn btn-sm btn-outline-primary w-100" id="phpmyadminUrlBtn">
                            <i class="bi bi-box-arrow-up-right me-1"></i>Open phpMyAdmin
                        </a>
                        <small class="text-muted d-block mt-1">Trying: <code>${primaryUrl}</code></small>
                    </div>
                `;
            }
            
            // Database credentials
            html += `
                <div class="row g-2 mt-2">
                    <div class="col-6">
                        <small class="text-muted">Database:</small>
                        <div class="fw-bold"><code>${data.database_name}</code></div>
                    </div>
                    <div class="col-6">
                        <small class="text-muted">Username:</small>
                        <div class="fw-bold"><code>${data.database_username}</code></div>
                    </div>
                    <div class="col-12 mt-2">
                        <small class="text-muted">Password:</small>
                        <div class="input-group input-group-sm">
                            <input type="password" class="form-control" value="${data.database_password || ''}" readonly id="pmapass">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePass('pmapass')">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-outline-primary" type="button" onclick="copyToClipboard('${data.database_password || ''}')">
                                <i class="bi bi-clipboard"></i>
                            </button>
                        </div>
                        <small class="text-muted">Keep this secure!</small>
                    </div>
                </div>
            `;
            
            content.innerHTML = html;
            container.style.display = 'block';
        })
        .catch(err => {
            console.error('Failed to fetch phpMyAdmin info:', err);
            document.getElementById('phpmyadminInfo').style.display = 'none';
        });
}

/**
 * Toggle password visibility
 */
function togglePass(inputId) {
    const input = document.getElementById(inputId);
    if (input.type === 'password') {
        input.type = 'text';
    } else {
        input.type = 'password';
    }
}

/**
 * Copy text to clipboard
 */
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('Copied to clipboard', 'success');
    }).catch(err => {
        showToast('Failed to copy', 'danger');
    });
}

/**
 * Fetch and update SSH import pending count badge
 */
function fetchSshImportPendingCount() {
    fetch('/ssh/import-db-status')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.pending_count > 0) {
                const badge = document.getElementById('importPendingBadge');
                if (badge) {
                    badge.textContent = data.pending_count;
                    badge.style.display = 'inline-block';
                }
            }
        })
        .catch(err => console.error('Failed to fetch import status:', err));
}

// ADDED: Setup modal cleanup function
function setupModalCleanup() {
    // Clean up any lingering modal states on page load
    const backdrops = document.querySelectorAll('.modal-backdrop');
    backdrops.forEach(backdrop => backdrop.remove());
    document.body.classList.remove('modal-open');
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';
    
    // Add event listener for all modal hidden events
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.addEventListener('hidden.bs.modal', function() {
            // Remove any lingering backdrops
            const backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach(backdrop => backdrop.remove());
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
            currentOpenModal = null;
        });
    });
}

// ADDED: Function to close all modals
function closeAllModals() {
    const modals = ['dbModal', 'manageConnectionsModal', 'runningQueriesModal', 'activeConnectionsModal', 'tableDetailsModal'];
    modals.forEach(modalId => {
        const modalElement = document.getElementById(modalId);
        if (modalElement) {
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) {
                modal.hide();
            }
        }
    });
    
    // Remove any lingering backdrops
    const backdrops = document.querySelectorAll('.modal-backdrop');
    backdrops.forEach(backdrop => backdrop.remove());
    document.body.classList.remove('modal-open');
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';
}

// ADDED: Function to show a single modal
function showSingleModal(modalId, options = {}) {
    // Close any open modal first
    closeAllModals();
    
    // Small delay to ensure previous modal is completely closed
    setTimeout(() => {
        const modalElement = document.getElementById(modalId);
        if (modalElement) {
            // Clean up any existing state
            modalElement.removeAttribute('aria-hidden');
            
            // Initialize and show modal
            const modal = new bootstrap.Modal(modalElement, {
                backdrop: options.backdrop !== undefined ? options.backdrop : 'static',
                keyboard: options.keyboard !== undefined ? options.keyboard : true
            });
            modal.show();
            currentOpenModal = modalId;
            
            // Handle modal hidden event to clean up
            modalElement.addEventListener('hidden.bs.modal', function onHidden() {
                modalElement.removeEventListener('hidden.bs.modal', onHidden);
                currentOpenModal = null;
                // Remove any lingering backdrops
                const backdrops = document.querySelectorAll('.modal-backdrop');
                backdrops.forEach(backdrop => backdrop.remove());
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
            });
        } else {
            console.error('Modal element not found:', modalId);
        }
    }, 150);
}

// ADDED: Open Add Connection Modal (for first connection)
function openAddConnectionModal() {
    // Reset form
    resetDbForm();
    document.getElementById('dbModalLabel').innerHTML = '<i class="bi bi-database-add"></i> Add Database Connection';
    document.getElementById('dbId').value = '';
    
    // Close manage modal if open, then open add modal
    const manageModal = document.getElementById('manageConnectionsModal');
    if (manageModal && manageModal.classList.contains('show')) {
        const modal = bootstrap.Modal.getInstance(manageModal);
        if (modal) {
            modal.hide();
            // Wait for manage modal to close before opening add modal
            setTimeout(() => {
                showSingleModal('dbModal');
            }, 200);
        } else {
            showSingleModal('dbModal');
        }
    } else {
        showSingleModal('dbModal');
    }
}

function setupKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Ctrl+Enter to run query
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            runQuery();
        }
        // Ctrl+F to format query
        if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
            e.preventDefault();
            formatQuery();
        }
        // ESC to close modal if open
        if (e.key === 'Escape' && currentOpenModal) {
            const modalElement = document.getElementById(currentOpenModal);
            if (modalElement) {
                const modal = bootstrap.Modal.getInstance(modalElement);
                if (modal) {
                    modal.hide();
                }
            }
        }
    });
}

// Database switching
function switchDatabase() {
    const dbSelect = document.getElementById('dbConnection');
    const newDbId = dbSelect.value;
    
    // Don't switch if same database
    if (newDbId === currentDbId) return;
    
    // If already switching, queue this switch
    if (isSwitchingDatabase) {
        pendingDbSwitch = newDbId;
        return;
    }
    
    // Handle deselection
    if (!newDbId) {
        document.getElementById('tablesList').innerHTML = 
            '<div class="text-center text-muted py-4"><i class="bi bi-database fs-1"></i><p class="mt-2">Select a database to view tables</p></div>';
        // Hide phpMyAdmin card
        document.getElementById('phpmyadminInfo').style.display = 'none';
        currentDbId = null;
        isSwitchingDatabase = false;
        return;
    }
    
    // Perform switch
    isSwitchingDatabase = true;
    currentDbId = newDbId;
    
    if (currentDbId) {
        // Show loading state
        showTablesLoading();
        
        // Load data in parallel
        Promise.all([
            loadTablesData(currentDbId),
            testConnectionData(currentDbId)
        ]).then(() => {
            updateActiveConnectionsCount();
            fetchPhpMyAdminInfo(currentDbId);
            isSwitchingDatabase = false;
            
            // Process pending switch if any
            if (pendingDbSwitch !== null && pendingDbSwitch !== currentDbId) {
                const tempPending = pendingDbSwitch;
                pendingDbSwitch = null;
                // Use Select2 if available to properly set value and trigger change
                if (window.$ && window.$.fn.select2) {
                    $('#dbConnection').val(tempPending).trigger('change');
                } else {
                    document.getElementById('dbConnection').value = tempPending;
                    switchDatabase();
                }
            }
        }).catch(() => {
            isSwitchingDatabase = false;
            if (pendingDbSwitch !== null) {
                const tempPending = pendingDbSwitch;
                pendingDbSwitch = null;
                // Use Select2 if available to properly set value and trigger change
                if (window.$ && window.$.fn.select2) {
                    $('#dbConnection').val(tempPending).trigger('change');
                } else {
                    document.getElementById('dbConnection').value = tempPending;
                    switchDatabase();
                }
            }
        });
    } else {
        document.getElementById('tablesList').innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-database fs-1"></i><p class="mt-2">Select a database to view tables</p></div>';
        // Hide phpMyAdmin card
        document.getElementById('phpmyadminInfo').style.display = 'none';
        isSwitchingDatabase = false;
    }
}

function showTablesLoading() {
    const container = document.getElementById('tablesList');
    container.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2 text-muted">Loading tables...</p>
        </div>
    `;
}

function loadTablesData(dbId) {
    return fetch(`/database/tables/${dbId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayTables(data.tables);
                document.getElementById('tableCountBadge').textContent = data.tables.length;
            } else {
                showToast('Error loading tables: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Failed to load tables', 'danger');
        });
}

function testConnectionData(dbId) {
    const statusDiv = document.getElementById('connectionStatus');
    const messageSpan = document.getElementById('statusMessage');
    
    statusDiv.style.display = 'block';
    statusDiv.className = 'alert alert-info mt-3';
    messageSpan.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Testing connection (this may take up to 10 seconds)...';
    
    // Create abort controller for timeout
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 15000); // 15 second timeout
    
    return fetch('/database/test-connection', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf_token
        },
        body: JSON.stringify({ id: dbId }),
        signal: controller.signal
    })
    .then(response => response.json())
    .then(data => {
        clearTimeout(timeoutId);
        
        if (data.success) {
            statusDiv.className = 'alert alert-success mt-3';
            const timeMsg = data.response_time_ms ? `(${data.response_time_ms}ms)` : '';
            messageSpan.innerHTML = `<i class="bi bi-check-circle me-2"></i>${data.message} ${timeMsg}`;
        } else {
            statusDiv.className = 'alert alert-danger mt-3';
            messageSpan.innerHTML = `<i class="bi bi-exclamation-triangle me-2"></i>${data.message}`;
        }
        
        setTimeout(() => {
            statusDiv.style.display = 'none';
        }, 5000);
    })
    .catch(error => {
        clearTimeout(timeoutId);
        
        if (error.name === 'AbortError') {
            statusDiv.className = 'alert alert-warning mt-3';
            messageSpan.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Connection test taking too long. Server might be slow or unreachable. Please check your network.';
        } else {
            statusDiv.className = 'alert alert-danger mt-3';
            messageSpan.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>Connection test failed: ' + (error.message || 'Unknown error');
        }
        
        setTimeout(() => {
            statusDiv.style.display = 'none';
        }, 8000);
    });
}

// Load tables for selected database
function loadTables(dbId) {
    if (!dbId) return;
    loadTablesData(dbId);
}

function displayTables(tables) {
    const container = document.getElementById('tablesList');
    
    if (!tables || tables.length === 0) {
        container.innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-table fs-1"></i><p class="mt-2">No tables found</p></div>';
        return;
    }
    
    let html = '';
    tables.forEach(table => {
        html += `
            <div class="table-item" onclick="selectTable('${table.name}')">
                <i class="bi bi-table"></i>
                <span class="table-name">${escapeHtml(table.name)}</span>
                <i class="bi bi-eye-fill eye-icon" 
                   style="cursor: pointer; margin-left: auto; color: var(--color-primary); font-size: 1.1rem;"
                   onclick="event.stopPropagation(); loadTableDetails('${table.name}')"
                   title="View Table Details">
                </i>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function selectTable(tableName) {
    currentTable = tableName;
    
    // Highlight selected table
    document.querySelectorAll('.table-item').forEach(item => {
        item.classList.remove('active');
        if (item.querySelector('.table-name')?.innerText === tableName) {
            item.classList.add('active');
        }
    });
    
    // Load table structure and preview
    loadTableStructure(currentDbId, tableName);
    
    // Generate SELECT query
    document.getElementById('sqlQuery').value = `SELECT * FROM \`${tableName}\` LIMIT 100;`;
}

function loadTableStructure(dbId, tableName) {
    fetch(`/database/table-structure/${dbId}/${tableName}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show structure in a tooltip or modal
                console.log('Table structure:', data.structure);
            }
        })
        .catch(error => console.error('Error:', error));
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}


// Test Connection
function testConnection() {
    if (!currentDbId) {
        showToast('Please select a database first', 'warning');
        return;
    }
    
    // Prevent multiple simultaneous tests
    if (isTestingConnection) {
        showToast('Connection test already in progress...', 'info');
        return;
    }
    
    isTestingConnection = true;
    
    const statusDiv = document.getElementById('connectionStatus');
    const messageSpan = document.getElementById('statusMessage');
    
    statusDiv.style.display = 'block';
    statusDiv.className = 'alert alert-info mt-3';
    messageSpan.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Testing connection (this may take up to 10 seconds)...';
    
    // Create abort controller
    const controller = new AbortController();
    testTimeoutId = setTimeout(() => controller.abort(), 15000);
    
    fetch('/database/test-connection', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf_token
        },
        body: JSON.stringify({ id: currentDbId }),
        signal: controller.signal
    })
    .then(response => response.json())
    .then(data => {
        clearTimeout(testTimeoutId);
        
        if (data.success) {
            statusDiv.className = 'alert alert-success mt-3';
            const timeMsg = data.response_time_ms ? `(${data.response_time_ms}ms)` : '';
            messageSpan.innerHTML = `<i class="bi bi-check-circle me-2"></i>${data.message} ${timeMsg}`;
            showToast('Connection successful!', 'success');
        } else {
            statusDiv.className = 'alert alert-danger mt-3';
            messageSpan.innerHTML = `<i class="bi bi-exclamation-triangle me-2"></i>${data.message}`;
            showToast('Connection failed: ' + data.message, 'danger');
        }
        
        setTimeout(() => {
            statusDiv.style.display = 'none';
        }, 5000);
        isTestingConnection = false;
    })
    .catch(error => {
        clearTimeout(testTimeoutId);
        
        if (error.name === 'AbortError') {
            statusDiv.className = 'alert alert-warning mt-3';
            messageSpan.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Connection test timeout. The server is taking too long to respond. Please check your network and server status.';
            showToast('Connection timeout - server not responding', 'warning');
        } else {
            statusDiv.className = 'alert alert-danger mt-3';
            messageSpan.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>Connection test failed: ' + (error.message || 'Unknown error');
            showToast('Connection test failed', 'danger');
        }
        
        setTimeout(() => {
            statusDiv.style.display = 'none';
        }, 8000);
        isTestingConnection = false;
    });
}


function testSimpleQuery() {
    if (!currentDbId) {
        showToast('Please select a database first', 'warning');
        return;
    }
    
    // Test with a simple query
    const testQuery = 'SELECT 1 as test, NOW() as current_time, DATABASE() as current_db';
    document.getElementById('sqlQuery').value = testQuery;
    runQuery();
}

// Query Execution
function runQuery() {
    const query = document.getElementById('sqlQuery').value.trim();
    
    if (!query) {
        showToast('Please enter a SQL query', 'warning');
        return;
    }
    
    if (!currentDbId) {
        showToast('Please select a database first', 'warning');
        return;
    }
    
    // Show loading state
    showQueryLoading(true);
    
    const startTime = performance.now();
    
    fetch('/database/run-query', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf_token
        },
        body: JSON.stringify({
            id: currentDbId,
            query: query
        })
    })
    .then(response => response.json())
    .then(data => {
        const endTime = performance.now();
        const executionTime = (endTime - startTime).toFixed(2);
        
        if (data.success) {
            const results = data.results || [];
            lastQueryResults = results.slice(0, 100);
            lastQueryResultsFull = results;
            lastExecutedQuery = query;
            lastExecutionTime = executionTime;
            
            if (results.length > 0) {
                displayQueryResultsInModal(lastQueryResults, results.length, executionTime, query);
                showToast(`Query executed successfully. ${results.length} rows returned.`, 'success');
            } else if (data.affected_rows !== undefined) {
                showQuerySuccessModal(data.affected_rows);
                showToast(`Query executed successfully. ${data.affected_rows} rows affected.`, 'success');
                // Refresh tables if DDL
                if (query.toLowerCase().includes('create table') || 
                    query.toLowerCase().includes('drop table') ||
                    query.toLowerCase().includes('alter table')) {
                    loadTables(currentDbId);
                }
            } else {
                // Empty result with no affected rows
                displayQuerySuccessEmptyModal(executionTime, query);
                showToast('Query executed successfully', 'success');
            }
        } else {
            displayQueryError(data.message);
            showToast('Query execution failed', 'danger');
        }
        
        showQueryLoading(false);
    })
    .catch(error => {
        console.error('Error:', error);
        displayQueryError('Network error or server issue');
        showQueryLoading(false);
        showToast('Failed to execute query', 'danger');
    });
}

function displayQueryResultsInModal(displayRows, totalRows, executionTime, query) {
    // Set modal header info first
    document.getElementById('queryInfo').textContent = query.substring(0, 100) + (query.length > 100 ? '...' : '');
    document.getElementById('executionTime').innerHTML = `<i class="bi bi-clock me-1"></i>${executionTime}ms`;
    
    const header = document.getElementById('queryResultHeader');
    const body = document.getElementById('queryResultBody');
    
    if (!displayRows || displayRows.length === 0) {
        // Show "no results" message
        header.innerHTML = '<tr><th>Message</th></tr>';
        body.innerHTML = `
            <tr>
                <td class="text-center py-5">
                    <i class="bi bi-info-circle fs-1 text-muted"></i>
                    <p class="mt-2">Query executed successfully but returned no rows.</p>
                </td>
            </tr>
        `;
        document.getElementById('resultRowCount').innerHTML = '0 rows';
        showSingleModal('queryResultsModal');
        return;
    }
    
    const headers = Object.keys(displayRows[0]);
    
    // Generate table header
    let headerHtml = '';
    headers.forEach(headerCol => {
        headerHtml += `<th>${escapeHtml(headerCol)}</th>`;
    });
    header.innerHTML = headerHtml;
    
    // Generate body (first 100 rows)
    let bodyHtml = '';
    displayRows.forEach(row => {
        bodyHtml += '<tr>';
        headers.forEach(header => {
            let value = row[header];
            if (value === null) value = '<span class="text-muted">NULL</span>';
            else if (typeof value === 'object') value = '<pre class="mb-0">' + escapeHtml(JSON.stringify(value, null, 2)) + '</pre>';
            else value = escapeHtml(String(value));
            bodyHtml += `<td style="max-width: 300px; overflow: auto;">${value}</td>`;
        });
        bodyHtml += '</tr>';
    });
    document.getElementById('queryResultBody').innerHTML = bodyHtml;
    
    // Show modal
    showSingleModal('queryResultsModal');
}

function displayQuerySuccessEmptyModal(executionTime, query) {
    document.getElementById('queryInfo').textContent = query.substring(0, 100) + (query.length > 100 ? '...' : '');
    document.getElementById('executionTime').innerHTML = `<i class="bi bi-clock me-1"></i>${executionTime}ms`;
    document.getElementById('resultRowCount').innerHTML = '0 rows';
    
    const header = document.getElementById('queryResultHeader');
    header.innerHTML = '<tr><th>Message</th></tr>';
    
    const body = document.getElementById('queryResultBody');
    body.innerHTML = `
        <tr>
            <td class="text-center py-5">
                <i class="bi bi-check-circle fs-1 text-success"></i>
                <p class="mt-2">Query executed successfully.<br><small class="text-muted">No rows returned.</small></p>
            </td>
        </tr>
    `;
    
    showSingleModal('queryResultsModal');
}

function showQueryLoading(show) {
    const runBtn = document.querySelector('button[onclick="runQuery()"]');
    if (runBtn) {
        if (show) {
            runBtn.disabled = true;
            runBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Running...';
        } else {
            runBtn.disabled = false;
            runBtn.innerHTML = '<i class="bi bi-play-fill"></i> Run';
        }
    }
}

function formatQuery() {
    const queryArea = document.getElementById('sqlQuery');
    let query = queryArea.value;
    
    // Simple SQL formatting
    query = query.replace(/SELECT/gi, 'SELECT\n  ');
    query = query.replace(/FROM/gi, '\nFROM');
    query = query.replace(/WHERE/gi, '\nWHERE');
    query = query.replace(/JOIN/gi, '\nJOIN');
    query = query.replace(/ORDER BY/gi, '\nORDER BY');
    query = query.replace(/GROUP BY/gi, '\nGROUP BY');
    query = query.replace(/AND/gi, '\n  AND');
    query = query.replace(/OR/gi, '\n  OR');
    
    queryArea.value = query;
    showToast('Query formatted', 'success');
}

function clearQuery() {
    document.getElementById('sqlQuery').value = '';
    lastQueryResults = null;
    lastQueryResultsFull = null;
    lastExecutedQuery = '';
    // Optionally close results modal if open
    const modal = bootstrap.Modal.getInstance(document.getElementById('queryResultsModal'));
    if (modal) {
        modal.hide();
    }
    showToast('Query editor cleared', 'info');
}

// Export Functions
function exportDatabase() {
    if (!currentDbId) {
        showToast('Please select a database first', 'warning');
        return;
    }
    
    const format = prompt('Export format? (sql, csv, json)', 'sql');
    if (!format || !['sql', 'csv', 'json'].includes(format.toLowerCase())) {
        if (format) {
            showToast('Invalid format. Use sql, csv, or json', 'danger');
        }
        return;
    }
    
    const formatLower = format.toLowerCase();
    const filename = `database_export_${currentDbId}_${new Date().toISOString().slice(0,10)}_${Date.now()}.${formatLower}`;
    
    showToast(`Exporting database as ${format.toUpperCase()}...`, 'info');
    
    fetch(`/database/export-data?id=${currentDbId}&format=${formatLower}`, {
        method: 'GET',
        headers: {
            'X-CSRF-TOKEN': csrf_token
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Export failed: ' + response.statusText);
        }
        return response.blob();
    })
    .then(blob => {
        const mimeType = formatLower === 'json' ? 'application/json' : 
                         formatLower === 'csv' ? 'text/csv' : 'text/plain';
        downloadFile(blob, filename, mimeType);
        showToast(`Database exported as ${format.toUpperCase()}`, 'success');
    })
    .catch(error => {
        console.error('Export error:', error);
        showToast('Export failed: ' + error.message, 'danger');
    });
}

function downloadFile(blobOrContent, filename, mimeType) {
    let blob;
    if (blobOrContent instanceof Blob) {
        blob = blobOrContent;
    } else {
        blob = new Blob([blobOrContent], { type: mimeType });
    }
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

function exportResults(type) {
    if (!lastQueryResults || lastQueryResults.length === 0) {
        showToast('No results to export', 'warning');
        return;
    }
    
    let content = '';
    let filename = `query_results_${Date.now()}`;
    let mimeType = '';
    
    switch(type) {
        case 'sql':
            content = generateSQLFromResults(lastQueryResults);
            filename += '.sql';
            mimeType = 'text/plain';
            break;
        case 'csv':
            content = generateCSVFromResults(lastQueryResults);
            filename += '.csv';
            mimeType = 'text/csv';
            break;
        case 'json':
            content = JSON.stringify(lastQueryResults, null, 2);
            filename += '.json';
            mimeType = 'application/json';
            break;
        case 'excel':
            // For Excel, we'll create an HTML table that Excel can open
            content = generateHTMLFromResults(lastQueryResults);
            filename += '.xls';
            mimeType = 'application/vnd.ms-excel';
            break;
    }
    
    downloadFile(content, filename, mimeType);
    showToast(`Results exported as ${type.toUpperCase()}`, 'success');
}

function generateCSVFromResults(results) {
    if (!results || results.length === 0) return '';
    
    const headers = Object.keys(results[0]);
    let csv = headers.join(',') + '\n';
    
    results.forEach(row => {
        const rowData = headers.map(header => {
            let value = row[header];
            if (value === null) value = '';
            if (typeof value === 'string' && (value.includes(',') || value.includes('"') || value.includes('\n'))) {
                value = '"' + value.replace(/"/g, '""') + '"';
            }
            return value;
        });
        csv += rowData.join(',') + '\n';
    });
    
    return csv;
}

function generateSQLFromResults(results, tableName = 'results') {
    if (!results || results.length === 0) return '';
    
    const headers = Object.keys(results[0]);
    let sql = `-- Generated INSERT statements for ${tableName}\n\n`;
    
    results.forEach(row => {
        sql += `INSERT INTO ${tableName} (`;
        sql += headers.join(', ');
        sql += ') VALUES (';
        
        const values = headers.map(header => {
            let value = row[header];
            if (value === null) return 'NULL';
            if (typeof value === 'string') return `'${value.replace(/'/g, "''")}'`;
            if (typeof value === 'object') return `'${JSON.stringify(value).replace(/'/g, "''")}'`;
            return value;
        });
        
        sql += values.join(', ');
        sql += ');\n';
    });
    
    return sql;
}

function generateHTMLFromResults(results) {
    if (!results || results.length === 0) return '';
    
    const headers = Object.keys(results[0]);
    let html = '<table border="1">\n<thead>\n<tr>';
    
    headers.forEach(header => {
        html += `<th>${escapeHtml(header)}</th>`;
    });
    
    html += '</tr>\n</thead>\n<tbody>\n';
    
    results.forEach(row => {
        html += '<tr>';
        headers.forEach(header => {
            let value = row[header];
            if (value === null) value = 'NULL';
            if (typeof value === 'object') value = JSON.stringify(value);
            html += `<td>${escapeHtml(String(value))}</td>`;
        });
        html += '</tr>\n';
    });
    
    html += '</tbody>\n</table>';
    return html;
}

// Running Queries Management
function showRunningQueries() {
    if (!currentDbId) {
        showToast('Please select a database first', 'warning');
        return;
    }
    
    showSingleModal('runningQueriesModal');
    refreshRunningQueries();
}

function refreshRunningQueries() {
    if (!currentDbId) return;
    
    const minutes = document.getElementById('minQuerySeconds')?.value || 60;
    
    fetch(`/database/running-queries/${currentDbId}?min_seconds=${minutes}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayRunningQueries(data.queries);
            } else {
                document.getElementById('runningQueriesBody').innerHTML = `
                    <tr><td colspan="8" class="text-center text-danger">${escapeHtml(data.message)}</td></tr>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('runningQueriesBody').innerHTML = `
                <tr><td colspan="8" class="text-center text-danger">Failed to load running queries</td></tr>
            `;
        });
}

function displayRunningQueries(queries) {
    const tbody = document.getElementById('runningQueriesBody');
    
    if (!queries || queries.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center">No running queries found</td></tr>';
        return;
    }
    
    let html = '';
    queries.forEach(query => {
        html += `
            <tr>
                <td><code>${query.id}</code></td>
                <td>${escapeHtml(query.user)}</td>
                <td>${escapeHtml(query.host)}</td>
                <td>${escapeHtml(query.db || 'N/A')}</td>
                <td>${query.time}</td>
                <td>${escapeHtml(query.state || 'Running')}</td>
                <td style="max-width: 300px; overflow: auto;">${escapeHtml(query.query || query.info || 'N/A')}</td>
                <td class="text-center">
                    <button class="btn btn-sm btn-danger" onclick="killQuery(${query.id})">
                        <i class="bi bi-x-circle"></i> Kill
                    </button>
                </td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
}

// Export all query results (full dataset)
function exportQueryResults(type) {
    if (!lastQueryResultsFull || lastQueryResultsFull.length === 0) {
        showToast('No results to export', 'warning');
        return;
    }
    
    let content = '';
    let filename = `query_results_${Date.now()}`;
    let mimeType = '';
    
    switch(type) {
        case 'sql':
            content = generateSQLFromResults(lastQueryResultsFull);
            filename += '.sql';
            mimeType = 'text/plain';
            break;
        case 'csv':
            content = generateCSVFromResults(lastQueryResultsFull);
            filename += '.csv';
            mimeType = 'text/csv';
            break;
        case 'json':
            content = JSON.stringify(lastQueryResultsFull, null, 2);
            filename += '.json';
            mimeType = 'application/json';
            break;
        case 'excel':
            content = generateHTMLFromResults(lastQueryResultsFull);
            filename += '.xls';
            mimeType = 'application/vnd.ms-excel';
            break;
    }
    
    downloadFile(content, filename, mimeType);
    showToast('Results exported as ' + type.toUpperCase(), 'success');
}

function displayQueryError(errorMessage) {
    // Show error in query results modal header area instead of inline
    const content = document.getElementById('queryResultsModal').querySelector('.modal-body');
    content.innerHTML = `
        <div class="alert alert-danger d-flex align-items-center" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>
            <div>
                <strong>Error:</strong> ${escapeHtml(errorMessage)}
                <br><small>Check your query syntax and try again.</small>
            </div>
        </div>
    `;
    // Update header info
    document.getElementById('queryInfo').textContent = 'Query Error';
    document.getElementById('executionTime').innerHTML = '';
    document.getElementById('resultRowCount').innerHTML = '0 rows';
    // Show modal
    showSingleModal('queryResultsModal');
}

function killQuery(processId) {
    if (!confirm(`Are you sure you want to kill process ID ${processId}?`)) return;
    
    fetch('/database/kill-query', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf_token
        },
        body: JSON.stringify({
            db_id: currentDbId,
            process_id: processId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Query killed successfully', 'success');
            refreshRunningQueries();
        } else {
            showToast('Failed to kill query: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to kill query', 'danger');
    });
}

function killAllLongRunningQueries() {
    if (!confirm('Are you sure you want to kill ALL long-running queries? This action cannot be undone.')) return;
    
    const minutes = document.getElementById('minQuerySeconds')?.value || 60;
    
    fetch('/database/kill-long-running-queries', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf_token
        },
        body: JSON.stringify({
            db_id: currentDbId,
            min_seconds: minutes
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(`Killed ${data.killed_count} long-running queries`, 'success');
            refreshRunningQueries();
        } else {
            showToast('Failed to kill queries: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to kill queries', 'danger');
    });
}

// Active Connections Management
function showActiveConnections() {
    if (!currentDbId) {
        showToast('Please select a database first', 'warning');
        return;
    }
    
    showSingleModal('activeConnectionsModal');
    refreshActiveConnections();
}

function refreshActiveConnections() {
    if (!currentDbId) return;
    
    fetch(`/database/active-connections/${currentDbId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayActiveConnections(data);
                updateActiveConnectionsCount();
            } else {
                showToast('Failed to load active connections', 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Failed to load active connections', 'danger');
        });
}

function displayActiveConnections(data) {
    // Update stats
    document.getElementById('totalConnections').textContent = data.connections?.length || 0;
    document.getElementById('uniqueUsers').textContent = data.summary?.unique_users || 0;
    document.getElementById('sleepConnections').textContent = data.summary?.sleep_connections || 0;
    document.getElementById('activeQueries').textContent = data.summary?.active_queries || 0;
    
    const tbody = document.getElementById('activeConnectionsBody');
    
    if (!data.connections || data.connections.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center">No active connections found</td></tr>';
        return;
    }
    
    let html = '';
    data.connections.forEach(conn => {
        // Map fields correctly from API response: id, user, host, db(database_name), command, time, state, info(query)
        const pid = conn.id || conn.pid || 'N/A';
        const user = conn.user || 'N/A';
        const host = conn.host || 'N/A';
        const command = conn.command || 'N/A';
        const duration = conn.time != null ? conn.time + 's' : 'N/A';
        const state = conn.state || 'N/A';
        const database = conn.database_name || conn.db || 'N/A';
        const query = conn.query || conn.info || 'N/A';
        
        html += `
            <tr>
                <td><code>${pid}</code></td>
                <td>${escapeHtml(user)}</td>
                <td>${escapeHtml(host)}</td>
                <td>${escapeHtml(command)}</td>
                <td>${duration}</td>
                <td>${escapeHtml(state)}</td>
                <td>${escapeHtml(database)}</td>
                <td style="max-width: 250px; overflow: auto;">${escapeHtml(query)}</td>
                <td class="text-center">
                    <button class="btn btn-sm btn-danger" onclick="killConnectionById(${pid})">
                        <i class="bi bi-x-circle"></i> Kill
                    </button>
                </td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
    document.getElementById('currentDbName').textContent = document.querySelector('#dbConnection option:checked')?.text || 'Unknown';
    document.getElementById('activeConnectionsCount').textContent = data.connections.length;
    
    // Also update button count for consistency
    const btnCountElem = document.getElementById('activeConnectionsBtnCount');
    if (btnCountElem) {
        btnCountElem.textContent = data.connections.length;
    }
}

function killConnectionById(processId) {
    if (!confirm(`Are you sure you want to terminate connection ${processId}?`)) return;
    
    fetch('/database/kill-query', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf_token
        },
        body: JSON.stringify({
            db_id: currentDbId,
            process_id: processId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Connection terminated successfully', 'success');
            refreshActiveConnections();
        } else {
            showToast('Failed to terminate connection: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to terminate connection', 'danger');
    });
}

function killAllIdleConnections() {
    if (!confirm('Are you sure you want to kill all idle connections? This may affect active users.')) return;
    
    fetch('/database/kill-idle-connections', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf_token
        },
        body: JSON.stringify({
            db_id: currentDbId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(`Killed ${data.killed_count} idle connections`, 'success');
            refreshActiveConnections();
        } else {
            showToast('Failed to kill idle connections: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to kill idle connections', 'danger');
    });
}

function killConnectionByUser() {
    const username = prompt('Enter username to kill all connections for:');
    if (!username) return;
    
    if (!confirm(`Kill all connections for user "${username}"?`)) return;
    
    fetch('/database/kill-connections-by-user', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf_token
        },
        body: JSON.stringify({
            db_id: currentDbId,
            username: username
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(`Killed ${data.killed_count} connections for user ${username}`, 'success');
            refreshActiveConnections();
        } else {
            showToast('Failed to kill connections: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to kill connections', 'danger');
    });
}

function updateActiveConnectionsCount() {
    if (!currentDbId) return;
    
    fetch(`/database/active-connections/${currentDbId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.connections) {
                const countElem = document.getElementById('activeConnectionsBtnCount');
                if (countElem) {
                    countElem.textContent = data.connections.length;
                }
            }
        })
        .catch(error => console.error('Error updating count:', error));
}

// Database Modal Functions - UPDATED
function openDbModal(connectionId = null) {
    // Reset form first
    resetDbForm();
    
    if (connectionId) {
        document.getElementById('dbModalLabel').innerHTML = '<i class="bi bi-pencil-square"></i> Edit Database Connection';
        loadConnectionData(connectionId);
    } else {
        document.getElementById('dbModalLabel').innerHTML = '<i class="bi bi-database-add"></i> Add Database Connection';
    }
    
    // Close manage modal if open, then open add modal
    const manageModal = document.getElementById('manageConnectionsModal');
    if (manageModal && manageModal.classList.contains('show')) {
        const modal = bootstrap.Modal.getInstance(manageModal);
        if (modal) {
            modal.hide();
            setTimeout(() => {
                showSingleModal('dbModal');
            }, 200);
        } else {
            showSingleModal('dbModal');
        }
    } else {
        showSingleModal('dbModal');
    }
}

// UPDATED: Manage Connections function
function manageConnections() {
    showSingleModal('manageConnectionsModal');
}

function resetDbForm() {
    document.getElementById('dbId').value = '';
    document.getElementById('dbName').value = '';
    document.getElementById('dbConnectionName').value = 'mysql';
    document.getElementById('dbHost').value = '127.0.0.1';
    document.getElementById('dbPort').value = '3306';
    document.getElementById('dbDatabase').value = '';
    document.getElementById('dbUsername').value = '';
    document.getElementById('dbPassword').value = '';
    document.getElementById('dbNotes').value = '';
    document.getElementById('dbIsDefault').checked = false;
    const testResultMsg = document.getElementById('testResultMsg');
    if (testResultMsg) {
        testResultMsg.style.display = 'none';
    }
}

function loadConnectionData(id) {
    fetch(`/database/get/${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const conn = data.connection;
                document.getElementById('dbId').value = conn.id;
                document.getElementById('dbName').value = conn.name;
                document.getElementById('dbConnectionName').value = conn.connection_name;
                document.getElementById('dbHost').value = conn.host;
                document.getElementById('dbPort').value = conn.port;
                document.getElementById('dbDatabase').value = conn.database;
                document.getElementById('dbUsername').value = conn.username;
                document.getElementById('dbPassword').value = '';
                document.getElementById('dbNotes').value = conn.notes || '';
                document.getElementById('dbIsDefault').checked = conn.is_default == 1;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Failed to load connection data', 'danger');
        });
}

function testBeforeSave() {
    const formData = getDbFormData();
    
    // Show loading state
    const testBtn = document.getElementById('testBeforeSaveBtn');
    if (!testBtn) return;
    
    const originalHtml = testBtn.innerHTML;
    testBtn.disabled = true;
    testBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Testing...';
    
    fetch('/database/test-connection-dynamic', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf_token
        },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        const resultDiv = document.getElementById('testResultMsg');
        if (resultDiv) {
            resultDiv.style.display = 'block';
            
            if (data.success) {
                resultDiv.className = 'alert alert-success mt-2';
                resultDiv.innerHTML = `<i class="bi bi-check-circle me-2"></i>${data.message}`;
                showToast('Connection successful!', 'success');
            } else {
                resultDiv.className = 'alert alert-danger mt-2';
                resultDiv.innerHTML = `<i class="bi bi-exclamation-triangle me-2"></i>${data.message}`;
                showToast('Connection failed', 'danger');
            }
        }
        
        testBtn.disabled = false;
        testBtn.innerHTML = originalHtml;
        
        // Auto hide after 5 seconds
        setTimeout(() => {
            if (resultDiv) {
                resultDiv.style.display = 'none';
            }
        }, 5000);
    })
    .catch(error => {
        const resultDiv = document.getElementById('testResultMsg');
        if (resultDiv) {
            resultDiv.style.display = 'block';
            resultDiv.className = 'alert alert-danger mt-2';
            resultDiv.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>Connection test failed';
        }
        testBtn.disabled = false;
        testBtn.innerHTML = originalHtml;
    });
}

function getDbFormData() {
    return {
        id: document.getElementById('dbId').value,
        name: document.getElementById('dbName').value,
        connection_name: document.getElementById('dbConnectionName').value,
        host: document.getElementById('dbHost').value,
        port: document.getElementById('dbPort').value,
        database: document.getElementById('dbDatabase').value,
        username: document.getElementById('dbUsername').value,
        password: document.getElementById('dbPassword').value,
        notes: document.getElementById('dbNotes').value,
        is_default: document.getElementById('dbIsDefault').checked ? 1 : 0
    };
}

function saveDatabase() {
    const formData = getDbFormData();
    
    if (!formData.name) {
        showToast('Please enter a connection name', 'warning');
        return;
    }
    
    if (!formData.database) {
        showToast('Please enter the database name', 'warning');
        return;
    }
    
    const isEdit = formData.id ? true : false;
    const url = isEdit ? `/database/update/${formData.id}` : '/database/store';
    const method = isEdit ? 'PUT' : 'POST';
    
    // Show loading state on save button
    const saveBtn = document.querySelector('#dbModal .btn-primary');
    if (!saveBtn) return;
    
    const originalText = saveBtn.innerHTML;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
    
    fetch(url, {
        method: method,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf_token
        },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalText;
        
        if (data.success) {
            showToast(`Database connection ${isEdit ? 'updated' : 'added'} successfully`, 'success');
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('dbModal'));
            if (modal) {
                modal.hide();
            }
            // Reload page to refresh connections
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            showToast('Failed to save database connection: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalText;
        console.error('Error:', error);
        showToast('Failed to save database connection', 'danger');
    });
}

// UPDATED: Edit connection function
function editConnection(id) {
    openDbModal(id);
}

function deleteConnection(id, name) {
    if (!confirm(`Are you sure you want to delete connection "${name}"? This action cannot be undone.`)) return;
    
    fetch(`/database/delete/${id}`, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf_token
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Database connection deleted successfully', 'success');
            location.reload();
        } else {
            showToast('Failed to delete connection: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to delete connection', 'danger');
    });
}

function setDefaultConnection(id) {
    fetch(`/database/set-default/${id}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf_token
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Default connection updated', 'success');
            location.reload();
        } else {
            showToast('Failed to set default connection: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to set default connection', 'danger');
    });
}

// Table Details Functions - UPDATED
function loadTableDetails(tableName) {
    if (!currentDbId) {
        showToast('Please select a database first', 'warning');
        return;
    }
    
    currentTable = tableName;
    showSingleModal('tableDetailsModal');
    
    // Load table details
    fetch(`/database/table-details/${currentDbId}/${tableName}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayTableDetails(data);
            } else {
                document.getElementById('tableDetailsContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> 
                        ${escapeHtml(data.message)}
                    </div>
                `;
            }
        })
        .catch(error => {
            document.getElementById('tableDetailsContent').innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i> 
                    Failed to load table details
                </div>
            `;
        });
}

function displayTableDetails(data) {
    const content = document.getElementById('tableDetailsContent');
    
    // Determine engine from data or fetch separately if needed
    const engine = data.engine || data.engine_type || 'N/A';
    const tableCollation = data.collation || data.table_collation || 'N/A';
    const createTime = data.create_time || data.create_time || 'N/A';
    const tableComment = data.table_comment || data.comment || '';
    
    let html = `
        <div class="d-flex justify-content-between align-items-start mb-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-table me-2"></i>${escapeHtml(data.table_name)}</h5>
            <button class="btn btn-sm btn-outline-primary" onclick="generateSelectQueryFromModal()">
                <i class="bi bi-lightning"></i> Generate SELECT
            </button>
        </div>
        
        <!-- Additional Table Info -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card border-0 bg-light">
                    <div class="card-body text-center py-3">
                        <h4 class="mb-0 text-primary">${data.row_count?.toLocaleString() || 0}</h4>
                        <small class="text-muted">Rows</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 bg-light">
                    <div class="card-body text-center py-3">
                        <h4 class="mb-0 text-primary">${data.column_count || 0}</h4>
                        <small class="text-muted">Columns</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 bg-light">
                    <div class="card-body text-center py-3">
                        <h4 class="mb-0 text-primary">${data.index_count || 0}</h4>
                        <small class="text-muted">Indexes</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 bg-light">
                    <div class="card-body text-center py-3">
                        <h5 class="mb-0 text-dark">${engine}</h5>
                        <small class="text-muted">Engine</small>
                    </div>
                </div>
            </div>
        </div>
        
        ${tableComment ? `<div class="alert alert-info py-2 mb-3"><i class="bi bi-chat-left-quote me-2"></i>${escapeHtml(tableComment)}</div>` : ''}
        
        <!-- Column Structure with fixed height scroll -->
        <h6 class="fw-bold"><i class="bi bi-list-ul me-2"></i>Column Structure</h6>
        <div class="table-responsive" style="max-height: 300px; overflow-y: auto; border-radius: 8px; border: 1px solid #e2e8f0;">
            <table class="table table-bordered table-sm mb-0">
                <thead class="table-light sticky-top">
                    <tr><th>Column</th><th>Type</th><th>Null</th><th>Default</th><th>Extra</th></tr>
                </thead>
                <tbody>
    `;
    
    if (data.columns && data.columns.length > 0) {
        data.columns.forEach(col => {
            html += `
                <tr>
                    <td><code class="text-primary">${escapeHtml(col.Field || col.column_name)}</code></td>
                    <td><code class="text-secondary">${escapeHtml(col.Type || col.data_type)}</code></td>
                    <td class="text-center">${col.Null === 'YES' || col.is_nullable ? '<i class="bi bi-check text-success"></i>' : '<i class="bi bi-x text-danger"></i>'}</td>
                    <td><small>${escapeHtml(col.Default || '-')}</small></td>
                    <td><small>${escapeHtml(col.Extra || '-')}</small></td>
                </tr>
            `;
        });
    } else {
        html += '<tr><td colspan="5" class="text-center py-4">No column data available</td></tr>';
    }
    
    html += `
                </tbody>
            </table>
        </div>
        
        <!-- Indexes with fixed height scroll -->
        <h6 class="fw-bold mt-4"><i class="bi bi-fingerprint me-2"></i>Indexes</h6>
        <div class="table-responsive" style="max-height: 250px; overflow-y: auto; border-radius: 8px; border: 1px solid #e2e8f0;">
            <table class="table table-bordered table-sm mb-0">
                <thead class="table-light sticky-top">
                    <tr><th>Index Name</th><th>Columns</th><th>Unique</th><th>Type</th></tr>
                </thead>
                <tbody>
    `;
    
    if (data.indexes && data.indexes.length > 0) {
        data.indexes.forEach(idx => {
            const isUnique = idx.Non_unique == 0 || idx.unique;
            html += `
                <tr>
                    <td><strong>${escapeHtml(idx.Index_name || idx.name)}</strong></td>
                    <td><code>${escapeHtml(idx.Column_name || idx.columns || '')}</code></td>
                    <td class="text-center">${isUnique ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-dash text-muted"></i>'}</td>
                    <td>${escapeHtml(idx.Index_type || idx.type || 'BTREE')}</td>
                </tr>
            `;
        });
    } else {
        html += '<tr><td colspan="4" class="text-center py-4">No indexes found</td></tr>';
    }
    
    html += `
                </tbody>
            </table>
        </div>
        
        <!-- Additional Details (if available) -->
        ${data.create_time ? `
            <div class="mt-3 text-muted small">
                <i class="bi bi-calendar me-1"></i>Created: ${escapeHtml(data.create_time)}
            </div>
        ` : ''}
    `;
    
    content.innerHTML = html;
}

function showQuerySuccessModal(affectedRows) {
    // Clear previous results to prevent stale export
    lastQueryResultsFull = [];
    lastExecutedQuery = '';
    
    document.getElementById('queryInfo').textContent = 'Query executed successfully';
    document.getElementById('executionTime').innerHTML = '';
    document.getElementById('resultRowCount').innerHTML = `${affectedRows} rows affected`;
    
    const header = document.getElementById('queryResultHeader');
    header.innerHTML = '<tr><th>Message</th></tr>';
    
    const body = document.getElementById('queryResultBody');
    body.innerHTML = `
        <tr>
            <td>
                <div class="alert alert-success mb-0">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <strong>Success:</strong> Query executed successfully.<br>
                    <span class="text-muted">${affectedRows} row(s) were affected by this operation.</span>
                </div>
            </td>
        </tr>
    `;
    
    showSingleModal('queryResultsModal');
}

function generateSelectQueryFromModal() {
    const tableName = currentTable;
    if (tableName) {
        const query = `SELECT * FROM \`${tableName}\` LIMIT 100;`;
        document.getElementById('sqlQuery').value = query;
        const modal = bootstrap.Modal.getInstance(document.getElementById('tableDetailsModal'));
        if (modal) {
            modal.hide();
        }
        showToast(`Query for ${tableName} generated`, 'success');
    }
}

/**
 * Close query results modal and load query into editor
 */
function closeQueryModalAndRun() {
    if (lastExecutedQuery) {
        document.getElementById('sqlQuery').value = lastExecutedQuery;
        showToast('Query loaded into editor for editing', 'success');
    }
    const modal = bootstrap.Modal.getInstance(document.getElementById('queryResultsModal'));
    if (modal) {
        modal.hide();
    }
}

// ============================================
// SSH DB IMPORT FUNCTIONALITY
// ============================================

/**
 * Open import modal and load pending count
 */
function openImportFromSshModal() {
    // Fetch pending count
    fetch('/ssh/import-db-status')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // Update badge on button
                const badge = document.getElementById('importPendingBadge');
                if (data.pending_count > 0) {
                    badge.textContent = data.pending_count;
                    badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                }
                
                if (data.pending_count === 0) {
                    showToast('✅ All domains already imported. No pending imports.', 'success');
                    return;
                }
                
                // Show modal and start import
                showSingleModal('importDbModal');
                setTimeout(startSshDbImport, 500);
            }
        })
        .catch(err => {
            console.error('Failed to get import status:', err);
            showToast('Could not check import status', 'warning');
            showSingleModal('importDbModal');
        });
}

/**
 * Start the import process from all SSH servers with domains
 */
async function startSshDbImport() {
    // Reset progress
    sshImportCancelled = false;
    sshImportProgress = { current: 0, total: 0, imported: 0, skipped: 0, errors: [] };
    sshImportServers = [];
    
    // Get all servers with domains from the global allServers array (populated by ssh page)
    // Since we're on Database Manager page, we need to fetch the server list
    try {
        const response = await fetch('/ssh/list-with-domains');
        const data = await response.json();
        
        if (!data.success || !Array.isArray(data.servers)) {
            showToast('Could not load SSH servers list', 'warning');
            return;
        }
        
        sshImportServers = data.servers.filter(s => s.domains && s.domains.length > 0);
        sshImportProgress.total = sshImportServers.length;
        
        if (sshImportServers.length === 0) {
            showToast('No SSH servers with domains found', 'warning');
            bootstrap.Modal.getInstance(document.getElementById('importDbModal')).hide();
            return;
        }
        
        // Update initial UI
        updateImportStats();
        updateImportUI('Starting import...');
        
        // Disable close button during import
        document.getElementById('closeImportBtn').disabled = true;
        document.getElementById('cancelImportBtn').disabled = false;
        
        // Process sequentially
        for (const server of sshImportServers) {
            if (sshImportCancelled) {
                showToast('Import cancelled', 'info');
                break;
            }
            
            updateImportUI(`Processing: ${server.host} (${server.hostname})`);
            
            try {
                const res = await fetch('/ssh/import-db-single', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf_token
                    },
                    body: JSON.stringify(server)
                });
                
                const result = await res.json();
                
                if (result.success) {
                    sshImportProgress.imported += result.imported_count || 0;
                    sshImportProgress.skipped += result.skipped_count || 0;
                    if (result.errors && result.errors.length > 0) {
                        sshImportProgress.errors.push(...result.errors);
                    }
                    if (result.domains_imported && result.domains_imported.length > 0) {
                        updateImportUI(`✅ Imported from ${server.host}: ${result.domains_imported.join(', ')}`);
                    } else {
                        updateImportUI(`ℹ️ ${server.host}: ${result.message || 'No new imports'}`);
                    }
                } else {
                    sshImportProgress.skipped++;
                    sshImportProgress.errors.push(`${server.host}: ${result.message}`);
                    updateImportUI(`❌ Failed: ${server.host}`);
                }
                
            } catch (err) {
                sshImportProgress.skipped++;
                sshImportProgress.errors.push(`${server.host}: ${err.message}`);
                updateImportUI(`❌ Error: ${server.host}`);
            }
            
            sshImportProgress.current++;
            updateImportStats();
            
            // Small delay to avoid overwhelming
            await new Promise(resolve => setTimeout(resolve, 200));
        }
        
        // Complete
        showImportSummary();
        
    } catch (error) {
        console.error('Import error:', error);
        showToast('Import process failed: ' + error.message, 'danger');
        bootstrap.Modal.getInstance(document.getElementById('importDbModal')).hide();
    }
}

/**
 * Update the current operation text in modal
 */
function updateImportUI(message) {
    const el = document.getElementById('currentOperation');
    if (el) el.textContent = message;
}

/**
 * Update progress bar, counts, percentage
 */
function updateImportStats() {
    const p = sshImportProgress;
    const percent = p.total > 0 ? Math.round((p.current / p.total) * 100) : 0;
    
    document.getElementById('importProgressBar').style.width = percent + '%';
    document.getElementById('importPercent').textContent = percent + '%';
    document.getElementById('importedCount').textContent = p.imported;
    document.getElementById('skippedCount').textContent = p.skipped;
    document.getElementById('remainingCount').textContent = Math.max(0, p.total - p.current);
}

/**
 * Cancel the ongoing import
 */
function cancelSshDbImport() {
    sshImportCancelled = true;
    document.getElementById('cancelImportBtn').disabled = true;
    document.getElementById('cancelImportBtn').innerHTML = '<i class="bi bi-hourglass-split"></i> Cancelling...';
}

/**
 * Show final summary after import completes
 */
function showImportSummary() {
    // Enable close button, disable cancel
    document.getElementById('closeImportBtn').disabled = false;
    document.getElementById('cancelImportBtn').style.display = 'none';
    
    const p = sshImportProgress;
    let summary = `
        <div class="text-center mb-3">
            <i class="bi bi-check-circle-fill text-success fs-1"></i>
            <h4 class="mt-2">Import Complete</h4>
        </div>
        <div class="d-flex justify-content-around mb-3">
            <div class="text-center">
                <h3 class="text-success">${p.imported}</h3>
                <small>Imported</small>
            </div>
            <div class="text-center">
                <h3 class="text-warning">${p.skipped}</h3>
                <small>Skipped</small>
            </div>
        </div>
    `;
    
    // If there were errors, show them
    if (p.errors.length > 0) {
        summary += `
            <div class="border-top pt-3 mt-2">
                <small class="text-danger fw-bold"><i class="bi bi-exclamation-triangle me-1"></i>Errors (first 10):</small>
                <ul class="mb-0 small text-danger" style="max-height: 200px; overflow-y: auto;">
        `;
        p.errors.slice(0, 10).forEach(err => {
            summary += `<li>${escapeHtml(err)}</li>`;
        });
        if (p.errors.length > 10) {
            summary += `<li>... and ${p.errors.length - 10} more</li>`;
        }
        summary += `</ul></div>`;
    }
    
    // Replace modal body content temporarily
    const modalBody = document.querySelector('#importDbModal .modal-body');
    const originalContent = modalBody.innerHTML;
    modalBody.innerHTML = summary;
    
    // Re-enable close button
    document.getElementById('closeImportBtn').disabled = false;
    
    // On modal hide, restore original content
    document.getElementById('importDbModal').addEventListener('hidden.bs.modal', function restore() {
        modalBody.innerHTML = originalContent;
        document.getElementById('importDbModal').removeEventListener('hidden.bs.modal', restore);
        // Reset cancel button for next time
        document.getElementById('cancelImportBtn').style.display = 'block';
        document.getElementById('cancelImportBtn').disabled = false;
        document.getElementById('cancelImportBtn').innerHTML = '<i class="bi bi-x-circle me-1"></i> Cancel';
    }, { once: true });
    
    if (p.imported > 0) {
        showToast(`Successfully imported ${p.imported} database credential(s)`, 'success');
        // Refresh the database connections dropdown
        setTimeout(() => location.reload(), 1500);
    } else {
        showToast('No new credentials were imported', 'warning');
    }
}

// Utility Functions
function refreshTables() {
    if (currentDbId) {
        loadTables(currentDbId);
        showToast('Tables refreshed', 'success');
    }
}

function showToast(message, type = 'info') {
    // Create toast container if not exists
    let toastContainer = document.querySelector('.toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        toastContainer.style.zIndex = '9999';
        document.body.appendChild(toastContainer);
    }
    
    const toastId = 'toast_' + Date.now();
    const bgClass = type === 'success' ? 'bg-success' : (type === 'danger' ? 'bg-danger' : (type === 'warning' ? 'bg-warning' : 'bg-info'));
    
    const toastHtml = `
        <div id="${toastId}" class="toast align-items-center text-white ${bgClass} border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="3000">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-${type === 'success' ? 'check-circle' : (type === 'danger' ? 'exclamation-triangle' : 'info-circle')} me-2"></i>
                    ${escapeHtml(message)}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    toastContainer.insertAdjacentHTML('beforeend', toastHtml);
    const toastElement = document.getElementById(toastId);
    if (toastElement) {
        const toast = new bootstrap.Toast(toastElement);
        toast.show();
        
        toastElement.addEventListener('hidden.bs.toast', () => {
            toastElement.remove();
        });
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Table Data Preview (additional feature) - Updated for modal display
function previewTableData(tableName) {
    if (!currentDbId) {
        showToast('Please select a database first', 'warning');
        return;
    }
    
    fetch(`/database/table-data/${currentDbId}/${tableName}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                const results = data.data;
                const displayRows = results.slice(0, 100);
                // Store for export
                lastQueryResultsFull = results;
                lastExecutedQuery = `SELECT * FROM \`${tableName}\` LIMIT 100`;
                displayQueryResultsInModal(displayRows, results.length, '0', lastExecutedQuery);
                showToast(`Loaded preview of ${tableName}`, 'success');
            } else {
                showToast('No data available', 'warning');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Failed to load table data', 'danger');
        });
}

// ADDED: Export for new functions
window.openAddConnectionModal = openAddConnectionModal;
window.closeAllModals = closeAllModals;
window.showSingleModal = showSingleModal;

// Export for global access (keep all existing)
window.switchDatabase = switchDatabase;
window.loadTables = loadTables;
window.selectTable = selectTable;
window.testConnection = testConnection;
window.testSimpleQuery = testSimpleQuery;
window.runQuery = runQuery;
window.formatQuery = formatQuery;
window.clearQuery = clearQuery;
window.exportDatabase = exportDatabase;
window.exportResults = exportResults;
window.showRunningQueries = showRunningQueries;
window.refreshRunningQueries = refreshRunningQueries;
window.killQuery = killQuery;
window.killAllLongRunningQueries = killAllLongRunningQueries;
window.showActiveConnections = showActiveConnections;
window.refreshActiveConnections = refreshActiveConnections;
window.killConnectionById = killConnectionById;
window.killAllIdleConnections = killAllIdleConnections;
window.killConnectionByUser = killConnectionByUser;
window.openDbModal = openDbModal;
window.manageConnections = manageConnections;
window.testBeforeSave = testBeforeSave;
window.saveDatabase = saveDatabase;
window.editConnection = editConnection;
window.deleteConnection = deleteConnection;
window.setDefaultConnection = setDefaultConnection;
window.refreshTables = refreshTables;
window.previewTableData = previewTableData;
window.generateSelectQueryFromModal = generateSelectQueryFromModal;
window.closeQueryModalAndRun = closeQueryModalAndRun;
window.openImportFromSshModal = openImportFromSshModal;
window.startSshDbImport = startSshDbImport;
window.cancelSshDbImport = cancelSshDbImport;
window.fetchPhpMyAdminInfo = fetchPhpMyAdminInfo;
window.togglePass = togglePass;
window.copyToClipboard = copyToClipboard;
window.loadTableDetails = loadTableDetails;