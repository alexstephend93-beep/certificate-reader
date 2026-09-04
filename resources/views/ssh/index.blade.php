@extends('layouts.app')

@section('title', 'SSH Manager')

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/ssh.css') }}">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/noty@3.2.0-beta4/lib/noty.min.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .ssh-toolbar {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.14), rgba(255, 255, 255, 0.06));
        border-radius: 18px;
        padding: 16px;
        backdrop-filter: blur(8px);
        border: 1px solid rgba(255, 255, 255, 0.16);
    }

    .toolbar-actions {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .toolbar-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 14px;
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.9);
        color: #334155;
        font-weight: 600;
        font-size: 0.85rem;
    }

    #noResultsMessage {
        display: none;
    }

    .search-input:focus {
        outline: 2px solid var(--color-primary);
        outline-offset: 2px;
    }

    @media (max-width: 768px) {
        .toolbar-actions {
            gap: 6px;
        }

        .toolbar-actions .btn {
            font-size: 0.8rem;
            padding: 6px 8px;
        }

        .toolbar-actions .btn i {
            font-size: 0.9rem;
        }
    }

    @media (max-width: 576px) {
        .stats-grid-ssh {
            grid-template-columns: 1fr;
        }

        .toolbar-actions {
            justify-content: center;
            gap: 4px;
        }

        .toolbar-actions .btn {
            font-size: 0.75rem;
            padding: 4px 6px;
        }
    }
</style>
@endsection

@section('content')
<div class="glass-card" data-aos="fade-down">
    <div class="gradient-header text-center">
        <h1 class="fw-bold mb-2">
            <i class="bi bi-server me-2"></i>
            SSH Manager
        </h1>
        <p class="lead mb-0">Manage SSH hosts from your local SSH config file</p>
    </div>

    <div class="p-4 p-md-5">
        <div class="ssh-toolbar mb-4">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-lg-8">
                    <label for="searchInput" class="form-label fw-semibold">
                        <i class="bi bi-search me-1"></i> Search Hosts
                    </label>
                    <div class="search-container">
                        <input
                            id="searchInput"
                            type="text"
                            class="search-input"
                            placeholder="Search by host, hostname, user, or domain"
                            autocomplete="off"
                            tabindex="0"
                        >
                        <button class="clear-search" type="button" onclick="clearSearch()">
                            <i class="bi bi-x-circle"></i> Clear
                        </button>
                        <button class="search-btn" type="button" onclick="searchServers()">
                            <i class="bi bi-search"></i> Search
                        </button>
                    </div>
                </div>
                <div class="col-12 col-lg-4">
                    <div class="toolbar-actions d-flex flex-wrap gap-2 align-items-center">
                        <span class="toolbar-badge">
                            <i class="bi bi-lightning-charge-fill text-warning"></i>
                            Live SSH Status
                        </span>
                         <div class="btn-group" role="group">
                             <button type="button" class="btn btn-success btn-sm" onclick="openAddSshServerModal()" title="Add New SSH Server">
                                 <i class="bi bi-plus-circle"></i> Add
                             </button>
                             <button type="button" class="btn btn-outline-primary btn-sm" onclick="exportSshServers()" title="Export SSH Servers">
                                 <i class="bi bi-download"></i> Export
                             </button>
                             <button type="button" class="btn btn-outline-secondary btn-sm" onclick="importSshServers()" title="Import SSH Servers">
                                 <i class="bi bi-upload"></i> Import
                             </button>
                             <button type="button" class="btn btn-outline-info btn-sm" onclick="downloadSampleJson()" title="Download Sample JSON">
                                 <i class="bi bi-file-earmark-code"></i> Sample
                             </button>
                         </div>
                         <div class="btn-group" role="group">
                             <button type="button" class="btn btn-outline-success btn-sm" onclick="uploadPemKey()" title="Upload PEM Key File">
                                 <i class="bi bi-upload"></i> Upload PEM
                             </button>
                             <button type="button" class="btn btn-outline-dark btn-sm" onclick="fixAllConnections()" title="Fix All Connections & Clean Config">
                                 <i class="bi bi-wrench"></i> Fix All
                             </button>
                         </div>
                        <button type="button" class="btn btn-outline-info btn-sm" onclick="openNsLookupModal()" title="NS Lookup for Domain">
                            <i class="bi bi-search"></i> NS Lookup
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="stats-grid-ssh">
            <div class="stat-card-ssh">
                <div id="totalServers" class="stat-number-ssh">0</div>
                <div class="stat-label">Total Servers</div>
            </div>
            <div class="stat-card-ssh">
                <div id="validKeys" class="stat-number-ssh">0</div>
                <div class="stat-label">Valid Keys</div>
            </div>
        </div>

        <div id="noResultsMessage" class="alert alert-warning mb-3" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            No matching SSH servers found for your search.
        </div>

        <div class="row g-4" id="serversGrid">
            <div class="col-12">
                <div class="text-center py-5 text-muted">
                    <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                    <div class="mt-3">Loading SSH servers...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SSH Server Edit Modal -->
<div class="modal fade" id="sshModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="sshModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header" style="background: var(--gradient-primary); border-bottom: none;">
                <h5 class="modal-title text-white" id="sshModalLabel">
                    <i class="bi bi-server me-2"></i>Edit SSH Server
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding: 30px;">
                <form id="sshForm" onsubmit="return false;">
                    <input type="hidden" id="originalHost" name="originalHost">

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-bold">Host Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="sshHost" placeholder="e.g., myserver" required>
                            <small class="text-muted">The alias name for this SSH connection</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Host <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="sshHostname" placeholder="e.g., example.com or 192.168.1.100" required>
                            <small class="text-muted">The actual hostname or IP address</small>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold">Port</label>
                            <input type="number" class="form-control" id="sshPort" value="22" min="1" max="65535">
                            <small class="text-muted">SSH port (default: 22)</small>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold">User <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="sshUser" value="ubuntu" placeholder="e.g., ubuntu" required>
                            <small class="text-muted">SSH username</small>
                        </div>

                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-bold">Identity File <span class="text-danger">*</span></label>
                            <select class="form-select" id="sshIdentityFile" required>
                                <option value="">Select SSH key file...</option>
                                <!-- Options will be populated by JavaScript -->
                            </select>
                            <small class="text-muted">Select your SSH private key file from Documents/SSH directory</small>
                        </div>

                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-bold">Domains</label>
                            <input type="text" class="form-control" id="sshDomains" placeholder="domain1.com, domain2.com">
                            <small class="text-muted">Comma-separated list of domains hosted on this server</small>
                        </div>

                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-bold">Description</label>
                            <textarea class="form-control" id="sshDescription" rows="2" placeholder="Optional description"></textarea>
                        </div>

                        <div class="col-md-12 mb-4">
                            <button type="button" class="btn btn-outline-success w-100" id="testSshBeforeSaveBtn" onclick="testSshBeforeSave()">
                                <i class="bi bi-plug"></i> Test SSH Connection Before Saving
                            </button>
                            <div id="sshTestResultMsg" class="mt-2" style="display: none;"></div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #e2e8f0;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveSshServer()">Save Server</button>
            </div>
        </div>
    </div>
</div>

<!-- SSH Import Modal -->
<div class="modal fade" id="sshImportModal" tabindex="-1" data-bs-backdrop="static" aria-labelledby="sshImportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header" style="background: var(--gradient-primary); border-bottom: none;">
                <h5 class="modal-title text-white" id="sshImportModalLabel">
                    <i class="bi bi-upload me-2"></i>Import SSH Servers
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding: 30px;">
                <div class="alert alert-info">
                    <h6><i class="bi bi-info-circle me-2"></i>Import Instructions</h6>
                    <p class="mb-2">Upload a JSON file containing SSH server configurations. The file should contain an array of server objects with the following structure:</p>
                    <pre class="bg-light p-2 rounded small"><code>[
  {
    "host": "server_name",
    "hostname": "192.168.1.100",
    "user": "ubuntu",
    "identity_file": "~/Documents/SSH/key.pem",
    "port": 22,
    "domains": ["example.com"],
    "description": "Server description"
  }
]</code></pre>
                </div>

                <form id="sshImportForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="sshImportFile" class="form-label fw-bold">Select JSON File</label>
                        <input type="file" class="form-control" id="sshImportFile" name="json_file" accept=".json" required>
                        <small class="text-muted">Maximum file size: 5MB</small>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="skipExisting">
                        <label class="form-check-label" for="skipExisting">
                            Skip existing servers (don't overwrite)
                        </label>
                    </div>
                </form>

                <div id="sshImportProgress" style="display: none;">
                    <div class="progress mb-3">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%"></div>
                    </div>
                    <div id="sshImportStatus">Preparing import...</div>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #e2e8f0;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitSshImport()">Import Servers</button>
            </div>
        </div>
    </div>
</div>

<!-- SSH Projects Browser Modal -->
<div class="modal fade" id="sshProjectsModal" tabindex="-1" data-bs-backdrop="static" aria-labelledby="sshProjectsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content" style="border-radius: 20px; overflow: hidden; background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(248, 250, 252, 0.95)); backdrop-filter: blur(10px);">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-bottom: none; position: relative;">
                <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255, 255, 255, 0.1); border-radius: 20px 20px 0 0;"></div>
                <h5 class="modal-title text-white position-relative" id="sshProjectsModalLabel" style="z-index: 1;">
                    <i class="bi bi-folder2-open-fill me-2"></i>Project Explorer
                </h5>
                <button type="button" class="btn-close btn-close-white position-relative" data-bs-dismiss="modal" aria-label="Close" style="z-index: 1;"></button>
            </div>
            <div class="modal-body" style="padding: 30px; background: rgba(255, 255, 255, 0.8);">
                <div class="text-center mb-4">
                    <h6 class="text-muted mb-2">
                        <i class="bi bi-hdd-rack-fill me-2" style="color: #667eea;"></i>
                        <span id="serverInfo">Loading server projects...</span>
                    </h6>
                    <div class="badge bg-primary px-3 py-2" style="font-size: 0.85rem;">
                        <i class="bi bi-server me-1"></i>
                        <span id="serverHost">server</span>
                    </div>
                </div>

                <div id="projectsLoading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                        <span class="sr-only">Loading...</span>
                    </div>
                    <div class="mt-3 text-muted">Scanning project directories...</div>
                </div>

                <div id="projectsGrid" class="row g-3" style="display: none;">
                    <!-- Projects will be populated here -->
                </div>

                <div id="noProjects" class="text-center py-5" style="display: none;">
                    <div style="font-size: 3rem; color: #e2e8f0;">
                        <i class="bi bi-folder-x"></i>
                    </div>
                    <h5 class="text-muted mt-3">No Projects Found</h5>
                    <p class="text-muted">No project directories were found on this server.</p>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid rgba(255, 255, 255, 0.2); background: rgba(255, 255, 255, 0.8);">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Close
                </button>
                <button type="button" class="btn btn-primary" onclick="openServerRootInVSCode()">
                    <i class="bi bi-folder me-1"></i>Open Server Root
                </button>
            </div>
        </div>
    </div>
</div>

<!-- NS Lookup Modal -->
<div class="modal fade" id="nsLookupModal" tabindex="-1" data-bs-backdrop="static" aria-labelledby="nsLookupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); border-bottom: none;">
                <h5 class="modal-title text-white" id="nsLookupModalLabel">
                    <i class="bi bi-search me-2"></i>NS Lookup
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding: 30px;">
                <div class="alert alert-info">
                    <h6><i class="bi bi-info-circle me-2"></i>Domain Name System Lookup</h6>
                    <p class="mb-2">Enter a domain name to perform NS lookup and get DNS information including IP addresses, MX records, and other DNS details.</p>
                </div>

                <form id="nsLookupForm" onsubmit="return false;">
                    <div class="mb-3">
                        <label for="domainInput" class="form-label fw-bold">Domain Name</label>
                        <input type="text" class="form-control" id="domainInput" placeholder="example.com" required>
                        <small class="text-muted">Enter domain name without http:// or https://</small>
                    </div>
                </form>

                <div id="nsLookupProgress" style="display: none;">
                    <div class="progress mb-3">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%"></div>
                    </div>
                    <div class="text-center">Performing NS lookup...</div>
                </div>

                <div id="nsLookupResult" style="display: none;">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <i class="bi bi-check-circle me-2"></i>Lookup Results
                        </div>
                        <div class="card-body">
                            <pre id="nsLookupOutput" class="bg-light p-3 rounded" style="font-family: 'Courier New', monospace; font-size: 0.9rem; white-space: pre-wrap;"></pre>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid rgba(255, 255, 255, 0.2); background: rgba(255, 255, 255, 0.8);">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Close
                </button>
                <button type="button" class="btn btn-primary" onclick="performNsLookup()">
                    <i class="bi bi-search me-1"></i>Lookup
                </button>
            </div>
        </div>
    </div>
</div>

<!-- SSH Project Explorer Modal (File Browser) -->
<div class="modal fade" id="sshExplorerModal" tabindex="-1" data-bs-backdrop="static" aria-labelledby="sshExplorerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content" style="border-radius: 20px; overflow: hidden; background: linear-gradient(135deg, rgba(255,255,255,0.96), rgba(248,250,252,0.96)); backdrop-filter: blur(10px);">
            <div class="modal-header" style="background: linear-gradient(135deg, #0f766e 0%, #115e59 100%); border-bottom: none;">
                <h5 class="modal-title text-white" id="sshExplorerModalLabel" style="white-space: nowrap;">
                    <i class="bi bi-diagram-3 me-2"></i>Project Explorer
                </h5>
                <div class="badge bg-white text-dark px-3 py-2 ms-auto me-3" style="font-size: 0.85rem; max-width: 40%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                    <span id="explorerServerBadge"><i class="bi bi-server me-1"></i>server</span>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding: 20px;">
                <div class="explorer-topbar">
                    <button class="btn btn-sm btn-outline-secondary" onclick="explorerGoBack()" title="Go to parent directory">
                        <i class="bi bi-arrow-left-circle"></i> Back
                    </button>
                    <button class="btn btn-sm btn-outline-primary" onclick="refreshExplorerDirectory()" title="Refresh current directory">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                    <button class="btn btn-sm btn-outline-success" onclick="createExplorerFile()" title="Create a new empty file in this directory">
                        <i class="bi bi-file-earmark-plus"></i> New File
                    </button>
                    <button class="btn btn-sm btn-outline-success" onclick="createExplorerDirectory()" title="Create a new folder in this directory (as root, permission 755)">
                        <i class="bi bi-folder-plus"></i> New Folder
                    </button>
                    <button class="btn btn-sm btn-outline-primary" onclick="document.getElementById('explorerUploadInput').click()" title="Upload a file into this directory (never overwrites an existing file)">
                        <i class="bi bi-cloud-arrow-up"></i> Upload
                    </button>
                    <input type="file" id="explorerUploadInput" style="display: none;" onchange="uploadExplorerFile(this)">
                    <nav id="explorerBreadcrumb" class="explorer-breadcrumb"></nav>
                </div>

                <div id="explorerLoading" class="text-center py-5" style="display: none;">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="mt-2 text-muted">Loading directory...</div>
                </div>

                <div id="explorerList" class="explorer-list" style="display: none;">
                    <div class="explorer-list-head">
                        <div><i class="bi bi-folder me-1"></i>Name</div>
                        <div class="explorer-col-size text-center" title="File size — for directories, the total size of all contents (du)">Size</div>
                        <div class="explorer-col-created text-center" title="Created on (when the filesystem provides it)">Created</div>
                        <div class="explorer-col-modified text-center" title="Last modified">Modified</div>
                        <div class="explorer-col-perm text-center" title="Permission (octal). Use the shield icon on a row to change it.">Perm</div>
                        <div class="explorer-col-actions text-center">Actions</div>
                    </div>
                    <div id="explorerEntries"></div>
                </div>

                <div id="explorerEmpty" class="text-center py-5 text-muted" style="display: none;">
                    <i class="bi bi-folder-x" style="font-size: 2.5rem;"></i>
                    <p class="mt-2 mb-0">This directory is empty.</p>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #e2e8f0; background: rgba(255,255,255,0.8);">
                <button type="button" class="btn btn-success" onclick="downloadDirectoryZip(explorerState.path)" title="Download current directory and everything inside it as a ZIP archive">
                    <i class="bi bi-file-earmark-zip me-1"></i>Zip Current Directory
                </button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- SSH File Preview Modal -->
<div class="modal fade" id="sshFilePreviewModal" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header" style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); border-bottom: none;">
                <h5 class="modal-title text-white text-truncate me-2" style="max-width: 45%;">
                    <i class="bi bi-file-earmark-text me-2"></i><span id="previewFileName">File</span>
                </h5>
                <span class="badge bg-white text-dark ms-auto me-3 px-3 py-2" id="previewFileSize"></span>
                <button class="btn btn-sm btn-light me-2" onclick="refreshFilePreview()" title="Fetch the latest file content from the server">
                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                </button>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" style="max-height: 72vh;">
                <div class="preview-meta px-3 pt-3" id="previewMeta" style="display: none; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <span class="badge bg-light text-dark border px-3 py-2"><i class="bi bi-hdd me-1"></i>Size: <span id="previewSizeBadge">—</span></span>
                    <span class="badge bg-light text-dark border px-3 py-2"><i class="bi bi-calendar-plus me-1"></i>Created: <span id="previewCreatedBadge">—</span></span>
                    <span class="badge bg-light text-dark border px-3 py-2"><i class="bi bi-calendar-check me-1"></i>Modified: <span id="previewModifiedBadge">—</span></span>
                </div>
                <div id="previewLoading" class="text-center py-5" style="display: none;">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="mt-2 text-muted">Loading file content...</div>
                </div>
                <pre id="previewContent" class="p-3 mb-0" style="display: none; margin: 0; white-space: pre-wrap; word-break: break-word; max-height: 72vh; overflow: auto; font-family: 'JetBrains Mono', 'Fira Code', Consolas, monospace; font-size: 0.85rem; background: #0f172a; color: #e2e8f0; border-radius: 0;"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- SSH File Edit Modal -->
<div class="modal fade" id="sshFileEditModal" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header" style="background: linear-gradient(135deg, #b45309 0%, #d97706 100%); border-bottom: none;">
                <h5 class="modal-title text-white text-truncate me-2" style="max-width: 50%;">
                    <i class="bi bi-pencil-square me-2"></i>Edit: <span id="editFileName">File</span>
                </h5>
                <span class="badge bg-white text-dark ms-auto me-3 px-3 py-2" id="editFileSizeBadge"></span>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <textarea id="editContent" class="explorer-edit-textarea" spellcheck="false" aria-label="File content editor"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-warning" id="editSaveBtn" onclick="saveExplorerFile()" title="Write the edited content back to the server (saved as root via sudo)">
                    <i class="bi bi-save me-1"></i>Save
                </button>
            </div>
        </div>
    </div>
</div>

<!-- SSH Permission Change Modal (chmod) -->
<div class="modal fade" id="sshPermsModal" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header" style="background: linear-gradient(135deg, #0e7490 0%, #0891b2 100%); border-bottom: none;">
                <h5 class="modal-title text-white text-truncate me-2" style="max-width: 55%;">
                    <i class="bi bi-shield-lock me-2"></i>Change Permission: <span id="permsFileName">File</span>
                </h5>
                <span class="badge bg-white text-dark ms-auto me-3 px-3 py-2" id="permsCurrentBadge" title="Current permission (octal)">—</span>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="permsPath" value="">
                <p class="text-muted small mb-3">
                    Select a permission preset for <strong id="permsTargetLabel">this item</strong>, or type a custom octal value.
                    Applied on the server as root via <code>sudo</code> (never overwrites or deletes anything).
                </p>
                <div id="permsPresetList" class="perms-preset-list"><!-- presets injected by JS --></div>
                <div class="input-group mt-3">
                    <span class="input-group-text" title="Custom octal permission"><i class="bi bi-123"></i></span>
                    <input type="text" id="permsCustomInput" class="form-control" placeholder="Custom octal e.g. 644" maxlength="4" inputmode="numeric" autocomplete="off">
                    <span class="input-group-text text-muted small">octal</span>
                </div>
                <div class="alert alert-light border mt-3 mb-0 small">
                    <i class="bi bi-info-circle me-1"></i>
                    <strong>r=4, w=2, x=1</strong> — e.g. <code>755</code> = owner rwx (4+2+1), group &amp; others r-x (4+1).
                    Folders normally use <code>755</code>, regular files <code>644</code>.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-success" id="permsApplyBtn" onclick="applyExplorerPerms()" title="Apply the selected permission on the server">
                    <i class="bi bi-shield-check me-1"></i>Apply Permission
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    const csrfToken = '{{ csrf_token() }}';
</script>
<script src="https://cdn.jsdelivr.net/npm/noty@3.2.0-beta4/lib/noty.min.js"></script>
<script src="{{ asset('assets/js/ssh.js') }}"></script>
@endsection
