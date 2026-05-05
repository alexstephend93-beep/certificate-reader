<!-- Add/Edit Database Modal -->
<div class="modal fade" id="dbModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="dbModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header" style="background: var(--gradient-primary); border-bottom: none;">
                <h5 class="modal-title text-white" id="dbModalLabel">
                    <i class="bi bi-database-add"></i> Add Database Connection
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding: 30px;">
                <form id="dbForm" onsubmit="return false;">
                    <input type="hidden" id="dbId" name="id">
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-bold">Connection Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="dbName" placeholder="e.g., production_db" required>
                            <small class="text-muted">A friendly name to identify this connection</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Database Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="dbConnectionName" required>
                                <option value="mysql">🐬 MySQL / MariaDB</option>
                                <option value="pgsql">🐘 PostgreSQL</option>
                                <option value="sqlite">📁 SQLite</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Host <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="dbHost" value="127.0.0.1" required>
                            <small class="text-muted">IP address or hostname</small>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Port <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="dbPort" value="3306" required>
                        </div>
                        
                        <div class="col-md-8 mb-3">
                            <label class="form-label fw-bold">Database Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="dbDatabase" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="dbUsername" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Password</label>
                            <input type="password" class="form-control" id="dbPassword" placeholder="••••••••">
                            <small class="text-muted">Leave empty to keep existing password</small>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-bold">Notes</label>
                            <textarea class="form-control" id="dbNotes" rows="2" placeholder="Optional notes about this database"></textarea>
                        </div>
                        
                        <div class="col-md-12 mb-4">
                            <button type="button" class="btn btn-outline-success w-100" id="testBeforeSaveBtn" onclick="testBeforeSave()">
                                <i class="bi bi-plug"></i> Test Connection Before Saving
                            </button>
                            <div id="testResultMsg" class="mt-2" style="display: none;"></div>
                        </div>
                        
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="dbIsDefault">
                                <label class="form-check-label fw-bold">Set as default connection</label>
                                <br>
                                <small class="text-muted">This connection will be selected by default</small>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #e2e8f0;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveDatabase()">Save Connection</button>
            </div>
        </div>
    </div>
</div>

<!-- Running Queries Modal -->
<div class="modal fade" id="runningQueriesModal" tabindex="-1" data-bs-backdrop="static" aria-labelledby="runningQueriesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header" style="background: var(--gradient-primary); border-bottom: none;">
                <h5 class="modal-title text-white" id="runningQueriesModalLabel">
                    <i class="bi bi-hourglass-split"></i> Running Queries (> 1 minute)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3 d-flex gap-2 align-items-center flex-wrap">
                    <label class="form-label mb-0">Min query time (seconds):</label>
                    <input type="number" id="minQuerySeconds" class="form-control" value="60" style="width: 150px;">
                    <button type="button" class="btn btn-sm btn-primary" onclick="refreshRunningQueries()">Refresh</button>
                    <button type="button" class="btn btn-sm btn-danger" onclick="killAllLongRunningQueries()">Kill All Long Running</button>
                </div>
                <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                    <table class="table table-bordered table-striped table-sm">
                        <thead class="sticky-top bg-white">
                            <tr><th>PID</th><th>User</th><th>Host</th><th>Database</th><th>Time (s)</th><th>State</th><th>Query</th><th class="text-center">Action</th></tr>
                        </thead>
                        <tbody id="runningQueriesBody">
                            <tr><td colspan="8" class="text-center py-5">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Manage Connections Modal -->
<div class="modal fade" id="manageConnectionsModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="manageConnectionsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" style="max-width: 1100px;">
        <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header" style="background: var(--gradient-primary); border-bottom: none;">
                <h5 class="modal-title text-white" id="manageConnectionsModalLabel">Manage Database Connections</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="connectionsTable">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Host:Port</th>
                                <th>Database</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="connectionsTableBody">
                            @foreach($databases as $db)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-database"></i>
                                        <strong>{{ $db->name }}</strong>
                                        @if($db->is_default) 
                                            <span class="badge bg-warning text-dark">Default</span>
                                        @endif
                                    </div>
                                 </td>
                                <td><span class="badge bg-info">{{ strtoupper($db->connection_name) }}</span> </td>
                                <td><code>{{ $db->host }}:{{ $db->port }}</code> </td>
                                <td>{{ $db->database }} </td>
                                <td>
                                    @if($db->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-danger">Inactive</span>
                                    @endif
                                 </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        @if(!$db->is_default)
                                            <button type="button" class="btn btn-outline-warning" onclick="setDefaultConnection({{ $db->id }})" title="Set as Default">
                                                <i class="bi bi-star-fill"></i>
                                            </button>
                                        @endif
                                        <button type="button" class="btn btn-outline-primary" onclick="editConnection({{ $db->id }})" title="Edit Connection">
                                            <i class="bi bi-pencil-fill"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger" onclick="deleteConnection({{ $db->id }}, '{{ $db->name }}')" title="Delete Connection">
                                            <i class="bi bi-trash-fill"></i>
                                        </button>
                                    </div>
                                 </td>
                             </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                @if($databases->isEmpty())
                    <div class="text-center py-5">
                        <i class="bi bi-database fs-1 text-muted"></i>
                        <p class="mt-2 text-muted">No database connections configured</p>
                        <button type="button" class="btn btn-primary mt-2" onclick="openAddConnectionModal()">
                            <i class="bi bi-plus-circle"></i> Add Your First Connection
                        </button>

                    </div>
                @endif
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <button type="button" class="btn btn-primary" onclick="openAddConnectionModal()">Add New Connection</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Active Connections Modal -->
<div class="modal fade" id="activeConnectionsModal" tabindex="-1" data-bs-backdrop="static" aria-labelledby="activeConnectionsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header" style="background: var(--gradient-primary); border-bottom: none;">
                <h5 class="modal-title text-white" id="activeConnectionsModalLabel">
                    <i class="bi bi-people-fill"></i> Active Connections - <span id="currentDbName">Loading...</span>
                    <span id="activeConnectionsCount" class="badge bg-light text-dark ms-2">0</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-4 g-3">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white p-3 text-center shadow-sm">
                            <h3 id="totalConnections" class="mb-0">0</h3>
                            <small>Total Connections</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white p-3 text-center shadow-sm">
                            <h3 id="uniqueUsers" class="mb-0">0</h3>
                            <small>Unique Users</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-secondary text-white p-3 text-center shadow-sm">
                            <h3 id="sleepConnections" class="mb-0">0</h3>
                            <small>Idle (Sleep)</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white p-3 text-center shadow-sm">
                            <h3 id="activeQueries" class="mb-0">0</h3>
                            <small>Active Queries</small>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3 d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-sm btn-primary" onclick="refreshActiveConnections()">Refresh</button>
                    <button type="button" class="btn btn-sm btn-outline-warning" onclick="killAllIdleConnections()">Kill All Idle</button>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="killConnectionByUser()">Kill By User</button>
                </div>
                
                <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                    <table class="table table-bordered table-striped table-sm">
                        <thead class="sticky-top bg-white">
                            <tr>
                                <th>PID</th>
                                <th>User</th>
                                <th>Host</th>
                                <th>Command</th>
                                <th>Duration (s)</th>
                                <th>State</th>
                                <th>Database</th>
                                <th>Query</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="activeConnectionsBody">
                            <tr><td colspan="9" class="text-center py-5">Loading connections...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Table Details Modal -->
<div class="modal fade" id="tableDetailsModal" tabindex="-1" data-bs-backdrop="static" aria-labelledby="tableDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header" style="background: var(--gradient-primary); border-bottom: none;">
                <h5 class="modal-title text-white" id="tableDetailsModalLabel">Table Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="tableDetailsContent" style="padding: 20px;">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading table details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="generateSelectQueryFromModal()">Generate SELECT Query</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Query Results Modal -->
<div class="modal fade" id="queryResultsModal" tabindex="-1" data-bs-backdrop="static" aria-labelledby="queryResultsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" style="max-width: 95%;">
        <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header" style="background: var(--gradient-primary); border-bottom: none;">
                <div class="d-flex flex-column">
                    <h5 class="modal-title text-white" id="queryResultsModalLabel">
                        <i class="bi bi-table me-2"></i>Query Results
                    </h5>
                    <small class="text-light-50" id="queryInfo" style="font-size: 0.85rem; opacity: 0.9;"></small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding: 20px;">
                <!-- Action Bar -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <span class="badge bg-primary" id="resultRowCount">0 rows</span>
                        <span class="badge bg-secondary ms-2" id="executionTime"></span>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-primary" onclick="closeQueryModalAndRun()">
                            <i class="bi bi-lightning"></i> Run in Editor
                        </button>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-success dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-download"></i> Export All
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" onclick="exportQueryResults('json')">JSON</a></li>
                                <li><a class="dropdown-item" href="#" onclick="exportQueryResults('csv')">CSV</a></li>
                                <li><a class="dropdown-item" href="#" onclick="exportQueryResults('sql')">SQL</a></li>
                                <li><a class="dropdown-item" href="#" onclick="exportQueryResults('excel')">Excel</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Results Table with Fixed Height -->
                <div class="table-responsive" style="max-height: 500px; overflow-y: auto; border-radius: 10px;">
                    <table class="table table-bordered table-striped table-sm" id="queryResultTable">
                        <thead class="table-light sticky-top">
                            <tr id="queryResultHeader"></tr>
                        </thead>
                        <tbody id="queryResultBody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Import DB Credentials from SSH Progress Modal -->
<div class="modal fade" id="importDbModal" tabindex="-1" data-bs-backdrop="static" aria-labelledby="importDbModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header" style="background: var(--gradient-primary); border-bottom: none;">
                <h5 class="modal-title text-white" id="importDbModalLabel">
                    <i class="bi bi-database-add me-2"></i>Importing Database Credentials
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" id="closeImportBtn" disabled></button>
            </div>
            <div class="modal-body" style="padding: 30px;">
                <!-- Progress Bar -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between mb-2">
                        <span id="currentServer">Initializing...</span>
                        <span id="importPercent" class="fw-bold">0%</span>
                    </div>
                    <div class="progress" style="height: 12px; border-radius: 6px; background: #e2e8f0;">
                        <div id="importProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" 
                             style="width: 0%; background: linear-gradient(90deg, #10b981, #3b82f6);"></div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-4">
                        <div class="card border-0 bg-light">
                            <div class="card-body text-center py-3">
                                <h4 class="mb-0 text-success" id="importedCount">0</h4>
                                <small class="text-muted">Imported</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="card border-0 bg-light">
                            <div class="card-body text-center py-3">
                                <h4 class="mb-0 text-warning" id="skippedCount">0</h4>
                                <small class="text-muted">Skipped</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="card border-0 bg-light">
                            <div class="card-body text-center py-3">
                                <h4 class="mb-0 text-primary" id="remainingCount">0</h4>
                                <small class="text-muted">Remaining</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Current Operation -->
                <div class="mb-3">
                    <small class="text-muted">Current Operation:</small>
                    <div id="currentOperation" class="fw-bold text-primary">Waiting to start...</div>
                </div>

                <!-- Errors Section -->
                <div id="importErrors" style="display: none; max-height: 250px; overflow-y: auto;" class="border rounded p-2 bg-light">
                    <small class="text-danger fw-bold"><i class="bi bi-exclamation-triangle me-1"></i>Errors:</small>
                    <ul id="errorsList" class="mb-0 text-danger small mt-1"></ul>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-danger" id="cancelImportBtn" onclick="cancelSshDbImport()">
                    <i class="bi bi-x-circle me-1"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" id="closeImportBtn" data-bs-dismiss="modal" disabled>
                    <i class="bi bi-check-circle me-1"></i> Done
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container for Notifications -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1100;">
    <div id="toastContainer"></div>
</div>

<script>
    // Initialize all modals when document is ready
    document.addEventListener('DOMContentLoaded', function() {
        // Ensure Bootstrap is loaded
        if (typeof bootstrap !== 'undefined') {
            console.log('Bootstrap initialized for modals');
        }
    });
    
    // Helper function to show modal safely
    function showModal(modalId) {
        const modalElement = document.getElementById(modalId);
        if (modalElement) {
            // Remove any existing backdrops
            const existingBackdrops = document.querySelectorAll('.modal-backdrop');
            existingBackdrops.forEach(backdrop => backdrop.remove());
            
            // Remove modal-open class from body
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
            
            // Initialize and show modal
            const modal = new bootstrap.Modal(modalElement, {
                backdrop: 'static',
                keyboard: true
            });
            modal.show();
        } else {
            console.error('Modal element not found:', modalId);
        }
    }
    
    // Override openDbModal function to use showModal
    const originalOpenDbModal = window.openDbModal;
    window.openDbModal = function(connectionId = null) {
        if (connectionId) {
            document.getElementById('dbModalLabel').innerHTML = '<i class="bi bi-pencil-square"></i> Edit Database Connection';
            loadConnectionData(connectionId);
        } else {
            document.getElementById('dbModalLabel').innerHTML = '<i class="bi bi-database-add"></i> Add Database Connection';
            resetDbForm();
        }
        showModal('dbModal');
    };
    
    // Override manageConnections function
    window.manageConnections = function() {
        showModal('manageConnectionsModal');
    };
</script>