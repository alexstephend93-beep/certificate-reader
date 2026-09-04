// Load servers via AJAX
let loadedHosts = [];
let allHosts = [];
let sshTestSuccessful = false;

function loadServers() {
    console.log('Loading SSH servers...');

    const grid = document.getElementById('serversGrid');
    if (!grid) {
        console.error('serversGrid element not found');
        return;
    }

    fetch('/ssh/list')
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error('HTTP error! status: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            console.log('Received data:', data);

            if (!data.success) {
                throw new Error(data.message || 'Unknown error');
            }

            allHosts = data.hosts || [];
            loadedHosts = data.hosts || [];

            console.log('Loaded', loadedHosts.length, 'servers, total available:', data.totalServers);

            // Limit rendering to prevent performance issues
            const maxRender = 200;
            const hostsToRender = loadedHosts.slice(0, maxRender);

            renderServers(hostsToRender);
            updateStats(data.totalServers, data.validKeys);

            // Show message if there are more servers
            if (data.hasMore) {
                const moreMsg = document.createElement('div');
                moreMsg.className = 'col-12 mt-3';
                moreMsg.innerHTML = `
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Showing ${data.shownCount} of ${data.totalServers} servers.
                        Use search to find specific servers.
                    </div>
                `;
                grid.appendChild(moreMsg);
            }
        })
        .catch(error => {
            console.error('Error loading servers:', error);
            grid.innerHTML = `
                <div class="col-12">
                    <div class="alert alert-danger">
                        Error loading servers: ${error.message}. Please refresh the page.
                    </div>
                </div>
            `;
        });
}

function updateStats(totalServers, validKeys) {
    const totalEl = document.getElementById('totalServers');
    const validEl = document.getElementById('validKeys');
    if (totalEl) totalEl.textContent = totalServers;
    if (validEl) validEl.textContent = validKeys;
}

function countValidKeys(hosts) {
    return hosts.filter(h => h.identity_file && h.key_exists).length;
}

// Enhanced search function
function searchServers() {
    const searchInputEl = document.getElementById('searchInput');
    const rawSearchTerm = searchInputEl ? searchInputEl.value : '';
    // Automatically convert upper case to lower case and normalize whitespace
    const searchTerm = rawSearchTerm.toLowerCase().trim().replace(/\s+/g, ' ');

    if (!searchTerm) {
        loadedHosts = [...allHosts];
        renderServers(loadedHosts);
        updateStats(allHosts.length, countValidKeys(allHosts));
        const noResultsMsg = document.getElementById('noResultsMessage');
        if (noResultsMsg) noResultsMsg.style.display = 'none';
        return;
    }

    const searchWords = searchTerm.split(' ').filter(Boolean);

    loadedHosts = allHosts.filter(host => {
        // Fields that should be searchable, all normalized to lower case
        const rawFields = [
            host.host || '',
            host.hostname || '',
            host.user || '',
            ...(host.domains || []),
            host.identity_file || '',
            basename(host.identity_file || ''),
            host.description || '',
            host.pem_file || '',
            host.file_basename || ''
        ].map(field => field.toLowerCase()).filter(Boolean);

        // Build multiple normalized variations of the host's searchable fields.
        // 1. raw lowercase values (e.g. "altro_nex_phonepe")
        // 2. separator characters replaced with spaces (e.g. "altro nex phonepe")
        // 3. all separators removed / characters concatenated (e.g. "altrenexphonepe"),
        //    so a search for "altronex" still matches the host "Altro_Nex_Phonepe".
        const searchableTexts = [
            ...new Set(rawFields),
            rawFields.join(' '),
            rawFields.map(field => field.replace(/[_\-./\\]+/g, ' ')).join(' '),
            rawFields.map(field => field.replace(/[^a-z0-9]+/g, '')).join(' ')
        ].filter(Boolean);

        // Check if all of the search words are found in any of the searchable variations
        return searchWords.every(word =>
            searchableTexts.some(text => text.includes(word))
        );
    });

    renderServers(loadedHosts);
    updateStats(allHosts.length, countValidKeys(allHosts));

    const noResultsMsg = document.getElementById('noResultsMessage');
    if (noResultsMsg) {
        noResultsMsg.style.display = loadedHosts.length === 0 ? 'block' : 'none';
    }
}

function clearSearch() {
    document.getElementById('searchInput').value = '';
    searchServers();
}

// Add event listeners for search input
const searchInput = document.getElementById('searchInput');
if (searchInput) {
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') searchServers();
    });

    // Add input event listener for real-time search
    searchInput.addEventListener('input', function(e) {
        // Directly convert upper case to lower case while typing
        const el = e.target;
        if (el.value && el.value !== el.value.toLowerCase()) {
            const cursorPos = el.selectionStart;
            el.value = el.value.toLowerCase();
            // Keep the caret at the same logical position
            try {
                el.setSelectionRange(cursorPos, cursorPos);
            } catch (err) {
                // Ignore any selection range errors (e.g. some mobile browsers)
            }
            e.preventDefault();
        }
        searchServers();
    });

    // Ensure search input is focusable and accessible
    searchInput.setAttribute('tabindex', '0');
    searchInput.style.pointerEvents = 'auto';
    searchInput.style.cursor = 'text';
}

function cleanDomainForDisplay(domain) {
    if (!domain) return '';
    return domain.replace(/^https?:\/\//i, '').replace(/\/+$/, '');
}

function renderServers(hosts) {
    const grid = document.getElementById('serversGrid');
    if (!grid) return;
    
    if (!hosts || hosts.length === 0) {
        grid.innerHTML = `
            <div class="col-12">
                <div class="text-center py-5">
                    <i class="bi bi-server fs-1 text-muted"></i>
                    <h4 class="mt-3">No servers configured</h4>
                    <p class="text-muted">Add your first server using the button above</p>
                </div>
            </div>
        `;
        return;
    }
    
    const html = hosts.map((host, index) => {
        const sshCommand = host.ssh_command || `ssh ${host.host}`;
        const port = host.port || 22;
        const domainsHtml = host.domains && host.domains.length > 0 
            ? host.domains.map(d => {
                const cleanDomain = cleanDomainForDisplay(d);
                const safeDomain = escapeHtml(cleanDomain).replace(/'/g, "\\'");
                return `
                <div class="server-detail" style="flex-wrap: wrap;">
                    <i class="bi bi-globe2" title="Domain"></i>
                    <span class="detail-value">https://${escapeHtml(cleanDomain)}</span>
                    <i class="bi bi-copy icon-copy" title="Copy domain"
                       style="margin-left: 6px; cursor: pointer;"
                       onclick="event.stopPropagation(); copyToClipboard('https://${safeDomain}', 'https://${safeDomain} copied to clipboard')"></i>
                    <i class="bi bi-link-45deg" title="Open in browser"
                       style="margin-left: 4px; cursor: pointer;"
                       onclick="event.stopPropagation(); window.open('https://${safeDomain}', '_blank')"></i>
                    <i class="bi bi-code-square icon-vscode" title="Open project in VS Code"
                       style="margin-left: 4px; cursor: pointer;"
                       onclick="event.stopPropagation(); openSpecificDomainInVSCode(this)" 
                       data-domain="${cleanDomain}"
                       data-host="${host.host}"
                       data-hostname="${host.hostname}"
                       data-user="${host.user}"
                       data-identity="${escapeHtml(host.identity_file || '')}"
                       data-port="${port}"></i>
                </div>
            `;
            }).join('')
            : `<div class="server-detail"><i class="bi bi-globe2" title="Domains"></i><span class="detail-value text-muted">N/A</span></div>`;
        
        const vscodeDomainsHtml = '';
        
        const lastConnectedHtml = host.last_connected
            ? `<div class="last-connected"><i class="bi bi-clock-history"></i> Last connected: ${formatTimeAgo(host.last_connected)}</div>`
            : '';
        
        const portHtml = port !== 22 
            ? `
                <div class="server-detail">
                    <i class="bi bi-plug-fill" title="Port"></i>
                    <span class="detail-value">${port}</span>
                </div>
            `
            : '';
        
        return `
            <div class="col-12 col-md-6 col-lg-4 server-card-wrapper" 
                 data-searchable="${[host.host, host.hostname, host.user, ...(host.domains || [])].join(' ').toLowerCase()}" 
                 data-host="${host.host}">
                <div class="server-card" 
                     data-server-host="${host.host}" 
                     data-server-index="${index}" 
                     data-hostname="${host.hostname}" 
                     data-port="${port}" 
                     data-identity-file="${host.identity_file || ''}">
                    <div class="server-header">
                        <div class="server-name">
                            <i class="bi bi-hdd-stack-fill"></i>
                            <span class="server-host-name">${host.host}</span>
                        </div>
                        <div class="server-header-actions">
                            <i class="bi ${host.is_favorite ? 'bi-star-fill' : 'bi-star'} favorite-star" data-host="${host.host}" style="cursor: pointer; font-size: 1.1rem; color: ${host.is_favorite ? '#f59e0b' : '#cbd5e1'};" title="${host.is_favorite ? 'Remove from favorites' : 'Add to favorites'}"></i>
                            <button class="btn btn-sm btn-outline-secondary" onclick='editServer("${host.host}")' style="padding: 4px 8px;">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick='confirmDeleteServer("${host.host}", "${escapeHtml(host.identity_file || '')}", ${index})' style="padding: 4px 8px;" title="Delete Server">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="server-body">
                    <div class="server-detail">
                        <i class="bi bi-geo-alt-fill" title="HostName"></i>
                        <span class="detail-value server-hostname">${host.hostname || 'N/A'}</span>
                        <i class="bi bi-copy icon-copy"
                           title="Copy hostname"
                           style="margin-left: 6px; cursor: pointer;"
                           onclick="event.stopPropagation(); copyToClipboard('${escapeHtml(host.hostname || '')}', 'Hostname copied to clipboard')"></i>
                    </div>

                        
                        <div class="server-detail">
                            <i class="bi bi-person-fill" title="User"></i>
                            <span class="detail-value server-user">${host.user || 'N/A'}</span>
                        </div>
                        
                        <div class="server-detail">
                            <i class="bi bi-key-fill" title="IdentityFile"></i>
                            <span class="detail-value">
                                ${basename(host.identity_file || 'N/A')}
                            </span>
                        </div>
                        ${portHtml}
                        ${domainsHtml}
                        
                        <div class="server-actions">
                            <i class="bi bi-folder2-open icon-folder" title="Browse Projects" onclick='browseProjects("${host.host}", "${host.hostname}", "${host.user}", "${escapeHtml(host.identity_file || '')}", ${port})'></i>
                            <i class="bi bi-diagram-3 icon-explorer" title="Project Explorer (Browse Files & Folders)" onclick='openProjectExplorer("${host.host}", "${host.hostname}", "${host.user}", "${escapeHtml(host.identity_file || '')}", ${port}, "/var/www")'></i>
                            <i class="bi bi-file-earmark-text icon-config" title="View Apache Config" onclick='viewApacheConfig("${host.host}", "${host.hostname}", "${host.user}", "${escapeHtml(host.identity_file || '')}", ${port})'></i>
                            <i class="bi bi-heart-pulse icon-diagnose" title="Diagnose Connection" onclick="diagnoseServer('${host.host}', this)"></i>
                            ${vscodeDomainsHtml}
                             <i class="bi bi-clipboard2-check icon-copy" title="Copy SSH command" onclick='copySshCommand("${host.host}")'></i>
                             <i class="bi bi-heart-pulse icon-diagnose" title="Proxy Server Health Checkup" onclick='showProxyHealth("${host.host}", this)'></i>
                            <div class="test-wrapper">
                                <i class="bi bi-plug-fill icon-test" title="Test server connection" onclick='testSingleConnection(this, ${index}, "${host.hostname}", ${port})'></i>
                                <span class="testing-spinner"></span>
                            </div>
                        </div>
                        ${lastConnectedHtml}
                    </div>
                </div>
            </div>
        `;
    }).join('');
    
    grid.innerHTML = html;
}

// Helper functions
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function copyToClipboard(text, successMessage = 'Copied to clipboard') {
    if (!text) {
        showToast('Nothing to copy', 'warning');
        return;
    }

    navigator.clipboard.writeText(text)
        .then(() => showToast(successMessage, 'success'))
        .catch(err => {
            console.error('Clipboard copy failed:', err);
            showToast('Failed to copy. Please copy manually.', 'danger');
        });
}

function basename(path) {

    if (!path) return 'N/A';
    return path.split('/').pop();
}

function formatTimeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);
    
    if (diffMins < 1) return 'just now';
    if (diffMins < 60) return diffMins + ' min ago';
    if (diffHours < 24) return diffHours + ' hours ago';
    return diffDays + ' days ago';
}

// SSH Server Management Functions

function editServer(host) {
    openSshServerModal('edit', host);
}

function openAddSshServerModal() {
    openSshServerModal('add');
}

function openSshServerModal(mode, host = null) {
    resetSshForm();

    // Populate SSH key files dropdown and then proceed
    populateSshKeyFiles().then(() => {
        if (mode === 'edit' && host) {
            fetch(`/ssh/get-server/${host}`, {
                headers: { 'Accept': 'application/json' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('originalHost').value = data.server.host;
                    document.getElementById('sshHost').value = data.server.host;
                    document.getElementById('sshHostname').value = data.server.hostname;
                    document.getElementById('sshPort').value = data.server.port || 22;
                    document.getElementById('sshUser').value = data.server.user;
                    document.getElementById('sshIdentityFile').value = data.server.identity_file;
                    document.getElementById('sshDomains').value = data.server.domains ? data.server.domains.join(', ') : '';
                    document.getElementById('sshDescription').value = data.server.description || '';
                    // Convert any existing upper case values to lower case when populating the edit form
                    normalizeAllSshFormFields();
                    document.getElementById('sshModalLabel').innerHTML = '<i class="bi bi-pencil-square me-2"></i>Edit SSH Server';

                    const modal = new bootstrap.Modal(document.getElementById('sshModal'));
                    modal.show();

                } else {
                    showToast('Failed to load server data: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Failed to load server data', 'danger');
            });
        } else if (mode === 'add') {
            document.getElementById('originalHost').value = '';
            document.getElementById('sshModalLabel').innerHTML = '<i class="bi bi-plus-circle me-2"></i>Add SSH Server';

            const modal = new bootstrap.Modal(document.getElementById('sshModal'));
            modal.show();
        }
    });
}

function confirmDeleteServer(host, identityFile, index) {
    if (!confirm(`Are you sure you want to delete server "${host}"? This will remove it from your SSH config.`)) return;

    fetch(`/ssh/delete/${host}`, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Server deleted successfully', 'success');
            loadServers(); // Reload the server list
        } else {
            showToast('Failed to delete server: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to delete server', 'danger');
    });
}


// Fallback function if Select2 focus doesn't work
function initializeSelectWithFallback() {
    const select = document.getElementById('sshIdentityFile');
    if (!select) return;
    
    // Try Select2 first
    if (typeof $ !== 'undefined' && $.fn.select2) {
        try {
            if ($('#sshIdentityFile').data('select2')) {
                $('#sshIdentityFile').select2('destroy');
            }
            
            $('#sshIdentityFile').select2({
                theme: 'bootstrap-5',
                placeholder: 'Select SSH key file...',
                allowClear: true,
                width: '100%',
                dropdownParent: $('#sshModal'),
                dropdownAutoWidth: true,
                // Add these options for better focus handling
                language: {
                    searching: function() { return 'Searching...'; }
                }
            });
            
            // Manual trigger for focus
            $(select).on('select2:open', function() {
                setTimeout(() => {
                    const searchField = document.querySelector('.select2-search__field');
                    if (searchField) {
                        searchField.focus();
                    }
                }, 50);
            });
            
        } catch(e) {
            console.warn('Select2 initialization failed, using native select', e);
            useNativeSelect();
        }
    } else {
        useNativeSelect();
    }
}

function useNativeSelect() {
    const select = document.getElementById('sshIdentityFile');
    if (select) {
        select.style.display = 'block';
        select.setAttribute('size', '5');
        select.style.height = 'auto';
        select.style.padding = '8px';
        
        // Add search input for native select
        const container = select.parentElement;
        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.placeholder = 'Search SSH keys...';
        searchInput.className = 'form-control mb-2';
        searchInput.style.marginBottom = '8px';
        searchInput.addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase();
            Array.from(select.options).forEach(option => {
                const text = option.text.toLowerCase();
                option.style.display = text.includes(term) ? '' : 'none';
            });
        });
        
        if (!container.querySelector('.native-select-search')) {
            searchInput.classList.add('native-select-search');
            container.insertBefore(searchInput, select);
        }
    }
}

function openSpecificDomainInVSCode(element) {
    const domain = element.getAttribute('data-domain');
    const host = element.getAttribute('data-host');
    const hostname = element.getAttribute('data-hostname');
    const user = element.getAttribute('data-user');
    const identityFile = element.getAttribute('data-identity');
    const port = element.getAttribute('data-port');

    if (!domain || !host) {
        showToast('Domain or host data missing', 'danger');
        return;
    }

    // Show loading state
    const originalColor = element.style.color;
    element.style.color = '#10b981';
    element.style.transform = 'scale(1.2)';

    showToast('Opening project in VS Code...', 'info');

    // First, try to find DocumentRoot via Apache config
    fetch('/ssh/apache-config', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            host: host,
            hostname: hostname,
            username: user,
            identity_file: identityFile,
            port: port || 22
        })
    })
    .then(response => response.json())
    .then(data => {
        // Normalize DocumentRoot -> Laravel project root
        const toProjectRoot = (docRoot) => {
            if (!docRoot) return null;
            let p = String(docRoot).trim();
            // remove trailing slashes
            p = p.replace(/\/+$/g, '');
            // remove /public (with or without trailing slash)
            p = p.replace(/\/public$/i, '');
            return p || null;
        };

        // IMPORTANT: avoid opening generic /var/www unless we truly cannot resolve a better path.
        let projectPath = null; // fallback


        if (data.success && data.virtual_hosts && data.virtual_hosts.length > 0) {
            // Find VirtualHost that matches the clicked domain
            let matchedVHost = null;

            for (const vhost of data.virtual_hosts) {
                if (vhost.domains && vhost.domains.includes(domain)) {
                    matchedVHost = vhost;
                    break;
                }
            }

            // If no exact match, try to find by domain pattern
            if (!matchedVHost) {
                for (const vhost of data.virtual_hosts) {
                    for (const vhostDomain of vhost.domains) {
                        if (domain.includes(vhostDomain) || vhostDomain.includes(domain)) {
                            matchedVHost = vhost;
                            break;
                        }
                    }

                    if (matchedVHost) break;
                }
            }

            // Use the matched VirtualHost's DocumentRoot
            if (matchedVHost && matchedVHost.document_root) {
                let documentRoot = matchedVHost.document_root;
                projectPath = toProjectRoot(documentRoot);

                console.log(`Found project path for domain ${domain}: ${projectPath} (from DocumentRoot: ${documentRoot})`);
                showToast(`Opening project for ${domain}`, 'success');
            } else {
                // Fallback: try to parse global DocumentRoot
                const documentRootMatch = data.content.match(/DocumentRoot\s+([^\s\n]+)/i);
                if (documentRootMatch) {
                    const documentRoot = documentRootMatch[1];
                    projectPath = toProjectRoot(documentRoot);
                    console.log(`Using fallback project path for domain ${domain}: ${projectPath}`);
                    showToast(`Opening project for ${domain} (fallback path)`, 'warning');

                } else {
                    showToast(`Could not determine project path for ${domain}`, 'warning');
                }
            }
        } else if (data.success && data.content) {
            // Fallback for configs without VirtualHost parsing
            const documentRootMatch = data.content.match(/DocumentRoot\s+([^\s\n]+)/i);
            if (documentRootMatch) {
                const documentRoot = documentRootMatch[1];
                projectPath = documentRoot.replace(/\/public\/?$/i, '').replace(/\/public$/, '');
                console.log(`Using legacy parsing for domain ${domain}: ${projectPath}`);
            }
        }
        
        // Build VS Code Remote SSH URI
        // Important: VS Code Remote expects the *remote* folder path.
        // If we fail to resolve domain → DocumentRoot → project root, do NOT
        // fall back to a generic /var/www (would open wrong folder).
        if (!projectPath) {
            showToast(`Could not resolve project path for ${domain} from Apache config`, 'warning');
            // Fallback to copying terminal command (still uses resolved path if any)
            const command = `code --new-window --remote ssh-remote+${host} "${projectPath || ''}"`;
            navigator.clipboard.writeText(command).then(() => {
                showToast('VS Code command copied to clipboard.', 'info');
            });
            return;
        }

        const vscodeUri = `vscode://vscode-remote/ssh-remote+${host}${projectPath}?windowId=_blank`;


        
        // Try to open in new window
        try {
            const newWindow = window.open(vscodeUri, '_blank');

            if (!newWindow || newWindow.closed || typeof newWindow.closed === 'undefined') {
                // Fallback: copy command to clipboard
                const command = `code --new-window --remote ssh-remote+${host} "${projectPath}"`;
                navigator.clipboard.writeText(command).then(() => {
                    showToast('VS Code command copied to clipboard. Paste in terminal.', 'info');
                });
            } else {
                showToast(`Opening ${domain} project in VS Code`, 'success');
            }
        } catch (e) {
            // Fallback: copy command to clipboard
            const command = `code --new-window --remote ssh-remote+${host} "${projectPath}"`;
            navigator.clipboard.writeText(command).then(() => {
                showToast('VS Code command copied. Paste in terminal.', 'info');
            });
        }

        // Reset loading state
        setTimeout(() => {
            element.style.color = originalColor;
            element.style.transform = '';
        }, 2000);
    });
}

function browseProjects(host, hostname, user, identityFile, port) {
    // Show loading
    showToast('Loading projects...', 'info');

    fetch('/ssh/list-projects', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            host: host,
            hostname: hostname,
            username: user,
            identity_file: identityFile,
            port: port || 22
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.projects) {
            // Filter out SSH warning messages
            const cleanProjects = data.projects.filter(project =>
                !project.startsWith('Warning:') &&
                project.trim() !== '' &&
                project !== '.' &&
                project !== '..'
            );

            showProjectsModal(host, hostname, user, identityFile, port, cleanProjects);
        } else {
            showToast('Failed to load projects: ' + (data.message || 'Unknown error'), 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to load projects', 'danger');
    });
}

function showProjectsModal(host, hostname, user, identityFile, port, projects) {
    // Update modal title and info
    document.getElementById('serverInfo').textContent = `Projects on ${hostname}`;
    document.getElementById('serverHost').textContent = host;

    const loadingDiv = document.getElementById('projectsLoading');
    const gridDiv = document.getElementById('projectsGrid');
    const noProjectsDiv = document.getElementById('noProjects');

    if (projects.length === 0) {
        loadingDiv.style.display = 'none';
        gridDiv.style.display = 'none';
        noProjectsDiv.style.display = 'block';
        return;
    }

    // Hide loading, show grid
    loadingDiv.style.display = 'none';
    noProjectsDiv.style.display = 'none';
    gridDiv.style.display = 'block';

    // Clear existing projects
    gridDiv.innerHTML = '';

    // Create project cards
    projects.forEach(project => {
        const projectCard = document.createElement('div');
        projectCard.className = 'col-lg-4 col-md-6 col-sm-12';
        projectCard.innerHTML = `
            <div class="project-card" style="
                background: linear-gradient(135deg, rgba(255, 255, 255, 0.9), rgba(248, 250, 252, 0.9));
                border: 1px solid rgba(148, 163, 184, 0.2);
                border-radius: 16px;
                padding: 20px;
                cursor: pointer;
                transition: all 0.3s ease;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
                position: relative;
                overflow: hidden;
            "
            onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 10px 25px -3px rgba(0, 0, 0, 0.15)';"
            onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px -1px rgba(0, 0, 0, 0.1)';"
            onclick="openProjectInVSCode('${host}', '${hostname}', '${user}', '${identityFile.replace(/'/g, "\\'")}', ${port}, '${project}')">

                <div style="position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #667eea, #764ba2);"></div>

                <div class="text-center">
                    <div style="font-size: 2.5rem; color: #667eea; margin-bottom: 12px;">
                        <i class="bi bi-folder-fill"></i>
                    </div>
                    <h6 class="mb-2 fw-bold" style="color: #334155; font-size: 1rem;">${project}</h6>
                    <small class="text-muted" style="font-size: 0.8rem;">
                        <i class="bi bi-folder2-open me-1"></i>/var/www/${project}
                    </small>
                    <div class="mt-3">
                        <span class="badge bg-primary px-2 py-1" style="font-size: 0.75rem;">
                            <i class="bi bi-code-square me-1"></i>Open in VS Code
                        </span>
                    </div>
                </div>
            </div>
        `;
        gridDiv.appendChild(projectCard);
    });

    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('sshProjectsModal'));
    modal.show();
}

function openProjectInVSCode(host, hostname, user, identityFile, port, projectName) {
    const projectPath = `/var/www/${projectName}`;

    // Show loading feedback
    showToast(`Opening ${projectName} in VS Code...`, 'info');

    // Build VS Code Remote SSH URI
    const vscodeUri = `vscode://vscode-remote/ssh-remote+${host}${projectPath}?windowId=_blank`;

    try {
        const newWindow = window.open(vscodeUri, '_blank');

        if (!newWindow || newWindow.closed || typeof newWindow.closed === 'undefined') {
            // Fallback: copy command to clipboard
            const command = `code --new-window --remote ssh-remote+${host} "${projectPath}"`;
            navigator.clipboard.writeText(command).then(() => {
                showToast('VS Code command copied to clipboard. Paste in terminal.', 'info');
            });
        } else {
            showToast(`Opening ${projectName} in VS Code`, 'success');
        }
    } catch (e) {
        // Fallback: copy command to clipboard
        const command = `code --new-window --remote ssh-remote+${host} "${projectPath}"`;
        navigator.clipboard.writeText(command).then(() => {
            showToast('VS Code command copied. Paste in terminal.', 'info');
        });
    }
}

function openServerRootInVSCode() {
    const modal = document.getElementById('sshProjectsModal');
    const serverHost = modal.querySelector('#serverHost').textContent;

    if (serverHost && serverHost !== 'server') {
        openProjectInVSCode(serverHost, '', '', '', 22, '');
    } else {
        showToast('Server information not available', 'warning');
    }
}

// =========== PROJECT EXPLORER (File Browser) ===========
let explorerState = {
    host: '', hostname: '', user: '', identityFile: '', port: 22,
    path: '/var/www', entries: [], previewFile: null, editFile: null
};
// Incremented on every open/navigate; stale async responses are ignored via this token
let explorerRequestId = 0;

function openProjectExplorer(host, hostname, user, identityFile, port, startPath) {
    // Reset ALL state for the newly selected server FIRST
    explorerState = {
        host: host, hostname: hostname, user: user, identityFile: identityFile,
        port: port || 22, path: startPath || '/var/www', entries: [], previewFile: null, editFile: null
    };
    explorerRequestId++; // invalidate any in-flight listing belonging to the previous server

    // Close the file-preview modal if it was left open from the previous server
    const previewModalEl = document.getElementById('sshFilePreviewModal');
    if (previewModalEl && previewModalEl.classList.contains('show')) {
        const inst = bootstrap.Modal.getInstance(previewModalEl);
        if (inst) inst.hide();
    }

    // Close an open editor modal too — an unsaved buffer from the previous
    // server must never be savable against the newly selected server's params
    const editModalEl = document.getElementById('sshFileEditModal');
    if (editModalEl && editModalEl.classList.contains('show')) {
        const inst = bootstrap.Modal.getInstance(editModalEl);
        if (inst) inst.hide();
    }

    // Clear every trace of the previously opened server's UI, then show loading
    document.getElementById('explorerServerBadge').innerHTML = '<i class="bi bi-server me-1"></i> ' + host;
    document.getElementById('explorerBreadcrumb').innerHTML = '';
    document.getElementById('explorerEntries').innerHTML = '';
    document.getElementById('explorerList').style.display = 'none';
    document.getElementById('explorerEmpty').style.display = 'none';
    const loadingEl = document.getElementById('explorerLoading');
    loadingEl.innerHTML = '<div class="spinner-border text-primary" role="status"></div><p class="mt-3 mb-0 text-muted">Loading directory...</p>';
    loadingEl.style.display = 'block';

    const modal = new bootstrap.Modal(document.getElementById('sshExplorerModal'));
    modal.show();
    loadExplorerDirectory();
}

function explorerApiParams(path) {
    const s = explorerState;
    return {
        host: s.host, hostname: s.hostname, username: s.user,
        identity_file: s.identityFile, port: s.port, path: path || s.path
    };
}

function joinExplorerPath(parent, name) {
    return (parent || '/').replace(/\/+$/, '') + '/' + name;
}

function formatBytes(bytes) {
    if (!bytes && bytes !== 0) return '';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let i = 0;
    let value = bytes;
    while (value >= 1024 && i < units.length - 1) { value /= 1024; i++; }
    return (i === 0 ? value : value.toFixed(2)) + ' ' + units[i];
}

/**
 * Format a raw Unix epoch (seconds) in the VIEWER'S local timezone —
 * same behaviour as standard file managers. Remote servers may run their
 * OS clock in UTC or IST; the epoch is an absolute instant, so rendering
 * it locally always shows the time the user expects (IST for us).
 * Returns null when the timestamp is unknown.
 */
function formatExplorerTs(ts) {
    if (ts === null || ts === undefined || ts <= 0) return null;
    const d = new Date(ts * 1000);
    if (isNaN(d.getTime())) return null;
    const pad = n => String(n).padStart(2, '0');
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate())
        + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
}

async function loadExplorerDirectory() {
    const requestId = ++explorerRequestId;
    const loading = document.getElementById('explorerLoading');
    const listEl = document.getElementById('explorerList');
    const emptyEl = document.getElementById('explorerEmpty');
    loading.style.display = 'block';
    listEl.style.display = 'none';
    emptyEl.style.display = 'none';
    try {
        const ctrl = (typeof AbortController !== 'undefined') ? new AbortController() : null;
        const timer = ctrl ? setTimeout(() => ctrl.abort(), 60000) : null;
        const res = await fetch('/ssh/explorer/list', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify(explorerApiParams()),
            signal: ctrl ? ctrl.signal : undefined
        });
        const data = await res.json();
        if (timer) clearTimeout(timer);
        // Ignore stale responses (e.g. user switched to another server mid-load)
        if (requestId !== explorerRequestId) return;
        if (!data.success) {
            loading.style.display = 'none';
            notyShow(data.message || 'Failed to load directory listing', 'error');
            return;
        }
        explorerState.path = data.path || explorerState.path;
        explorerState.entries = data.entries || [];
        updateExplorerBreadcrumb();
        renderExplorerList();
        loading.style.display = 'none';
    } catch (e) {
        console.error('Explorer error:', e);
        if (requestId !== explorerRequestId) return;
        loading.style.display = 'none';
        notyShow('Failed to load directory listing', 'error');
    }
}

function renderExplorerList() {
    const listEl = document.getElementById('explorerList');
    const emptyEl = document.getElementById('explorerEmpty');
    const entriesEl = document.getElementById('explorerEntries');

    if (!explorerState.entries.length) {
        listEl.style.display = 'none';
        emptyEl.style.display = 'block';
        return;
    }
    listEl.style.display = 'block';
    emptyEl.style.display = 'none';
    entriesEl.innerHTML = '';

    explorerState.entries.forEach(entry => {
        const fullPath = joinExplorerPath(explorerState.path, entry.name);
        const row = document.createElement('div');
        row.className = 'explorer-row' + (entry.is_dir ? ' explorer-dir' : '');
        row.innerHTML = `
            <div class="explorer-name" ${entry.is_dir ? `onclick="enterExplorerDirectory('${fullPath}')"` : ''}>
                <i class="bi ${entry.is_dir ? 'bi-folder-fill text-warning' : 'bi-file-earmark text-secondary'}"></i>
                <span>${escapeHtml(entry.name)}</span>
            </div>
            <div class="explorer-size" title="${entry.is_dir ? 'Directory size (total of all contents, computed with du)' : 'File size'}">${entry.is_dir ? (entry.dir_size != null ? formatBytes(entry.dir_size) : '—') : formatBytes(entry.size)}</div>
            <div class="explorer-created" title="Created on (in your local timezone)">${escapeHtml(formatExplorerTs(entry.created_ts) || entry.created_at || 'N/A')}</div>
            <div class="explorer-modified" title="Last modified (in your local timezone)">${escapeHtml(formatExplorerTs(entry.modified_ts) || entry.modified_at || 'N/A')}</div>
            <div class="explorer-perm" title="Permission${entry.owner ? ' — owner ' + escapeHtml(entry.owner + (entry.group ? ':' + entry.group : '')) : ''} — click the shield icon to change"><span class="perm-badge">${escapeHtml(entry.perms || '—')}</span></div>
            <div class="explorer-actions">
                ${entry.is_dir
                    ? `<i class="bi bi-arrow-clockwise icon-explorer-refresh" title="Refresh this directory" onclick="explorerGoTo('${fullPath}')"></i>
                       <i class="bi bi-file-earmark-zip icon-explorer-zip" title="Zip this directory (all contents)" onclick="downloadDirectoryZip('${fullPath}')"></i>
                       <i class="bi bi-input-cursor-text icon-explorer-rename" title="Rename this folder" onclick="renameExplorerEntry('${fullPath}')"></i>
                       <i class="bi bi-shield-lock icon-explorer-perm" title="Change permission of this folder" onclick="openExplorerPermsModal('${fullPath}', '${escapeHtml(entry.perms || '')}', true)"></i>
                       <i class="bi bi-trash icon-explorer-del" title="Delete this folder and everything inside it" onclick="deleteExplorerEntry('${fullPath}', true)"></i>`
                    : `<i class="bi bi-eye icon-explorer-eye" title="Preview file content" onclick="openFilePreview('${fullPath}', '${escapeHtml(entry.name)}', ${entry.size})"></i>
                       <i class="bi bi-pencil-square icon-explorer-edit" title="Edit file content" onclick="editExplorerFile('${fullPath}', ${entry.size})"></i>
                       <i class="bi bi-download icon-explorer-dl" title="Download file" onclick="downloadFile('${fullPath}')"></i>
                       <i class="bi bi-copy icon-explorer-dup" title="Duplicate this file (safe copy, never overwrites)" onclick="duplicateExplorerEntry('${fullPath}')"></i>
                       <i class="bi bi-input-cursor-text icon-explorer-rename" title="Rename this file" onclick="renameExplorerEntry('${fullPath}')"></i>
                       <i class="bi bi-shield-lock icon-explorer-perm" title="Change permission of this file" onclick="openExplorerPermsModal('${fullPath}', '${escapeHtml(entry.perms || '')}', false)"></i>
                       <i class="bi bi-trash icon-explorer-del" title="Delete this file" onclick="deleteExplorerEntry('${fullPath}', false)"></i>`}
            </div>
        `;
        entriesEl.appendChild(row);
    });
}

function updateExplorerBreadcrumb() {
    const el = document.getElementById('explorerBreadcrumb');
    if (!el) return;
    const parts = explorerState.path.split('/').filter(Boolean);
    let acc = '';
    let html = `<span class="crumb crumb-root" onclick="explorerGoTo('/')"><i class="bi bi-house-door"></i> /</span>`;
    parts.forEach(p => {
        acc += '/' + p;
        html += `<span class="crumb-sep">/</span><span class="crumb" onclick="explorerGoTo('${acc}')">${escapeHtml(p)}</span>`;
    });
    el.innerHTML = html;
}

function explorerGoTo(path) {
    explorerState.path = path || '/';
    loadExplorerDirectory();
}

function enterExplorerDirectory(path) {
    explorerState.path = path;
    loadExplorerDirectory();
}

function explorerGoBack() {
    const parts = explorerState.path.split('/').filter(Boolean);
    parts.pop();
    explorerState.path = '/' + parts.join('/');
    loadExplorerDirectory();
}

function refreshExplorerDirectory() {
    loadExplorerDirectory();
}

function downloadFile(filePath) {
    const q = new URLSearchParams(explorerApiParams(filePath)).toString();
    notyShow('Downloading file...', 'info', 2500);
    window.location.href = '/ssh/explorer/download?' + q;
}

function downloadDirectoryZip(dirPath) {
    const q = new URLSearchParams(explorerApiParams(dirPath)).toString();
    notyShow('Preparing ZIP archive...', 'info', 4000);
    window.location.href = '/ssh/explorer/zip?' + q;
}

// ---- File manager operations (edit / save / rename / create / delete) ----
// Must mirror MAX_EDIT_BYTES (2 MB) in SshController.php — files above this
// limit are refused for editing with an error Noty (server double-checks too)
const EDIT_SIZE_LIMIT = 2 * 1024 * 1024;

async function explorerJsonPost(url, body, timeoutMs) {
    // AbortController guard: if the SSH round-trip ever stalls, the request
    // is aborted after the timeout so buttons can never stay stuck on a
    // spinner ("Saving...") forever.
    const ctrl = (typeof AbortController !== 'undefined') ? new AbortController() : null;
    const timer = ctrl ? setTimeout(() => ctrl.abort(), timeoutMs || 90000) : null;
    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify(Object.assign(explorerApiParams(null), body || {})),
            signal: ctrl ? ctrl.signal : undefined
        });
        return await res.json();
    } finally {
        if (timer) clearTimeout(timer);
    }
}

function explorerBasename(p) {
    const parts = String(p || '').split('/').filter(Boolean);
    return parts.length ? parts[parts.length - 1] : String(p || '');
}

async function editExplorerFile(filePath, fileSize) {
    const fileName = explorerBasename(filePath);
    if (fileSize != null && fileSize > EDIT_SIZE_LIMIT) {
        notyShow('Editing "' + fileName + '" (' + formatBytes(fileSize) + ') is not supported. Files above '
            + formatBytes(EDIT_SIZE_LIMIT) + ' can freeze the browser.', 'error', 6000);
        return;
    }
    notyShow('Opening editor for "' + fileName + '"...', 'info', 2000);
    try {
        const data = await explorerJsonPost('/ssh/explorer/edit', { path: filePath });
        if (!data.success) {
            notyShow(data.message || 'Unable to open this file for editing', 'error', 7000);
            return;
        }
        explorerState.editFile = { path: data.path, modifiedTs: data.modified_ts != null ? data.modified_ts : null };
        document.getElementById('editFileName').textContent = data.name;
        document.getElementById('editFileSizeBadge').textContent = formatBytes(data.size);
        document.getElementById('editContent').value = data.content;
        new bootstrap.Modal(document.getElementById('sshFileEditModal')).show();
    } catch (e) {
        console.error('Edit open error:', e);
        notyShow('Failed to open the file for editing', 'error');
    }
}

async function saveExplorerFile() {
    const f = explorerState.editFile;
    if (!f) return;
    const btn = document.getElementById('editSaveBtn');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
    try {
        const data = await explorerJsonPost('/ssh/explorer/save', {
            path: f.path,
            content: document.getElementById('editContent').value,
            expected_mtime: f.modifiedTs
        }, 120000);
        if (!data.success) {
            notyShow(data.message || 'Save failed — nothing was written', 'error', 8000);
            return;
        }
        explorerState.editFile = null;
        const m = bootstrap.Modal.getInstance(document.getElementById('sshFileEditModal'));
        if (m) m.hide();
        notyShow(data.message || 'File saved successfully', 'success');
        loadExplorerDirectory(); // refresh listing so the Modified column updates
    } catch (e) {
        console.error('Save error:', e);
        notyShow(e && e.name === 'AbortError'
            ? 'Save timed out — the server did not respond in time. Nothing is lost: the editor stays open, try saving again.'
            : 'Save failed due to a network error', 'error', 8000);
    } finally {
        btn.disabled = false;
        btn.innerHTML = original;
    }
}

function renameExplorerEntry(filePath) {
    const currentName = explorerBasename(filePath);
    const newName = prompt('Rename "' + currentName + '" to:', currentName);
    if (newName === null) return;
    const trimmed = newName.trim();
    if (!trimmed || trimmed === currentName) return;
    if (trimmed.includes('/')) {
        notyShow('The name cannot contain "/"', 'warning');
        return;
    }
    explorerJsonPost('/ssh/explorer/rename', { path: filePath, new_name: trimmed })
        .then(data => {
            if (data.success) {
                notyShow(data.message || 'Renamed successfully', 'success');
                loadExplorerDirectory();
            } else {
                notyShow(data.message || 'Rename failed', 'error', 7000);
            }
        })
        .catch(() => notyShow('Rename failed due to a network error', 'error'));
}

function createExplorerDirectory() {
    const name = prompt('New folder name:', 'new-folder');
    if (name === null) return;
    const trimmed = name.trim();
    if (!trimmed) return;
    if (trimmed.includes('/')) {
        notyShow('The folder name cannot contain "/"', 'warning');
        return;
    }
    explorerJsonPost('/ssh/explorer/mkdir', { path: explorerState.path, name: trimmed })
        .then(data => {
            if (data.success) {
                notyShow(data.message || 'Folder created', 'success');
                loadExplorerDirectory();
            } else {
                notyShow(data.message || 'Could not create the folder', 'error', 7000);
            }
        })
        .catch(() => notyShow('Could not create the folder (network error)', 'error'));
}

function createExplorerFile() {
    const name = prompt('New file name:', 'new-file.txt');
    if (name === null) return;
    const trimmed = name.trim();
    if (!trimmed) return;
    if (trimmed.includes('/')) {
        notyShow('The file name cannot contain "/"', 'warning');
        return;
    }
    explorerJsonPost('/ssh/explorer/touch', { path: explorerState.path, name: trimmed })
        .then(data => {
            if (data.success) {
                notyShow(data.message || 'File created', 'success');
                loadExplorerDirectory();
                // Jump straight into the editor for the brand-new file
                if (data.path) editExplorerFile(data.path, 0);
            } else {
                notyShow(data.message || 'Could not create the file', 'error', 7000);
            }
        })
        .catch(() => notyShow('Could not create the file (network error)', 'error'));
}

function deleteExplorerEntry(filePath, isDir) {
    const name = explorerBasename(filePath);
    const msg = isDir
        ? 'Delete folder "' + name + '" and EVERYTHING inside it? This cannot be undone.'
        : 'Delete file "' + name + '"? This cannot be undone.';
    if (!confirm(msg)) return;
    explorerJsonPost('/ssh/explorer/delete', { path: filePath, type: isDir ? 'D' : 'F' })
        .then(data => {
            if (data.success) {
                notyShow(data.message || 'Deleted successfully', 'success');
                loadExplorerDirectory();
            } else {
                notyShow(data.message || 'Delete failed', 'error', 7000);
            }
        })
        .catch(() => notyShow('Delete failed due to a network error', 'error'));
}

// ---- Change permission (chmod) modal ----
// Common octal presets; the current permission is pre-selected when it matches.
const EXPLORER_PERM_PRESETS = [
    { v: '777', d: 'rwx rwx rwx — everyone can read/write/execute (dangerous)' },
    { v: '775', d: 'rwx rwx r-x — owner & group full, others read/enter' },
    { v: '755', d: 'rwx r-x r-x — standard for folders & executable files' },
    { v: '750', d: 'rwx r-x --- — owner & group only' },
    { v: '700', d: 'rwx --- --- — owner only' },
    { v: '664', d: 'rw- rw- r-- — owner & group write, others read' },
    { v: '644', d: 'rw- r-- r-- — standard for regular files' },
    { v: '640', d: 'rw- r-- --- — owner write, group read' },
    { v: '600', d: 'rw- --- --- — owner read/write only (private)' },
    { v: '555', d: 'r-x r-x r-x — read/enter only (read-only)' },
    { v: '444', d: 'r-- r-- r-- — everyone read-only' },
    { v: '400', d: 'r-- --- --- — owner read-only' }
];

function openExplorerPermsModal(path, currentPerms, isDir) {
    document.getElementById('permsPath').value = path;
    document.getElementById('permsFileName').textContent = explorerBasename(path);
    document.getElementById('permsCurrentBadge').textContent = currentPerms || '—';
    document.getElementById('permsTargetLabel').textContent = isDir ? 'this folder' : 'this file';
    const listEl = document.getElementById('permsPresetList');
    listEl.innerHTML = EXPLORER_PERM_PRESETS.map(p =>
        '<label class="perms-preset-item">' +
            '<input type="radio" name="permsPreset" value="' + p.v + '"' + (p.v === currentPerms ? ' checked' : '') + '>' +
            '<span class="perms-octal">' + p.v + '</span>' +
            '<span class="perms-desc">' + p.d + (p.v === currentPerms ? ' <strong>(current)</strong>' : '') + '</span>' +
        '</label>'
    ).join('');
    document.getElementById('permsCustomInput').value = '';
    new bootstrap.Modal(document.getElementById('sshPermsModal')).show();
}

async function applyExplorerPerms() {
    const path = document.getElementById('permsPath').value;
    const custom = document.getElementById('permsCustomInput').value.trim();
    const checked = document.querySelector('input[name="permsPreset"]:checked');
    const perms = custom || (checked ? checked.value : '');
    if (!/^[0-7]{3,4}$/.test(perms)) {
        notyShow('Enter a valid octal permission (e.g. 644 or 0755)', 'warning');
        return;
    }
    const btn = document.getElementById('permsApplyBtn');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Applying...';
    try {
        const data = await explorerJsonPost('/ssh/explorer/chmod', { path: path, perms: perms }, 60000);
        if (!data.success) {
            notyShow(data.message || 'Could not change the permission', 'error', 7000);
            return;
        }
        const m = bootstrap.Modal.getInstance(document.getElementById('sshPermsModal'));
        if (m) m.hide();
        notyShow(data.message || 'Permission updated to ' + perms, 'success');
        loadExplorerDirectory();
    } catch (e) {
        console.error('chmod error:', e);
        notyShow(e && e.name === 'AbortError'
            ? 'Permission change timed out — try again.'
            : 'Permission change failed due to a network error', 'error', 7000);
    } finally {
        btn.disabled = false;
        btn.innerHTML = original;
    }
}

// ---- Duplicate (safe copy) a file ----
function duplicateExplorerEntry(filePath) {
    const currentName = explorerBasename(filePath);
    const dot = currentName.lastIndexOf('.');
    const suggested = dot > 0 ? currentName.slice(0, dot) + '-copy' + currentName.slice(dot) : currentName + '-copy';
    const newName = prompt('Duplicate "' + currentName + '" as:', suggested);
    if (newName === null) return;
    const trimmed = newName.trim();
    if (!trimmed || trimmed === currentName) return;
    if (trimmed.includes('/')) {
        notyShow('The name cannot contain "/"', 'warning');
        return;
    }
    explorerJsonPost('/ssh/explorer/duplicate', { path: filePath, new_name: trimmed }, 120000)
        .then(data => {
            if (data.success) {
                notyShow(data.message || 'Duplicated successfully', 'success');
                loadExplorerDirectory();
            } else {
                notyShow(data.message || 'Duplicate failed', 'error', 7000);
            }
        })
        .catch(e => notyShow(e && e.name === 'AbortError'
            ? 'Duplicate timed out — try again.'
            : 'Duplicate failed due to a network error', 'error'));
}

// ---- Upload a file into the current directory ----
const EXPLORER_UPLOAD_LIMIT = 10 * 1024 * 1024; // 10 MB — keep in sync with exploreUpload() validation

async function uploadExplorerFile(input) {
    const file = input.files && input.files[0];
    input.value = ''; // allow re-selecting the same file later
    if (!file) return;
    if (file.size > EXPLORER_UPLOAD_LIMIT) {
        notyShow('"' + file.name + '" (' + formatBytes(file.size) + ') exceeds the '
            + formatBytes(EXPLORER_UPLOAD_LIMIT) + ' upload limit.', 'error', 7000);
        return;
    }
    notyShow('Uploading "' + file.name + '"...', 'info', 3000);
    const fd = new FormData();
    const params = explorerApiParams(null);
    Object.keys(params).forEach(k => fd.append(k, params[k]));
    fd.append('upload', file);
    try {
        const ctrl = (typeof AbortController !== 'undefined') ? new AbortController() : null;
        const timer = ctrl ? setTimeout(() => ctrl.abort(), 120000) : null;
        const res = await fetch('/ssh/explorer/upload', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: fd,
            signal: ctrl ? ctrl.signal : undefined
        });
        const data = await res.json();
        if (timer) clearTimeout(timer);
        if (!data.success) {
            notyShow(data.message || 'Upload failed', 'error', 7000);
            return;
        }
        notyShow(data.message || 'File uploaded successfully', 'success');
        loadExplorerDirectory();
    } catch (e) {
        console.error('Upload error:', e);
        notyShow(e && e.name === 'AbortError'
            ? 'Upload timed out — try again.'
            : 'Upload failed due to a network error', 'error', 7000);
    }
}

// Tab key inserts 4 spaces inside the editor instead of moving focus
(function bindExplorerEditTab() {
    const ta = document.getElementById('editContent');
    if (!ta || ta.dataset.tabBound) return;
    ta.dataset.tabBound = '1';
    ta.addEventListener('keydown', function (e) {
        if (e.key !== 'Tab') return;
        e.preventDefault();
        const start = this.selectionStart, end = this.selectionEnd;
        this.value = this.value.slice(0, start) + '    ' + this.value.slice(end);
        this.selectionStart = this.selectionEnd = start + 4;
    });
})();

// ---- File preview(with refresh + Chrome-safety guard) ----
const PREVIEW_RENDER_LIMIT =25 * 1024 * 1024;

async function openFilePreview(filePath, fileName, fileSize) {
    if (fileSize > PREVIEW_RENDER_LIMIT) {
        notyShow(
            'Previewing "' + (fileName || 'file') + '" (' + formatBytes(fileSize) + ') may make Chrome unresponsive. Use the download icon instead.',
            'error', 6000
        );
        return;
    }
    explorerState.previewFile = { path: filePath };
    document.getElementById('previewFileName').textContent = fileName || 'File';
    document.getElementById('previewFileSize').textContent = fileSize >= 0 ? formatBytes(fileSize) : '';
    document.getElementById('previewSizeBadge').textContent = fileSize >= 0 ? formatBytes(fileSize) : '—';
    document.getElementById('previewCreatedBadge').textContent = '…';
    document.getElementById('previewModifiedBadge').textContent = '…';
    document.getElementById('previewMeta').style.display = 'flex';
    document.getElementById('previewLoading').style.display = 'block';
    document.getElementById('previewContent').style.display = 'none';
    const modal = new bootstrap.Modal(document.getElementById('sshFilePreviewModal'));
    modal.show();
    await fetchPreviewContent(false);
}

async function fetchPreviewContent(isRefresh) {
    const p = explorerState.previewFile;
    if (!p) return;
    try {
        const q = new URLSearchParams(explorerApiParams(p.path)).toString();
        const res = await fetch('/ssh/explorer/file?' + q);
        const data = await res.json();
        if (!data.success) {
            document.getElementById('previewLoading').style.display = 'none';
            if (data.code === 'FILE_TOO_LARGE') {
                notyShow(data.message || 'This file is too large to preview safely — it may make Chrome unresponsive. Use the download icon instead.', 'error', 6000);
                const pv = bootstrap.Modal.getInstance(document.getElementById('sshFilePreviewModal'));
                if (pv) pv.hide();
            } else {
                notyShow(data.message || 'Failed to load file content', 'error');
            }
            return;
        }
        const contentEl = document.getElementById('previewContent');
        contentEl.textContent = data.content !== '' ? data.content : '(Empty file)';
        document.getElementById('previewFileName').textContent = data.name;
        document.getElementById('previewFileSize').textContent = formatBytes(data.size);
document.getElementById('previewSizeBadge').textContent = formatBytes(data.size);
        document.getElementById('previewCreatedBadge').textContent = formatExplorerTs(data.created_ts) || data.created_at || 'N/A';
        document.getElementById('previewModifiedBadge').textContent = formatExplorerTs(data.modified_ts) || data.modified_at || 'N/A';
        document.getElementById('previewMeta').style.display = 'flex';
        document.getElementById('previewLoading').style.display = 'none';
        contentEl.style.display = 'block';
        if (isRefresh) notyShow('File content refreshed with the latest version', 'success');
    } catch (e) {
        console.error('Preview error:', e);
        document.getElementById('previewLoading').style.display = 'none';
        notyShow('Failed to load file content', 'error');
    }
}

function refreshFilePreview() {
    notyShow('Refreshing file content...', 'info', 2000);
    fetchPreviewContent(true);
}

// NS Lookup Functions
function openNsLookupModal() {
    // Reset the form
    document.getElementById('nsLookupForm').reset();
    document.getElementById('nsLookupProgress').style.display = 'none';
    document.getElementById('nsLookupResult').style.display = 'none';

    const modal = new bootstrap.Modal(document.getElementById('nsLookupModal'));
    modal.show();
}

function performNsLookup() {
    const domainInput = document.getElementById('domainInput');
    const rawInput = domainInput.value.trim();

    if (!rawInput) {
        showToast('Please enter a domain name or URL', 'warning');
        domainInput.focus();
        return;
    }

    // Clean the input: remove protocol and trailing slashes
    let domain = rawInput
        .replace(/^https?:\/\//i, '')  // Remove http:// or https://
        .replace(/\/+$/, '');          // Remove trailing slashes

    // Basic domain validation
    if (!/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/.test(domain)) {
        showToast('Please enter a valid domain name or URL', 'warning');
        domainInput.focus();
        return;
    }

    // Show progress
    document.getElementById('nsLookupProgress').style.display = 'block';
    document.getElementById('nsLookupResult').style.display = 'none';

    fetch('/ssh/ns-lookup', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            domain: domain
        })
    })
    .then(response => response.json())
    .then(data => {
        // Hide progress
        document.getElementById('nsLookupProgress').style.display = 'none';

        if (data.success) {
            document.getElementById('nsLookupResult').style.display = 'block';
            document.getElementById('nsLookupOutput').textContent = data.result;
            showToast('NS lookup completed successfully', 'success');
        } else {
            showToast('NS lookup failed: ' + (data.message || 'Unknown error'), 'danger');
        }
    })
    .catch(error => {
        // Hide progress
        document.getElementById('nsLookupProgress').style.display = 'none';
        console.error('NS lookup error:', error);
        showToast('NS lookup failed due to network error', 'danger');
    });
}

function viewApacheConfig(host, hostname, user, identityFile, port) {
    showToast('Loading Apache config...', 'info');

    fetch('/ssh/apache-config', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            host: host,
            hostname: hostname,
            username: user,
            identity_file: identityFile,
            port: port || 22
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showApacheConfigModal(host, data.content, data.path);
        } else {
            showToast('Failed to load Apache config: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to load Apache config', 'danger');
    });
}

function showApacheConfigModal(host, config, configPath) {
    // Clean up the config content (remove JSON escaping and format properly)
    let cleanConfig = config
        .replace(/\\n/g, '\n')  // Convert \n to actual newlines
        .replace(/\\t/g, '\t')  // Convert \t to actual tabs
        .replace(/\\/g, '');    // Remove remaining backslashes

    let modalHtml = `
        <div class="modal fade" id="apacheModal" tabindex="-1">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Apache Config for ${host}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-2">
                            <small class="text-muted">Configuration path: <code>${configPath || 'Unknown'}</code></small>
                        </div>
                        <pre class="bg-light p-3 rounded" style="max-height: 500px; overflow-y: auto; font-family: 'Courier New', monospace; font-size: 0.9rem;"><code id="apacheConfigContent" data-original="${escapeHtml(config)}">${escapeHtml(cleanConfig)}</code></pre>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" onclick="copyApacheConfig()">
                            <i class="bi bi-clipboard"></i> Copy Config
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remove existing modal if present
    const existingModal = document.getElementById('apacheModal');
    if (existingModal) existingModal.remove();

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const apacheModalEl = document.getElementById('apacheModal');
    const apacheModal = new bootstrap.Modal(apacheModalEl);
    // Auto-dispose dynamic modal after it is closed to avoid lingering state/backdrops
    apacheModalEl.addEventListener('hidden.bs.modal', function onHidden() {
        apacheModalEl.removeEventListener('hidden.bs.modal', onHidden);
        apacheModal.dispose();
        apacheModalEl.remove();
    });
    apacheModal.show();
}

function diagnoseServer(host, element) {
    // Add loading spinner to the button
    const icon = element;  // element is already the <i> tag
    const originalClass = icon.className;
    icon.className = 'bi bi-hourglass-split';

        fetch(`/ssh/diagnose/${host}`, {
            headers: {
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
        // Restore original icon
        icon.className = originalClass;

        if (data.success) {
            showDiagnosticsModal(host, data.diagnostics, data.ssh_command);
        } else {
            showToast('Failed to diagnose server: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        // Restore original icon
        icon.className = originalClass;
        console.error('Error:', error);
        showToast('Failed to diagnose server', 'danger');
    });
}

function showDiagnosticsModal(host, diagnostics, sshCommand) {
    let modalHtml = `
        <div class="modal fade" id="diagnosticsModal" tabindex="-1">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Connection Diagnostics for ${host}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <strong>SSH Command:</strong>
                            <code class="d-block p-2 bg-light rounded">${sshCommand}</code>
                        </div>
                        <pre class="bg-light p-3 rounded" style="max-height: 400px; overflow-y: auto;"><code>${diagnostics.map(d => escapeHtml(d)).join('\n')}</code></pre>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remove existing modal if present
    const existingModal = document.getElementById('diagnosticsModal');
    if (existingModal) existingModal.remove();

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const diagnosticsModalEl = document.getElementById('diagnosticsModal');
    const diagnosticsModal = new bootstrap.Modal(diagnosticsModalEl);
    // Auto-dispose dynamic modal after it is closed to avoid lingering state/backdrops
    diagnosticsModalEl.addEventListener('hidden.bs.modal', function onHidden() {
        diagnosticsModalEl.removeEventListener('hidden.bs.modal', onHidden);
        diagnosticsModal.dispose();
        diagnosticsModalEl.remove();
    });
    diagnosticsModal.show();
}

function copySshCommand(host) {
    // Get the SSH command from the server
    fetch(`/ssh/command/${host}`, {
        headers: {
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            navigator.clipboard.writeText(data.command).then(() => {
                showToast('SSH command copied to clipboard', 'success');
            }).catch(err => {
                showToast('Failed to copy command', 'danger');
            });
        } else {
            showToast('Failed to get SSH command', 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to get SSH command', 'danger');
    });
}

function openTerminal(command) {
    // This would typically open a terminal, but in web context we can show the command
    showToast('Terminal command: ' + command, 'info');
    console.log('Terminal command:', command);
}

function showProxyHealth(host, element) {
    const icon = element || null;
    const originalClass = icon ? icon.className : 'bi bi-heart-pulse icon-diagnose';
    
    let blinkInterval = null;
    if (icon) {
        let showHourglass = true;
        blinkInterval = setInterval(() => {
            showHourglass = !showHourglass;
            icon.className = showHourglass ? 'bi bi-hourglass-split' : 'bi bi-hourglass';
        }, 400);
    }
    
    showToast(`Checking proxy health for ${host}...`, 'info');

    fetch('/ssh/proxy-health/' + encodeURIComponent(host), {
        headers: {
            'Accept': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return response.json();
    })
    .then(data => {
        if (blinkInterval) clearInterval(blinkInterval);
        if (icon) icon.className = originalClass;
        if (data.success) {
            try {
                showProxyHealthModal(host, data.health);
            } catch (modalErr) {
                console.error('Modal render error:', modalErr);
                showToast('Health data received but failed to render. Check console.', 'warning');
            }
        } else {
            showToast('Failed to get proxy health: ' + (data.message || 'Unknown error'), 'danger');
        }
    })
    .catch(error => {
        if (blinkInterval) clearInterval(blinkInterval);
        if (icon) icon.className = originalClass;
        console.error('Health fetch error:', error);
        showToast('Failed to get proxy health: ' + error.message, 'danger');
    });
}

function showProxyHealthModal(host, health) {
    const statusColor = {
        'healthy': '#10b981',
        'warning': '#f59e0b',
        'error': '#ef4444',
        'unknown': '#64748b'
    };
    const statusColorClass = health.overall_status || 'unknown';
    const color = statusColor[statusColorClass] || '#64748b';

    let detailsHtml = '';
    try {
        if (health.details && typeof health.details === 'object') {
            detailsHtml = buildHealthDetails(health);
        }
    } catch (err) {
        console.error('Health details render error:', err, health);
        detailsHtml = '<div class="alert alert-warning">Error rendering health details. Raw data logged to console.</div>';
    }

    let modalHtml = `
        <div class="modal fade" id="proxyHealthModal" tabindex="-1">
            <div class="modal-dialog modal-xl">
                <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
                    <div class="modal-header" style="background: linear-gradient(135deg, #059669 0%, #0d9488 100%); border-bottom: none;">
                        <h5 class="modal-title text-white">
                            <i class="bi bi-heart-pulse-fill me-2"></i>Proxy Server Health - ${escapeHtml(host)}
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" style="padding: 24px;">
                        <div class="text-center mb-4">
                            <h6 class="text-muted mb-2">Overall Status</h6>
                            <div class="badge px-4 py-2 fs-6" style="background: ${color}; color: white; border-radius: 10px;">
                                ${escapeHtml(statusColorClass.toUpperCase())}
                            </div>
                        </div>
                        <hr class="my-4">
                        ${detailsHtml || '<p class="text-muted text-center">No health details available.</p>'}
                    </div>
                    <div class="modal-footer" style="border-top: 1px solid #e2e8f0;">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" onclick="bootstrap.Modal.getInstance(document.getElementById('proxyHealthModal')).hide(); showProxyHealth('${escapeHtml(host)}', document.querySelector('[data-host-health=\"${escapeHtml(host)}\"]'))">
                            <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    const existingModal = document.getElementById('proxyHealthModal');
    if (existingModal) existingModal.remove();
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    try {
        const proxyHealthModalEl = document.getElementById('proxyHealthModal');
        const proxyHealthModal = new bootstrap.Modal(proxyHealthModalEl);
        // Auto-dispose dynamic modal after it is closed to avoid lingering state/backdrops
        proxyHealthModalEl.addEventListener('hidden.bs.modal', function onHidden() {
            proxyHealthModalEl.removeEventListener('hidden.bs.modal', onHidden);
            proxyHealthModal.dispose();
            proxyHealthModalEl.remove();
        });
        proxyHealthModal.show();
    } catch (e) {
        console.error('Bootstrap modal show error:', e);
        showToast('Failed to open health modal, but data was received.', 'warning');
    }
}

function buildHealthDetails(health) {
    const multilineKeys = new Set([
        'cpu_usage_top', 'memory_usage_top', 'cpu_usage_ps', 'disk_io',
        'outbound_connections', 'systemd_failed_services',
        'open_ports', 'established_connections', 'load_average', 'timezone', 'language', 'ufw'
    ]);

    function renderValue(key, value) {
        const str = String(value ?? 'N/A').trim();
        const isEmpty = !str || str === 'N/A';
        if (multilineKeys.has(key) && str.includes('\n')) {
            return `<pre class="bg-light p-2 rounded small mb-0" style="max-height:220px;overflow:auto;">${escapeHtml(str)}</pre>`;
        }
        if (key === 'ssl_cert_check' || key.startsWith('ssl_')) {
            const beforeMatch = str.match(/notBefore=(.*)/);
            const afterMatch = str.match(/notAfter=(.*)/);
            const start = beforeMatch ? beforeMatch[1].trim() : null;
            const end = afterMatch ? afterMatch[1].trim() : null;
            if (end) {
                const daysLeft = Math.max(0, Math.ceil((new Date(end).getTime() - Date.now()) / 86400000));
                return `<span class="small">${start ? escapeHtml(start) : ''} → <strong>${escapeHtml(end)}</strong> <span class="text-muted">(${daysLeft}d left)</span></span>`;
            }
        }
        if (isEmpty) return '<span class="text-muted">N/A</span>';
        return escapeHtml(str);
    }

    function healthBadge(key, val) {
        const str = String(val ?? '').trim().toLowerCase();
        const num = parseInt(String(val ?? '').trim(), 10);
        switch (key) {
            case 'uptime':
                return '<i class="bi bi-check-circle-fill text-success"></i>';
            case 'load_average': {
                const parts = str.split(/[ ,]+/);
                const load1 = parseFloat(parts[parts.length - 3] || '0');
                const load15 = parseFloat(parts[parts.length - 1] || '0');
                const cpuCount = parseInt(health.details.cpu_count || '1', 10);
                const avg = (load1 + load15) / 2;
                if (avg > cpuCount * 2) return '<i class="bi bi-exclamation-triangle-fill text-danger"></i>';
                if (avg > cpuCount) return '<i class="bi bi-exclamation-circle-fill text-warning"></i>';
                return '<i class="bi bi-check-circle-fill text-success"></i>';
            }
            case 'cpu_usage': {
                const m = str.match(/([0-9.]+)%\s*us/);
                const pct = m ? parseFloat(m[1]) : 0;
                if (pct > 85) return '<i class="bi bi-exclamation-triangle-fill text-danger"></i>';
                if (pct > 55) return '<i class="bi bi-exclamation-circle-fill text-warning"></i>';
                if (pct > 0) return '<i class="bi bi-check-circle-fill text-success"></i>';
                return '<i class="bi bi-info-circle-fill text-info"></i>';
            }
            case 'memory': {
                const parts = str.split(/\s+/);
                const idx = parts.indexOf('Mi') > -1 ? parts.indexOf('Mi') - 1 : parts.indexOf('Gi') - 1;
                if (idx < 0) return '<i class="bi bi-info-circle-fill text-info"></i>';
                const total = parseFloat(parts[idx] || '0');
                const used = parseFloat(parts[idx + 2] || '0');
                if (total > 0 && (used / total) > 0.92) return '<i class="bi bi-exclamation-triangle-fill text-danger"></i>';
                if (total > 0 && (used / total) > 0.75) return '<i class="bi bi-exclamation-circle-fill text-warning"></i>';
                return '<i class="bi bi-check-circle-fill text-success"></i>';
            }
            case 'disk_usage': {
                const m = str.match(/(\d+)%/);
                if (m) {
                    const pct = parseInt(m[1], 10);
                    if (pct >= 95) return '<i class="bi bi-exclamation-triangle-fill text-danger"></i>';
                    if (pct >= 80) return '<i class="bi bi-exclamation-circle-fill text-warning"></i>';
                    if (pct >= 50) return '<i class="bi bi-info-circle-fill text-info"></i>';
                    return '<i class="bi bi-check-circle-fill text-success"></i>';
                }
                return '<i class="bi bi-info-circle-fill text-info"></i>';
            }
            case 'inodes': {
                const m = str.match(/(\d+)%/);
                if (m) {
                    const pct = parseInt(m[1], 10);
                    if (pct >= 95) return '<i class="bi bi-exclamation-triangle-fill text-danger"></i>';
                    if (pct >= 80) return '<i class="bi bi-exclamation-circle-fill text-warning"></i>';
                    return '<i class="bi bi-check-circle-fill text-success"></i>';
                }
                return '<i class="bi bi-info-circle-fill text-info"></i>';
            }
            case 'cpu_info':
            case 'architecture':
            case 'language':
            case 'timezone':
            case 'cpu_count':
                return '<i class="bi bi-info-circle-fill text-info"></i>';
            case 'kernel':
            case 'os': {
                if (str === 'n/a') return '<i class="bi bi-question-circle-fill text-muted"></i>';
                return '<i class="bi bi-check-circle-fill text-success"></i>';
            }
            case 'hostname_resolved':
            case 'current_user':
            case 'home_directory':
                return str === 'n/a' ? '<i class="bi bi-question-circle-fill text-muted"></i>' : '<i class="bi bi-check-circle-fill text-success"></i>';
            case 'ssh_service':
                return str === 'active' ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-exclamation-triangle-fill text-danger"></i>';
            case 'fail2ban':
            case 'cron_status':
            case 'anacron_status':
                return str === 'active' ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-exclamation-circle-fill text-warning"></i>';
            case 'ufw':
                return /active/.test(str) ? '<i class="bi bi-check-circle-fill text-success"></i>' : (str === 'n/a' ? '<i class="bi bi-question-circle-fill text-muted"></i>' : '<i class="bi bi-exclamation-circle-fill text-warning"></i>');
            case 'swap': {
                const parts = str.split(/\s+/);
                const idx = parts.indexOf('Mi') > -1 ? parts.indexOf('Mi') - 1 : (parts.indexOf('Gi') > -1 ? parts.indexOf('Gi') - 1 : -1);
                if (idx < 1) return '<i class="bi bi-info-circle-fill text-info"></i>';
                const total = parseFloat(parts[idx] || '0');
                const used = parseFloat(parts[idx + 2] || '0');
                if (total > 0 && (used / total) > 0.8) return '<i class="bi bi-exclamation-triangle-fill text-danger"></i>';
                if (total > 0 && (used / total) > 0.5) return '<i class="bi bi-exclamation-circle-fill text-warning"></i>';
                return '<i class="bi bi-check-circle-fill text-success"></i>';
            }
            case 'pending_updates': {
                if (num === 0) return '<i class="bi bi-check-circle-fill text-success"></i>';
                if (num > 100) return '<i class="bi bi-exclamation-triangle-fill text-danger"></i>';
                if (num > 50) return '<i class="bi bi-exclamation-circle-fill text-warning"></i>';
                return '<i class="bi bi-info-circle-fill text-info"></i>';
            }
            case 'sshd_failed_logins': {
                if (isNaN(num)) return '<i class="bi bi-info-circle-fill text-info"></i>';
                if (num === 0) return '<i class="bi bi-check-circle-fill text-success"></i>';
                if (num > 20) return '<i class="bi bi-exclamation-triangle-fill text-danger"></i>';
                return '<i class="bi bi-exclamation-circle-fill text-warning"></i>';
            }
            case 'zombie_processes': {
                if (isNaN(num)) return '<i class="bi bi-info-circle-fill text-info"></i>';
                if (num === 0) return '<i class="bi bi-check-circle-fill text-success"></i>';
                if (num > 5) return '<i class="bi bi-exclamation-triangle-fill text-danger"></i>';
                return '<i class="bi bi-exclamation-circle-fill text-warning"></i>';
            }
            case 'key_exists':
                return val ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-exclamation-triangle-fill text-danger"></i>';
            case 'key_permissions': {
                const ps = String(val ?? '').trim();
                return (ps === '0400' || ps === '0600') ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-exclamation-triangle-fill text-danger"></i>';
            }
            case 'key_size_bytes': {
                if (isNaN(num)) return '<i class="bi bi-info-circle-fill text-info"></i>';
                return num > 0 ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-exclamation-triangle-fill text-danger"></i>';
            }
            default:
                return '<i class="bi bi-info-circle-fill text-info"></i>';
        }
    }

    const allSections = [
        { title: '🖥️ System', keys: ['uptime','load_average','memory','disk_usage','os','kernel','architecture','hostname_resolved','current_user','home_directory','timezone','language','last_reboot'] },
        { title: '📦 Packages', keys: ['pending_updates'] },
        { title: '🔒 Services', keys: ['ssh_service','fail2ban','ufw','cron_status','anacron_status','systemd_failed_services'] },
        { title: '💻 CPU', keys: ['cpu_count','cpu_info','cpu_usage','cpu_usage_top','cpu_usage_ps'] },
        { title: '🧠 Memory & Swap', keys: ['memory','swap','memory_usage_top'] },
        { title: '💾 Disk & I/O', keys: ['disk_usage','disk_io','inodes'] },
        { title: '🔄 Processes', keys: ['total_processes','zombie_processes'] },
        { title: '🌐 Network', keys: ['open_ports','established_connections','outbound_connections'] },
        { title: '📡 Time & Clock', keys: ['ntp_sync'] },
        { title: '🔐 Proxy Key', keys: ['key_exists','key_path','key_permissions','key_size_bytes'] },
        { title: '🚪 Limits', keys: ['open_fd','fd_limit'] },
        { title: '🔑 Security', keys: ['sshd_failed_logins'] },
        { title: '🔗 Connection', keys: ['connection'] },
    ];

    let detailsHtml = '';
    try {
        allSections.forEach(sec => {
            const rows = sec.keys.filter(k => health.details[k] !== undefined);
            if (!rows.length) return;
            detailsHtml += `<div class="mb-3"><h6 class="text-uppercase text-muted fw-bold mb-2" style="letter-spacing:0.5px;font-size:0.75rem;">${sec.title}</h6>`;
            rows.forEach(key => {
                const val = health.details[key];
                const label = key.replace(/\./g, ' ').replace(/([A-Z])/g, ' $1').replace(/\b\w/g, c => c.toUpperCase());
                const isBool = typeof val === 'boolean';
                let display = '';
                if (isBool) {
                    display = val
                        ? '<span class="text-success"><i class="bi bi-check-circle-fill"></i> OK</span>'
                        : '<span class="text-danger"><i class="bi bi-x-circle-fill"></i> Failed</span>';
                } else if (key === 'connection') {
                    display = val
                        ? '<span class="text-success"><i class="bi bi-check-circle-fill"></i> Connected</span>'
                        : '<span class="text-danger"><i class="bi bi-x-circle-fill"></i> Failed</span>';
                } else {
                    const numericKeys = ['pending_updates','total_processes','zombie_processes','sshd_failed_logins','cpu_count','open_ports','established_connections'];
                    if (numericKeys.includes(key)) {
                        const n = parseInt(String(val), 10);
                        if (!isNaN(n)) {
                            const bcls = key === 'sshd_failed_logins' && n > 0 ? 'bg-danger' : (n === 0 ? 'bg-success' : 'bg-info');
                            display = `<span class="badge ${bcls}">${n}</span>`;
                        } else {
                            display = renderValue(key, val);
                        }
                    } else {
                        try { display = renderValue(key, val); } catch (e) { display = escapeHtml(String(val)); }
                    }
                }
                let badge = '';
                try { badge = healthBadge(key, val); } catch (e) { badge = '<i class="bi bi-info-circle-fill text-muted"></i>'; }
                detailsHtml += `
                    <div class="row mb-2 align-items-center">
                        <div class="col-sm-3 fw-semibold small">${badge} ${escapeHtml(label)}</div>
                        <div class="col-sm-9">${display}</div>
                    </div>`;
            });
            detailsHtml += '</div>';
        });

        const sslEntries = Object.entries(health.details).filter(([k, v]) => (k.startsWith('ssl_') || k.startsWith('ssl_raw_')) && k !== 'ssl_cert_check' && typeof v === 'string' && (v.includes('notAfter=') || v.includes('Protocol')));
        if (sslEntries.length) {
            detailsHtml += `<div class="mb-3"><h6 class="text-uppercase text-muted fw-bold mb-2" style="letter-spacing:0.5px;font-size:0.75rem;">🔐 SSL Certificates</h6>`;
            sslEntries.forEach(([key, raw]) => {
                const label = key.replace(/^ssl_raw_/, '').replace(/^ssl_/, '').replace(/_/g, '.');
                if (key.startsWith('ssl_raw_')) {
                    const protocol = raw.match(/Protocol\s+:\s*(\S+)/i);
                    const cipher = raw.match(/Cipher\s+:\s*(\S+)/i);
                    detailsHtml += `
                        <div class="row mb-2 align-items-center">
                            <div class="col-sm-3 fw-semibold small"><i class="bi bi-shield-lock text-info"></i> ${escapeHtml(label)}</div>
                            <div class="col-sm-9">
                                <span class="badge bg-info me-1">${escapeHtml(protocol ? protocol[1] : 'N/A')}</span>
                                <span class="badge bg-success">${escapeHtml(cipher ? cipher[1].replace(/0x[0-9a-f]+/i, '').trim() : 'N/A')}</span>
                            </div>
                        </div>`;
                } else {
                    const beforeMatch = raw.match(/notBefore=(.*)/);
                    const afterMatch = raw.match(/notAfter=(.*)/);
                    const issuerMatch = raw.match(/Issuer: (.*)/);
                    const subjectMatch = raw.match(/Subject: (.*)/);
                    const sanMatch = raw.match(/X509v3 Subject Alternative Name: \n\s*(.*)/);
                    const notBefore = beforeMatch ? beforeMatch[1].trim() : null;
                    const notAfter = afterMatch ? afterMatch[1].trim() : null;
                    const daysLeft = notAfter ? Math.max(0, Math.ceil((new Date(notAfter).getTime() - Date.now()) / 86400000)) : null;
                    let badge = '';
                    if (daysLeft === null) badge = '<span class="badge bg-secondary">Unknown</span>';
                    else if (daysLeft === 0) badge = '<span class="badge bg-danger">Expired</span>';
                    else if (daysLeft < 7) badge = `<span class="badge bg-danger">${daysLeft}d left</span>`;
                    else if (daysLeft < 30) badge = `<span class="badge bg-warning text-dark">${daysLeft}d left</span>`;
                    else badge = `<span class="badge bg-success">${daysLeft}d left</span>`;
                    detailsHtml += `
                        <div class="row mb-2 align-items-center">
                            <div class="col-sm-3 fw-semibold small"><i class="bi bi-shield-check text-info"></i> ${escapeHtml(label)}</div>
                            <div class="col-sm-9">
                                <div class="small text-muted mb-1">${notBefore ? escapeHtml(notBefore) : ''} → <strong>${notAfter ? escapeHtml(notAfter) : 'N/A'}</strong> ${badge}</div>
                                ${subjectMatch ? `<div class="small"><strong>Subject:</strong> <span class="text-break">${escapeHtml(subjectMatch[1].trim())}</span></div>` : ''}
                                ${issuerMatch ? `<div class="small"><strong>Issuer:</strong> <span class="text-break">${escapeHtml(issuerMatch[1].trim())}</span></div>` : ''}
                                ${sanMatch ? `<div class="small"><strong>SAN:</strong> <span class="text-break">${escapeHtml(sanMatch[1].trim())}</span></div>` : ''}
                            </div>
                        </div>`;
                }
            });
            detailsHtml += '</div>';
        }
    } catch (err) {
        console.error('Health details inner render error:', err);
        detailsHtml += '<div class="alert alert-danger small">Partial render error. Check console.</div>';
    }

    return detailsHtml;
}

function testSingleConnection(element, index, hostname, port) {
    const icon = element;  // element is already the <i> tag
    const spinner = element.nextElementSibling;

    // Show loading state on the button
    icon.style.display = 'none';
    spinner.style.display = 'inline-block';

    // Find the host data to get username and identity_file
    const hostData = loadedHosts[index];
    if (!hostData) {
        showToast('Host data not found', 'danger');
        icon.style.display = 'inline-block';
        spinner.style.display = 'none';
        return;
    }

    // Find the server card for highlighting
    const serverCard = element.closest('.server-card');
    const testWrapper = element.closest('.test-wrapper');
    
    // Remove any existing connection status classes
    if (serverCard) {
        serverCard.classList.remove('connection-success', 'connection-failed');
        // Remove existing tooltip
        serverCard.removeAttribute('data-bs-toggle');
        serverCard.removeAttribute('data-bs-placement');
        serverCard.removeAttribute('data-bs-title');
        serverCard.removeAttribute('title');
    }
    if (testWrapper) {
        testWrapper.classList.remove('test-success', 'test-failed');
    }

    fetch('/ssh/test', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            hostname: hostname,
            port: port || 22,
            username: hostData.user,
            identity_file: hostData.identity_file
        })
    })
    .then(response => response.json())
    .then(data => {
        // Hide loading state
        icon.style.display = 'inline-block';
        spinner.style.display = 'none';

        if (data.success) {
            showToast(`✅ ${hostData.host}: Connection successful!`, 'success');
            // Add success classes
            if (serverCard) {
                serverCard.classList.add('connection-success');
                // Add success tooltip with Bootstrap
                serverCard.setAttribute('data-bs-toggle', 'tooltip');
                serverCard.setAttribute('data-bs-placement', 'top');
                serverCard.setAttribute('data-bs-title', `✅ Connection successful! Server ${hostData.host} is reachable.`);
                serverCard.setAttribute('title', `✅ Connection successful! Server ${hostData.host} is reachable.`);
                
                // Initialize tooltip
                if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                    new bootstrap.Tooltip(serverCard);
                }
                
                // Add blink effect
                serverCard.classList.add('blink-success');
                setTimeout(() => {
                    if (serverCard) serverCard.classList.remove('blink-success');
                }, 1000);
            }
            if (testWrapper) {
                testWrapper.classList.add('test-success');
                // Add tooltip to test wrapper
                testWrapper.setAttribute('data-bs-toggle', 'tooltip');
                testWrapper.setAttribute('data-bs-placement', 'top');
                testWrapper.setAttribute('data-bs-title', '✅ Connection successful');
                testWrapper.setAttribute('title', '✅ Connection successful');
                
                if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                    new bootstrap.Tooltip(testWrapper);
                }
                
                // Add blink effect for icon
                icon.classList.add('blink-icon');
                setTimeout(() => {
                    icon.classList.remove('blink-icon');
                }, 1000);
            }
            // Change icon color to green (permanent until next test)
            icon.style.color = '#10b981';
            icon.classList.add('connection-tested-success');
            
            // Add tooltip to icon
            icon.setAttribute('data-bs-toggle', 'tooltip');
            icon.setAttribute('data-bs-placement', 'top');
            icon.setAttribute('data-bs-title', '✅ Connection successful');
            icon.setAttribute('title', '✅ Connection successful');
            
        } else {
            const errorMessage = data.message || 'Connection failed';
            showToast(`❌ ${hostData.host}: Connection failed - ${errorMessage}`, 'danger');
            // Add failed classes
            if (serverCard) {
                serverCard.classList.add('connection-failed');
                // Add error tooltip with Bootstrap showing the actual error
                serverCard.setAttribute('data-bs-toggle', 'tooltip');
                serverCard.setAttribute('data-bs-placement', 'top');
                serverCard.setAttribute('data-bs-title', `❌ Connection failed: ${errorMessage}`);
                serverCard.setAttribute('title', `❌ Connection failed: ${errorMessage}`);
                
                // Initialize tooltip
                if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                    new bootstrap.Tooltip(serverCard);
                }
                
                // Add blink effect
                serverCard.classList.add('blink-failed');
                setTimeout(() => {
                    if (serverCard) serverCard.classList.remove('blink-failed');
                }, 1000);
            }
            if (testWrapper) {
                testWrapper.classList.add('test-failed');
                // Add tooltip to test wrapper with error
                testWrapper.setAttribute('data-bs-toggle', 'tooltip');
                testWrapper.setAttribute('data-bs-placement', 'top');
                testWrapper.setAttribute('data-bs-title', `❌ ${errorMessage}`);
                testWrapper.setAttribute('title', `❌ ${errorMessage}`);
                
                if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                    new bootstrap.Tooltip(testWrapper);
                }
                
                // Add blink effect for icon
                icon.classList.add('blink-icon');
                setTimeout(() => {
                    icon.classList.remove('blink-icon');
                }, 1000);
            }
            // Change icon color to red (permanent until next test)
            icon.style.color = '#ef4444';
            icon.classList.add('connection-tested-failed');
            
            // Add tooltip to icon with error
            icon.setAttribute('data-bs-toggle', 'tooltip');
            icon.setAttribute('data-bs-placement', 'top');
            icon.setAttribute('data-bs-title', `❌ ${errorMessage}`);
            icon.setAttribute('title', `❌ ${errorMessage}`);
        }
        
        // Initialize tooltips for the icon if bootstrap is available
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            new bootstrap.Tooltip(icon);
        }
    })
    .catch(error => {
        // Hide loading state
        icon.style.display = 'inline-block';
        spinner.style.display = 'none';

        const errorMessage = error.message || 'Network error';
        showToast(`❌ ${hostData.host}: Connection test failed`, 'danger');
        // Add failed classes
        if (serverCard) {
            serverCard.classList.add('connection-failed');
            // Add error tooltip
            serverCard.setAttribute('data-bs-toggle', 'tooltip');
            serverCard.setAttribute('data-bs-placement', 'top');
            serverCard.setAttribute('data-bs-title', `❌ Connection test failed: ${errorMessage}`);
            serverCard.setAttribute('title', `❌ Connection test failed: ${errorMessage}`);
            
            if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                new bootstrap.Tooltip(serverCard);
            }
            
            // Add blink effect
            serverCard.classList.add('blink-failed');
            setTimeout(() => {
                if (serverCard) serverCard.classList.remove('blink-failed');
            }, 1000);
        }
        if (testWrapper) {
            testWrapper.classList.add('test-failed');
            // Add tooltip to test wrapper
            testWrapper.setAttribute('data-bs-toggle', 'tooltip');
            testWrapper.setAttribute('data-bs-placement', 'top');
            testWrapper.setAttribute('data-bs-title', `❌ ${errorMessage}`);
            testWrapper.setAttribute('title', `❌ ${errorMessage}`);
            
            if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                new bootstrap.Tooltip(testWrapper);
            }
            
            // Add blink effect for icon
            icon.classList.add('blink-icon');
            setTimeout(() => {
                icon.classList.remove('blink-icon');
            }, 1000);
        }
        // Change icon color to red (permanent until next test)
        icon.style.color = '#ef4444';
        icon.classList.add('connection-tested-failed');
        
        // Add tooltip to icon
        icon.setAttribute('data-bs-toggle', 'tooltip');
        icon.setAttribute('data-bs-placement', 'top');
        icon.setAttribute('data-bs-title', `❌ ${errorMessage}`);
        icon.setAttribute('title', `❌ ${errorMessage}`);
        
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            new bootstrap.Tooltip(icon);
        }
        
        console.error('Error:', error);
    });
}

// Copy Apache config to clipboard
function copyApacheConfig() {
    const configElement = document.getElementById('apacheConfigContent');
    if (configElement) {
        // Use the original content for copying (properly formatted)
        const configText = configElement.getAttribute('data-original') || configElement.textContent;
        // Clean up the JSON escaping for copying
        const cleanText = configText
            .replace(/\\n/g, '\n')
            .replace(/\\t/g, '\t')
            .replace(/\\"/g, '"')
            .replace(/\\/g, '');

        navigator.clipboard.writeText(cleanText).then(() => {
            showToast('Apache config copied to clipboard!', 'success');
        }).catch(err => {
            showToast('Failed to copy config', 'danger');
            console.error('Copy failed:', err);
        });
    } else {
        showToast('Config content not found', 'danger');
    }
}

// Show command modal
function showCommandModal(title, content, commandText = null) {
    let modalHtml = `
        <div class="modal fade" id="commandModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title mb-0">
                            <i class="bi bi-terminal me-2"></i>${title}
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        ${content}
                    </div>
                    <div class="modal-footer">
                        ${commandText ? `<button type="button" class="btn btn-success me-2" onclick="copyCommand('${commandText.replace(/'/g, "\\'")}')">
                            <i class="bi bi-clipboard-check me-1"></i>Copy Command
                        </button>` : ''}
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remove existing modal if present
    const existingModal = document.getElementById('commandModal');
    if (existingModal) existingModal.remove();

    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const commandModalEl = document.getElementById('commandModal');
    const commandModal = new bootstrap.Modal(commandModalEl);
    // Auto-dispose dynamic modal after it is closed to avoid lingering state/backdrops
    commandModalEl.addEventListener('hidden.bs.modal', function onHidden() {
        commandModalEl.removeEventListener('hidden.bs.modal', onHidden);
        commandModal.dispose();
        commandModalEl.remove();
    });
    commandModal.show();
}

// Copy command to clipboard
function copyCommand(command) {
    navigator.clipboard.writeText(command).then(() => {
        // Close the modal and show success message
        const modal = bootstrap.Modal.getInstance(document.getElementById('commandModal'));
        if (modal) modal.hide();
        showToast('Command copied! Paste it in your terminal to open VS Code.', 'success');
    }).catch(err => {
        showToast('Failed to copy command. Please copy manually.', 'danger');
        console.error('Copy failed:', err);
    });
}

// SSH Export/Import Functions
function exportSshServers() {
    window.location.href = '/ssh/export';
}

function importSshServers() {
    const modal = new bootstrap.Modal(document.getElementById('sshImportModal'));
    modal.show();
}

function downloadSampleJson() {
    window.location.href = '/ssh/import-sample';
}

function submitSshImport() {
    const form = document.getElementById('sshImportForm');
    const formData = new FormData(form);
    const fileInput = document.getElementById('sshImportFile');

    if (!fileInput.files[0]) {
        showToast('Please select a JSON file to import', 'warning');
        return;
    }

    // Show progress
    document.getElementById('sshImportProgress').style.display = 'block';
    document.getElementById('sshImportStatus').textContent = 'Uploading and processing file...';

    fetch('/ssh/import', {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('sshImportModal'));
            if (modal) modal.hide();
            // Reload servers
            loadServers();
        } else {
            showToast('Import failed: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Import error:', error);
        showToast('Import failed due to network error', 'danger');
    })
    .finally(() => {
        document.getElementById('sshImportProgress').style.display = 'none';
    });
}

// Upload PEM Key Function
function uploadPemKey() {
    // Create a file input element
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = '.pem';
    input.style.display = 'none';

    input.onchange = function(e) {
        const file = e.target.files[0];
        if (file) {
            uploadPemFile(file);
        }
    };

    document.body.appendChild(input);
    input.click();
    document.body.removeChild(input);
}

function uploadPemFile(file) {
    const formData = new FormData();
    formData.append('pem_file', file);

    showToast('Uploading PEM file...', 'info');

    fetch('/ssh/upload-key', {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            // Refresh the page or reload servers to show new key
            setTimeout(() => {
                loadServers();
            }, 1000);
        } else {
            showToast('Upload failed: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to upload PEM file', 'danger');
    });
}

function fixAllConnections() {
    if (!confirm('This will clean SSH config (remove empty entries) and fix PEM file permissions. Continue?')) {
        return;
    }

    showToast('Cleaning SSH config and fixing PEM permissions...', 'info');

    fetch('/ssh/fix-all-connections', {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            loadServers(); // Reload to show updated status
        } else {
            showToast('Failed: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to clean config and fix permissions', 'danger');
    });
}

// Save SSH server
function saveSshServer() {
    const originalHost = document.getElementById('originalHost').value;

    // Sanitize host name: convert to lower case and replace hyphens with underscores before saving
    normalizeAllSshFormFields();
    const hostInput = document.getElementById('sshHost');

    // Prepare data for submission
    const serverData = {
        host: hostInput.value,
        hostname: document.getElementById('sshHostname').value,
        port: document.getElementById('sshPort').value,
        user: document.getElementById('sshUser').value,
        identity_file: document.getElementById('sshIdentityFile').value,
        domains: document.getElementById('sshDomains').value.split(',').map(d => d.trim()).filter(d => d),
        description: document.getElementById('sshDescription').value
    };

    // Validate required fields
    if (!serverData.host || !serverData.hostname || !serverData.user || !serverData.identity_file) {
        showToast('Please fill in all required fields (Host, Hostname, User, Identity File)', 'warning');
        return;
    }

    // Check if test was successful
    if (!sshTestSuccessful) {
        const isEdit = originalHost && originalHost.trim() !== '';
        const action = isEdit ? 'update' : 'add';
        const confirmMsg = `You haven't tested the SSH connection yet. Are you sure you want to ${action} this server without testing?`;
        if (!confirm(confirmMsg)) {
            return;
        }
    }



    // Determine if this is add or edit
    const isEdit = originalHost && originalHost.trim() !== '';
    const url = isEdit ? `/ssh/update/${originalHost}` : '/ssh/add';
    const method = isEdit ? 'PUT' : 'POST';

    fetch(url, {
        method: method,
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify(serverData)
    })
    .then(response => {
        // Check if response is JSON or HTML
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            return response.json();
        } else {
            // If not JSON, treat as error
            return response.text().then(text => {
                throw new Error('Server returned HTML instead of JSON: ' + text.substring(0, 200));
            });
        }
    })
    .then(data => {
        if (data.success) {
            // Close modal and reload servers
            const modal = bootstrap.Modal.getInstance(document.getElementById('sshModal'));
            if (modal) modal.hide();

            const action = isEdit ? 'updated' : 'added';
            showToast(`SSH server ${action} successfully!`, 'success');
            loadServers(); // Reload the server list
        } else {
            showToast(`Failed to ${isEdit ? 'update' : 'add'} server: ` + (data.message || 'Unknown error'), 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // Show more detailed error information
        if (error.message.includes('HTML')) {
            showToast('Server authentication error. Please refresh the page and try again.', 'danger');
        } else {
            showToast(`Failed to ${isEdit ? 'update' : 'add'} server: ${error.message}`, 'danger');
        }
    });
}

// Reset SSH form
function resetSshForm() {
    document.getElementById('sshForm').reset();
    document.getElementById('originalHost').value = '';
    document.getElementById('sshPort').value = '22';
    document.getElementById('sshTestResultMsg').style.display = 'none';
    document.getElementById('sshModalLabel').innerHTML = '<i class="bi bi-plus-circle me-2"></i>Add SSH Server';
    sshTestSuccessful = false;
}

// Test SSH connection before saving
function testSshBeforeSave() {
    const testBtn = document.getElementById('testSshBeforeSaveBtn');
    const resultMsg = document.getElementById('sshTestResultMsg');

    // Get form values
    const hostname = document.getElementById('sshHostname').value;
    const port = document.getElementById('sshPort').value;
    const username = document.getElementById('sshUser').value;
    const identityFile = document.getElementById('sshIdentityFile').value;

    if (!hostname || !username || !identityFile) {
        resultMsg.style.display = 'block';
        resultMsg.innerHTML = '<div class="alert alert-warning">Please fill in hostname, username, and identity file first.</div>';
        return;
    }

    // Show loading state
    testBtn.disabled = true;
    testBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Testing...';
    resultMsg.style.display = 'none';

    fetch('/ssh/test', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            hostname: hostname,
            port: port || 22,
            username: username,
            identity_file: identityFile
        })
    })
    .then(response => response.json())
    .then(data => {
        // Reset button
        testBtn.disabled = false;
        testBtn.innerHTML = '<i class="bi bi-plug"></i> Test SSH Connection Before Saving';

        // Show result and set flag
        resultMsg.style.display = 'block';
        if (data.success) {
            resultMsg.innerHTML = '<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>SSH connection successful!</div>';
            sshTestSuccessful = true;
        } else {
            resultMsg.innerHTML = '<div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i>SSH connection failed: ' + data.message + '</div>';
            sshTestSuccessful = false;
        }
    })
    .catch(error => {
        // Reset button
        testBtn.disabled = false;
        testBtn.innerHTML = '<i class="bi bi-plug"></i> Test SSH Connection Before Saving';

        resultMsg.style.display = 'block';
        resultMsg.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>Connection test failed.</div>';
        console.error('Error:', error);
    });
}

// Populate SSH key files dropdown - FIXED with focus handling
function populateSshKeyFiles() {
    return new Promise((resolve, reject) => {
        const select = document.getElementById('sshIdentityFile');

        // Clear existing options except the first one
        while (select.options.length > 1) {
            select.remove(1);
        }

        // Add loading option
        const loadingOption = document.createElement('option');
        loadingOption.value = '';
        loadingOption.textContent = 'Loading SSH key files...';
        loadingOption.disabled = true;
        select.appendChild(loadingOption);

        fetch('/ssh/ssh-key-files')
        .then(response => response.json())
        .then(data => {
            // Remove loading option
            if (select.options.length > 1 && select.options[1].textContent === 'Loading SSH key files...') {
                select.remove(1);
            }

            if (data.success && data.keyFiles) {
                data.keyFiles.forEach(keyFile => {
                    const option = document.createElement('option');
                    option.value = keyFile.path;
                    option.textContent = keyFile.filename + (keyFile.exists ? ' ✓' : ' ⚠️');
                    option.setAttribute('data-exists', keyFile.exists);
                    select.appendChild(option);
                });

                // Initialize Select2 with focus fix
                if (typeof $ !== 'undefined' && $.fn.select2) {
                    // Destroy existing instance if any
                    if ($('#sshIdentityFile').data('select2')) {
                        $('#sshIdentityFile').select2('destroy');
                    }

                    $('#sshIdentityFile').select2({
                        theme: 'bootstrap-5',
                        placeholder: 'Select SSH key file...',
                        allowClear: true,
                        width: '100%',
                        dropdownParent: $('#sshModal'), // Critical: attach to modal for proper focus
                        containerCssClass: 'select2-container--bootstrap-5'
                    });

                    // Force focus on Select2 when modal is shown
                    setTimeout(() => {
                        const select2Container = $('#sshIdentityFile').next('.select2-container');
                        if (select2Container.length) {
                            select2Container.find('.select2-selection').on('click', function(e) {
                                e.stopPropagation();
                                $('#sshIdentityFile').select2('open');
                            });
                        }
                    }, 100);
                }
                resolve();
            } else {
                const errorOption = document.createElement('option');
                errorOption.value = '';
                errorOption.textContent = 'Failed to load SSH key files';
                errorOption.disabled = true;
                select.appendChild(errorOption);
                resolve();
            }
        })
        .catch(error => {
            console.error('Error loading SSH key files:', error);
            if (select.options.length > 1 && select.options[1].textContent === 'Loading SSH key files...') {
                select.remove(1);
            }
            const errorOption = document.createElement('option');
            errorOption.value = '';
            errorOption.textContent = 'Error loading SSH key files';
            errorOption.disabled = true;
            select.appendChild(errorOption);
            resolve();
        });
    });
}

// Helper function for toasts (assuming it exists or needs to be added)
function notyShow(message, type = 'error', timeout = 4000) {
    if (typeof Noty === 'function') {
        new Noty({
            type: type,
            text: message,
            layout: 'topRight',
            timeout: timeout,
            progressBar: true,
            closeWith: ['click', 'button']
        }).show();
    } else {
        showToast(message, type === 'error' ? 'danger' : (type === 'warning' ? 'warning' : 'info'));
    }
}

function showToast(message, type) {
    // Simple toast implementation - you might want to use a proper toast library
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    toast.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(toast);

    // Auto remove after 5 seconds
    setTimeout(() => {
        if (toast.parentNode) {
            toast.remove();
        }
    }, 5000);
}

// Auto-normalize Add/Edit SSH Server form fields:
// - Convert upper case to lower case (typing, copy-paste, and on blur)
// - Host Name (sshHost) also replaces hyphens with underscores
const sshFormLowercaseFields = ['sshHost', 'sshHostname', 'sshUser', 'sshDomains'];

function normalizeSshFormField(id) {
    const el = document.getElementById(id);
    if (!el || !el.value) return;

    const cursorPos = el.selectionStart;
    let newVal = el.value.toLowerCase();

    // Host Name: replace hyphens with underscores (kept for SSH config compatibility)
    if (id === 'sshHost') {
        newVal = newVal.replace(/-/g, '_');
    }

    if (newVal !== el.value) {
        el.value = newVal;
        // Keep the caret at the same logical position
        try {
            el.setSelectionRange(cursorPos, cursorPos);
        } catch (err) {
            // Ignore any selection range errors (e.g. some mobile browsers)
        }
    }
}

function normalizeAllSshFormFields() {
    sshFormLowercaseFields.forEach(function(id) {
        normalizeSshFormField(id);
    });
}

// Attach listeners to the lowercase fields in the Add/Edit SSH Server modal
sshFormLowercaseFields.forEach(function(id) {
    const el = document.getElementById(id);
    if (!el) return;

    // Live conversion while typing
    el.addEventListener('input', function() {
        normalizeSshFormField(id);
    });

    // Conversion after pasting text
    el.addEventListener('paste', function() {
        // Wait for the browser to insert the pasted text before normalizing
        setTimeout(function() {
            normalizeSshFormField(id);
        }, 0);
    });

    // Final cleanup when focus leaves the field
    el.addEventListener('blur', function() {
        normalizeSshFormField(id);
    });
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadServers();

    // Favorite star toggle handler (delegated)
    document.addEventListener('click', function(e) {
        const star = e.target.closest('.favorite-star');
        if (!star) return;

        const host = star.getAttribute('data-host');
        if (!host) return;

        // Optimistic UI update
        const wasFavorite = star.classList.contains('bi-star-fill');
        if (wasFavorite) {
            star.classList.remove('bi-star-fill');
            star.classList.add('bi-star');
            star.style.color = '#cbd5e1';
            star.title = 'Add to favorites';
        } else {
            star.classList.remove('bi-star');
            star.classList.add('bi-star-fill');
            star.style.color = '#f59e0b';
            star.title = 'Remove from favorites';
        }

        fetch('/ssh/toggle-favorite', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ host: host })
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                // Revert on failure
                if (wasFavorite) {
                    star.classList.add('bi-star-fill');
                    star.classList.remove('bi-star');
                    star.style.color = '#f59e0b';
                    star.title = 'Remove from favorites';
                } else {
                    star.classList.add('bi-star');
                    star.classList.remove('bi-star-fill');
                    star.style.color = '#cbd5e1';
                    star.title = 'Add to favorites';
                }
                showToast('Failed to toggle favorite', 'danger');
            } else {
                showToast(data.is_favorite ? 'Added to favorites' : 'Removed from favorites', 'success');
            }
        })
        .catch(error => {
            // Revert on error
            if (wasFavorite) {
                star.classList.add('bi-star-fill');
                star.classList.remove('bi-star');
                star.style.color = '#f59e0b';
                star.title = 'Remove from favorites';
            } else {
                star.classList.add('bi-star');
                star.classList.remove('bi-star-fill');
                star.style.color = '#cbd5e1';
                star.title = 'Add to favorites';
            }
            console.error('Favorite toggle error:', error);
            showToast('Failed to toggle favorite', 'danger');
        });
    });
});
