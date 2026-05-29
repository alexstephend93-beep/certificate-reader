@extends('layouts.app')

@section('title', 'SSL Certificate & Key Matcher')

@section('styles')
<style>
    .ssl-matcher-container {
        max-width: 1400px;
        margin: 0 auto;
    }
    
    .match-tabs {
        overflow: visible !important;
        display: flex;
        gap: 10px;
        margin-bottom: 25px;
        flex-wrap: wrap;
    }
    
    .match-tab {
        padding: 12px 24px;
        background: rgba(0, 0, 0, 0.3);
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-radius: 12px;
        color: white;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        white-space: nowrap;
        flex-shrink: 0;
        text-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);
    }
    
    .match-tab:hover {
        background: rgba(0, 0, 0, 0.4);
        border-color: rgba(255, 255, 255, 0.5);
    }
    
    .match-tab.active {
        background: var(--gradient-primary, linear-gradient(135deg, #667eea, #764ba2));
        border-color: transparent;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        transform: translateY(-2px);
        color: white;
    }
    
    .panel {
        display: none;
        animation: fadeIn 0.4s ease;
        min-height: 200px;
    }
    
    .panel.active {
        display: block;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .file-input-group {
        display: flex;
        gap: 15px;
        align-items: center;
        margin-bottom: 15px;
        flex-wrap: wrap;
    }
    
    .file-input-group .form-control {
        flex: 1;
    }
    
    .result-card {
        background: white;
        border-radius: 20px;
        padding: 25px;
        margin-top: 30px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        border: 1px solid #e2e8f0;
    }
    
    .match-badge {
        display: inline-block;
        padding: 8px 20px;
        border-radius: 30px;
        font-weight: 700;
        font-size: 1.1rem;
    }
    
    .match-success {
        background: #d1fae5;
        color: #059669;
    }
    
    .match-fail {
        background: #fee2e2;
        color: #dc2626;
    }
    
    .detail-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    
    .detail-card {
        background: #f8fafc;
        border-radius: 16px;
        padding: 20px;
        border: 1px solid #e2e8f0;
    }
    
    .detail-card h4 {
        font-size: 1rem;
        font-weight: 700;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid var(--color-primary);
        display: flex;
        align-items: center;
        gap: 8px;
        white-space: nowrap;
        flex-shrink: 0;
        white-space: nowrap;
        flex-shrink: 0;
    }
    
    .detail-row {
        display: flex;
        margin-bottom: 10px;
        font-size: 0.85rem;
    }
    
    .detail-label {
        width: 130px;
        font-weight: 600;
        color: #475569;
        flex-shrink: 0;
    }
    
    .detail-value {
        color: #1e293b;
        word-break: break-all;
        flex: 1;
    }
    
    .fingerprint {
        font-family: monospace;
        font-size: 0.75rem;
        background: #1e1e2e;
        color: #a5f3c3;
        padding: 8px 12px;
        border-radius: 8px;
        word-break: break-all;
    }
    
    .btn-clear {
        background: #64748b;
        border: none;
        border-radius: 10px;
        padding: 8px 20px;
        color: white;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .btn-clear:hover {
        background: #475569;
    }
    
    .commands-section {
        background: #1e1e2e;
        border-radius: 16px;
        padding: 20px;
        margin-top: 30px;
    }
    
    .commands-section h4 {
        color: #e2e8f0;
        margin-bottom: 15px;
    }
    
    .command-item {
        background: #2d2d3d;
        border-radius: 10px;
        padding: 12px 15px;
        margin-bottom: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .command-text {
        font-family: monospace;
        color: #a5f3c3;
        font-size: 0.8rem;
        word-break: break-all;
    }
    
    .loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 9999;
        display: none;
        align-items: center;
        justify-content: center;
    }
    
    .loading-spinner {
        width: 50px;
        height: 50px;
        border: 3px solid rgba(255, 255, 255, 0.3);
        border-top-color: var(--color-primary);
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    @media (max-width: 768px) {
        .detail-row {
            flex-direction: column;
        }
        .detail-label {
            width: 100%;
            margin-bottom: 5px;
        }
        .file-input-group {
            flex-direction: column;
        }
        .file-input-group .btn-outline-secondary {
            width: 100%;
        }
    }
</style>
@endsection

@section('content')
<div class="glass-card" data-aos="fade-down" style="overflow: visible;">
    <div class="gradient-header text-center">
        <h1 class="fw-bold">
            <i class="bi bi-shield-lock-fill me-3"></i>
            SSL Certificate & Key Matcher
        </h1>
        <p class="lead mb-0">
            <i class="bi bi-diagram-3 me-2"></i>
            Verify if your certificates, private keys, and public keys match
        </p>
    </div>

    <div class="p-4 p-md-5" style="overflow: visible;">
        <div class="ssl-matcher-container">
            <!-- Tab Buttons -->
            <div class="match-tabs">
                <button class="match-tab active" data-tab="cert-key">
                    <i class="bi bi-file-lock-fill me-2"></i>Cert + Private Key
                </button>
                <button class="match-tab" data-tab="cert-public">
                    <i class="bi bi-file-text-fill me-2"></i>Cert + Public Key
                </button>
                <button class="match-tab" data-tab="certs">
                    <i class="bi bi-files me-2"></i>Certificate vs Certificate
                </button>
                <button class="match-tab" data-tab="pub-key">
                    <i class="bi bi-person-bounding-box me-2"></i>Public Key + Private Key
                </button>
            </div>

            <!-- Panel 1: Certificate + Private Key -->
            <div id="panel-cert-key" class="panel active">
                <div class="row g-4">
                    <div class="col-md-6">

                    <div class="glass-card p-4">
                            <h5 class="fw-bold mb-3">
                                <i class="bi bi-file-earmark-lock-fill me-2"></i>
                                SSL Certificate
                            </h5>
                            <div class="file-input-group">
                                <input type="file" id="certFile" class="form-control" accept=".crt,.cer,.pem">
                                <button class="btn btn-outline-secondary" onclick="clearFile('certFile', 'certContent')">
                                    <i class="bi bi-x-circle"></i> Clear
                                </button>
                            </div>
                            <textarea id="certContent" rows="8" class="form-control font-monospace" placeholder="-----BEGIN CERTIFICATE-----
MIIDXTCCAkWgAwIBAgIJAK5...
-----END CERTIFICATE-----"></textarea>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="glass-card p-4">
                            <h5 class="fw-bold mb-3">
                                <i class="bi bi-key-fill me-2"></i>
                                Private Key
                            </h5>
                            <div class="file-input-group">
                                <input type="file" id="keyFile" class="form-control" accept=".key,.pem">
                                <button class="btn btn-outline-secondary" onclick="clearFile('keyFile', 'keyContent')">
                                    <i class="bi bi-x-circle"></i> Clear
                                </button>
                            </div>
                            <input type="password" id="keyPassword" class="form-control mb-3" placeholder="Key Password (if encrypted)">
                            <textarea id="keyContent" rows="8" class="form-control font-monospace" placeholder="-----BEGIN RSA PRIVATE KEY-----
MIIEpAIBAAKCAQEA...
-----END RSA PRIVATE KEY-----"></textarea>
                        </div>
                    </div>
                </div>
                <div class="text-center mt-4">
                    <button class="btn btn-primary btn-lg" onclick="matchCertKey()">
                        <i class="bi bi-check-circle me-2"></i> Check Match
                    </button>
                    <button class="btn btn-secondary btn-lg ms-2" onclick="clearAll()">
                        <i class="bi bi-trash me-2"></i> Clear All
                    </button>
                </div>
            </div>

            <!-- Panel 2: Certificate + Public Key -->
            <div id="panel-cert-public" class="panel">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="glass-card p-4">
                            <h5 class="fw-bold mb-3">
                                <i class="bi bi-file-earmark-lock-fill me-2"></i>
                                SSL Certificate
                            </h5>
                            <div class="file-input-group">
                                <input type="file" id="certFile2" class="form-control" accept=".crt,.cer,.pem">
                                <button class="btn btn-outline-secondary" onclick="clearFile('certFile2', 'certContent2')">
                                    <i class="bi bi-x-circle"></i> Clear
                                </button>
                            </div>
                            <textarea id="certContent2" rows="8" class="form-control font-monospace" placeholder="-----BEGIN CERTIFICATE-----
MIIDXTCCAkWgAwIBAgIJAK5...
-----END CERTIFICATE-----"></textarea>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="glass-card p-4">
                            <h5 class="fw-bold mb-3">
                                <i class="bi bi-unlock-fill me-2"></i>
                                Public Key
                            </h5>
                            <div class="file-input-group">
                                <input type="file" id="pubFile" class="form-control" accept=".pub,.pem,.txt">
                                <button class="btn btn-outline-secondary" onclick="clearFile('pubFile', 'pubContent')">
                                    <i class="bi bi-x-circle"></i> Clear
                                </button>
                            </div>
                            <textarea id="pubContent" rows="8" class="form-control font-monospace" placeholder="-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8A...
-----END PUBLIC KEY-----"></textarea>
                        </div>
                    </div>
                </div>
                <div class="text-center mt-4">
                    <button class="btn btn-primary btn-lg" onclick="matchCertPublic()">
                        <i class="bi bi-check-circle me-2"></i> Check Match
                    </button>
                    <button class="btn btn-secondary btn-lg ms-2" onclick="clearAll()">
                        <i class="bi bi-trash me-2"></i> Clear All
                    </button>
                </div>
            </div>

            <!-- Panel 3: Certificate vs Certificate -->
            <div id="panel-certs" class="panel">
                <div class="row g-4">

                    <div class="col-md-6">
                        <div class="glass-card p-4">
                            <h5 class="fw-bold mb-3">
                                <i class="bi bi-file-earmark-lock-fill me-2"></i>
                                Certificate 1
                            </h5>
                            <div class="file-input-group">
                                <input type="file" id="cert1File" class="form-control" accept=".crt,.cer,.pem">
                                <button class="btn btn-outline-secondary" onclick="clearFile('cert1File', 'cert1Content')">
                                    <i class="bi bi-x-circle"></i> Clear
                                </button>
                            </div>
                            <textarea id="cert1Content" rows="8" class="form-control font-monospace" placeholder="-----BEGIN CERTIFICATE-----
MIIDXTCCAkWgAwIBAgIJAK5...
-----END CERTIFICATE-----"></textarea>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="glass-card p-4">
                            <h5 class="fw-bold mb-3">
                                <i class="bi bi-file-earmark-lock-fill me-2"></i>
                                Certificate 2
                            </h5>
                            <div class="file-input-group">
                                <input type="file" id="cert2File" class="form-control" accept=".crt,.cer,.pem">
                                <button class="btn btn-outline-secondary" onclick="clearFile('cert2File', 'cert2Content')">
                                    <i class="bi bi-x-circle"></i> Clear
                                </button>
                            </div>
                            <textarea id="cert2Content" rows="8" class="form-control font-monospace" placeholder="-----BEGIN CERTIFICATE-----
MIIDXTCCAkWgAwIBAgIJAK5...
-----END CERTIFICATE-----"></textarea>
                        </div>
                    </div>
                </div>
                <div class="text-center mt-4">
                    <button class="btn btn-primary btn-lg" onclick="matchCerts()">
                        <i class="bi bi-check-circle me-2"></i> Compare Certificates
                    </button>
                    <button class="btn btn-secondary btn-lg ms-2" onclick="clearAll()">
                        <i class="bi bi-trash me-2"></i> Clear All
                    </button>
                </div>
            </div>

            <!-- Panel 4: Public Key + Private Key -->
            <div id="panel-pub-key" class="panel">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="glass-card p-4">
                            <h5 class="fw-bold mb-3">
                                <i class="bi bi-unlock-fill me-2"></i>
                                Public Key
                            </h5>
                            <div class="file-input-group">
                                <input type="file" id="pubFile2" class="form-control" accept=".pub,.pem,.txt">
                                <button class="btn btn-outline-secondary" onclick="clearFile('pubFile2', 'pubContent2')">
                                    <i class="bi bi-x-circle"></i> Clear
                                </button>
                            </div>
                            <textarea id="pubContent2" rows="8" class="form-control font-monospace" placeholder="-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8A...
-----END PUBLIC KEY-----"></textarea>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="glass-card p-4">
                            <h5 class="fw-bold mb-3">
                                <i class="bi bi-file-earmark-lock-fill me-2"></i>
                                Private Key
                            </h5>
                            <div class="file-input-group">
                                <input type="file" id="keyFile2" class="form-control" accept=".key,.pem">
                                <button class="btn btn-outline-secondary" onclick="clearFile('keyFile2', 'keyContent2')">
                                    <i class="bi bi-x-circle"></i> Clear
                                </button>
                            </div>
                            <input type="password" id="keyPassword2" class="form-control mb-3" placeholder="Key Password (if encrypted)">
                            <textarea id="keyContent2" rows="8" class="form-control font-monospace" placeholder="-----BEGIN RSA PRIVATE KEY-----
MIIEpAIBAAKCAQEA...
-----END RSA PRIVATE KEY-----"></textarea>
                        </div>
                    </div>
                </div>
                <div class="text-center mt-4">
                    <button class="btn btn-primary btn-lg" onclick="matchPubPriv()">
                        <i class="bi bi-check-circle me-2"></i> Check Match
                    </button>
                    <button class="btn btn-secondary btn-lg ms-2" onclick="clearAll()">
                        <i class="bi bi-trash me-2"></i> Clear All
                    </button>
                </div>
            </div>


            <!-- Results Section -->
            <div id="resultsSection" style="display: none;">
                <div class="result-card">
                    <div class="text-center mb-4">
                        <span id="matchBadge" class="match-badge"></span>
                        <h3 id="matchMessage" class="mt-3"></h3>
                    </div>
                    
                    <div id="matchDetails" class="detail-grid"></div>
                    
                    <div class="text-center mt-4">
                        <button class="btn btn-outline-primary" onclick="copyResults()">
                            <i class="bi bi-clipboard me-2"></i> Copy Results
                        </button>
                    </div>
                </div>
            </div>

            <!-- OpenSSL Commands Reference -->
            <div class="commands-section mt-4">
                <h4><i class="bi bi-terminal-fill me-2"></i> OpenSSL Commands Reference</h4>
                <div id="commandsList">
                    <div class="command-item">
                        <code class="command-text">openssl req -noout -modulus -in domain.csr | openssl md5</code>
                        <button class="btn btn-sm btn-outline-light" onclick="copyToClipboard('openssl req -noout -modulus -in domain.csr | openssl md5')">
                            <i class="bi bi-clipboard"></i> Copy
                        </button>
                    </div>
                    <div class="command-item">
                        <code class="command-text">openssl rsa -noout -modulus -in private.key | openssl md5</code>
                        <button class="btn btn-sm btn-outline-light" onclick="copyToClipboard('openssl rsa -noout -modulus -in private.key | openssl md5')">
                            <i class="bi bi-clipboard"></i> Copy
                        </button>
                    </div>
                    <div class="command-item">
                        <code class="command-text">openssl x509 -noout -modulus -in certificate.crt | openssl md5</code>
                        <button class="btn btn-sm btn-outline-light" onclick="copyToClipboard('openssl x509 -noout -modulus -in certificate.crt | openssl md5')">
                            <i class="bi bi-clipboard"></i> Copy
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="loading-overlay">
    <div class="loading-spinner"></div>
</div>
@endsection


<script>
    window.SSL_MATCHER_CSRF_TOKEN = '{{ csrf_token() }}';
</script>
<script src="{{ asset('assets/js/ssl-matcher.js') }}"></script>
