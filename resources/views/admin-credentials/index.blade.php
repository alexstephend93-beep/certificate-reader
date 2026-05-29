@extends('layouts.app')

@section('title', 'Admin Credentials Manager')

@section('styles')
<style>
    /* Dashboard Card Styles */
    .dashboard-card {
        background: white;
        border-radius: 20px;
        overflow: hidden;
        transition: all 0.3s ease;
        border: 1px solid #e2e8f0;
        height: 100%;
        position: relative;
    }
    
    .dashboard-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 30px -12px rgba(0, 0, 0, 0.15);
        border-color: var(--color-primary);
    }
    
    .dashboard-header {
        padding: 20px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .dashboard-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        background: var(--gradient-primary);
        color: white;
    }
    
    .dashboard-title {
        font-weight: 700;
        font-size: 1rem;
        margin: 0;
    }
    
    .dashboard-url {
        font-size: 0.7rem;
        color: #64748b;
        word-break: break-all;
    }
    
    .favorite-star {
        cursor: pointer;
        font-size: 1.2rem;
        transition: all 0.3s ease;
    }
    
    .favorite-star:hover {
        transform: scale(1.1);
    }
    
    .favorite-star.active {
        color: #f59e0b;
    }
    
    .credential-item {
        background: #f8fafc;
        border-radius: 12px;
        padding: 12px;
        margin-bottom: 10px;
        transition: all 0.3s ease;
    }
    
    .credential-item:hover {
        background: #f1f5f9;
        transform: translateX(3px);
    }
    
    .credential-role {
        font-size: 0.65rem;
        padding: 2px 8px;
        border-radius: 20px;
        background: #e0e7ff;
        color: var(--color-primary);
    }
    
    .credential-default {
        font-size: 0.6rem;
        padding: 2px 6px;
        border-radius: 10px;
        background: #10b981;
        color: white;
    }
    
    .action-buttons {
        display: flex;
        gap: 8px;
        margin-top: 10px;
    }
    
    .action-icon {
        cursor: pointer;
        font-size: 1rem;
        transition: all 0.3s ease;
        padding: 5px;
        border-radius: 8px;
    }
    
    .action-icon:hover {
        background: #e2e8f0;
    }
    
    .action-icon.login {
        color: var(--color-primary);
    }
    
    .action-icon.copy {
        color: #10b981;
    }
    
    .action-icon.edit {
        color: #f59e0b;
    }
    
    .action-icon.delete {
        color: #ef4444;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: white;
        border-radius: 20px;
        padding: 20px;
        text-align: center;
        border: 1px solid #e2e8f0;
    }
    
    .stat-number {
        font-size: 2rem;
        font-weight: 800;
        color: var(--color-primary);
    }
    
    .search-container {
        background: white;
        border-radius: 60px;
        padding: 6px;
        display: flex;
        gap: 10px;
        margin-bottom: 25px;
        border: 1px solid #e2e8f0;
        flex-wrap: wrap;
    }
    
    .search-input {
        flex: 1;
        border: none;
        padding: 12px 24px;
        border-radius: 60px;
        font-size: 0.95rem;
        outline: none;
        background: transparent;
        min-width: 200px;
    }
    
    .search-btn {
        background: var(--gradient-primary);
        border: none;
        border-radius: 50px;
        padding: 10px 28px;
        color: white;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        white-space: nowrap;
    }
    
    .search-btn:hover {
        transform: scale(1.02);
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
    }
    
    .clear-search {
        background: #f1f5f9;
        border: none;
        border-radius: 50px;
        padding: 10px 20px;
        color: #64748b;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        white-space: nowrap;
    }
    
    .clear-search:hover {
        background: #e2e8f0;
        color: #475569;
    }
    
    .btn-add-dashboard {
        background: var(--gradient-primary);
        border: none;
        border-radius: 12px;
        padding: 12px 28px;
        font-weight: 600;
        font-size: 0.95rem;
        color: white;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }
    
    .btn-add-dashboard:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);
    }
    
    /* Modal Styles */
    .modal-dialog.modal-lg {
        max-width: 800px;
    }
    
    .modal-content {
        border-radius: 24px !important;
        border: none !important;
        overflow: hidden;
    }
    
    .modal-header {
        border-bottom: none !important;
        padding: 20px 28px !important;
    }
    
    .modal-body {
        padding: 28px !important;
        max-height: 70vh;
        overflow-y: auto;
    }
    
    .form-control, .form-select {
        border-radius: 12px !important;
        padding: 10px 14px !important;
        border: 1px solid #e2e8f0;
        transition: all 0.3s ease;
        width: 100%;
    }
    
    .form-control:focus, .form-select:focus {
        border-color: var(--color-primary) !important;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1) !important;
        outline: none;
    }
    
    .form-label {
        font-weight: 600;
        margin-bottom: 8px;
        color: var(--color-dark);
    }
    
    /* Credential Row Styles */
    .credential-row {
        background: #f8fafc;
        border-radius: 16px;
        padding: 16px;
        margin-bottom: 16px;
        border: 1px solid #e2e8f0;
    }
    
    .credential-row:first-child .remove-credential {
        display: none !important;
    }
    
    .btn-remove-credential {
        background: #fee2e2;
        border: none;
        border-radius: 10px;
        padding: 10px 12px;
        color: #dc2626;
        font-size: 0.75rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }
    
    .btn-remove-credential:hover {
        background: #dc2626;
        color: white;
    }
    
    .btn-add-credential {
        background: transparent;
        border: 2px dashed #cbd5e1;
        border-radius: 12px;
        padding: 10px;
        color: var(--color-primary);
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        margin-top: 10px;
    }
    
    .btn-add-credential:hover {
        border-color: var(--color-primary);
        background: rgba(99, 102, 241, 0.05);
    }
    
    .btn-modal-save {
        background: var(--gradient-primary);
        border: none;
        border-radius: 12px;
        padding: 12px 24px;
        font-weight: 600;
        font-size: 0.9rem;
        color: white;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        flex: 1;
    }
    
    .btn-modal-save:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);
    }
    
    .btn-modal-cancel {
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 12px 24px;
        font-weight: 500;
        font-size: 0.9rem;
        color: #475569;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        flex: 1;
    }
    
    .btn-modal-cancel:hover {
        background: #e2e8f0;
        color: #1e293b;
    }
    
    .no-results {
        text-align: center;
        padding: 60px 20px;
    }
    
    .no-results i {
        font-size: 4rem;
        color: #cbd5e1;
    }
    
    .dashboard-wrapper.hidden {
        display: none;
    }
    
    /* Validation Error Styles */
    .is-invalid {
        border-color: #dc2626 !important;
    }
    
    .invalid-feedback {
        color: #dc2626;
        font-size: 0.75rem;
        margin-top: 5px;
        display: block;
    }
    
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }
        
        .stat-number {
            font-size: 1.5rem;
        }
        
        .search-container {
            flex-direction: column;
            border-radius: 20px;
        }
        
        .search-btn, .clear-search {
            width: 100%;
            justify-content: center;
        }
        
        .modal-dialog.modal-lg {
            margin: 10px;
            max-width: calc(100% - 20px);
        }
        
        .modal-body {
            padding: 20px !important;
        }
        
        .btn-modal-save, .btn-modal-cancel {
            padding: 10px 16px;
        }
        
        .btn-add-dashboard {
            padding: 10px 20px;
            font-size: 0.85rem;
        }
    }
</style>
@endsection

@section('content')
<div class="glass-card" data-aos="fade-down">
    <div class="gradient-header text-center">
        <h1 class="fw-bold">
            <i class="bi bi-key-fill me-3"></i>
            Admin Credentials Manager
        </h1>
        <p class="lead mb-0">
            <i class="bi bi-shield-lock me-2"></i>
            Securely store and manage all your admin panel credentials
        </p>
    </div>

    <div class="p-4 p-md-5">
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number">{{ $dashboards->count() }}</div>
                <div class="stat-label">Total Dashboards</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">{{ $dashboards->sum(fn($d) => $d->credentials->count()) }}</div>
                <div class="stat-label">Total Credentials</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">{{ $favorites->count() }}</div>
                <div class="stat-label">Favorites</div>
            </div>
        </div>
        
        <!-- Search Bar -->
        <div class="search-container">
            <input type="text" id="searchInput" class="search-input" placeholder="🔍 Search by dashboard name, URL, email, or username...">
            <button class="search-btn" onclick="searchDashboards()">
                <i class="bi bi-search"></i> Search
            </button>
            <button class="clear-search" onclick="clearSearch()">
                <i class="bi bi-x-lg"></i> Clear
            </button>
        </div>
        
        <!-- Add New Button -->
        <div class="text-end mb-4">
            <button class="btn-add-dashboard" onclick="openAddModal()">
                <i class="bi bi-plus-circle"></i> Add New Dashboard
            </button>
        </div>
        
        <!-- Dashboards Grid -->
        <div class="row g-4" id="dashboardsGrid">
            @forelse($dashboards as $dashboard)
            <div class="col-12 col-md-6 col-lg-4 dashboard-wrapper" 
                 data-searchable="{{ strtolower($dashboard->name . ' ' . $dashboard->url . ' ' . $dashboard->credentials->pluck('email')->join(' ') . ' ' . $dashboard->credentials->pluck('username')->join(' ')) }}">
                <div class="dashboard-card">
                    <div class="dashboard-header">
                        <div class="d-flex align-items-center gap-3">
                            <div class="dashboard-icon" style="background: {{ $dashboard->color ?? 'var(--gradient-primary)' }};">
                                <i class="bi bi-{{ $dashboard->icon }}"></i>
                            </div>
                            <div>
                                <h4 class="dashboard-title">{{ $dashboard->name }}</h4>
                                <small class="dashboard-url">{{ Str::limit($dashboard->url, 40) }}</small>
                            </div>
                        </div>
                        <i class="bi bi-star{{ $dashboard->is_favorite ? '-fill favorite-star active' : ' favorite-star' }}" 
                           data-id="{{ $dashboard->id }}" 
                           onclick="toggleFavorite({{ $dashboard->id }}, this)"></i>
                    </div>
                    
                    <div class="p-3">
                        @if($dashboard->description)
                            <p class="small text-muted mb-2">{{ $dashboard->description }}</p>
                        @endif
                        
                        <div class="mb-2">
                            <small class="text-muted">Integration: {{ $dashboard->integration_name ?? 'Custom' }}</small>
                        </div>
                        
                        <!-- Credentials List -->
                        @foreach($dashboard->credentials as $cred)
                            <div class="credential-item" data-cred-id="{{ $cred->id }}">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <strong>{{ $cred->email ?: $cred->username }}</strong>
                                        @if($cred->is_default)
                                            <span class="credential-default ms-2">Default</span>
                                        @endif
                                        @if($cred->role)
                                            <span class="credential-role ms-2">{{ $cred->role }}</span>
                                        @endif
                                    </div>
                                    <div class="action-buttons">
                                        <i class="bi bi-box-arrow-up-right action-icon login"
                                           title="Auto Login"
                                           onclick="window.open('{{ route('admin-credentials.auto-login-page', $cred->id) }}', '_blank')"></i>
                                        <i class="bi bi-clipboard action-icon copy" 
                                           title="Copy Credentials" 
                                           onclick="copyCredentials({{ $cred->id }})"></i>
                                        <i class="bi bi-pencil action-icon edit" 
                                           title="Edit Credential" 
                                           onclick="editCredential({{ $cred->id }})"></i>
                                        <i class="bi bi-star action-icon" 
                                           title="Set as Default" 
                                           onclick="setDefaultCredential({{ $cred->id }})"></i>
                                        <i class="bi bi-trash action-icon delete" 
                                           title="Delete Credential" 
                                           onclick="deleteCredential({{ $cred->id }})"></i>
                                    </div>
                                </div>
                                <div class="small text-muted">
                                    @if($cred->username)
                                        <div><i class="bi bi-person"></i> {{ $cred->username }}</div>
                                    @endif
                                    <div><i class="bi bi-envelope"></i> {{ $cred->email }}</div>
                                </div>
                            </div>
                        @endforeach
                        
                        <!-- Add Credential Button -->
                        <button class="btn-add-credential mt-2" onclick="addCredential({{ $dashboard->id }})">
                            <i class="bi bi-plus-circle"></i> Add Credential
                        </button>
                    </div>
                    
                    <div class="p-3 border-top d-flex justify-content-between">
                        <small class="text-muted">
                            <i class="bi bi-eye"></i> {{ $dashboard->usage_count }} uses
                        </small>
                        <div>
                            <i class="bi bi-pencil action-icon edit" title="Edit Dashboard" onclick="editDashboard({{ $dashboard->id }})"></i>
                            <i class="bi bi-trash action-icon delete ms-2" title="Delete Dashboard" onclick="deleteDashboard({{ $dashboard->id }})"></i>
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-12">
                <div class="text-center py-5">
                    <i class="bi bi-key fs-1 text-muted"></i>
                    <h4 class="mt-3">No dashboards configured</h4>
                    <p class="text-muted">Add your first dashboard using the button above</p>
                </div>
            </div>
            @endforelse
        </div>
        
        <div id="noResultsMessage" class="text-center py-5" style="display: none;">
            <i class="bi bi-search fs-1 text-muted"></i>
            <h4 class="mt-3">No dashboards found</h4>
            <p class="text-muted">Try a different search term</p>
        </div>
    </div>
</div>

<!-- Add/Edit Dashboard Modal -->
<div class="modal fade" id="dashboardModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: var(--gradient-primary);">
                <h5 class="modal-title text-white" id="dashboardModalTitle" style="font-weight: 700;">
                    <i class="bi bi-plus-circle me-2"></i> Add Dashboard
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="dashboardForm">
                    @csrf
                    <input type="hidden" name="_method" id="formMethod" value="POST">
                    <input type="hidden" name="dashboard_id" id="dashboardId">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Dashboard Name</label>
                            <input type="text" name="name" id="dashName" class="form-control" placeholder="e.g., WordPress Admin">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Integration Name</label>
                            <input type="text" name="integration_name" id="dashIntegration" class="form-control" placeholder="e.g., WordPress, Laravel">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">URL</label>
                        <input type="url" name="url" id="dashUrl" class="form-control" placeholder="https://example.com/admin">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Icon</label>
                            <input type="text" name="icon" id="dashIcon" class="form-control" placeholder="wordpress, laravel, server">
                            <small class="text-muted">Bootstrap icon name</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Brand Color</label>
                            <input type="color" name="color" id="dashColor" class="form-control" style="height: 45px;" value="#6366f1">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="dashDescription" class="form-control" rows="2" placeholder="Optional description"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Credentials</label>
                        <div id="credentialsContainer">
                            <div class="credential-row">
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <input type="email" name="emails[]" class="form-control" placeholder="Email">
                                    </div>
                                    <div class="col-md-6">
                                        <input type="password" name="passwords[]" class="form-control" placeholder="Password">
                                    </div>
                                    <div class="col-md-6">
                                        <input type="text" name="usernames[]" class="form-control" placeholder="Username (optional)">
                                    </div>
                                    <div class="col-md-4">
                                        <input type="text" name="roles[]" class="form-control" placeholder="Role">
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn-remove-credential remove-credential">
                                            <i class="bi bi-trash"></i> Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn-add-credential" onclick="addCredentialRow()">
                            <i class="bi bi-plus-circle"></i> Add Another Credential
                        </button>
                    </div>
                    
                    <div class="d-flex gap-3 mt-4">
                        <button type="submit" class="btn-modal-save">
                            <i class="bi bi-check-circle"></i> Save Dashboard
                        </button>
                        <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Edit Credential Modal -->
<div class="modal fade" id="credentialModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 24px; overflow: hidden;">
            <div class="modal-header" style="background: var(--gradient-primary);">
                <h5 class="modal-title text-white" style="font-weight: 700;">
                    <i class="bi bi-pencil-square me-2"></i> Edit Credential
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="credentialForm">
                    @csrf
                    <input type="hidden" name="_method" id="credFormMethod" value="PUT">
                    <input type="hidden" name="credential_id" id="credentialId">
                    
                    <div class="mb-3">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="credEmail" class="form-control">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" id="credUsername" class="form-control">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" id="credPassword" class="form-control" placeholder="Leave blank to keep current">
                        <small class="text-muted">Leave empty to keep current password</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <input type="text" name="role" id="credRole" class="form-control" placeholder="e.g., Admin, Editor">
                    </div>
                    
                    <div class="d-flex gap-3 mt-4">
                        <button type="submit" class="btn-modal-save">
                            <i class="bi bi-check-circle"></i> Update Credential
                        </button>
                        <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Search functionality
function searchDashboards() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase().trim();
    const wrappers = document.querySelectorAll('.dashboard-wrapper');
    let visibleCount = 0;
    
    if (!searchTerm) {
        wrappers.forEach(wrapper => wrapper.classList.remove('hidden'));
        document.getElementById('noResultsMessage').style.display = 'none';
        return;
    }
    
    wrappers.forEach(wrapper => {
        const searchable = wrapper.dataset.searchable || '';
        if (searchable.includes(searchTerm)) {
            wrapper.classList.remove('hidden');
            visibleCount++;
        } else {
            wrapper.classList.add('hidden');
        }
    });
    
    document.getElementById('noResultsMessage').style.display = visibleCount === 0 ? 'block' : 'none';
}

function clearSearch() {
    document.getElementById('searchInput').value = '';
    searchDashboards();
}

document.getElementById('searchInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') searchDashboards();
});

// Credential rows management
function addCredentialRow() {
    const container = document.getElementById('credentialsContainer');
    const newRow = document.createElement('div');
    newRow.className = 'credential-row';
    newRow.innerHTML = `
        <div class="row g-2">
            <div class="col-md-6">
                <input type="email" name="emails[]" class="form-control" placeholder="Email">
            </div>
            <div class="col-md-6">
                <input type="password" name="passwords[]" class="form-control" placeholder="Password">
            </div>
            <div class="col-md-6">
                <input type="text" name="usernames[]" class="form-control" placeholder="Username (optional)">
            </div>
            <div class="col-md-4">
                <input type="text" name="roles[]" class="form-control" placeholder="Role">
            </div>
            <div class="col-md-2">
                <button type="button" class="btn-remove-credential remove-credential">
                    <i class="bi bi-trash"></i> Remove
                </button>
            </div>
        </div>
    `;
    container.appendChild(newRow);
    newRow.querySelector('.remove-credential').addEventListener('click', () => newRow.remove());
}

// Open Add Modal
function openAddModal() {
    document.getElementById('dashboardModalTitle').innerHTML = '<i class="bi bi-plus-circle me-2"></i> Add Dashboard';
    document.getElementById('formMethod').value = 'POST';
    document.getElementById('dashboardForm').action = '{{ route("admin-credentials.store") }}';
    document.getElementById('dashboardForm').reset();
    document.getElementById('dashboardId').value = '';
    document.getElementById('dashColor').value = '#6366f1';
    
    // Reset credentials container
    const container = document.getElementById('credentialsContainer');
    container.innerHTML = `
        <div class="credential-row">
            <div class="row g-2">
                <div class="col-md-6">
                    <input type="email" name="emails[]" class="form-control" placeholder="Email">
                </div>
                <div class="col-md-6">
                    <input type="password" name="passwords[]" class="form-control" placeholder="Password">
                </div>
                <div class="col-md-6">
                    <input type="text" name="usernames[]" class="form-control" placeholder="Username (optional)">
                </div>
                <div class="col-md-4">
                    <input type="text" name="roles[]" class="form-control" placeholder="Role">
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn-remove-credential remove-credential" style="display: none;">
                        <i class="bi bi-trash"></i> Remove
                    </button>
                </div>
            </div>
        </div>
    `;
    
    new bootstrap.Modal(document.getElementById('dashboardModal')).show();
}

// Edit Dashboard
function editDashboard(id) {
    fetch(`/admin-credentials/${id}/edit`)
        .then(res => res.json())
        .then(data => {
            document.getElementById('dashboardModalTitle').innerHTML = '<i class="bi bi-pencil-square me-2"></i> Edit Dashboard';
            document.getElementById('formMethod').value = 'PUT';
            document.getElementById('dashboardForm').action = `/admin-credentials/${id}`;
            document.getElementById('dashboardId').value = id;
            document.getElementById('dashName').value = data.name;
            document.getElementById('dashIntegration').value = data.integration_name;
            document.getElementById('dashUrl').value = data.url;
            document.getElementById('dashIcon').value = data.icon;
            document.getElementById('dashColor').value = data.color || '#6366f1';
            document.getElementById('dashDescription').value = data.description;
            
            new bootstrap.Modal(document.getElementById('dashboardModal')).show();
        });
}

// Delete Dashboard
function deleteDashboard(id) {
    if (confirm('Are you sure you want to delete this dashboard and all its credentials?')) {
        fetch(`/admin-credentials/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            }
        }).then(() => location.reload());
    }
}

// Add Credential
function addCredential(dashboardId) {
    const email = prompt('Enter email address:');
    if (email) {
        const password = prompt('Enter password:');
        if (password) {
            fetch(`/admin-credentials/${dashboardId}/credential`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    email: email,
                    password: password,
                    username: prompt('Enter username (optional):') || '',
                    role: prompt('Enter role (optional):') || 'User'
                })
            }).then(() => location.reload());
        }
    }
}

// Edit Credential
function editCredential(id) {
    fetch(`/admin-credentials/credential/${id}`)
        .then(res => res.json())
        .then(data => {
            document.getElementById('credentialId').value = id;
            document.getElementById('credEmail').value = data.email;
            document.getElementById('credUsername').value = data.username || '';
            document.getElementById('credRole').value = data.role || '';
            document.getElementById('credPassword').value = '';
            document.getElementById('credentialForm').action = `/admin-credentials/credential/${id}`;
            new bootstrap.Modal(document.getElementById('credentialModal')).show();
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error loading credential data', 'error');
        });
}

// Delete Credential
function deleteCredential(id) {
    if (confirm('Are you sure you want to delete this credential?')) {
        fetch(`/admin-credentials/credential/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            }
        }).then(() => location.reload());
    }
}

// Set Default Credential
function setDefaultCredential(id) {
    fetch(`/admin-credentials/credential/${id}/default`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json'
        }
    }).then(() => location.reload());
}

// Toggle Favorite
function toggleFavorite(id, element) {
    fetch(`/admin-credentials/${id}/favorite`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json'
        }
    }).then(res => res.json())
      .then(data => {
          if (data.is_favorite) {
              element.classList.add('bi-star-fill', 'active');
              element.classList.remove('bi-star');
          } else {
              element.classList.add('bi-star');
              element.classList.remove('bi-star-fill', 'active');
          }
          showToast(data.is_favorite ? 'Added to favorites' : 'Removed from favorites', 'success');
      });
}

// Copy Credentials
function copyCredentials(id) {
    fetch(`/admin-credentials/copy/${id}`)
        .then(res => res.json())
        .then(data => {
            const text = `Email: ${data.email}\nUsername: ${data.username || 'N/A'}\nPassword: ${data.password}\nRole: ${data.role || 'N/A'}`;
            navigator.clipboard.writeText(text);
            showToast('Credentials copied to clipboard!', 'success');
        });
}

// Handle Dashboard Form Submit
document.getElementById('dashboardForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalHtml = submitBtn.innerHTML;
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Saving...';
    
    // Clear previous errors
    document.querySelectorAll('#dashboardModal .invalid-feedback').forEach(el => el.remove());
    document.querySelectorAll('#dashboardModal .is-invalid').forEach(el => el.classList.remove('is-invalid'));
    
    try {
        const response = await fetch(this.action, {
            method: this.querySelector('input[name="_method"]')?.value || 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => {
                window.location.href = data.redirect || '{{ route("admin-credentials.index") }}';
            }, 1000);
        } else if (data.errors) {
            for (const [field, errors] of Object.entries(data.errors)) {
                let input;
                if (field === 'name') input = document.getElementById('dashName');
                else if (field === 'url') input = document.getElementById('dashUrl');
                else if (field.startsWith('emails.')) {
                    const index = field.match(/\d+/)?.[0];
                    if (index !== undefined) {
                        input = document.querySelector(`input[name="emails[${index}]"]`);
                    }
                } else if (field.startsWith('passwords.')) {
                    const index = field.match(/\d+/)?.[0];
                    if (index !== undefined) {
                        input = document.querySelector(`input[name="passwords[${index}]"]`);
                    }
                }
                
                if (input) {
                    input.classList.add('is-invalid');
                    const feedback = document.createElement('div');
                    feedback.className = 'invalid-feedback';
                    feedback.textContent = errors[0];
                    input.parentNode.appendChild(feedback);
                }
            }
            showToast('Please fix the errors above', 'error');
        } else {
            showToast(data.message || 'Something went wrong', 'error');
        }
    } catch (error) {
        showToast('Network error. Please try again.', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalHtml;
    }
});

// Handle Credential Form Submit
document.getElementById('credentialForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const credentialId = document.getElementById('credentialId').value;
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalHtml = submitBtn.innerHTML;
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Updating...';
    
    // Clear previous errors
    document.querySelectorAll('#credentialModal .invalid-feedback').forEach(el => el.remove());
    document.querySelectorAll('#credentialModal .is-invalid').forEach(el => el.classList.remove('is-invalid'));
    
    try {
        const response = await fetch(`/admin-credentials/credential/${credentialId}`, {
            method: 'PUT',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                email: document.getElementById('credEmail').value,
                username: document.getElementById('credUsername').value,
                password: document.getElementById('credPassword').value,
                role: document.getElementById('credRole').value
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else if (data.errors) {
            for (const [field, errors] of Object.entries(data.errors)) {
                let input;
                if (field === 'email') {
                    input = document.getElementById('credEmail');
                } else if (field === 'password') {
                    input = document.getElementById('credPassword');
                } else {
                    input = document.querySelector(`#credentialModal [name="${field}"]`);
                }
                
                if (input) {
                    input.classList.add('is-invalid');
                    const feedback = document.createElement('div');
                    feedback.className = 'invalid-feedback';
                    feedback.textContent = errors[0];
                    input.parentNode.appendChild(feedback);
                }
            }
            showToast('Please fix the errors above', 'error');
        } else {
            showToast(data.message || 'Something went wrong', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Network error. Please try again.', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalHtml;
    }
});

// Show Toast
let toastTimeout;
function showToast(message, type) {
    const existingToast = document.querySelector('.toast-notification');
    if (existingToast) {
        existingToast.remove();
        if (toastTimeout) clearTimeout(toastTimeout);
    }
    
    const toast = document.createElement('div');
    toast.className = `toast-notification ${type}`;
    toast.innerHTML = `<i class="bi bi-${type === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill'} me-2"></i> ${message}`;
    toast.style.cssText = `
        position: fixed;
        bottom: 100px;
        right: 30px;
        background: ${type === 'success' ? '#10b981' : '#ef4444'};
        color: white;
        padding: 12px 20px;
        border-radius: 10px;
        z-index: 10002;
        animation: slideUp 0.3s ease;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    `;
    document.body.appendChild(toast);
    
    toastTimeout = setTimeout(() => {
        if (toast) {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(20px)';
            setTimeout(() => toast.remove(), 300);
        }
    }, 3000);
}

// Initialize search data
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.dashboard-wrapper').forEach(wrapper => {
        const card = wrapper.querySelector('.dashboard-card');
        if (card) {
            const name = card.querySelector('.dashboard-title')?.textContent || '';
            const url = card.querySelector('.dashboard-url')?.textContent || '';
            const emails = Array.from(card.querySelectorAll('.credential-item .bi-envelope')).map(el => el.parentElement?.textContent || '');
            wrapper.dataset.searchable = `${name} ${url} ${emails.join(' ')}`.toLowerCase();
        }
    });
});
</script>
@endsection