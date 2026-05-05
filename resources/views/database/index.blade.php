@extends('layouts.app')

@section('title', 'Database Manager')

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/database.css') }}">
<style>
    /* Additional inline styles for better layout */
    .db-selector-section {
        background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0.05));
        border-radius: 20px;
        padding: 20px 24px;
        backdrop-filter: blur(10px);
    }
    
    .stats-grid-ssh {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card-ssh {
        background: white;
        border-radius: 20px;
        padding: 20px;
        text-align: center;
        transition: all 0.3s ease;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    
    .stat-card-ssh:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
        border-color: var(--color-primary);
    }
    
    .stat-number-ssh {
        font-size: 2rem;
        font-weight: 800;
        color: var(--color-primary);
        font-family: 'Space Grotesk', monospace;
        line-height: 1.2;
    }
    
    .stat-label {
        font-size: 0.75rem;
        color: #64748b;
        margin-top: 5px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    /* Fix Bootstrap Modal Issues */
    .modal {
        z-index: 1050;
    }
    
    .modal-backdrop {
        z-index: 1040;
    }
    
    .modal-content {
        z-index: 1051;
        position: relative;
    }
    
    @media (max-width: 992px) {
        .stats-grid-ssh {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    @media (max-width: 576px) {
        .stats-grid-ssh {
            grid-template-columns: 1fr;
        }
    }
</style>
@endsection

@section('content')
<div class="glass-card" data-aos="fade-down">
    <div class="gradient-header text-center">
        <h1 class="fw-bold">
            <i class="bi bi-database-fill-gear me-3"></i>
            Database Manager
        </h1>
        <p class="lead mb-0">
            <i class="bi bi-hdd-stack me-2"></i>
            Manage multiple databases, run queries, and export data
        </p>
    </div>

    <div class="p-4 p-md-5">
        <!-- Database Selector -->
        <div class="db-selector-section mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
                <div class="flex-grow-1" style="min-width: 250px;">
                    <label class="form-label fw-bold mb-2">
                        <i class="bi bi-database"></i> Database Connections
                    </label>
                    
                    <!-- Searchable Select2 Dropdown -->
                    <select id="dbConnection" class="form-select select2-db" style="border-radius: 12px; width: 100%;">
                        <option value="">-- Select Database --</option>
                        @foreach($databases as $db)
                            <option value="{{ $db->id }}" {{ $db->is_default ? 'selected' : '' }}>
                                {{ $db->name }} 
                                ({{ strtoupper($db->connection_name) }}) 
                                - {{ $db->database }}
                                @if($db->is_default) ⭐ @endif
                            </option>
                        @endforeach
                    </select>
                    
                    <div class="d-flex gap-2 mt-2">
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="openDbModal()" title="Add Database">
                            <i class="bi bi-plus-circle"></i> Add
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="manageConnections()" title="Manage Connections">
                            <i class="bi bi-gear"></i> Manage
                        </button>
                    </div>
                    
                    <!-- phpMyAdmin Info Card (hidden by default) -->
                    <div id="phpmyadminInfo" class="card border-0 shadow-sm mt-3" style="display: none; border-radius: 12px;">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-phpmyadmin fs-4 text-primary me-2"></i>
                                <h6 class="mb-0 fw-bold">phpMyAdmin Access</h6>
                            </div>
                            <div id="phpmyadminContent">
                                <!-- Content populated by JS -->
                            </div>
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-sm btn-outline-success" onclick="testConnection()" title="Test Connection">
                        <i class="bi bi-plug"></i> Test
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="openImportFromSshModal()" title="Import from SSH Servers" style="position: relative;">
                        <i class="bi bi-database-add"></i> Import from SSH
                        <span id="importPendingBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display: none;">
                            <span class="visually-hidden">unread messages</span>
                        </span>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="exportDatabase()" title="Export Database">
                        <i class="bi bi-download"></i> Export
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-warning" onclick="showRunningQueries()" title="Running Queries">
                        <i class="bi bi-hourglass-split"></i> Queries
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-info" onclick="showActiveConnections()" id="activeConnectionsBtn" title="Active Connections">
                        <i class="bi bi-people-fill"></i> Connections (<span id="activeConnectionsBtnCount">0</span>)
                    </button>
                </div>
            </div>
            <div id="connectionStatus" class="alert mt-3" style="display: none; border-radius: 12px;">
                <i class="bi bi-info-circle"></i> <span id="statusMessage"></span>
            </div>
        </div>

        <!-- Main Content Row -->
        <div class="row g-4">
            <!-- Left Sidebar - Tables List -->
            <div class="col-lg-3 col-md-4">
                <div class="glass-card p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0">
                            <i class="bi bi-table"></i> Tables
                            <span class="badge bg-primary ms-2" id="tableCountBadge">0</span>
                        </h6>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="refreshTables()" title="Refresh Tables" style="border-radius: 10px;">
                            <i class="bi bi-arrow-repeat"></i>
                        </button>
                    </div>
                    <div id="tablesList" class="tables-list-container" style="max-height: 65vh; overflow-y: auto; padding-right: 5px;">
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-database fs-1"></i>
                            <p class="mt-2">Select a database to view tables</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side - Query Editor -->
            <div class="col-lg-9 col-md-8">
                <div class="glass-card p-3">
                    <h6 class="fw-bold mb-3">
                        <i class="bi bi-code-square"></i> SQL Query Editor
                        <small class="text-muted">(Ctrl+Enter to run)</small>
                    </h6>
                    
                    <textarea id="sqlQuery" rows="8" class="form-control font-monospace" 
                            placeholder="SELECT * FROM users LIMIT 10;"></textarea>
                    
                    <div class="d-flex gap-2 mt-3 flex-wrap">
                        <button type="button" class="btn btn-primary" onclick="runQuery()" title="Run Query (Ctrl+Enter)">
                            <i class="bi bi-play-fill"></i> Run
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="formatQuery()" title="Format SQL">
                            <i class="bi bi-magic"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="clearQuery()" title="Clear Editor">
                            <i class="bi bi-eraser"></i>
                        </button>
                        <button type="button" class="btn btn-outline-warning" onclick="testSimpleQuery()" title="Test Connection">
                            <i class="bi bi-bug"></i>
                        </button>
                        
                        <div class="ms-auto">
                            <div class="dropdown">
                                <button type="button" class="btn btn-outline-success dropdown-toggle" data-bs-toggle="dropdown" title="Export Results">
                                    <i class="bi bi-download"></i> Export
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="#" onclick="exportResults('sql')">
                                        <i class="bi bi-file-code"></i> SQL
                                    </a></li>
                                    <li><a class="dropdown-item" href="#" onclick="exportResults('csv')">
                                        <i class="bi bi-file-spreadsheet"></i> CSV
                                    </a></li>
                                    <li><a class="dropdown-item" href="#" onclick="exportResults('json')">
                                        <i class="bi bi-filetype-json"></i> JSON
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="#" onclick="exportResults('excel')">
                                        <i class="bi bi-file-excel"></i> Excel
                                    </a></li>
                                </ul>
                            </div>
                        </div>
                        </div>

                        <!-- Query results are displayed in a modal -->
                        <div id="queryPlaceholder" class="text-center py-5 text-muted" style="display: none;">
                            <i class="bi bi-table fs-1"></i>
                            <p class="mt-2">Results will appear in a modal window</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include Modals -->
@include('database.partials.modals')
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    var csrf_token = '{{ csrf_token() }}';
</script>
<script src="{{ asset('assets/js/database.js') }}"></script>
@endsection