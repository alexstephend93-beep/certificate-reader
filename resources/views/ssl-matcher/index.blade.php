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
    
    .format-select {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 8px 12px;
        font-size: 0.9rem;
    }
    
    .converted-output {
        background: #f8fafc;
        border-radius: 12px;
        padding: 15px;
        margin-top: 15px;
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
                <button class="match-tab" data-tab="csr-key-cert">
                    <i class="bi bi-diagram-3 me-2"></i>CSR + Private Key + Cert
                </button>
                <button class="match-tab" data-tab="pub-key">
                    <i class="bi bi-person-bounding-box me-2"></i>Public Key + Private Key
                </button>
                <button class="match-tab" data-tab="decrypt-key">
                    <i class="bi bi-unlock me-2"></i>Decrypt Private Key
                </button>
                <button class="match-tab" data-tab="csr">
                    <i class="bi bi-file-earmark-text-fill me-2"></i>CSR Validator
                </button>
                <button class="match-tab" data-tab="format-converter">
                    <i class="bi bi-arrow-left-right me-2"></i>Format Converter
                </button>
            </div>

            <!-- Panel 1: Cert + Private Key -->
            <div id="panel-cert-key" class="panel active">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="glass-card p-4">
                            <h5 class="fw-bold mb-3">
                                <i class="bi bi-file-earmark-lock-fill me-2"></i>
                                SSL Certificate
                            </h5>
                            <div class="file-input-group">
                                <input type="file" id="certFile" class="form-control" accept=".crt,.pem">
                                <button class="btn btn-outline-secondary" onclick="clearFile('certFile', 'certContent')">
                                    <i class="bi bi-x-circle"></i> Clear
                                </button>
                            </div>
                            <textarea id="certContent" rows="8" class="form-control font-monospace" placeholder="-----BEGIN CERTIFICATE-----..."></textarea>
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
                            <textarea id="keyContent" rows="8" class="form-control font-monospace" placeholder="-----BEGIN PRIVATE KEY-----..."></textarea>
                            <input type="password" id="keyPassword" class="form-control mt-3" placeholder="Private key password (if encrypted)">
                            <div class="text-center mt-4">
                                <button class="btn btn-primary btn-lg" onclick="matchCertKey()">
                                    <i class="bi bi-check-lg me-2"></i> Check Match
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel 2: Cert + Public Key -->
            <div id="panel-cert-public" class="panel">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="glass-card p-4">
                            <h5 class="fw-bold mb-3">
                                <i class="bi bi-file-earmark-lock-fill me-2"></i>
                                SSL Certificate
                            </h5>
                            <div class="file-input-group">
                                <input type="file" id="certFile2" class="form-control" accept=".crt,.pem">
                                <button class="btn btn-outline-secondary" onclick="clearFile('certFile2', 'certContent2')">
                                    <i class="bi bi-x-circle"></i> Clear
                                </button>
                            </div>
                            <textarea id="certContent2" rows="8" class="form-control font-monospace" placeholder="-----BEGIN CERTIFICATE-----..."></textarea>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="glass-card p-4">
                            <h5 class="fw-bold mb-3">
                                <i class="bi bi-unlock-fill me-2"></i>
                                Public Key
                            </h5>
                            <div class="file-input-group">
                                <input type="file" id="pubFile" class="form-control" accept=".pub,.pem">
                                <button class="btn btn-outline-secondary" onclick="clearFile('pubFile', 'pubContent')">
                                    <i class="bi bi-x-circle"></i> Clear
                                </button>
                            </div>
                            <textarea id="pubContent" rows="8" class="form-control font-monospace" placeholder="-----BEGIN PUBLIC KEY-----..."></textarea>
                            <div class="text-center mt-4">
                                <button class="btn btn-primary btn-lg" onclick="matchCertPublic()">
                                    <i class="bi bi-check-lg me-2"></i> Check Match
                                </button>
                            </div>
                        </div>
                    </div>
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
                                <input type="file" id="cert1File" class="form-control" accept=".crt,.pem">
                                <button class="btn btn-outline-secondary" onclick="clearFile('cert1File', 'cert1Content')">
                                    <i class="bi bi-x-circle"></i> Clear
                                </button>
                            </div>
                            <textarea id="cert1Content" rows="8" class="form-control font-monospace" placeholder="-----BEGIN CERTIFICATE-----..."></textarea>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="glass-card p-4">
                            <h5 class="fw-bold mb-3">
                                <i class="bi bi-file-earmark-lock-fill me-2"></i>
                                Certificate 2
                            </h5>
                            <div class="file-input-group">
                                <input type="file" id="cert2File" class="form-control" accept=".crt,.pem">
                                <button class="btn btn-outline-secondary" onclick="clearFile('cert2File', 'cert2Content')">
                                    <i class="bi bi-x-circle"></i> Clear
                                </button>
                            </div>
                            <textarea id="cert2Content" rows="8" class="form-control font-monospace" placeholder="-----BEGIN CERTIFICATE-----..."></textarea>
                            <div class="text-center mt-4">
                                <button class="btn btn-primary btn-lg" onclick="matchCerts()">
                                    <i class="bi bi-check-lg me-2"></i> Compare Certificates
                                </button>
                            </div>
                        </div>
                    </div>
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
                                <input type="file" id="pubFile2" class="form-control" accept=".pub,.pem">
                                <button class="btn btn-outline-secondary" onclick="clearFile('pubFile2', 'pubContent2')">
                                    <i class="bi bi-x-circle"></i> Clear
                                </button>
                            </div>
                            <textarea id="pubContent2" rows="8" class="form-control font-monospace" placeholder="-----BEGIN PUBLIC KEY-----..."></textarea>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="glass-card p-4">
                            <h5 class="fw-bold mb-3">
                                <i class="bi bi-key-fill me-2"></i>
                                Private Key
                            </h5>
                            <div class="file-input-group">
                                <input type="file" id="keyFile2" class="form-control" accept=".key,.pem">
                                <button class="btn btn-outline-secondary" onclick="clearFile('keyFile2', 'keyContent2')">
                                    <i class="bi bi-x-circle"></i> Clear
                                </button>
                            </div>
                            <textarea id="keyContent2" rows="8" class="form-control font-monospace" placeholder="-----BEGIN PRIVATE KEY-----..."></textarea>
                            <input type="password" id="keyPassword2" class="form-control mt-3" placeholder="Private key password (if encrypted)">
                            <div class="text-center mt-4">
                                <button class="btn btn-primary btn-lg" onclick="matchPubPriv()">
                                    <i class="bi bi-check-lg me-2"></i> Check Match
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel 5: Decrypt Private Key -->
            <div id="panel-decrypt-key" class="panel">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="glass-card p-4">
                            <h5 class="fw-bold mb-3">
                                <i class="bi bi-file-earmark-lock-fill me-2"></i>
                                Encrypted Private Key
                            </h5>
                            <div class="file-input-group">
                                <input type="file" id="decryptKeyFile" class="form-control" accept=".key,.pem">
                                <button class="btn btn-outline-secondary" onclick="clearFile('decryptKeyFile', 'decryptKeyContent')">
                                    <i class="bi bi-x-circle"></i> Clear
                                </button>
                            </div>
                            <textarea id="decryptKeyContent" rows="8" class="form-control font-monospace" placeholder="-----BEGIN RSA PRIVATE KEY-----
Proc-Type: 4,ENCRYPTED
DEK-Info: AES-256-CBC,..."></textarea>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="glass-card p-4">
                            <h5 class="fw-bold mb-3">
                                <i class="bi bi-key-fill me-2"></i>
                                Password
                            </h5>
                            <input type="password" id="decryptKeyPassword" class="form-control mb-3" placeholder="Enter private key password">
                            <div class="text-center mt-4">
                                <button class="btn btn-primary btn-lg" onclick="decryptKey()">
                                    <i class="bi bi-unlock me-2"></i> Decrypt Key
                                </button>
                                <button class="btn btn-secondary btn-lg ms-2" onclick="clearDecryptPanel()">
                                    <i class="bi bi-trash me-2"></i> Clear
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel 6: CSR Validator -->
            <div id="panel-csr" class="panel">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="glass-card p-4">
                            <h5 class="fw-bold mb-3">
                                <i class="bi bi-file-earmark-text-fill me-2"></i>
                                Certificate Signing Request (CSR)
                            </h5>
                            <div class="file-input-group">
                                <input type="file" id="csrFile" class="form-control" accept=".csr,.pem">
                                <button class="btn btn-outline-secondary" onclick="clearFile('csrFile', 'csrContent')">
                                    <i class="bi bi-x-circle"></i> Clear
                                </button>
                            </div>
                            <textarea id="csrContent" rows="8" class="form-control font-monospace" placeholder="-----BEGIN CERTIFICATE REQUEST-----..."></textarea>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="glass-card p-4">
                            <h5 class="fw-bold mb-3">
                                <i class="bi bi-key-fill me-2"></i>
                                Private Key (Optional)
                            </h5>
                            <div class="file-input-group">
                                <input type="file" id="csrKeyFile" class="form-control" accept=".key,.pem">
                                <button class="btn btn-outline-secondary" onclick="clearFile('csrKeyFile', 'csrKeyContent')">
                                    <i class="bi bi-x-circle"></i> Clear
                                </button>
                            </div>
                            <textarea id="csrKeyContent" rows="5" class="form-control font-monospace" placeholder="-----BEGIN PRIVATE KEY-----..."></textarea>
                            <input type="password" id="csrKeyPassword" class="form-control mt-3" placeholder="Private key password (if encrypted)">
                            <div class="text-center mt-4">
                                <button class="btn btn-primary btn-lg" onclick="validateCSR()">
                                    <i class="bi bi-check-lg me-2"></i> Validate CSR
                                </button>
                                <button class="btn btn-success btn-lg ms-2" onclick="matchCSRWithKey()">
                                    <i class="bi bi-key me-2"></i> Match CSR with Key
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel 7: Format Converter -->
            <div id="panel-format-converter" class="panel">
                <div class="glass-card p-4">
                    <h5 class="fw-bold mb-4">
                        <i class="bi bi-arrow-left-right me-2"></i>
                        Certificate & Key Format Converter
                    </h5>
                    
                    <div class="row g-4">
                        <div class="col-md-5">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Input Type</label>
                                <select id="inputType" class="form-select format-select" onchange="updateConverterPlaceholders()">
                                    <option value="certificate">Certificate</option>
                                    <option value="private_key">Private Key</option>
                                    <option value="public_key">Public Key</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Input Format</label>
                                <select id="inputFormat" class="form-select format-select">
                                    <option value="pem">PEM</option>
                                    <option value="der">DER</option>
                                    <option value="pkcs7">PKCS#7/P7B</option>
                                    <option value="pkcs12">PKCS#12/PFX</option>
                                </select>
                            </div>
                            <div class="file-input-group">
                                <input type="file" id="convertFile" class="form-control" accept=".crt,.pem,.der,.p7b,.pfx,.p12,.key">
                                <button class="btn btn-outline-secondary" onclick="clearFile('convertFile', 'convertContent')">
                                    <i class="bi bi-x-circle"></i> Clear
                                </button>
                            </div>
                            <textarea id="convertContent" rows="6" class="form-control font-monospace" placeholder="-----BEGIN CERTIFICATE-----..."></textarea>
                            <input type="password" id="pkcs12Password" class="form-control mt-3" placeholder="PKCS#12/PFX password (if any)" style="display: none;">
                        </div>
                        
                        <div class="col-md-2 text-center d-flex align-items-center justify-content-center">
                            <div>
                                <i class="bi bi-arrow-right-circle-fill" style="font-size: 3rem; color: var(--color-primary);"></i>
                                <button class="btn btn-primary btn-lg mt-3 w-100" onclick="convertFormat()">
                                    <i class="bi bi-arrow-repeat me-2"></i> Convert
                                </button>
                            </div>
                        </div>
                        
                        <div class="col-md-5">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Output Format</label>
                                <select id="outputFormat" class="form-select format-select">
                                    <option value="pem">PEM</option>
                                    <option value="der">DER</option>
                                    <option value="pkcs7">PKCS#7/P7B</option>
                                    <option value="pkcs12">PKCS#12/PFX</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Server Format (Optional)</label>
                                <select id="serverFormat" class="form-select format-select" onchange="applyServerFormat()">
                                    <option value="">None (Standard)</option>
                                    <option value="apache">Apache (mod_ssl)</option>
                                    <option value="nginx">Nginx</option>
                                    <option value="iis">IIS</option>
                                    <option value="java">Java Keystore</option>
                                </select>
                            </div>
                            <textarea id="convertedOutput" rows="8" class="form-control font-monospace" readonly placeholder="Converted output will appear here..."></textarea>
                            <div class="text-center mt-3">
                                <button class="btn btn-outline-primary" onclick="copyConvertedOutput()">
                                    <i class="bi bi-clipboard me-2"></i> Copy Output
                                </button>
                                <button class="btn btn-outline-success ms-2" onclick="downloadConvertedOutput()">
                                    <i class="bi bi-download me-2"></i> Download
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel: CSR + Private Key + Certificate -->
            <div id="panel-csr-key-cert" class="panel">
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="glass-card p-4">
                            <h5 class="fw-bold mb-3">
                                <i class="bi bi-file-earmark-text-fill me-2"></i>
                                CSR
                            </h5>
                            <div class="file-input-group">
                                <input type="file" id="csrMatchFile" class="form-control" accept=".csr,.pem">
                                <button class="btn btn-outline-secondary" onclick="clearFile('csrMatchFile', 'csrMatchContent')">
                                    <i class="bi bi-x-circle"></i> Clear
                                </button>
                            </div>
                            <textarea id="csrMatchContent" rows="6" class="form-control font-monospace" placeholder="-----BEGIN CERTIFICATE REQUEST-----..."></textarea>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="glass-card p-4">
                            <h5 class="fw-bold mb-3">
                                <i class="bi bi-key-fill me-2"></i>
                                Private Key
                            </h5>
                            <div class="file-input-group">
                                <input type="file" id="keyMatchFile" class="form-control" accept=".key,.pem">
                                <button class="btn btn-outline-secondary" onclick="clearFile('keyMatchFile', 'keyMatchContent')">
                                    <i class="bi bi-x-circle"></i> Clear
                                </button>
                            </div>
                            <textarea id="keyMatchContent" rows="6" class="form-control font-monospace" placeholder="-----BEGIN PRIVATE KEY-----..."></textarea>
                            <input type="password" id="keyMatchPassword" class="form-control mt-3" placeholder="Private key password (if encrypted)">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="glass-card p-4">
                            <h5 class="fw-bold mb-3">
                                <i class="bi bi-file-earmark-lock-fill me-2"></i>
                                Certificate
                            </h5>
                            <div class="file-input-group">
                                <input type="file" id="certMatchFile" class="form-control" accept=".crt,.pem">
                                <button class="btn btn-outline-secondary" onclick="clearFile('certMatchFile', 'certMatchContent')">
                                    <i class="bi bi-x-circle"></i> Clear
                                </button>
                            </div>
                            <textarea id="certMatchContent" rows="6" class="form-control font-monospace" placeholder="-----BEGIN CERTIFICATE-----..."></textarea>
                            <div class="text-center mt-4">
                                <button class="btn btn-primary btn-lg" onclick="matchCSRKeyCert()">
                                    <i class="bi bi-check-lg me-2"></i> Check All Matches
                                </button>
                            </div>
                        </div>
                    </div>
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
                    <div class="command-item">
                        <code class="command-text">openssl req -text -noout -in domain.csr</code>
                        <button class="btn btn-sm btn-outline-light" onclick="copyToClipboard('openssl req -text -noout -in domain.csr')">
                            <i class="bi bi-clipboard"></i> Copy
                        </button>
                    </div>
                    <div class="command-item">
                        <code class="command-text">openssl x509 -in cert.crt -outform DER -out cert.der</code>
                        <button class="btn btn-sm btn-outline-light" onclick="copyToClipboard('openssl x509 -in cert.crt -outform DER -out cert.der')">
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

<script>
(() => {
  // Public API (called from inline onclick attributes)
  window.matchCertKey = matchCertKey;
  window.matchCertPublic = matchCertPublic;
  window.matchCerts = matchCerts;
  window.matchPubPriv = matchPubPriv;
  window.clearAll = clearAll;
  window.copyResults = copyResults;
  window.copyToClipboard = window.copyToClipboard || copyToClipboard;
  window.decryptKey = decryptKey;
  window.clearDecryptPanel = clearDecryptPanel;
  window.copyDecryptedKey = copyDecryptedKey;
  
  // New APIs
  window.validateCSR = validateCSR;
  window.matchCSRWithKey = matchCSRWithKey;
  window.convertFormat = convertFormat;
  window.copyConvertedOutput = copyConvertedOutput;
  window.downloadConvertedOutput = downloadConvertedOutput;
  window.updateConverterPlaceholders = updateConverterPlaceholders;
  window.applyServerFormat = applyServerFormat;
  window.matchCSRKeyCert = matchCSRKeyCert;

  // Tab switching
  function initTabs() {
    document.querySelectorAll('.match-tab').forEach(tab => {
      tab.addEventListener('click', function () {
        document.querySelectorAll('.match-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');

        const tabId = this.dataset.tab;
        document.querySelectorAll('.panel').forEach(panel => panel.classList.remove('active'));

        const panel = document.getElementById(`panel-${tabId}`);
        if (panel) panel.classList.add('active');

        const resultsSection = document.getElementById('resultsSection');
        if (resultsSection) resultsSection.style.display = 'none';
      });
    });
  }

  // File upload handlers
  function setupFileHandler(fileInputId, textareaId) {
    const fileInput = document.getElementById(fileInputId);
    const textarea = document.getElementById(textareaId);
    if (!fileInput || !textarea) return;

    fileInput.addEventListener('change', function (e) {
      const file = e.target.files[0];
      if (!file) return;

      const reader = new FileReader();
      reader.onload = function (event) {
        textarea.value = event.target.result;
      };
      reader.readAsText(file);
    });
  }

  function clearFile(fileInputId, textareaId) {
    const fi = document.getElementById(fileInputId);
    const ta = document.getElementById(textareaId);
    if (fi) fi.value = '';
    if (ta) ta.value = '';
  }
  window.clearFile = clearFile;

  // Helpers
  function showLoading(show) {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) overlay.style.display = show ? 'flex' : 'none';
  }

  function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast-notification ${type}`;
    toast.innerHTML = `<i class="bi bi-${type === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill'} me-2"></i> ${message}`;

    toast.style.cssText = `
      position: fixed;
      bottom: 20px;
      right: 20px;
      background: ${type === 'success' ? '#10b981' : '#ef4444'};
      color: white;
      padding: 12px 20px;
      border-radius: 10px;
      z-index: 10002;
      animation: slideInRight 0.3s ease;
      box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    `;

    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
  }

  function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
  }

  function copyToClipboard(text) {
    navigator.clipboard.writeText(text);
    showToast('Command copied to clipboard!', 'success');
  }

  function copyResults() {
    const matchMessage = document.getElementById('matchMessage')?.innerText || '';
    const details = document.getElementById('matchDetails')?.innerText || '';
    const textToCopy = `${matchMessage}\n\n${details}`.trim();

    navigator.clipboard.writeText(textToCopy);
    showToast('Results copied to clipboard!', 'success');
  }

  // Results rendering
  function displayResults(data) {
    const matchBadge = document.getElementById('matchBadge');
    const matchMessage = document.getElementById('matchMessage');
    const matchDetails = document.getElementById('matchDetails');

    if (!matchBadge || !matchMessage || !matchDetails) return;

    matchBadge.className = `match-badge ${data.match ? 'match-success' : 'match-fail'}`;
    matchBadge.innerHTML = data.match
      ? '<i class="bi bi-check-circle-fill me-2"></i> MATCH'
      : '<i class="bi bi-x-circle-fill me-2"></i> NO MATCH';

    matchMessage.innerHTML = data.message || '';

    let detailsHtml = '';

    if (data.certificate) {
      detailsHtml += `
        <div class="detail-card">
          <h4><i class="bi bi-file-earmark-lock-fill"></i> Certificate Details</h4>
          <div class="detail-row"><span class="detail-label">Subject:</span><span class="detail-value">${escapeHtml(data.certificate.subject)}</span></div>
          <div class="detail-row"><span class="detail-label">Issuer:</span><span class="detail-value">${escapeHtml(data.certificate.issuer)}</span></div>
          <div class="detail-row"><span class="detail-label">Serial Number:</span><span class="detail-value">${escapeHtml(data.certificate.serial_number)}</span></div>
          <div class="detail-row"><span class="detail-label">Valid From:</span><span class="detail-value">${escapeHtml(data.certificate.valid_from)}</span></div>
          <div class="detail-row"><span class="detail-label">Valid To:</span><span class="detail-value">${escapeHtml(data.certificate.valid_to)}</span></div>
          <div class="detail-row"><span class="detail-label">Signature Algorithm:</span><span class="detail-value">${escapeHtml(data.certificate.signature_algorithm)}</span></div>
          <div class="detail-row"><span class="detail-label">Public Key Algorithm:</span><span class="detail-value">${escapeHtml(data.certificate.public_key_algorithm)}</span></div>
          <div class="detail-row"><span class="detail-label">Version:</span><span class="detail-value">${escapeHtml(data.certificate.version)}</span></div>
          <div class="detail-row"><span class="detail-label">MD5 Fingerprint:</span><span class="fingerprint">${escapeHtml(data.certificate.fingerprint_md5)}</span></div>
          <div class="detail-row"><span class="detail-label">SHA1 Fingerprint:</span><span class="fingerprint">${escapeHtml(data.certificate.fingerprint_sha1)}</span></div>
          <div class="detail-row"><span class="detail-label">SHA256 Fingerprint:</span><span class="fingerprint">${escapeHtml(data.certificate.fingerprint_sha256)}</span></div>
        </div>
      `;
    }

    if (data.private_key) {
      detailsHtml += `
        <div class="detail-card">
          <h4><i class="bi bi-key-fill"></i> Private Key Details</h4>
          <div class="detail-row"><span class="detail-label">Type:</span><span class="detail-value">${escapeHtml(data.private_key.type)}</span></div>
          <div class="detail-row"><span class="detail-label">Key Size:</span><span class="detail-value">${escapeHtml(data.private_key.key_size)} bits</span></div>
          <div class="detail-row"><span class="detail-label">Encrypted:</span><span class="detail-value">${data.private_key.is_encrypted ? 'Yes' : 'No'}</span></div>
          <div class="detail-row"><span class="detail-label">MD5 Fingerprint:</span><span class="fingerprint">${escapeHtml(data.private_key.fingerprint_md5)}</span></div>
          <div class="detail-row"><span class="detail-label">SHA1 Fingerprint:</span><span class="fingerprint">${escapeHtml(data.private_key.fingerprint_sha1)}</span></div>
          <div class="detail-row"><span class="detail-label">SHA256 Fingerprint:</span><span class="fingerprint">${escapeHtml(data.private_key.fingerprint_sha256)}</span></div>
        </div>
      `;
    }

    if (data.public_key) {
      detailsHtml += `
        <div class="detail-card">
          <h4><i class="bi bi-unlock-fill"></i> Public Key Details</h4>
          <div class="detail-row"><span class="detail-label">Type:</span><span class="detail-value">${escapeHtml(data.public_key.type)}</span></div>
          <div class="detail-row"><span class="detail-label">Key Size:</span><span class="detail-value">${escapeHtml(data.public_key.key_size)} bits</span></div>
          <div class="detail-row"><span class="detail-label">MD5 Fingerprint:</span><span class="fingerprint">${escapeHtml(data.public_key.fingerprint_md5)}</span></div>
          <div class="detail-row"><span class="detail-label">SHA1 Fingerprint:</span><span class="fingerprint">${escapeHtml(data.public_key.fingerprint_sha1)}</span></div>
          <div class="detail-row"><span class="detail-label">SHA256 Fingerprint:</span><span class="fingerprint">${escapeHtml(data.public_key.fingerprint_sha256)}</span></div>
        </div>
      `;
    }

    if (data.csr) {
      detailsHtml += `
        <div class="detail-card">
          <h4><i class="bi bi-file-earmark-text-fill"></i> CSR Details</h4>
          <div class="detail-row"><span class="detail-label">Subject:</span><span class="detail-value">${escapeHtml(data.csr.subject)}</span></div>
          <div class="detail-row"><span class="detail-label">Public Key Algorithm:</span><span class="detail-value">${escapeHtml(data.csr.public_key_algorithm)}</span></div>
          <div class="detail-row"><span class="detail-label">Key Size:</span><span class="detail-value">${escapeHtml(data.csr.key_size)} bits</span></div>
          <div class="detail-row"><span class="detail-label">Signature Algorithm:</span><span class="detail-value">${escapeHtml(data.csr.signature_algorithm)}</span></div>
          <div class="detail-row"><span class="detail-label">Version:</span><span class="detail-value">${escapeHtml(data.csr.version)}</span></div>
          <div class="detail-row"><span class="detail-label">MD5 Fingerprint:</span><span class="fingerprint">${escapeHtml(data.csr.fingerprint_md5)}</span></div>
          <div class="detail-row"><span class="detail-label">SHA256 Fingerprint:</span><span class="fingerprint">${escapeHtml(data.csr.fingerprint_sha256)}</span></div>
        </div>
      `;
      
      if (data.csr.san_list && data.csr.san_list.length > 0) {
        detailsHtml += `
          <div class="detail-card">
            <h4><i class="bi bi-diagram-3"></i> Subject Alternative Names (SANs)</h4>
            ${data.csr.san_list.map(san => `<div class="detail-row"><span class="detail-value">• ${escapeHtml(san)}</span></div>`).join('')}
          </div>
        `;
      }
    }

    if (data.cert_modulus_hash) {
      detailsHtml += `
        <div class="detail-card">
          <h4><i class="bi bi-diagram-3"></i> Modulus Comparison</h4>
          <div class="detail-row"><span class="detail-label">CSR/Cert Modulus:</span><span class="fingerprint">${escapeHtml(data.cert_modulus_hash)}</span></div>
          <div class="detail-row"><span class="detail-label">Key Modulus:</span><span class="fingerprint">${escapeHtml(data.key_modulus_hash)}</span></div>
        </div>
      `;
    }

    matchDetails.innerHTML = detailsHtml;
    const resultsSection = document.getElementById('resultsSection');
    if (resultsSection) {
      resultsSection.style.display = 'block';
      resultsSection.scrollIntoView({ behavior: 'smooth' });
    }
  }

  // Global config
  const csrfToken = window.SSL_MATCHER_CSRF_TOKEN || '';

  async function postJson(url, payload) {
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      body: JSON.stringify(payload)
    });
    return response.json();
  }


  async function matchCSRKeyCert() {
      const csr = document.getElementById('csrMatchContent').value;
      const privateKey = document.getElementById('keyMatchContent').value;
      const certificate = document.getElementById('certMatchContent').value;
      const keyPassword = document.getElementById('keyMatchPassword').value;

      if (!csr && !privateKey && !certificate) {
          return showToast('Please provide CSR, Private Key, and Certificate', 'error');
      }
      if (!csr) return showToast('Please provide CSR content or upload file', 'error');
      if (!privateKey) return showToast('Please provide private key content or upload file', 'error');
      if (!certificate) return showToast('Please provide certificate content or upload file', 'error');

      showLoading(true);
      try {
          const data = await postJson('/ssl-matcher/match-csr-key-cert', {
              csr: csr,
              private_key: privateKey,
              key_password: keyPassword,
              certificate: certificate
          });

          if (data.success) {
              displayTripleMatchResults(data);
              showToast(data.message, data.match ? 'success' : 'warning');
          } else {
              showToast(data.message || 'Failed to match', 'error');
          }
      } catch (e) {
          console.error(e);
          showToast('An error occurred', 'error');
      } finally {
          showLoading(false);
      }
  }

  async function matchCertKey() {
    const certificate = document.getElementById('certContent').value;
    const privateKey = document.getElementById('keyContent').value;
    const keyPassword = document.getElementById('keyPassword').value;

    if (!certificate && !privateKey) return showToast('Please provide certificate and private key', 'error');
    if (!certificate) return showToast('Please provide certificate content or upload file', 'error');
    if (!privateKey) return showToast('Please provide private key content or upload file', 'error');

    showLoading(true);
    try {
      const data = await postJson('/ssl-matcher/match-cert-key', {
        certificate,
        private_key: privateKey,
        key_password: keyPassword
      });

      if (data.success) displayResults(data);
      else showToast(data.message || 'Failed to match', 'error');
    } catch (e) {
      console.error(e);
      showToast('An error occurred', 'error');
    } finally {
      showLoading(false);
    }
  }

  async function matchCertPublic() {
    const certificate = document.getElementById('certContent2').value;
    const publicKey = document.getElementById('pubContent').value;

    if (!certificate && !publicKey) return showToast('Please provide certificate and public key', 'error');
    if (!certificate) return showToast('Please provide certificate content or upload file', 'error');
    if (!publicKey) return showToast('Please provide public key content or upload file', 'error');

    showLoading(true);
    try {
      const data = await postJson('/ssl-matcher/match-cert-public', {
        certificate,
        public_key: publicKey
      });

      if (data.success) displayResults(data);
      else showToast(data.message || 'Failed to match', 'error');
    } catch (e) {
      console.error(e);
      showToast('An error occurred', 'error');
    } finally {
      showLoading(false);
    }
  }

  async function matchCerts() {
    const certificate1 = document.getElementById('cert1Content').value;
    const certificate2 = document.getElementById('cert2Content').value;

    if (!certificate1 && !certificate2) return showToast('Please provide both certificates', 'error');
    if (!certificate1) return showToast('Please provide certificate 1', 'error');
    if (!certificate2) return showToast('Please provide certificate 2', 'error');

    showLoading(true);
    try {
      const data = await postJson('/ssl-matcher/match-certs', {
        certificate1,
        certificate2
      });

      if (data.success) displayResults(data);
      else showToast(data.message || 'Failed to compare certificates', 'error');
    } catch (e) {
      console.error(e);
      showToast('An error occurred', 'error');
    } finally {
      showLoading(false);
    }
  }

  async function matchPubPriv() {
    const publicKey = document.getElementById('pubContent2').value;
    const privateKey = document.getElementById('keyContent2').value;
    const keyPassword = document.getElementById('keyPassword2').value;

    if (!publicKey && !privateKey) return showToast('Please provide public key and private key', 'error');
    if (!publicKey) return showToast('Please provide public key content or upload file', 'error');
    if (!privateKey) return showToast('Please provide private key content or upload file', 'error');

    showLoading(true);
    try {
      const data = await postJson('/ssl-matcher/match-pub-priv', {
        public_key: publicKey,
        private_key: privateKey,
        key_password: keyPassword
      });

      if (data.success) displayResults(data);
      else showToast(data.message || 'Failed to match', 'error');
    } catch (e) {
      console.error(e);
      showToast('An error occurred', 'error');
    } finally {
      showLoading(false);
    }
  }

  async function decryptKey() {
    const privateKey = document.getElementById('decryptKeyContent').value;
    const keyPassword = document.getElementById('decryptKeyPassword').value;

    if (!privateKey) return showToast('Please provide encrypted private key content or upload file', 'error');
    if (!keyPassword) return showToast('Please provide the password', 'error');

    showLoading(true);
    try {
      const data = await postJson('/ssl-matcher/decrypt-key', {
        private_key: privateKey,
        key_password: keyPassword
      });

      if (data.success) {
        displayDecryptResults(data, true);
        showToast(data.message || 'Key decrypted successfully', 'success');
      } else {
        displayDecryptResults(data, false);
        showToast(data.message || 'Failed to decrypt key', 'error');
      }
    } catch (e) {
      console.error(e);
      const errorData = {
        success: false,
        message: e.message || 'Network error or server unreachable'
      };
      displayDecryptResults(errorData, false);
      showToast('An error occurred during decryption', 'error');
    } finally {
      showLoading(false);
    }
  }

  function displayDecryptResults(data, isSuccess = false) {
    const resultsSection = document.getElementById('resultsSection');
    const matchBadge = document.getElementById('matchBadge');
    const matchMessage = document.getElementById('matchMessage');
    const matchDetails = document.getElementById('matchDetails');

    if (!resultsSection || !matchBadge || !matchMessage || !matchDetails) return;

    if (isSuccess && data.decrypted_key) {
      matchBadge.className = 'match-badge match-success';
      matchBadge.innerHTML = '<i class="bi bi-unlock-fill me-2"></i> DECRYPTED';
      matchMessage.innerHTML = data.message || 'Private key has been decrypted successfully.';

      const decryptBlock = document.createElement('div');
      decryptBlock.className = 'detail-card';
      decryptBlock.innerHTML = `
        <h4><i class="bi bi-key-fill"></i> Decrypted Private Key</h4>
        <p class="mb-2 text-muted">Copy the decrypted key below:</p>
        <textarea id="decryptedKeyOutput" rows="10" class="form-control font-monospace" style="font-size:0.8rem;">${escapeHtml(data.decrypted_key)}</textarea>
        <div class="text-center mt-3">
          <button class="btn btn-outline-primary btn-sm" onclick="copyDecryptedKey()">
            <i class="bi bi-clipboard me-2"></i> Copy Decrypted Key
          </button>
        </div>
      `;
      matchDetails.innerHTML = '';
      matchDetails.appendChild(decryptBlock);
    } else {
      matchBadge.className = 'match-badge match-fail';
      matchBadge.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-2"></i> DECRYPTION FAILED';
      const errorMessage = data.message || 'Failed to decrypt the private key. Please verify your password and key format.';
      matchMessage.innerHTML = errorMessage;

      const errorBlock = document.createElement('div');
      errorBlock.className = 'detail-card';
      errorBlock.style.borderLeft = '4px solid #dc2626';
      errorBlock.innerHTML = `
        <h4><i class="bi bi-shield-exclamation me-2" style="color: #dc2626;"></i> Error Details</h4>
        <div class="alert alert-danger mb-0" role="alert" style="background-color: #fef2f2; border-color: #fecaca; color: #991b1b;">
          <i class="bi bi-exclamation-circle-fill me-2"></i>
          ${escapeHtml(errorMessage)}
        </div>
        <div class="mt-3 text-muted small">
          <i class="bi bi-lightbulb me-1"></i> Possible causes:
          <ul class="mt-2 mb-0">
            <li>Incorrect password</li>
            <li>Corrupted or invalid private key format</li>
            <li>The key may not be encrypted (try without password)</li>
            <li>Unsupported encryption format</li>
          </ul>
        </div>
      `;
      matchDetails.innerHTML = '';
      matchDetails.appendChild(errorBlock);
    }

    resultsSection.style.display = 'block';
    resultsSection.scrollIntoView({ behavior: 'smooth' });
  }

  function copyDecryptedKey() {
    const output = document.getElementById('decryptedKeyOutput');
    if (output) {
      output.select();
      output.setSelectionRange(0, 99999);
      navigator.clipboard.writeText(output.value)
        .then(() => showToast('Decrypted key copied to clipboard!', 'success'))
        .catch(() => showToast('Failed to copy', 'error'));
    }
  }

  function clearDecryptPanel() {
    const elFile = document.getElementById('decryptKeyFile');
    const elText = document.getElementById('decryptKeyContent');
    const elPass = document.getElementById('decryptKeyPassword');
    if (elFile) elFile.value = '';
    if (elText) elText.value = '';
    if (elPass) elPass.value = '';

    const resultsSection = document.getElementById('resultsSection');
    if (resultsSection) resultsSection.style.display = 'none';
  }

  // CSR Functions
  async function validateCSR() {
    const csr = document.getElementById('csrContent').value;
    
    if (!csr) return showToast('Please provide CSR content or upload file', 'error');
    
    showLoading(true);
    try {
      const data = await postJson('/ssl-matcher/validate-csr', { csr });
      
      if (data.success) {
        displayResults(data);
        showToast('CSR validated successfully', 'success');
      } else {
        showToast(data.message || 'Failed to validate CSR', 'error');
      }
    } catch (e) {
      console.error(e);
      showToast('An error occurred', 'error');
    } finally {
      showLoading(false);
    }
  }

  async function matchCSRWithKey() {
    const csr = document.getElementById('csrContent').value;
    const privateKey = document.getElementById('csrKeyContent').value;
    const keyPassword = document.getElementById('csrKeyPassword').value;
    
    if (!csr) return showToast('Please provide CSR content', 'error');
    if (!privateKey) return showToast('Please provide private key content', 'error');
    
    showLoading(true);
    try {
      const data = await postJson('/ssl-matcher/match-csr-key', {
        csr,
        private_key: privateKey,
        key_password: keyPassword
      });
      
      if (data.success) displayResults(data);
      else showToast(data.message || 'Failed to match CSR with key', 'error');
    } catch (e) {
      console.error(e);
      showToast('An error occurred', 'error');
    } finally {
      showLoading(false);
    }
  }

  // Format Converter Functions
  function updateConverterPlaceholders() {
    const inputType = document.getElementById('inputType').value;
    const inputFormat = document.getElementById('inputFormat').value;
    const pkcs12Password = document.getElementById('pkcs12Password');
    const convertContent = document.getElementById('convertContent');
    
    if (inputFormat === 'pkcs12') {
      pkcs12Password.style.display = 'block';
    } else {
      pkcs12Password.style.display = 'none';
    }
    
    if (inputType === 'certificate') {
      convertContent.placeholder = '-----BEGIN CERTIFICATE-----...';
    } else if (inputType === 'private_key') {
      convertContent.placeholder = '-----BEGIN PRIVATE KEY-----...';
    } else {
      convertContent.placeholder = '-----BEGIN PUBLIC KEY-----...';
    }
  }

  function applyServerFormat() {
    const serverFormat = document.getElementById('serverFormat').value;
    const outputFormat = document.getElementById('outputFormat').value;
    
    if (serverFormat === 'apache') {
      showToast('Apache format: Ensure certificate and key are properly concatenated', 'info');
    } else if (serverFormat === 'nginx') {
      showToast('Nginx format: Standard PEM format required', 'info');
    } else if (serverFormat === 'iis') {
      if (outputFormat !== 'pkcs12') {
        showToast('IIS typically requires PKCS#12/PFX format. Consider changing output format.', 'warning');
      }
    }
  }

  async function convertFormat() {
    const inputType = document.getElementById('inputType').value;
    const inputFormat = document.getElementById('inputFormat').value;
    const outputFormat = document.getElementById('outputFormat').value;
    const content = document.getElementById('convertContent').value;
    const password = document.getElementById('pkcs12Password').value;
    const serverFormat = document.getElementById('serverFormat').value;
    
    if (!content) return showToast('Please provide content to convert', 'error');
    
    showLoading(true);
    try {
      const data = await postJson('/ssl-matcher/convert-format', {
        input_type: inputType,
        input_format: inputFormat,
        output_format: outputFormat,
        content: content,
        password: password,
        server_format: serverFormat
      });
      
      if (data.success) {
        document.getElementById('convertedOutput').value = data.converted_content;
        showToast('Conversion successful!', 'success');
      } else {
        showToast(data.message || 'Failed to convert', 'error');
      }
    } catch (e) {
      console.error(e);
      showToast('An error occurred', 'error');
    } finally {
      showLoading(false);
    }
  }

  function copyConvertedOutput() {
    const output = document.getElementById('convertedOutput');
    if (output && output.value) {
      output.select();
      output.setSelectionRange(0, 99999);
      navigator.clipboard.writeText(output.value)
        .then(() => showToast('Converted output copied to clipboard!', 'success'))
        .catch(() => showToast('Failed to copy', 'error'));
    } else {
      showToast('Nothing to copy', 'warning');
    }
  }

  function downloadConvertedOutput() {
    const output = document.getElementById('convertedOutput');
    if (output && output.value) {
      const inputType = document.getElementById('inputType').value;
      const outputFormat = document.getElementById('outputFormat').value;
      let extension = '';
      let filename = `converted.`;
      
      switch(outputFormat) {
        case 'pem':
          extension = 'pem';
          break;
        case 'der':
          extension = 'der';
          break;
        case 'pkcs7':
          extension = 'p7b';
          break;
        case 'pkcs12':
          extension = 'pfx';
          break;
      }
      
      filename += extension;
      
      const blob = new Blob([output.value], { type: 'text/plain' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = filename;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
      
      showToast(`Downloaded as ${filename}`, 'success');
    } else {
      showToast('Nothing to download', 'warning');
    }
  }

  function clearAll() {
    const textareas = ['certContent', 'keyContent', 'certContent2', 'pubContent', 'cert1Content', 'cert2Content', 'pubContent2', 'keyContent2', 'csrContent', 'csrKeyContent', 'convertContent', 'convertedOutput'];
    textareas.forEach(id => {
      const el = document.getElementById(id);
      if (el) el.value = '';
    });

    const fileInputs = ['certFile', 'keyFile', 'certFile2', 'pubFile', 'cert1File', 'cert2File', 'pubFile2', 'keyFile2', 'csrFile', 'csrKeyFile', 'convertFile'];
    fileInputs.forEach(id => {
      const el = document.getElementById(id);
      if (el) el.value = '';
    });

    const passwords = ['keyPassword', 'keyPassword2', 'csrKeyPassword', 'decryptKeyPassword', 'pkcs12Password'];
    passwords.forEach(id => {
      const el = document.getElementById(id);
      if (el) el.value = '';
    });

    const resultsSection = document.getElementById('resultsSection');
    if (resultsSection) resultsSection.style.display = 'none';
  }


  function displayTripleMatchResults(data) {
    const matchBadge = document.getElementById('matchBadge');
    const matchMessage = document.getElementById('matchMessage');
    const matchDetails = document.getElementById('matchDetails');

    if (!matchBadge || !matchMessage || !matchDetails) return;

    matchBadge.className = `match-badge ${data.match ? 'match-success' : 'match-fail'}`;
    matchBadge.innerHTML = data.match
      ? '<i class="bi bi-check-circle-fill me-2"></i> ALL MATCH'
      : '<i class="bi bi-x-circle-fill me-2"></i> MISMATCH DETECTED';

    // More detailed message
    let detailedMessage = data.message || '';
    
    // Add specific guidance based on what matches
    if (!data.match) {
        const mismatches = [];
        if (!data.match_details.csr_key) mismatches.push('CSR ↔ Private Key');
        if (!data.match_details.csr_cert) mismatches.push('CSR ↔ Certificate');
        if (!data.match_details.key_cert) mismatches.push('Private Key ↔ Certificate');
        
        if (data.match_details.csr_key && !data.match_details.csr_cert && !data.match_details.key_cert) {
            detailedMessage += ' 🔑 The Private Key matches the CSR, but the Certificate belongs to a DIFFERENT key pair. You need to use the Certificate that was issued from this CSR.';
        } else if (!data.match_details.csr_key && data.match_details.csr_cert && !data.match_details.key_cert) {
            detailedMessage += ' 📜 The CSR matches the Certificate, but the Private Key is from a DIFFERENT key pair.';
        } else if (!data.match_details.csr_key && !data.match_details.csr_cert && data.match_details.key_cert) {
            detailedMessage += ' 🔐 The Private Key matches the Certificate, but the CSR is from a DIFFERENT key pair.';
        } else if (data.match_details.csr_key && data.match_details.csr_cert && !data.match_details.key_cert) {
            detailedMessage += ' ⚠️ CSR matches both, but Private Key doesn\'t match Certificate - likely using wrong private key.';
        }
    } else {
        detailedMessage += ' ✅ All three components are from the same key pair and match perfectly!';
    }
    
    matchMessage.innerHTML = detailedMessage;

    let detailsHtml = '';

    // Match status cards with better visual indicators
    detailsHtml += `
      <div class="detail-card" style="grid-column: 1 / -1;">
        <h4><i class="bi bi-diagram-3 me-2"></i> Match Status</h4>
        <div class="row g-3">
          <div class="col-md-4">
            <div class="p-3 rounded text-center ${data.match_details.csr_key ? 'bg-success bg-opacity-10 border border-success' : 'bg-danger bg-opacity-10 border border-danger'}">
              <i class="bi ${data.match_details.csr_key ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger'} me-2"></i>
              CSR ↔ Private Key
              <span class="badge ${data.match_details.csr_key ? 'bg-success' : 'bg-danger'} d-block mt-2">
                ${data.match_details.csr_key ? '✅ MATCH' : '❌ NO MATCH'}
              </span>
            </div>
          </div>
          <div class="col-md-4">
            <div class="p-3 rounded text-center ${data.match_details.csr_cert ? 'bg-success bg-opacity-10 border border-success' : 'bg-danger bg-opacity-10 border border-danger'}">
              <i class="bi ${data.match_details.csr_cert ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger'} me-2"></i>
              CSR ↔ Certificate
              <span class="badge ${data.match_details.csr_cert ? 'bg-success' : 'bg-danger'} d-block mt-2">
                ${data.match_details.csr_cert ? '✅ MATCH' : '❌ NO MATCH'}
              </span>
            </div>
          </div>
          <div class="col-md-4">
            <div class="p-3 rounded text-center ${data.match_details.key_cert ? 'bg-success bg-opacity-10 border border-success' : 'bg-danger bg-opacity-10 border border-danger'}">
              <i class="bi ${data.match_details.key_cert ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger'} me-2"></i>
              Private Key ↔ Certificate
              <span class="badge ${data.match_details.key_cert ? 'bg-success' : 'bg-danger'} d-block mt-2">
                ${data.match_details.key_cert ? '✅ MATCH' : '❌ NO MATCH'}
              </span>
            </div>
          </div>
        </div>
        <!-- Summary of what matches -->
        <div class="mt-3 p-3 bg-light rounded">
          <strong>Summary:</strong>
          ${data.match_details.csr_key ? '✅ CSR matches Private Key' : '❌ CSR does NOT match Private Key'} | 
          ${data.match_details.csr_cert ? '✅ CSR matches Certificate' : '❌ CSR does NOT match Certificate'} | 
          ${data.match_details.key_cert ? '✅ Private Key matches Certificate' : '❌ Private Key does NOT match Certificate'}
          <br>
          <small class="text-muted">
            <i class="bi bi-info-circle me-1"></i>
            ${data.match_details.csr_key && !data.match_details.csr_cert && !data.match_details.key_cert 
              ? '💡 The Private Key matches the CSR, but the Certificate is from a different key pair. You need the Certificate that was issued from this CSR.'
              : data.match_details.csr_key && data.match_details.csr_cert && !data.match_details.key_cert
              ? '💡 CSR matches both, but Private Key doesn\'t match Certificate - likely using wrong private key.'
              : data.match_details.csr_cert && data.match_details.key_cert && !data.match_details.csr_key
              ? '💡 Certificate and Private Key match, but CSR is from a different key pair.'
              : '💡 For everything to work, all three must match. This usually means using the CSR to get a Certificate from a CA, then keeping the Private Key.'
            }
          </small>
        </div>
      </div>
    `;

    // CSR Details - Show full Subject
    if (data.csr) {
      detailsHtml += `
        <div class="detail-card">
          <h4><i class="bi bi-file-earmark-text-fill me-2"></i> CSR Details</h4>
          <div class="detail-row"><span class="detail-label">Subject:</span><span class="detail-value" style="font-size:0.8rem;word-break:break-all;">${escapeHtml(data.csr.subject)}</span></div>
          <div class="detail-row"><span class="detail-label">Common Name (CN):</span><span class="detail-value">${escapeHtml(data.csr.subject.split('CN=').pop()?.split(',')[0] || 'N/A')}</span></div>
          <div class="detail-row"><span class="detail-label">Key Size:</span><span class="detail-value">${escapeHtml(data.csr.key_size)} bits</span></div>
          <div class="detail-row"><span class="detail-label">Signature Algorithm:</span><span class="detail-value">${escapeHtml(data.csr.signature_algorithm)}</span></div>
          <div class="detail-row"><span class="detail-label">Modulus Hash:</span><span class="fingerprint">${escapeHtml(data.csr_modulus_hash)}</span></div>
        </div>
      `;
    }

    // Private Key Details
    if (data.private_key) {
      detailsHtml += `
        <div class="detail-card">
          <h4><i class="bi bi-key-fill me-2"></i> Private Key Details</h4>
          <div class="detail-row"><span class="detail-label">Type:</span><span class="detail-value">${escapeHtml(data.private_key.type)}</span></div>
          <div class="detail-row"><span class="detail-label">Key Size:</span><span class="detail-value">${escapeHtml(data.private_key.key_size)} bits</span></div>
          <div class="detail-row"><span class="detail-label">Modulus Hash:</span><span class="fingerprint">${escapeHtml(data.key_modulus_hash)}</span></div>
        </div>
      `;
    }

    // Certificate Details - Show full Subject and highlight differences
    if (data.certificate) {
      const certCn = data.certificate.subject.split('CN=').pop()?.split(',')[0] || 'N/A';
      const csrCn = data.csr ? data.csr.subject.split('CN=').pop()?.split(',')[0] || 'N/A' : 'N/A';
      const cnMatch = certCn === csrCn;
      
      detailsHtml += `
        <div class="detail-card">
          <h4><i class="bi bi-file-earmark-lock-fill me-2"></i> Certificate Details</h4>
          <div class="detail-row"><span class="detail-label">Subject:</span><span class="detail-value" style="font-size:0.8rem;word-break:break-all;">${escapeHtml(data.certificate.subject)}</span></div>
          <div class="detail-row">
            <span class="detail-label">Common Name (CN):</span>
            <span class="detail-value">
              ${escapeHtml(certCn)}
              ${!cnMatch ? ' ⚠️ (Does NOT match CSR CN: ' + escapeHtml(csrCn) + ')' : ''}
            </span>
          </div>
          <div class="detail-row"><span class="detail-label">Issuer:</span><span class="detail-value" style="font-size:0.8rem;">${escapeHtml(data.certificate.issuer)}</span></div>
          <div class="detail-row"><span class="detail-label">Serial Number:</span><span class="detail-value">${escapeHtml(data.certificate.serial_number)}</span></div>
          <div class="detail-row"><span class="detail-label">Valid From:</span><span class="detail-value">${escapeHtml(data.certificate.valid_from)}</span></div>
          <div class="detail-row"><span class="detail-label">Valid To:</span><span class="detail-value">${escapeHtml(data.certificate.valid_to)}</span></div>
          <div class="detail-row"><span class="detail-label">Modulus Hash:</span><span class="fingerprint">${escapeHtml(data.cert_modulus_hash)}</span></div>
          ${!data.match_details.csr_cert ? `
            <div class="mt-2 p-2 bg-danger bg-opacity-10 rounded border border-danger">
              <i class="bi bi-exclamation-triangle-fill text-danger me-1"></i>
              <strong>Certificate Modulus Hash doesn't match CSR!</strong> This Certificate was issued using a different private key.
            </div>
          ` : ''}
        </div>
      `;
    }

    matchDetails.innerHTML = detailsHtml;
    const resultsSection = document.getElementById('resultsSection');
    if (resultsSection) {
      resultsSection.style.display = 'block';
      resultsSection.scrollIntoView({ behavior: 'smooth' });
    }
}

  // Init on load
  document.addEventListener('DOMContentLoaded', function () {
    initTabs();
    updateConverterPlaceholders();

    // File handlers for all panels
    setupFileHandler('certFile', 'certContent');
    setupFileHandler('keyFile', 'keyContent');
    setupFileHandler('certFile2', 'certContent2');
    setupFileHandler('pubFile', 'pubContent');
    setupFileHandler('cert1File', 'cert1Content');
    setupFileHandler('cert2File', 'cert2Content');
    setupFileHandler('pubFile2', 'pubContent2');
    setupFileHandler('keyFile2', 'keyContent2');
    setupFileHandler('decryptKeyFile', 'decryptKeyContent');
    setupFileHandler('csrFile', 'csrContent');
    setupFileHandler('csrKeyFile', 'csrKeyContent');
    setupFileHandler('convertFile', 'convertContent');

    setupFileHandler('csrMatchFile', 'csrMatchContent');
    setupFileHandler('keyMatchFile', 'keyMatchContent');
    setupFileHandler('certMatchFile', 'certMatchContent');
  });
})();
</script>