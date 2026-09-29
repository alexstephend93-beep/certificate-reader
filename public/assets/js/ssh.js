// Load servers via AJAX
let loadedHosts = [];
let allHosts = [];
let sshTestSuccessful = false;

// --- Server list UI state (search pipeline, quick filters, sort, refresh) ---
const SSH_RENDER_LIMIT = 200;         // max cards rendered at once (performance guard)
const SSH_SEARCH_DEBOUNCE_MS = 180;   // debounce for the live search box
let sshSearchDebounceTimer = null;    // pending debounced search render
let sshSortMode = 'name';             // name | recent | domains
let sshFilterMode = 'all';            // all | favorites | missing-key
let sshLastLoadedAt = null;           // Date of the last successful /ssh/list load
let sshLastUpdatedTimer = null;       // interval that refreshes the "last updated" label

function loadServers() {
    console.log('Loading SSH servers...');

    const grid = document.getElementById('serversGrid');
    if (!grid) {
        console.error('serversGrid element not found');
        return;
    }

    setSshListLoading(true);

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
            sshLastLoadedAt = new Date();
            updateSshLastUpdatedLabel();

            console.log('Loaded', allHosts.length, 'servers, total available:', data.totalServers);

            // Render through the shared pipeline so an active search term,
            // quick filter and sort order survive a refresh.
            searchServers();

            updateStats(data.totalServers, data.validKeys);
            updateSshTotalOpens(data.totalOpens || 0);

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

            setSshListLoading(false);
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
            setSshListLoading(false);
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
    // An immediate call (Enter, Search button, filter chip, sort, refresh) always
    // wins over a still-pending debounced render.
    if (sshSearchDebounceTimer) {
        clearTimeout(sshSearchDebounceTimer);
        sshSearchDebounceTimer = null;
    }

    const searchInputEl = document.getElementById('searchInput');
    const rawSearchTerm = searchInputEl ? searchInputEl.value : '';
    // Automatically convert upper case to lower case and normalize whitespace
    const searchTerm = rawSearchTerm.toLowerCase().trim().replace(/\s+/g, ' ');

    if (!searchTerm) {
        loadedHosts = [...allHosts];
        finalizeSshRender(true);
        return;
    }

    const searchWords = searchTerm.split(/[ _]+/).filter(Boolean);

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

    finalizeSshRender(false);
}

function clearSearch() {
    document.getElementById('searchInput').value = '';
    searchServers();
}

// ===== Shared render pipeline: quick filter -> sort -> render cap =====

/**
 * Quick filter chips: all | favorites | missing-key.
 * A key counts as missing when an identity file is configured but the file no
 * longer exists on disk (mirrors the backend "Valid Keys" counter).
 */
function hostPassesQuickFilter(host) {
    if (sshFilterMode === 'favorites') return !!host.is_favorite;
    if (sshFilterMode === 'missing-key') return !!host.identity_file && !host.key_exists;
    if (sshFilterMode === 'never-opened') return !(host.open_count > 0);
    return true;
}

/**
 * Sort the already-filtered list.
 * The default mode intentionally keeps the order returned by the backend
 * (favourites first, then host A–Z) so the initial view is unchanged.
 * The other modes sort client-side and still float favourites to the top.
 */
function sortSshHosts(hosts) {
    if (sshSortMode !== 'recent' && sshSortMode !== 'domains' && sshSortMode !== 'opened') {
        return hosts;
    }

    const list = hosts.slice();
    const byFavourite = (a, b) => (b.is_favorite ? 1 : 0) - (a.is_favorite ? 1 : 0);
    const byHost = (a, b) => (a.host || '').localeCompare(b.host || '', undefined, { sensitivity: 'base', numeric: true });

    if (sshSortMode === 'recent') {
        list.sort((a, b) => {
            const fav = byFavourite(a, b);
            if (fav !== 0) return fav;
            const ta = a.last_connected ? new Date(a.last_connected).getTime() : 0;
            const tb = b.last_connected ? new Date(b.last_connected).getTime() : 0;
            if (tb !== ta) return tb - ta;
            return byHost(a, b);
        });
    } else if (sshSortMode === 'opened') {
        // Most opened first (counts come from storage/app/ssh/open_counts.json)
        list.sort((a, b) => {
            const fav = byFavourite(a, b);
            if (fav !== 0) return fav;
            const oa = a.open_count || 0;
            const ob = b.open_count || 0;
            if (ob !== oa) return ob - oa;
            return byHost(a, b);
        });
    } else {
        list.sort((a, b) => {
            const fav = byFavourite(a, b);
            if (fav !== 0) return fav;
            const da = (a.domains || []).length;
            const db = (b.domains || []).length;
            if (db !== da) return db - da;
            return byHost(a, b);
        });
    }

    return list;
}

/**
 * Empty state shown when a search term or quick filter matches no server.
 * Kept separate from renderServers() so the onboarding state
 * ("No servers configured") is never shown for an active query.
 */
function renderFilteredEmptyState() {
    const grid = document.getElementById('serversGrid');
    if (!grid) return;
    grid.innerHTML = `
        <div class="col-12">
            <div class="text-center py-5">
                <i class="bi bi-funnel fs-1 text-muted"></i>
                <h4 class="mt-3">No servers match the current view</h4>
                <p class="text-muted">Adjust your search or pick another filter to see more servers.</p>
            </div>
        </div>
    `;
}

/**
 * Apply the quick filter, sort, render at most SSH_RENDER_LIMIT cards and keep
 * `loadedHosts` in sync with the rendered slice — the per-card test button
 * looks its host up by index, so the two must match exactly.
 *
 * @param {boolean} isEmptySearch true when the search box is empty (so the
 *                                "no results" alert only shows for a real query)
 */
function finalizeSshRender(isEmptySearch) {
    let list = loadedHosts.filter(hostPassesQuickFilter);
    list = sortSshHosts(list);

    const matchedCount = list.length;
    loadedHosts = list.slice(0, SSH_RENDER_LIMIT);

    const hasActiveQuery = !isEmptySearch || sshFilterMode !== 'all';

    if (matchedCount === 0 && hasActiveQuery) {
        // Search/filter returned nothing — do not show the "no servers configured"
        // onboarding state, which would be misleading here.
        renderFilteredEmptyState();
    } else {
        renderServers(loadedHosts);
    }

    updateStats(allHosts.length, countValidKeys(allHosts));

    const noResultsMsg = document.getElementById('noResultsMessage');
    if (noResultsMsg) {
        noResultsMsg.style.display = (matchedCount === 0 && hasActiveQuery) ? 'block' : 'none';
    }

    // Tell the user when the render cap is hiding matching cards.
    const grid = document.getElementById('serversGrid');
    if (grid && matchedCount > loadedHosts.length) {
        const moreMsg = document.createElement('div');
        moreMsg.className = 'col-12 mt-3';
        moreMsg.innerHTML = `
            <div class="alert alert-info mb-0">
                <i class="bi bi-info-circle me-2"></i>
                Showing the first ${loadedHosts.length} of ${matchedCount} matching servers. Refine your search or filter to narrow the list.
            </div>
        `;
        grid.appendChild(moreMsg);
    }
}

// Quick filter chips (All / Favorites / Missing key)
function applySshFilter(filter) {
    sshFilterMode = filter || 'all';

    document.querySelectorAll('.ssh-chip').forEach(function (chip) {
        const isActive = chip.getAttribute('data-filter') === sshFilterMode;
        chip.classList.toggle('active', isActive);
        chip.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });

    searchServers();
}

// Sort dropdown
function applySshSort(mode) {
    sshSortMode = mode || 'name';
    searchServers();
}

// Toolbar Refresh: re-scan the SSH config on the server and reload the list
function refreshSshServers() {
    loadServers();
}

// Visual feedback while /ssh/list is in flight
function setSshListLoading(isLoading) {
    const btn = document.getElementById('sshRefreshBtn');
    if (!btn) return;
    btn.disabled = !!isLoading;
    const icon = btn.querySelector('i');
    if (icon) icon.className = isLoading ? 'bi bi-arrow-repeat ssh-spin' : 'bi bi-arrow-clockwise';
}

// Keep the "Last updated" label current
function updateSshLastUpdatedLabel() {
    const el = document.getElementById('sshLastUpdated');
    if (!el) return;
    el.textContent = sshLastLoadedAt ? 'Last updated: ' + formatTimeAgo(sshLastLoadedAt) : 'Last updated: —';
}

// Focus (and select) the server search box
function focusSshSearch() {
    const el = document.getElementById('searchInput');
    if (!el) return;
    el.focus();
    try { el.select(); } catch (err) { /* ignore */ }
}

// Debounced render for the live search box
function debounceSshSearch() {
    if (sshSearchDebounceTimer) clearTimeout(sshSearchDebounceTimer);
    sshSearchDebounceTimer = setTimeout(function () {
        sshSearchDebounceTimer = null;
        searchServers();
    }, SSH_SEARCH_DEBOUNCE_MS);
}

/* ============================================================
 * SERVER "OPENED" COUNTERS
 * ------------------------------------------------------------
 * The counter stored in storage/app/ssh/open_counts.json is
 * incremented ONLY when a server is really "opened":
 *   • Open project in VS Code
 *   • Browse Projects
 *   • Project Explorer (Browse Files & Folders)
 *
 * Other card actions (Apache config, SSL install, proxy health,
 * connection test, copy SSH command) deliberately DO NOT count.
 * ============================================================ */

// Tell the backend that a server was opened and refresh its badge.
function recordServerOpen(host) {
    if (!host) return;

    fetch('/ssh/record-open', {
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
        if (!data || !data.success) return;

        // Keep the in-memory copy in sync (used by the "Most opened" sort
        // and the "Never opened" filter).
        allHosts.forEach(function (h) {
            if (h.host === host) {
                h.open_count = data.count;
                h.last_opened = data.last_opened;
            }
        });

        updateSshOpenBadge(host, data.count, data.last_opened);
        updateSshTotalOpens(data.total);

        // Deliberately not re-sorting here: cards jumping around mid-click
        // would be disorienting — the new order applies on the next render.
    })
    .catch(error => console.warn('Could not record server open:', error));
}

// Update the "Opened N×" row of a single card without re-rendering the grid.
function updateSshOpenBadge(host, count, lastOpened) {
    document.querySelectorAll('.server-card').forEach(function (card) {
        if (card.getAttribute('data-server-host') !== host) return;

        const row = card.querySelector('.server-open-count');
        if (row) {
            const value = row.querySelector('.detail-value');
            if (value) value.innerHTML = count > 0 ? 'Opened ' + count + '&times;' : 'Never opened';
            row.classList.toggle('server-open-count-zero', !(count > 0));
            row.setAttribute('title', lastOpened
                ? 'Last opened ' + formatTimeAgo(lastOpened)
                : (count > 0 ? 'Opened ' + count + ' time(s)' : 'This server has not been opened yet'));
        }

        const wrapper = card.closest('.server-card-wrapper');
        if (wrapper) wrapper.setAttribute('data-open-count', String(count));
    });
}

// Update the "Total Opens" stat card.
function updateSshTotalOpens(total) {
    const el = document.getElementById('totalOpens');
    if (el) el.textContent = Number(total) || 0;
}

// Clear every open counter (empties storage/app/ssh/open_counts.json).
function clearSshOpenCounts() {
    if (!confirm('Clear the "Opened" counter for every server?\n\nThis empties the storage JSON file (storage/app/ssh/open_counts.json) and cannot be undone.')) {
        return;
    }

    const btn = document.getElementById('sshClearOpenCountsBtn');
    if (btn) btn.disabled = true;

    fetch('/ssh/clear-open-counts', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        }
    })
    .then(response => response.json())
    .then(data => {
        if (!data || !data.success) {
            showToast('Could not clear the open counts', 'danger');
            return;
        }

        allHosts.forEach(function (h) {
            h.open_count = 0;
            h.last_opened = null;
        });

        updateSshTotalOpens(0);
        searchServers(); // re-render the cards with the counters reset
        showToast('Open counts cleared', 'success');
    })
    .catch(function () {
        showToast('Could not clear the open counts', 'danger');
    })
    .then(function () {
        if (btn) btn.disabled = false;
    });
}

// Delegated: only card actions tagged with data-count-open increment the counter
// (Open project in VS Code, Browse Projects, Project Explorer).
// Registered in the CAPTURE phase so inline handlers that call
// event.stopPropagation() (e.g. the "open domain in VS Code" icon) are still counted.
document.addEventListener('click', function (e) {
    const target = e.target;
    if (!target || typeof target.closest !== 'function') return;

    const trigger = target.closest('[data-count-open]');
    if (!trigger) return;

    const card = trigger.closest('.server-card');
    if (!card) return;

    const host = card.getAttribute('data-server-host');
    if (host) recordServerOpen(host);
}, true);

// Add event listeners for search input
const searchInput = document.getElementById('searchInput');
if (searchInput) {
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') searchServers();
    });

    // Add input event listener for real-time search
    searchInput.addEventListener('input', function(e) {
        const el = e.target;
        // Directly convert upper case to lower case while typing
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
        // Convert spaces to underscores (e.g. "1pay nsdl prod" => "1pay_nsdl_prod")
        if (el.value.includes(' ')) {
            el.value = el.value.replace(/ /g, '_');
            e.preventDefault();
        }
        // Debounced so typing does not rebuild the whole grid on every keystroke
        debounceSshSearch();
    });

    // Ensure search input is focusable and accessible
    searchInput.setAttribute('tabindex', '0');
    searchInput.style.pointerEvents = 'auto';
    searchInput.style.cursor = 'text';
}

// Keyboard shortcuts for the server search box:
//   Ctrl/Cmd + K  and  "/"  -> focus the search box
//   Esc                     -> clear the search (only when no modal is open)
document.addEventListener('keydown', function (e) {
    const target = e.target || {};
    const tag = (target.tagName || '').toUpperCase();
    const isTyping = tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || target.isContentEditable === true;

    if ((e.ctrlKey || e.metaKey) && !e.altKey && (e.key === 'k' || e.key === 'K')) {
        e.preventDefault();
        focusSshSearch();
        return;
    }

    if (e.key === '/' && !isTyping && !document.querySelector('.modal.show')) {
        e.preventDefault();
        focusSshSearch();
        return;
    }

    // Never interfere with Bootstrap's Esc-to-close while a modal is open
    if (e.key === 'Escape' && !document.querySelector('.modal.show')) {
        const el = document.getElementById('searchInput');
        if (el && el.value) {
            clearSearch();
        }
    }
});

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
                    <i class="bi bi-code-square icon-vscode" data-count-open="1" title="Open project in VS Code"
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

        // How many times this server has been opened (storage/app/ssh/open_counts.json)
        const openCount = host.open_count || 0;
        const openCountTitle = host.last_opened
            ? 'Last opened ' + formatTimeAgo(host.last_opened)
            : (openCount > 0 ? 'Opened ' + openCount + ' time(s)' : 'This server has not been opened yet');
        const openCountHtml = `
                        <div class="server-detail server-open-count${openCount > 0 ? '' : ' server-open-count-zero'}" title="${openCountTitle}">
                            <i class="bi bi-box-arrow-in-right"></i>
                            <span class="detail-value">${openCount > 0 ? 'Opened ' + openCount + '&times;' : 'Never opened'}</span>
                        </div>`;
        
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
                 data-host="${host.host}" data-key-missing="${(host.identity_file && host.key_exists === false) ? '1' : '0'}">
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
                        ${(host.identity_file && host.key_exists === false) ? `
                        <div class="server-detail server-key-missing" title="The identity file configured for this server was not found on disk">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <span class="detail-value">Key file missing</span>
                        </div>` : ''}
                        ${domainsHtml}
                        
                        <div class="server-actions">
                            <i class="bi bi-folder2-open icon-folder" data-count-open="1" title="Browse Projects" onclick='browseProjects("${host.host}", "${host.hostname}", "${host.user}", "${escapeHtml(host.identity_file || '')}", ${port})'></i>
                            <i class="bi bi-diagram-3 icon-explorer" data-count-open="1" title="Project Explorer (Browse Files & Folders)" onclick='openProjectExplorer("${host.host}", "${host.hostname}", "${host.user}", "${escapeHtml(host.identity_file || '')}", ${port}, "/var/www")'></i>
                            <i class="bi bi-file-earmark-text icon-config" title="View Apache Config" onclick='viewApacheConfig("${host.host}", "${host.hostname}", "${host.user}", "${escapeHtml(host.identity_file || '')}", ${port})'></i>
                            <i class="bi bi-patch-check-fill icon-ssl" title="Install SSL Certificate (Let's Encrypt / Paid)" onclick='openSslInstallModal("${host.host}", "${host.hostname}", "${host.user}", "${escapeHtml(host.identity_file || '')}", ${port})'></i>
                            ${vscodeDomainsHtml}
                             <i class="bi bi-clipboard2-check icon-copy" title="Copy SSH command" onclick='copySshCommand("${host.host}")'></i>
                             <i class="bi bi-heart-pulse icon-diagnose" title="Proxy Server Health Checkup" onclick='showProxyHealth("${host.host}", this)'></i>
                            <div class="test-wrapper">
                                <i class="bi bi-plug-fill icon-test" title="Test server connection" onclick='testSingleConnection(this, ${index}, "${host.hostname}", ${port})'></i>
                                <span class="testing-spinner"></span>
                            </div>
                        </div>
                        ${openCountHtml}
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

    // Hard client-side timeout so the spinner always resolves, even if the
    // remote host is slow or unreachable.
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 120000);

    fetch('/ssh/proxy-health/' + encodeURIComponent(host), {
        headers: {
            'Accept': 'application/json'
        },
        signal: controller.signal
    })
    .then(response => {
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return response.json();
    })
    .then(data => {
        clearTimeout(timeoutId);
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
        clearTimeout(timeoutId);
        if (blinkInterval) clearInterval(blinkInterval);
        if (icon) icon.className = originalClass;
        console.error('Health fetch error:', error);
        if (error && error.name === 'AbortError') {
            showToast('Proxy health check timed out after 2 minutes.', 'warning');
        } else {
            showToast('Failed to get proxy health: ' + error.message, 'danger');
        }
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

/**
 * Format a value expressed in MB into a human readable RAM string.
 */
function formatRam(mb) {
    const n = parseFloat(mb);
    if (isNaN(n) || n <= 0) return 'N/A';
    if (n >= 1024) return (n / 1024).toFixed(1) + ' GB';
    return Math.round(n) + ' MB';
}

/**
 * Highlight panel for Used RAM and Available RAM.
 * Uses the numeric values (mem_total_mb / mem_used_mb / mem_available_mb)
 * parsed server-side from `free -m` so the numbers are precise.
 */
function renderMemoryHighlight(details) {
    if (!details) return '';
    const total = parseFloat(details.mem_total_mb);
    const used = parseFloat(details.mem_used_mb);
    const avail = parseFloat(details.mem_available_mb);
    if (isNaN(total) || total <= 0) return '';

    const usedVal = isNaN(used) ? 0 : used;
    const availVal = isNaN(avail) ? 0 : avail;
    const usedPct = Math.min(100, Math.max(0, Math.round((usedVal / total) * 100)));
    const availPct = Math.min(100, Math.max(0, Math.round((availVal / total) * 100)));

    let usedBar = 'bg-success', usedText = 'text-success', usedBorder = '#a7f3d0', usedBg = '#ecfdf5';
    if (usedPct > 92) {
        usedBar = 'bg-danger'; usedText = 'text-danger'; usedBorder = '#fecaca'; usedBg = '#fef2f2';
    } else if (usedPct > 75) {
        usedBar = 'bg-warning'; usedText = 'text-warning'; usedBorder = '#fde68a'; usedBg = '#fffbeb';
    }

    let availBar = 'bg-primary', availText = 'text-primary', availBorder = '#bfdbfe', availBg = '#eff6ff';
    if (availPct < 10) {
        availBar = 'bg-danger'; availText = 'text-danger'; availBorder = '#fecaca'; availBg = '#fef2f2';
    } else if (availPct < 25) {
        availBar = 'bg-warning'; availText = 'text-warning'; availBorder = '#fde68a'; availBg = '#fffbeb';
    }

    return `
        <div class="row g-2 mb-3">
            <div class="col-12 col-md-4">
                <div class="p-3 rounded h-100" style="background:${usedBg};border:1px solid ${usedBorder};">
                    <div class="text-uppercase fw-bold text-muted" style="font-size:0.7rem;letter-spacing:0.5px;">🔥 Used RAM</div>
                    <div class="fs-4 fw-bold ${usedText}">${escapeHtml(formatRam(usedVal))}</div>
                    <div class="progress mt-2" style="height:8px;">
                        <div class="progress-bar ${usedBar}" role="progressbar" style="width:${usedPct}%;" aria-valuenow="${usedPct}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="small text-muted mt-1">${usedPct}% of ${escapeHtml(formatRam(total))}</div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="p-3 rounded h-100" style="background:${availBg};border:1px solid ${availBorder};">
                    <div class="text-uppercase fw-bold text-muted" style="font-size:0.7rem;letter-spacing:0.5px;">✅ Available RAM</div>
                    <div class="fs-4 fw-bold ${availText}">${escapeHtml(formatRam(availVal))}</div>
                    <div class="progress mt-2" style="height:8px;">
                        <div class="progress-bar ${availBar}" role="progressbar" style="width:${availPct}%;" aria-valuenow="${availPct}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="small text-muted mt-1">${availPct}% of ${escapeHtml(formatRam(total))} free for use</div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="p-3 rounded h-100" style="background:#f8fafc;border:1px solid #e2e8f0;">
                    <div class="text-uppercase fw-bold text-muted" style="font-size:0.7rem;letter-spacing:0.5px;">💾 Total RAM</div>
                    <div class="fs-4 fw-bold text-dark">${escapeHtml(formatRam(total))}</div>
                    <div class="small text-muted mt-2">Physical memory reported by <code>free -m</code></div>
                </div>
            </div>
        </div>`;
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
            if (sec.title.indexOf('Memory') !== -1) {
                detailsHtml += renderMemoryHighlight(health.details);
            }
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

/**
 * Helper function to create a tooltip that auto-hides after a specified delay
 * Tooltip will still be available on hover after auto-hide
 * @param {HTMLElement} element - The element to attach the tooltip to
 * @param {string} title - The tooltip text
 * @param {string} placement - Tooltip placement (default: 'top')
 * @param {number} delay - Time in ms before tooltip auto-hides (default: 5000)
 */
function autoHideTooltip(element, title, placement = 'top', delay = 5000) {
    if (!element) return;
    
    // Set tooltip attributes
    element.setAttribute('data-bs-toggle', 'tooltip');
    element.setAttribute('data-bs-placement', placement);
    element.setAttribute('data-bs-title', title);
    element.setAttribute('title', title);
    
    // Initialize tooltip
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        const tooltipInstance = new bootstrap.Tooltip(element);
        
        // Auto-hide tooltip popup after specified delay
        // Using hide() instead of dispose() so tooltip still works on hover
        setTimeout(() => {
            tooltipInstance.hide();
        }, delay);
    }
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
                // Add success tooltip with auto-hide after 5 seconds
                autoHideTooltip(serverCard, `✅ Connection successful! Server ${hostData.host} is reachable.`, 'top', 5000);
                
                // Add blink effect
                serverCard.classList.add('blink-success');
                setTimeout(() => {
                    if (serverCard) serverCard.classList.remove('blink-success');
                }, 1000);
            }
            if (testWrapper) {
                testWrapper.classList.add('test-success');
                // Add tooltip to test wrapper with auto-hide
                autoHideTooltip(testWrapper, '✅ Connection successful', 'top', 5000);
                
                // Add blink effect for icon
                icon.classList.add('blink-icon');
                setTimeout(() => {
                    icon.classList.remove('blink-icon');
                }, 1000);
            }
            // Change icon color to green (permanent until next test)
            icon.style.color = '#10b981';
            icon.classList.add('connection-tested-success');
            
            // Add tooltip to icon with auto-hide
            autoHideTooltip(icon, '✅ Connection successful', 'top', 5000);
            
        } else {
            const errorMessage = data.message || 'Connection failed';
            showToast(`❌ ${hostData.host}: Connection failed - ${errorMessage}`, 'danger');
            // Add failed classes
            if (serverCard) {
                serverCard.classList.add('connection-failed');
                // Add error tooltip with auto-hide after 5 seconds
                autoHideTooltip(serverCard, `❌ Connection failed: ${errorMessage}`, 'top', 5000);
                
                // Add blink effect
                serverCard.classList.add('blink-failed');
                setTimeout(() => {
                    if (serverCard) serverCard.classList.remove('blink-failed');
                }, 1000);
            }
            if (testWrapper) {
                testWrapper.classList.add('test-failed');
                // Add tooltip to test wrapper with auto-hide
                autoHideTooltip(testWrapper, `❌ ${errorMessage}`, 'top', 5000);
                
                // Add blink effect for icon
                icon.classList.add('blink-icon');
                setTimeout(() => {
                    icon.classList.remove('blink-icon');
                }, 1000);
            }
            // Change icon color to red (permanent until next test)
            icon.style.color = '#ef4444';
            icon.classList.add('connection-tested-failed');
            
            // Add tooltip to icon with auto-hide
            autoHideTooltip(icon, `❌ ${errorMessage}`, 'top', 5000);
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
            // Add error tooltip with auto-hide
            autoHideTooltip(serverCard, `❌ Connection test failed: ${errorMessage}`, 'top', 5000);
            
            // Add blink effect
            serverCard.classList.add('blink-failed');
            setTimeout(() => {
                if (serverCard) serverCard.classList.remove('blink-failed');
            }, 1000);
        }
        if (testWrapper) {
            testWrapper.classList.add('test-failed');
            // Add tooltip to test wrapper with auto-hide
            autoHideTooltip(testWrapper, `❌ ${errorMessage}`, 'top', 5000);
            
            // Add blink effect for icon
            icon.classList.add('blink-icon');
            setTimeout(() => {
                icon.classList.remove('blink-icon');
            }, 1000);
        }
        // Change icon color to red (permanent until next test)
        icon.style.color = '#ef4444';
        icon.classList.add('connection-tested-failed');
        
        // Add tooltip to icon with auto-hide
        autoHideTooltip(icon, `❌ ${errorMessage}`, 'top', 5000);
        
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

    // Keep the "Last updated" label fresh without re-fetching the list
    updateSshLastUpdatedLabel();
    if (sshLastUpdatedTimer) clearInterval(sshLastUpdatedTimer);
    sshLastUpdatedTimer = setInterval(updateSshLastUpdatedLabel, 30000);

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

/* ============================================================
 * SSL INSTALLATION (Let's Encrypt / Paid SSL)
 * ============================================================ */
let sslContext = { host: '', hostname: '', user: '', identityFile: '', port: 22 };
let sslModalInstance = null;

function openSslInstallModal(host, hostname, user, identityFile, port) {
    sslContext = { host: host, hostname: hostname, user: user, identityFile: identityFile, port: port || 22 };
    document.getElementById('sslServerLabel').textContent = hostname || host;
    resetSslInstallForm();
    const modalEl = document.getElementById('sslInstallModal');
    sslModalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
    sslModalInstance.show();
}

function resetSslInstallForm() {
    document.getElementById('sslLeDomain').value = '';
    document.getElementById('sslLeDocroot').value = '';
    document.getElementById('sslPaidDomain').value = '';
    document.getElementById('sslPaidDocroot').value = '';
    ['cert', 'key', 'chain'].forEach(f => {
        const cap = f.charAt(0).toUpperCase() + f.slice(1);
        const fileEl = document.getElementById('ssl' + cap + 'File');
        const textEl = document.getElementById('ssl' + cap + 'Text');
        if (fileEl) fileEl.value = '';
        if (textEl) textEl.value = '';
        setSslInputMode(f, 'file');
    });
    // Always start on the Let's Encrypt tab
    new bootstrap.Tab(document.getElementById('sslTabFree')).show();
    hideSslOutput();
    setSslBusy(false);
}

function setSslInputMode(field, mode) {
    const cap = field.charAt(0).toUpperCase() + field.slice(1);
    const fileEl = document.getElementById('ssl' + cap + 'File');
    const textEl = document.getElementById('ssl' + cap + 'Text');
    const fileBtn = document.getElementById('ssl' + cap + 'ModeFileBtn');
    const textBtn = document.getElementById('ssl' + cap + 'ModeTextBtn');
    if (!fileEl || !textEl || !fileBtn || !textBtn) return;
    const isFile = mode === 'file';
    fileEl.style.display = isFile ? '' : 'none';
    textEl.style.display = isFile ? 'none' : '';
    fileBtn.classList.toggle('active', isFile);
    textBtn.classList.toggle('active', !isFile);
}

async function readSslField(field) {
    const cap = field.charAt(0).toUpperCase() + field.slice(1);
    const fileEl = document.getElementById('ssl' + cap + 'File');
    const textEl = document.getElementById('ssl' + cap + 'Text');
    const isFile = fileEl.style.display !== 'none';
    if (isFile) {
        if (!fileEl.files || !fileEl.files[0]) return null;
        return await fileEl.files[0].text();
    }
    const t = (textEl.value || '').trim();
    return t === '' ? null : t;
}

function showSslOutput() {
    document.getElementById('sslInstallOutput').style.display = 'block';
}

function hideSslOutput() {
    const el = document.getElementById('sslInstallOutput');
    el.style.display = 'none';
    el.innerHTML = '';
}

function setSslBusy(busy) {
    const le = document.getElementById('sslLeSubmitBtn');
    const paid = document.getElementById('sslPaidSubmitBtn');
    if (le) le.disabled = busy;
    if (paid) paid.disabled = busy;
    document.getElementById('sslInstallProgress').style.display = busy ? 'block' : 'none';
}

function renderSslSteps(steps) {
    const icons = {
        ok: 'bi-check-circle-fill text-success',
        error: 'bi-x-circle-fill text-danger',
        running: 'bi-hourglass-split text-warning',
        info: 'bi-info-circle text-primary'
    };
    showSslOutput();
    const box = document.getElementById('sslInstallOutput');
    box.innerHTML = '<div class="ssl-steps">' + steps.map(s => `
        <div class="ssl-step ssl-step-${escapeHtml(s.status)}">
            <div class="ssl-step-title"><i class="bi ${icons[s.status] || icons.info} me-2"></i>${escapeHtml(s.name)}</div>
            ${s.output ? `<pre class="ssl-step-output">${escapeHtml(s.output)}</pre>` : ''}
        </div>`).join('') + '</div>';
}

function normalizeSslDocroot(path) {
    if (!path) return null;
    const p = path.replace(/\/+$/, '');
    if (!p.startsWith('/') || p.includes('..') || /\s/.test(p) || !/^\/[A-Za-z0-9._\-]+(\/[A-Za-z0-9._\-]+)*$/.test(p)) return null;
    return p;
}

/* ---------- SSL directory picker (browse the server's folders) ---------- */
let sslDirPicker = { target: null, path: '/var/www' };
let sslDirPickerModalInstance = null;

function openSslDirPicker(targetId) {
    if (!sslContext.host) {
        showToast('Open the SSL modal for a server first', 'warning');
        return;
    }
    sslDirPicker.target = targetId;
    const current = normalizeSslDocroot((document.getElementById(targetId).value || '').trim());
    sslDirPicker.path = current || '/var/www';
    document.getElementById('sslDirPickerServer').textContent = sslContext.hostname || sslContext.host;

    const modalEl = document.getElementById('sslDirPickerModal');
    if (!modalEl.dataset.pickerBound) {
        modalEl.dataset.pickerBound = '1';
        modalEl.addEventListener('show.bs.modal', () => document.body.classList.add('ssl-picker-open'));
        modalEl.addEventListener('hidden.bs.modal', () => document.body.classList.remove('ssl-picker-open'));
    }
    if (!sslDirPickerModalInstance) {
        sslDirPickerModalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
    }
    sslDirPickerModalInstance.show();
    loadSslDirPicker();
}

function loadSslDirPicker(path) {
    if (typeof path === 'string' && path) sslDirPicker.path = path;
    const listEl = document.getElementById('sslDirList');
    renderSslDirCrumb();
    listEl.innerHTML = '<div class="text-center py-4 text-muted small"><span class="spinner-border spinner-border-sm me-2"></span>Loading directories…</div>';

    fetch('/ssh/explorer/list', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({
            host: sslContext.host,
            hostname: sslContext.hostname,
            username: sslContext.user,
            identity_file: sslContext.identityFile,
            port: sslContext.port,
            path: sslDirPicker.path
        })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) throw new Error(data.message || 'Unable to read the directory');
        if (data.path) sslDirPicker.path = data.path;
        renderSslDirCrumb();
        renderSslDirList(data.entries || []);
    })
    .catch(err => {
        listEl.innerHTML = '<div class="text-center py-4 text-danger small"><i class="bi bi-x-circle me-2"></i>' + escapeHtml(err.message || 'Failed to list the directory') + '</div>';
    });
}

function renderSslDirList(entries) {
    const listEl = document.getElementById('sslDirList');
    const dirs = (entries || []).filter(e => e.is_dir);
    if (!dirs.length) {
        listEl.innerHTML = '<div class="text-center py-4 text-muted small"><i class="bi bi-folder2 me-2"></i>No subdirectories in this folder.</div>';
        return;
    }
    listEl.innerHTML = dirs.map(d => {
        const full = joinSslDirPath(sslDirPicker.path, d.name);
        return '<div class="ssl-dir-row" data-path="' + escapeHtml(full) + '" title="' + escapeHtml(full) + '">' +
            '<i class="bi bi-folder-fill"></i>' +
            '<span class="ssl-dir-name">' + escapeHtml(d.name) + '</span>' +
            '<i class="bi bi-chevron-right ms-auto ssl-dir-go"></i>' +
        '</div>';
    }).join('');
    listEl.querySelectorAll('.ssl-dir-row').forEach(row => {
        row.addEventListener('click', () => loadSslDirPicker(row.dataset.path));
    });
}

function joinSslDirPath(base, name) {
    return base === '/' ? '/' + name : base.replace(/\/+$/, '') + '/' + name;
}

function upSslDirPath() {
    const p = (sslDirPicker.path || '/').replace(/\/+$/, '');
    const idx = p.lastIndexOf('/');
    return idx <= 0 ? '/' : p.slice(0, idx);
}

function renderSslDirCrumb() {
    const crumb = document.getElementById('sslDirCrumb');
    if (!crumb) return;
    crumb.innerHTML = '';
    crumb.title = sslDirPicker.path;
    const addSeg = (label, path) => {
        const a = document.createElement('a');
        a.href = '#';
        a.textContent = label;
        a.className = 'ssl-dir-crumb-seg';
        a.addEventListener('click', ev => { ev.preventDefault(); loadSslDirPicker(path); });
        crumb.appendChild(a);
    };
    addSeg('/', '/');
    let acc = '';
    sslDirPicker.path.split('/').forEach(seg => {
        if (!seg) return;
        acc += '/' + seg;
        const sep = document.createElement('span');
        sep.className = 'ssl-dir-crumb-sep';
        sep.textContent = ' / ';
        crumb.appendChild(sep);
        addSeg(seg, acc);
    });
}

function confirmSslDirPick() {
    if (!sslDirPicker.target) return;
    document.getElementById(sslDirPicker.target).value = sslDirPicker.path;
    if (sslDirPickerModalInstance) sslDirPickerModalInstance.hide();
    showToast('Directory selected: ' + sslDirPicker.path, 'success');
}

async function submitLetsEncryptSsl() {
    const domain = (document.getElementById('sslLeDomain').value || '').trim();
    if (!domain) {
        showToast('Please enter a domain name', 'warning');
        return;
    }
    const docroot = normalizeSslDocroot((document.getElementById('sslLeDocroot').value || '').trim());
    if (!docroot) {
        showToast('Please enter a valid absolute directory path (e.g. /var/www/your-project/public)', 'warning');
        return;
    }
    if (!confirm(`Install a free Let's Encrypt SSL for "${domain}"?\n\nDirectory: ${docroot}\n\nThe DNS A record of the domain will be verified against this server's public IP first.`)) {
        return;
    }

    hideSslOutput();
    setSslBusy(true);
    try {
        const res = await fetch('/ssh/ssl/install-letsencrypt', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({
                host: sslContext.host,
                hostname: sslContext.hostname,
                username: sslContext.user,
                identity_file: sslContext.identityFile,
                port: sslContext.port,
                domain: domain,
                docroot: docroot
            })
        });
        const data = await res.json();
        if (data.steps) renderSslSteps(data.steps);
        showToast(data.message || (data.success ? "Let's Encrypt SSL installed successfully" : 'SSL installation failed'), data.success ? 'success' : 'danger');
    } catch (e) {
        console.error("Let's Encrypt SSL error:", e);
        showToast('Request failed: ' + e.message, 'danger');
    } finally {
        setSslBusy(false);
    }
}

async function submitPaidSsl() {
    const domain = (document.getElementById('sslPaidDomain').value || '').trim();
    if (!domain) {
        showToast('Please enter a domain name', 'warning');
        return;
    }
    const docroot = normalizeSslDocroot((document.getElementById('sslPaidDocroot').value || '').trim());
    if (!docroot) {
        showToast('Please enter a valid absolute directory path (e.g. /var/www/your-project/public)', 'warning');
        return;
    }

    const cert = await readSslField('cert');
    const key = await readSslField('key');
    const chain = await readSslField('chain');

    if (!cert) { showToast('Public certificate is required (upload a file or paste the text)', 'warning'); return; }
    if (!key) { showToast('Private key is required (upload a file or paste the text)', 'warning'); return; }
    if (!cert.includes('BEGIN CERTIFICATE')) { showToast('The public certificate does not look like a PEM certificate', 'danger'); return; }
    if (!key.includes('PRIVATE KEY')) { showToast('The private key does not look like a PEM private key', 'danger'); return; }

    if (!confirm(`Install the paid SSL for "${domain}"?\n\nDirectory: ${docroot}\n\nThe certificate/key pair is verified first — nothing is uploaded if they do not match.`)) {
        return;
    }

    hideSslOutput();
    setSslBusy(true);
    try {
        const fd = new FormData();
        fd.append('host', sslContext.host);
        fd.append('hostname', sslContext.hostname);
        fd.append('username', sslContext.user);
        fd.append('identity_file', sslContext.identityFile);
        fd.append('port', sslContext.port);
        fd.append('domain', domain);
        fd.append('docroot', docroot);
        // Files are read client-side and sent as text
        fd.append('cert_text', cert);
        fd.append('key_text', key);
        if (chain) fd.append('chain_text', chain);

        const res = await fetch('/ssh/ssl/install-paid', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: fd
        });
        const data = await res.json();
        if (data.steps) renderSslSteps(data.steps);
        showToast(data.message || (data.success ? 'Paid SSL installed successfully' : 'Paid SSL installation failed'), data.success ? 'success' : 'danger');
    } catch (e) {
        console.error('Paid SSL error:', e);
        showToast('Request failed: ' + e.message, 'danger');
    } finally {
        setSslBusy(false);
    }
}
