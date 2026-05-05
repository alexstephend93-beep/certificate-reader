@extends('layouts.app')

@section('title', 'Chain Validator | Network Tools')

@section('styles')
<style>
    /* Chain visualization - vertical stack with connecting lines */
    .chain-container {
        position: relative;
        padding: 20px 0;
    }
    
    .chain-node {
        background: white;
        border-radius: 20px;
        padding: 30px;
        margin-bottom: 20px;
        box-shadow: 0 10px 30px -10px rgba(0,0,0,0.1);
        border-left: 6px solid var(--color-primary);
        transition: var(--transition-smooth);
        position: relative;
        overflow: hidden;
    }
    
    .chain-node:hover {
        transform: translateX(5px);
        box-shadow: 0 15px 35px -10px rgba(0,0,0,0.15);
    }
    
    /* Certificate type specific borders */
    .chain-node.type-root {
        border-left-color: #8e44ad;
    }
    
    .chain-node.type-intermediate {
        border-left-color: #2980b9;
    }
    
    .chain-node.type-leaf {
        border-left-color: var(--color-primary);
    }
    
    /* Connecting line */
    .chain-node:not(:last-child)::after {
        content: '';
        position: absolute;
        left: 28px;
        bottom: -20px;
        width: 2px;
        height: 20px;
        background: linear-gradient(to bottom, rgba(0,0,0,0.15), transparent);
    }
    
    .node-header {
        display: flex;
        align-items: flex-start;
        gap: 20px;
        margin-bottom: 15px;
    }
    
    .node-icon {
        font-size: 2rem;
        color: var(--color-primary);
        background: rgba(var(--bs-primary-rgb), 0.1);
        width: 60px;
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 15px;
        flex-shrink: 0;
    }
    
    .type-root .node-icon {
        background: rgba(142, 68, 173, 0.1);
        color: #8e44ad;
    }
    
    .type-intermediate .node-icon {
        background: rgba(52, 152, 219, 0.1);
        color: #2980b9;
    }
    
    .type-leaf .node-icon {
        background: rgba(var(--bs-primary-rgb), 0.1);
        color: var(--color-primary);
    }
    
    .node-content {
        flex-grow: 1;
        word-break: break-word;
    }
    
    .node-title {
        font-size: 1.2rem;
        font-weight: 600;
        margin-bottom: 5px;
        color: #2c3e50;
    }
    
    .node-subtitle {
        font-size: 0.9rem;
        color: #7f8c8d;
        margin-bottom: 10px;
    }
    
    .status-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 15px;
    }
    
    .status-badge {
        font-size: 0.75rem;
        padding: 5px 12px;
        border-radius: 20px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .status-badge.valid {
        background: rgba(46, 204, 113, 0.15);
        color: #27ae60;
    }
    
    .status-badge.expired {
        background: rgba(231, 76, 60, 0.15);
        color: #c0392b;
    }
    
    .status-badge.root {
        background: rgba(142, 68, 173, 0.15);
        color: #8e44ad;
    }
    
    .status-badge.intermediate {
        background: rgba(52, 152, 219, 0.15);
        color: #2980b9;
    }
    
    .status-badge.leaf {
        background: rgba(243, 156, 18, 0.15);
        color: #d35400;
    }
    
    .status-badge.signature-valid {
        background: rgba(39, 174, 96, 0.15);
        color: #27ae60;
    }
    
    .status-badge.signature-invalid {
        background: rgba(231, 76, 60, 0.15);
        color: #c0392b;
    }
    
    .status-badge.signature-missing {
        background: rgba(241, 196, 15, 0.15);
        color: #f39c12;
    }
    
    .cert-details-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 12px;
        margin-top: 15px;
        padding: 15px;
        background: rgba(0,0,0,0.03);
        border-radius: 12px;
        font-size: 0.85rem;
    }
    
    .detail-item {
        display: flex;
        flex-direction: column;
    }
    
    .detail-item strong {
        color: var(--color-primary);
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 2px;
    }
    
    .detail-item span {
        word-break: break-word;
    }
    
    .download-section {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 20px;
        padding-top: 15px;
        border-top: 1px solid rgba(0,0,0,0.08);
    }
    
    .download-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        text-decoration: none;
        transition: var(--transition-smooth);
        border: none;
        cursor: pointer;
    }
    
    .download-btn.root {
        background: rgba(142, 68, 173, 0.1);
        color: #8e44ad;
        border: 1px solid rgba(142, 68, 173, 0.2);
    }
    
    .download-btn.intermediate {
        background: rgba(52, 152, 219, 0.1);
        color: #2980b9;
        border: 1px solid rgba(52, 152, 219, 0.2);
    }
    
    .download-btn.leaf {
        background: rgba(var(--bs-primary-rgb), 0.1);
        color: var(--color-primary);
        border: 1px solid rgba(var(--bs-primary-rgb), 0.2);
    }
    
    .download-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    /* Chain statistics banner */
    .chain-stats {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        padding: 20px 25px;
        background: linear-gradient(135deg, rgba(var(--bs-primary-rgb), 0.05), rgba(var(--bs-primary-rgb), 0.02));
        border-radius: 15px;
        margin-bottom: 30px;
        border: 1px solid rgba(var(--bs-primary-rgb), 0.1);
    }
    
    .stat-item {
        text-align: center;
        min-width: 80px;
    }
    
    .stat-value {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--color-primary);
        display: block;
        line-height: 1;
    }
    
    .stat-label {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #7f8c8d;
        margin-top: 5px;
    }
    
    /* Chain type icon indicators */
    .chain-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-left: 8px;
    }
    
    .chain-badge.root {
        background: rgba(142, 68, 173, 0.15);
        color: #8e44ad;
    }
    
    .chain-badge.intermediate {
        background: rgba(52, 152, 219, 0.15);
        color: #2980b9;
    }
    
    .chain-badge.leaf {
        background: rgba(243, 156, 18, 0.15);
        color: #d35400;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .chain-node {
            padding: 20px;
            margin-left: 0 !important;
        }
        
        .chain-node:not(:last-child)::after {
            left: 28px;
        }
        
        .node-header {
            flex-direction: column;
            gap: 10px;
        }
        
        .node-icon {
            width: 50px;
            height: 50px;
            font-size: 1.5rem;
        }
        
        .cert-details-grid {
            grid-template-columns: 1fr;
        }
        
        .download-section {
            flex-direction: column;
        }
        
        .download-btn {
            width: 100%;
            justify-content: center;
        }
    }
</style>
@endsection

@section('content')
<div class="glass-card" data-aos="fade-down" data-aos-duration="1000">
    <div class="gradient-header text-center">
        <h1 class="fw-bold"><i class="bi bi-diagram-3-fill me-3"></i> Chain Validator</h1>
        <p class="lead mb-0"><i class="bi bi-link-45deg me-2"></i> Inspect full SSL/TLS certificate chains and trust paths</p>
    </div>

    <div class="p-4 p-md-5">
        @if (session('error'))
        <div class="alert alert-danger border-0 rounded-4 d-flex align-items-center gap-3 mb-4" role="alert" data-aos="fade-up">
            <i class="bi bi-exclamation-triangle-fill fs-3"></i>
            <span class="fw-semibold">{{ session('error') }}</span>
        </div>
        @endif

        @if (session('success') && session()->has('chain_data'))
            @php 
                $chain = session('chain_data');
                $stats = session('chain_stats', [
                    'total' => count($chain),
                    'root_ca' => 0,
                    'intermediate_ca' => 0,
                    'leaf' => 0,
                    'expired' => 0,
                    'valid' => 0,
                ]);
                
                // Calculate stats if not already computed
                if ($stats['root_ca'] == 0 && $stats['intermediate_ca'] == 0) {
                    foreach ($chain as $cert) {
                        if ($cert['cert_type'] === 'root') $stats['root_ca']++;
                        elseif ($cert['cert_type'] === 'intermediate') $stats['intermediate_ca']++;
                        else $stats['leaf']++;
                    }
                    $stats['expired'] = count(array_filter($chain, fn($c) => $c['is_expired']));
                    $stats['valid'] = count(array_filter($chain, fn($c) => !$c['is_expired']));
                }
            @endphp

            @if (!empty(session('parse_warnings')))
            <div class="alert alert-warning border-0 rounded-4 mb-4" data-aos="fade-up">
                <h6 class="fw-bold mb-2"><i class="bi bi-exclamation-triangle me-2"></i> Warnings</h6>
                <ul class="mb-0">
                    @foreach (session('parse_warnings') as $warning)
                        <li>{{ $warning }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            @if (!empty(session('parse_errors')))
            <div class="alert alert-danger border-0 rounded-4 mb-4" data-aos="fade-up">
                <h6 class="fw-bold mb-2"><i class="bi bi-x-circle me-2"></i> Errors</h6>
                <ul class="mb-0">
                    @foreach (session('parse_errors') as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <div class="success-alert d-flex align-items-center gap-3 mb-5" data-aos="fade-down">
                <i class="bi bi-check-circle-fill fs-2 text-success"></i>
                <div>
                    <h5 class="fw-bold mb-1">Chain Analyzed Successfully!</h5>
                    <p class="mb-0">Found <strong>{{ $stats['total'] }}</strong> certificates 
                        @if($stats['root_ca'] > 0) • <strong>{{ $stats['root_ca'] }}</strong> root CA @endif
                        @if($stats['intermediate_ca'] > 0) • <strong>{{ $stats['intermediate_ca'] }}</strong> intermediate @endif
                        @if($stats['leaf'] > 0) • <strong>{{ $stats['leaf'] }}</strong> leaf @endif
                        • <strong>{{ $stats['valid'] }}</strong> valid, <strong>{{ $stats['expired'] }}</strong> expired</p>
                </div>
            </div>

            @if($stats['total'] > 0)
            <div class="chain-stats" data-aos="fade-up">
                <div class="stat-item">
                    <span class="stat-value">{{ $stats['total'] }}</span>
                    <div class="stat-label">Total</div>
                </div>
                <div class="stat-item">
                    <span class="stat-value" style="color: #8e44ad;">{{ $stats['root_ca'] }}</span>
                    <div class="stat-label">Root CA</div>
                </div>
                <div class="stat-item">
                    <span class="stat-value" style="color: #2980b9;">{{ $stats['intermediate_ca'] }}</span>
                    <div class="stat-label">Intermediate</div>
                </div>
                <div class="stat-item">
                    <span class="stat-value" style="color: var(--color-primary);">{{ $stats['leaf'] }}</span>
                    <div class="stat-label">Leaf</div>
                </div>
                <div class="stat-item">
                    <span class="stat-value text-success">{{ $stats['valid'] }}</span>
                    <div class="stat-label">Valid</div>
                </div>
                <div class="stat-item">
                    <span class="stat-value text-danger">{{ $stats['expired'] }}</span>
                    <div class="stat-label">Expired</div>
                </div>
            </div>
            @endif

            <div class="mb-5" data-aos="fade-up">
                <h3 class="fw-bold mb-4"><i class="bi bi-diagram-2-fill me-2"></i> Certificate Chain</h3>
                
                <div class="chain-container">
                    @foreach($chain as $index => $cert)
                    <div class="chain-node type-{{ $cert['cert_type'] }}" data-aos="fade-up" data-aos-delay="{{ $index * 100 }}">
                        <div class="node-header">
                            <div class="node-icon">
                                @if($cert['cert_type'] === 'root')
                                    <i class="bi bi-bank"></i>
                                @elseif($cert['cert_type'] === 'intermediate')
                                    <i class="bi bi-diagram-2"></i>
                                @else
                                    <i class="bi bi-hdd-network"></i>
                                @endif
                            </div>
                            <div class="node-content" style="flex-grow: 1;">
                                <div class="node-title">
                                    {{ $cert['subject'] }}
                                    <span class="chain-badge {{ $cert['cert_type'] }}">{{ $cert['cert_type'] }}</span>
                                </div>
                                <div class="node-subtitle">
                                    <i class="bi bi-person-badge me-1"></i> Issued by: <span class="fw-medium">{{ $cert['issuer'] }}</span>
                                </div>
                                
                                <div class="status-badges">
                                    @if($cert['is_expired'])
                                        <span class="status-badge expired"><i class="bi bi-x-circle-fill"></i> Expired</span>
                                    @else
                                        <span class="status-badge valid"><i class="bi bi-check-circle-fill"></i> Valid Period</span>
                                    @endif
                                    
                                    @if($cert['cert_type'] === 'root')
                                        <span class="status-badge root"><i class="bi bi-shield-lock-fill"></i> Root CA</span>
                                    @elseif($cert['cert_type'] === 'intermediate')
                                        <span class="status-badge intermediate"><i class="bi bi-link"></i> Intermediate</span>
                                    @else
                                        <span class="status-badge leaf"><i class="bi bi-laptop"></i> End-Entity</span>
                                    @endif
                                    
                                    @if($cert['signature_valid'] === true)
                                        <span class="status-badge signature-valid" title="Signature cryptographically verified">
                                            <i class="bi bi-check2"></i> Signed
                                        </span>
                                    @elseif($cert['signature_valid'] === false)
                                        <span class="status-badge signature-invalid" title="Signature verification FAILED">
                                            <i class="bi bi-x-circle"></i> Bad Signature
                                        </span>
                                    @elseif($cert['is_self_signed'])
                                        <span class="status-badge signature-missing" title="Self-signed - trust depends on your root store">
                                            <i class="bi bi-shield-question"></i> Self-Signed
                                        </span>
                                    @elseif($cert['signature_valid'] === 'issuer_missing')
                                        <span class="status-badge signature-missing" title="Issuer certificate not provided in chain">
                                            <i class="bi bi-question-circle"></i> Issuer Missing
                                        </span>
                                    @else
                                        <span class="status-badge signature-missing" title="Cannot verify - issuer not found">
                                            <i class="bi bi-dash-circle"></i> Unverifiable
                                        </span>
                                    @endif
                                </div>

                                <div class="cert-details-grid">
                                    <div class="detail-item">
                                        <strong>Valid From</strong>
                                        <span>{{ $cert['valid_from'] }}</span>
                                    </div>
                                    <div class="detail-item">
                                        <strong>Valid To</strong>
                                        <span class="{{ $cert['is_expired'] ? 'text-danger fw-bold' : '' }}">{{ $cert['valid_to'] }}</span>
                                    </div>
                                    @if($cert['public_key_size'])
                                    <div class="detail-item">
                                        <strong>Key Size</strong>
                                        <span>{{ $cert['public_key_size'] }}</span>
                                    </div>
                                    @endif
                                    @if($cert['signature_algorithm'])
                                    <div class="detail-item">
                                        <strong>Signature Algorithm</strong>
                                        <span>{{ $cert['signature_algorithm'] }}</span>
                                    </div>
                                    @endif
                                    @if($cert['serial_number'])
                                    <div class="detail-item">
                                        <strong>Serial Number</strong>
                                        <span style="font-family: monospace; font-size: 0.75rem;">{{ substr($cert['serial_number'], 0, 20) }}{{ strlen($cert['serial_number']) > 20 ? '...' : '' }}</span>
                                    </div>
                                    @endif
                                    @if($cert['version'])
                                    <div class="detail-item">
                                        <strong>Version</strong>
                                        <span>v{{ $cert['version'] }}</span>
                                    </div>
                                    @endif
                                </div>

                                @if($cert['fingerprint_sha256'])
                                <div class="mt-3" style="padding: 10px; background: rgba(0,0,0,0.02); border-radius: 8px;">
                                    <strong class="small"><i class="bi bi-fingerprint me-1"></i> SHA-256 Fingerprint:</strong>
                                    <div class="font-monospace small text-muted mt-1" style="word-break: break-all; font-size: 0.75rem;">
                                        {{ $cert['fingerprint_sha256'] }}
                                    </div>
                                    @if($cert['fingerprint_sha1'])
                                    <div class="font-monospace small text-muted mt-2" style="word-break: break-all; font-size: 0.75rem;">
                                        <strong>SHA-1:</strong> {{ $cert['fingerprint_sha1'] }}
                                    </div>
                                    @endif
                                </div>
                                @endif

                                <!-- Individual certificate download options -->
                                <div class="download-section">
                                    @php
                                        $sanitizedSubject = preg_replace('/[^a-zA-Z0-9.-]/', '_', $cert['subject']);
                                    @endphp
                                    <a href="{{ url('/chain-validator/download/' . $cert['id']) }}" 
                                       class="download-btn {{ $cert['cert_type'] }}"
                                       title="Download this {{ $cert['cert_type'] }} certificate as PEM file">
                                        <i class="bi bi-download"></i> 
                                        @if($cert['cert_type'] === 'root')
                                            Download Root CA (root.txt)
                                        @elseif($cert['cert_type'] === 'intermediate')
                                            Download Intermediate (intermediate.txt)
                                        @else
                                            Download {{ $cert['cert_type'] }} ({{ $sanitizedSubject }}.txt)
                                        @endif
                                    </a>
                                </div>

                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <div class="mt-5 text-center">
                <a href="{{ url('/chain-validator') }}" class="btn btn-outline-primary rounded-pill px-4 py-2 me-3 fw-bold">
                    <i class="bi bi-arrow-repeat me-2"></i> Validate Another
                </a>
                <a href="{{ url('/chain-validator/download-bundle') }}" class="btn btn-success rounded-pill px-4 py-2 fw-bold" style="border: none;">
                    <i class="bi bi-download me-2"></i> Download Complete Bundle
                </a>
            </div>
        @else
            <form method="POST" action="{{ url('/chain-validator/parse') }}" enctype="multipart/form-data" data-aos="fade-up">
                @csrf
                <div class="mb-4">
                    <label class="fw-bold mb-3 d-flex align-items-center gap-2 fs-5">
                        <i class="bi bi-file-earmark-diff-fill"></i> Upload Bundle or Paste Certificate Chain (PEM format)
                    </label>
                    <div class="mb-3">
                        <input class="form-control form-control-lg" style="border-radius: 20px; padding: 15px 20px; border: 2px solid rgba(0,0,0,0.1);" 
                               type="file" name="chain_file" accept=".pem,.crt,.cer,.txt">
                    </div>
                    <div class="text-center my-3 fw-bold text-muted">OR</div>
                    <textarea name="chain_bundle" class="form-control" 
                              style="border-radius: 25px; padding: 20px; border: 2px solid rgba(0,0,0,0.1); font-family: monospace; font-size: 0.9rem;" 
                              rows="10" 
                              placeholder="-----BEGIN CERTIFICATE-----&#10;(End-Entity Server Certificate)&#10;-----END CERTIFICATE-----&#10;&#10;-----BEGIN CERTIFICATE-----&#10;(Intermediate CA)&#10;-----END CERTIFICATE-----&#10;&#10;-----BEGIN CERTIFICATE-----&#10;(Root CA)&#10;-----END CERTIFICATE-----">{{ old('chain_bundle') }}</textarea>
                    <div class="form-text mt-2">
                        <i class="bi bi-info-circle me-2"></i> 
                        Paste your full certificate chain (leaf → intermediate(s) → root). 
                        Certificates can be in any order - they will be automatically sorted.
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-100 rounded-pill py-3 fw-bold" 
                        style="background: var(--gradient-primary); border: none; font-size: 1.1rem; box-shadow: 0 10px 20px rgba(0,0,0,0.15); transition: transform 0.3s;">
                    <i class="bi bi-diagram-3 me-2"></i> Analyze Certificate Chain 
                    <i class="bi bi-arrow-right ms-2"></i>
                </button>
            </form>
        @endif
    </div>
</div>

@endsection

@section('scripts')
<script>
    // Validate form on submit
    document.addEventListener('DOMContentLoaded', function() {
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const textarea = form.querySelector('textarea[name="chain_bundle"]');
                const fileInput = form.querySelector('input[name="chain_file"]');
                
                if (textarea && !textarea.value.trim() && (!fileInput || !fileInput.files.length)) {
                    e.preventDefault();
                    alert('Please either paste a certificate chain or select a file.');
                    return false;
                }
            });
        });
    });
</script>
@endsection

@section('scripts')
<script>
    // Validate form on submit
    document.addEventListener('DOMContentLoaded', function() {
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const textarea = form.querySelector('textarea[name="chain_bundle"]');
                const fileInput = form.querySelector('input[name="chain_file"]');
                
                if (textarea && !textarea.value.trim() && (!fileInput || !fileInput.files.length)) {
                    e.preventDefault();
                    alert('Please either paste a certificate chain or select a file.');
                    return false;
                }
            });
        });
    });
</script>
@endsection
