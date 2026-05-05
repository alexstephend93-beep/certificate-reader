@extends('layouts.app')

@section('title', 'API Tester | HTTP Request Debugger')

@section('styles')
<style>
    .method-selector {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }
    
    .method-btn {
        border-radius: 40px;
        padding: 8px 20px;
        font-weight: 600;
        transition: var(--transition-smooth);
        cursor: pointer;
        text-align: center;
        background: rgba(0,0,0,0.05);
        border: 2px solid transparent;
    }
    
    .method-btn.GET { color: #10b981; border-color: #10b981; }
    .method-btn.POST { color: #3b82f6; border-color: #3b82f6; }
    .method-btn.PUT { color: #f59e0b; border-color: #f59e0b; }
    .method-btn.PATCH { color: #8b5cf6; border-color: #8b5cf6; }
    .method-btn.DELETE { color: #ef4444; border-color: #ef4444; }
    
    .method-btn.active.GET { background: #10b981; color: white; }
    .method-btn.active.POST { background: #3b82f6; color: white; }
    .method-btn.active.PUT { background: #f59e0b; color: white; }
    .method-btn.active.PATCH { background: #8b5cf6; color: white; }
    .method-btn.active.DELETE { background: #ef4444; color: white; }
    
    .method-btn:hover { transform: translateY(-2px); }
    
    /* Enhanced Submit Button */
    .submit-btn-wrapper {
        position: relative;
        display: inline-block;
        width: 100%;
    }
    
    .submit-btn-enhanced {
        background: var(--gradient-primary);
        border: none;
        border-radius: 60px;
        padding: 18px 35px;
        font-weight: 700;
        font-size: 1.1rem;
        color: white;
        transition: all 0.3s ease;
        width: 100%;
        position: relative;
        overflow: hidden;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        box-shadow: 0 10px 30px rgba(99, 102, 241, 0.3);
    }
    
    .submit-btn-enhanced::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.3);
        transform: translate(-50%, -50%);
        transition: width 0.6s, height 0.6s;
    }
    
    .submit-btn-enhanced:hover::before {
        width: 300px;
        height: 300px;
    }
    
    .submit-btn-enhanced:hover {
        transform: translateY(-3px);
        box-shadow: 0 20px 40px rgba(99, 102, 241, 0.4);
    }
    
    .submit-btn-enhanced:active {
        transform: translateY(0);
    }
    
    .submit-btn-enhanced i {
        font-size: 1.3rem;
        transition: transform 0.3s ease;
    }
    
    .submit-btn-enhanced:hover i {
        transform: translateX(5px);
    }
    
    /* Loading state */
    .submit-btn-enhanced.loading {
        pointer-events: none;
        opacity: 0.7;
    }
    
    .submit-btn-enhanced.loading .btn-text {
        display: none;
    }
    
    .submit-btn-enhanced.loading .loading-spinner {
        display: inline-block;
    }
    
    .loading-spinner {
        display: none;
        width: 20px;
        height: 20px;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-top-color: white;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    .response-card {
        background: var(--color-dark);
        border-radius: 20px;
        padding: 20px;
        margin-top: 30px;
        position: relative;
    }
    
    .status-badge {
        display: inline-block;
        padding: 6px 16px;
        border-radius: 40px;
        font-weight: 600;
        font-size: 0.85rem;
    }
    
    .status-success { background: #10b981; color: white; }
    .status-error { background: #ef4444; color: white; }
    .status-warning { background: #f59e0b; color: white; }
    
    .json-preview {
        background: #1e1e2e;
        border-radius: 12px;
        padding: 15px;
        max-height: 500px;
        overflow: auto;
        font-family: 'Courier New', monospace;
        font-size: 13px;
        color: #e2e8f0;
        position: relative;
    }
    
    .json-preview pre {
        margin: 0;
        white-space: pre-wrap;
        word-wrap: break-word;
    }
    
    /* Copy Button Styles */
    .copy-response-btn {
        position: absolute;
        top: 15px;
        right: 15px;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 10px;
        padding: 8px 15px;
        font-size: 0.8rem;
        color: white;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
        backdrop-filter: blur(10px);
        z-index: 10;
    }
    
    .copy-response-btn:hover {
        background: var(--color-primary);
        border-color: var(--color-primary);
        transform: scale(1.05);
    }
    
    .copy-response-btn.copied {
        background: #10b981;
        border-color: #10b981;
    }
    
    .copy-all-btn {
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 10px;
        padding: 8px 15px;
        font-size: 0.85rem;
        color: white;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .copy-all-btn:hover {
        background: var(--color-primary);
        transform: translateY(-2px);
    }
    
    .headers-table {
        width: 100%;
        font-size: 0.85rem;
    }
    
    .headers-table td {
        padding: 8px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    
    .dynamic-header-row {
        background: white;
        padding: 12px;
        border-radius: 12px;
        margin-bottom: 10px;
        border: 1px solid rgba(0,0,0,0.1);
    }
    
    .curl-command {
        background: #1e1e2e;
        border-radius: 12px;
        padding: 15px;
        color: #e2e8f0;
        font-family: 'Courier New', monospace;
        font-size: 12px;
        overflow-x: auto;
        white-space: pre-wrap;
        word-break: break-all;
        position: relative;
    }
    
    .copy-curl-btn {
        position: absolute;
        top: 10px;
        right: 10px;
        background: rgba(255, 255, 255, 0.1);
        border: none;
        border-radius: 8px;
        padding: 5px 12px;
        font-size: 0.75rem;
        color: white;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .copy-curl-btn:hover {
        background: var(--color-primary);
    }
    
    .url-input {
        font-family: monospace;
        font-size: 1rem;
    }
    
    .response-actions {
        display: flex;
        gap: 10px;
        margin-bottom: 15px;
        flex-wrap: wrap;
    }
    
    /* Toast Notification */
    .toast-notification {
        position: fixed;
        bottom: 30px;
        right: 30px;
        background: #10b981;
        color: white;
        padding: 12px 24px;
        border-radius: 50px;
        font-weight: 500;
        z-index: 9999;
        animation: slideInRight 0.3s ease;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    }
    
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
</style>
@endsection

@section('content')
<div class="glass-card" data-aos="fade-down">
    <div class="gradient-header text-center">
        <h1 class="fw-bold">
            <i class="bi bi-bug-fill me-3"></i>
            API Tester
        </h1>
        <p class="lead mb-0">
            <i class="bi bi-lightning-charge me-2"></i>
            Test and debug HTTP requests with ease
        </p>
    </div>

    <div class="p-4 p-md-5">
        <!-- Request Form -->
        <form id="apiForm" method="POST" action="{{ url('/api-tester/send') }}">
            @csrf
            
            <!-- URL Input -->
            <div class="mb-4">
                <label class="fw-bold mb-2 d-flex align-items-center gap-2">
                    <i class="bi bi-link-45deg"></i> Request URL
                </label>
                <input type="url" 
                       class="form-control form-control-lg url-input" 
                       id="url" 
                       name="url" 
                       placeholder="https://api.example.com/users"
                       required
                       style="border-radius: 15px;">
            </div>
            
            <!-- Method Selector -->
            <div class="mb-4">
                <label class="fw-bold mb-2 d-flex align-items-center gap-2">
                    <i class="bi bi-lightning"></i> HTTP Method
                </label>
                <div class="method-selector">
                    <div class="method-btn GET active" data-method="GET">GET</div>
                    <div class="method-btn POST" data-method="POST">POST</div>
                    <div class="method-btn PUT" data-method="PUT">PUT</div>
                    <div class="method-btn PATCH" data-method="PATCH">PATCH</div>
                    <div class="method-btn DELETE" data-method="DELETE">DELETE</div>
                </div>
                <input type="hidden" name="method" id="method" value="GET">
            </div>
            
            <!-- Headers Section -->
            <div class="mb-4">
                <label class="fw-bold mb-2 d-flex align-items-center gap-2">
                    <i class="bi bi-tags"></i> Headers
                </label>
                <div id="headersContainer">
                    <div class="dynamic-header-row row g-2">
                        <div class="col-md-5">
                            <input type="text" class="form-control" name="headers[0][key]" placeholder="Header Name (e.g., Content-Type)">
                        </div>
                        <div class="col-md-5">
                            <input type="text" class="form-control" name="headers[0][value]" placeholder="Header Value">
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-danger btn-sm w-100 remove-header">
                                <i class="bi bi-trash"></i> Remove
                            </button>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="addHeaderBtn">
                    <i class="bi bi-plus-circle"></i> Add Header
                </button>
            </div>
            
            <!-- Authentication -->
            <div class="mb-4">
                <label class="fw-bold mb-2 d-flex align-items-center gap-2">
                    <i class="bi bi-shield-lock"></i> Authentication
                </label>
                <select class="form-select mb-2" id="authType" name="auth_type" style="border-radius: 10px;">
                    <option value="none">None</option>
                    <option value="bearer">Bearer Token</option>
                    <option value="basic">Basic Auth</option>
                </select>
                
                <div id="bearerAuth" style="display: none;">
                    <input type="text" class="form-control mt-2" name="auth_token" placeholder="Bearer Token">
                </div>
                
                <div id="basicAuth" style="display: none;">
                    <div class="row g-2 mt-2">
                        <div class="col-md-6">
                            <input type="text" class="form-control" name="auth_username" placeholder="Username">
                        </div>
                        <div class="col-md-6">
                            <input type="password" class="form-control" name="auth_password" placeholder="Password">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Request Body -->
            <div class="mb-4" id="bodySection">
                <label class="fw-bold mb-2 d-flex align-items-center gap-2">
                    <i class="bi bi-file-text"></i> Request Body
                </label>
                <select class="form-select mb-2" id="bodyType" name="body_type" style="border-radius: 10px;">
                    <option value="none">None</option>
                    <option value="json">JSON</option>
                    <option value="form">Form Data (x-www-form-urlencoded)</option>
                    <option value="raw">Raw Text</option>
                </select>
                
                <textarea class="form-control" 
                          id="body" 
                          name="body" 
                          rows="6" 
                          placeholder='{
    "key": "value"
}' 
                          style="font-family: monospace; border-radius: 12px;"></textarea>
            </div>
            
            <!-- Advanced Options -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="fw-bold mb-2 d-flex align-items-center gap-2">
                        <i class="bi bi-clock"></i> Timeout (seconds)
                    </label>
                    <input type="number" class="form-control" name="timeout" value="30" min="1" max="120" style="border-radius: 10px;">
                </div>
                <div class="col-md-6">
                    <label class="fw-bold mb-2 d-flex align-items-center gap-2">
                        <i class="bi bi-arrow-repeat"></i> Follow Redirects
                    </label>
                    <select class="form-select" name="follow_redirects" style="border-radius: 10px;">
                        <option value="1">Yes</option>
                        <option value="0">No</option>
                    </select>
                </div>
            </div>
            
            <!-- Enhanced Submit Button -->
            <div class="submit-btn-wrapper">
                <button type="submit" class="submit-btn-enhanced" id="submitBtn">
                    <i class="bi bi-send-fill"></i>
                    <span class="btn-text">Send Request</span>
                    <span class="loading-spinner"></span>
                    <i class="bi bi-arrow-right-short fs-3"></i>
                </button>
            </div>
        </form>
        
        <!-- Response Section -->
        <div id="responseSection" style="display: none;" class="mt-4"></div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    let headerIndex = 1;
    let currentResponseData = null;
    
    // Method selector
    document.querySelectorAll('.method-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.method-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('method').value = this.dataset.method;
        });
    });
    
    // Add header
    document.getElementById('addHeaderBtn').addEventListener('click', function() {
        const container = document.getElementById('headersContainer');
        const newRow = document.createElement('div');
        newRow.className = 'dynamic-header-row row g-2';
        newRow.innerHTML = `
            <div class="col-md-5">
                <input type="text" class="form-control" name="headers[${headerIndex}][key]" placeholder="Header Name">
            </div>
            <div class="col-md-5">
                <input type="text" class="form-control" name="headers[${headerIndex}][value]" placeholder="Header Value">
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-danger btn-sm w-100 remove-header">
                    <i class="bi bi-trash"></i> Remove
                </button>
            </div>
        `;
        container.appendChild(newRow);
        headerIndex++;
        
        newRow.querySelector('.remove-header').addEventListener('click', function() {
            newRow.remove();
        });
    });
    
    // Remove header (initial)
    document.querySelectorAll('.remove-header').forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.dynamic-header-row').remove();
        });
    });
    
    // Auth toggle
    document.getElementById('authType').addEventListener('change', function() {
        document.getElementById('bearerAuth').style.display = 'none';
        document.getElementById('basicAuth').style.display = 'none';
        
        if (this.value === 'bearer') {
            document.getElementById('bearerAuth').style.display = 'block';
        } else if (this.value === 'basic') {
            document.getElementById('basicAuth').style.display = 'block';
        }
    });
    
    // Body type hint
    document.getElementById('bodyType').addEventListener('change', function() {
        const textarea = document.getElementById('body');
        if (this.value === 'json') {
            textarea.placeholder = '{\n    "key": "value"\n}';
        } else if (this.value === 'form') {
            textarea.placeholder = 'key1=value1&key2=value2';
        } else {
            textarea.placeholder = 'Enter raw text...';
        }
    });
    
    // Show toast notification
    function showToast(message, isError = false) {
        const toast = document.createElement('div');
        toast.className = 'toast-notification';
        toast.style.background = isError ? '#ef4444' : '#10b981';
        toast.innerHTML = `<i class="bi ${isError ? 'bi-exclamation-triangle' : 'bi-check-circle'} me-2"></i>${message}`;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 3000);
    }
    
    // Copy to clipboard function
    async function copyToClipboard(text, label = 'Copied') {
        try {
            await navigator.clipboard.writeText(text);
            showToast(`${label} to clipboard!`);
            return true;
        } catch (err) {
            showToast('Failed to copy', true);
            return false;
        }
    }
    
    // Copy entire response
    function copyFullResponse() {
        if (!currentResponseData) return;
        
        let fullResponse = `STATUS: ${currentResponseData.status_code} ${currentResponseData.reason_phrase}\n`;
        fullResponse += `TIME: ${currentResponseData.elapsed_ms}ms\n`;
        fullResponse += `SIZE: ${formatBytes(currentResponseData.size_bytes)}\n`;
        fullResponse += `\n--- HEADERS ---\n`;
        
        for (const [key, value] of Object.entries(currentResponseData.headers)) {
            fullResponse += `${key}: ${value}\n`;
        }
        
        fullResponse += `\n--- BODY ---\n`;
        fullResponse += currentResponseData.body_pretty || currentResponseData.body_raw;
        
        copyToClipboard(fullResponse, 'Full response copied');
    }
    
    function formatBytes(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
    
    function displayResponse(data) {
        currentResponseData = data;
        const responseSection = document.getElementById('responseSection');
        
        let statusClass = 'status-success';
        if (data.status_code >= 400) statusClass = 'status-error';
        else if (data.status_code >= 300) statusClass = 'status-warning';
        
        const html = `
            <div class="response-card" data-aos="fade-up">
                <div class="response-actions">
                    <button class="copy-all-btn" onclick="copyFullResponse()">
                        <i class="bi bi-clipboard-data"></i> Copy Full Response
                    </button>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div>
                        <span class="status-badge ${statusClass}">
                            <i class="bi ${data.status_code >= 400 ? 'bi-exclamation-triangle' : 'bi-check-circle'}"></i>
                            ${data.status_code} ${data.reason_phrase}
                        </span>
                    </div>
                    <div class="text-muted small">
                        <i class="bi bi-stopwatch"></i> ${data.elapsed_ms}ms &nbsp;|&nbsp;
                        <i class="bi bi-database"></i> ${formatBytes(data.size_bytes)}
                    </div>
                </div>
                
                <div class="mb-3">
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#responseHeaders">
                        <i class="bi bi-tags"></i> Response Headers (${Object.keys(data.headers).length})
                    </button>
                    <div class="collapse mt-2" id="responseHeaders">
                        <div class="table-responsive">
                            <table class="headers-table">
                                ${Object.entries(data.headers).map(([k, v]) => `
                                    <tr>
                                        <td><strong>${escapeHtml(k)}</strong></td>
                                        <td>${escapeHtml(v)}</td>
                                    </tr>
                                `).join('')}
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong>Response Body:</strong>
                    </div>
                    <div class="json-preview">
                        <button class="copy-response-btn" onclick="copyToClipboard(document.getElementById('responseBodyText').textContent, 'Response body copied')">
                            <i class="bi bi-clipboard"></i> Copy Body
                        </button>
                        <pre id="responseBodyText">${escapeHtml(data.body_pretty || data.body_raw)}</pre>
                    </div>
                </div>
                
                <div>
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#curlCommand">
                        <i class="bi bi-terminal"></i> Show cURL Command
                    </button>
                    <div class="collapse mt-2" id="curlCommand">
                        <div class="curl-command">
                            <button class="copy-curl-btn" onclick="copyToClipboard(${JSON.stringify(data.curl_command)}, 'cURL command copied')">
                                <i class="bi bi-clipboard"></i> Copy
                            </button>
                            <pre style="margin:0; white-space:pre-wrap;">${escapeHtml(data.curl_command)}</pre>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        responseSection.innerHTML = html;
        responseSection.style.display = 'block';
        
        // Initialize Bootstrap collapse
        const collapseElements = document.querySelectorAll('.collapse');
        collapseElements.forEach(el => {
            new bootstrap.Collapse(el, { toggle: false });
        });
        
        // Scroll to response
        responseSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    
    function displayError(message) {
        currentResponseData = null;
        const responseSection = document.getElementById('responseSection');
        responseSection.innerHTML = `
            <div class="response-card" data-aos="fade-up">
                <div class="d-flex align-items-center gap-3 text-danger">
                    <i class="bi bi-exclamation-triangle-fill fs-1"></i>
                    <div>
                        <h5 class="fw-bold mb-1">Request Failed</h5>
                        <p class="mb-0">${escapeHtml(message)}</p>
                    </div>
                </div>
            </div>
        `;
        responseSection.style.display = 'block';
    }
    
    // Form submission with loading state
    document.getElementById('apiForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const submitBtn = document.getElementById('submitBtn');
        const responseSection = document.getElementById('responseSection');
        
        // Add loading state
        submitBtn.classList.add('loading');
        submitBtn.disabled = true;
        responseSection.style.display = 'none';
        responseSection.innerHTML = '';
        
        const formData = new FormData(this);
        
        try {
            const response = await fetch(this.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                    'Accept': 'application/json'
                },
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                displayResponse(data);
            } else {
                displayError(data.error || 'Request failed');
            }
        } catch (error) {
            displayError('Network error: ' + error.message);
        } finally {
            // Remove loading state
            submitBtn.classList.remove('loading');
            submitBtn.disabled = false;
        }
    });
</script>
@endsection