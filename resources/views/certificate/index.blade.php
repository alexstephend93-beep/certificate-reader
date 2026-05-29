@extends('layouts.app')

@section('title', 'Certificate Reader | Network Tools')

@section('styles')
<style>
    .success-alert { background: linear-gradient(135deg, rgba(46, 204, 113, 0.2), rgba(52, 152, 219, 0.2)); border-left: 6px solid #2ecc71; border-radius: 20px; padding: 20px 25px; margin-bottom: 30px; animation: slideInDown 0.5s ease; word-break: break-word; }
    .parse-another-btn { background: var(--gradient-primary); border: none; border-radius: 60px; padding: 15px 35px; color: white; font-weight: 600; font-size: clamp(0.9rem, 2vw, 1rem); text-decoration: none; display: inline-flex; align-items: center; gap: 12px; box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2); transition: var(--transition-smooth); animation: bounce 2s infinite; white-space: nowrap; }
    .parse-another-btn:hover { transform: translateY(-3px) scale(1.05); box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3); color: white; }
    .download-card { background: white; border-radius: 30px; padding: clamp(20px, 4vw, 30px); text-decoration: none; color: var(--color-dark); transition: var(--transition-smooth); border: 2px solid transparent; height: 100%; display: flex; flex-direction: column; box-shadow: 0 15px 30px -10px rgba(0, 0, 0, 0.1); }
    .download-card:hover { border-color: var(--color-primary); transform: translateY(-5px); box-shadow: 0 30px 40px -15px var(--color-primary); }
    .download-card .filename { background: var(--color-light); padding: 12px 15px; border-radius: 15px; font-family: 'SF Mono', 'Courier New', monospace; font-size: clamp(0.75rem, 1.5vw, 0.9rem); word-break: break-all; margin-top: 10px; border: 1px solid rgba(0, 0, 0, 0.1); }
    .result-card { background: white; border-radius: 25px; padding: clamp(20px, 3vw, 25px); box-shadow: 0 15px 30px -10px rgba(0, 0, 0, 0.1); transition: var(--transition-smooth); height: 100%; border: 1px solid rgba(0, 0, 0, 0.05); word-wrap: break-word; overflow-wrap: break-word; }
    .result-card:hover { transform: translateY(-5px); box-shadow: 0 25px 40px -15px var(--color-primary); }
    .result-card h6 { font-size: clamp(0.75rem, 1.5vw, 0.85rem); letter-spacing: 0.05em; color: var(--color-primary); margin-bottom: 15px; }
    .result-card .card-value { font-size: clamp(0.95rem, 2vw, 1.1rem); font-weight: 500; line-height: 1.5; word-break: break-word; overflow-wrap: break-word; hyphens: auto; }
    .cert-type-badge { display: inline-flex; align-items: center; gap: 12px; padding: 15px 35px; border-radius: 60px; font-size: clamp(1rem, 2.5vw, 1.3rem); font-weight: 700; background: var(--gradient-primary); color: white; box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2); animation: pulse 2s infinite; max-width: 100%; flex-wrap: wrap; justify-content: center; }
    .fingerprint-box { background: var(--gradient-primary); border-radius: 20px; padding: 20px; color: white; word-break: break-all; transition: var(--transition-smooth); height: 100%; }
    .fingerprint-box .fingerprint-hash { font-family: 'SF Mono', 'Courier New', monospace; font-size: clamp(0.8rem, 1.5vw, 0.9rem); word-break: break-all; background: rgba(0, 0, 0, 0.2); padding: 10px; border-radius: 10px; margin-top: 10px; }
    .info-section { background: var(--gradient-primary); border-radius: 30px; padding: 30px; color: white; margin-top: 40px; }
    .info-section code { background: rgba(0, 0, 0, 0.2); padding: 8px 15px; border-radius: 12px; display: inline-block; max-width: 100%; word-break: break-all; font-size: clamp(0.8rem, 1.5vw, 0.9rem); color: #ffffff; font-weight: bold; }
    .openssl-section { background: var(--color-dark); border-radius: 40px; padding: clamp(25px, 4vw, 40px); color: white; margin-top: 40px; }
    .cmd-card { background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); border-radius: 25px; padding: 20px; height: 100%; transition: var(--transition-smooth); border: 1px solid rgba(255, 255, 255, 0.2); }
    .cmd-card:hover { transform: translateY(-5px); background: rgba(255, 255, 255, 0.15); border-color: var(--color-accent); }
    .cmd-block { background: rgba(0, 0, 0, 0.3); border-radius: 15px; padding: 15px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .cmd-block code { flex: 1; font-family: 'SF Mono', 'Courier New', monospace; font-size: clamp(0.7rem, 1.5vw, 0.8rem); color: var(--color-accent); word-break: break-all; }
    .copy-btn { background: rgba(255, 255, 255, 0.2); border: none; color: white; padding: 6px 15px; border-radius: 8px; font-size: 0.8rem; cursor: pointer; transition: var(--transition-smooth); white-space: nowrap; }
    .copy-btn:hover { background: var(--color-primary); transform: scale(1.05); }
    textarea { border-radius: 25px !important; padding: 20px !important; border: 2px solid rgba(0, 0, 0, 0.1) !important; font-family: 'SF Mono', 'Courier New', monospace !important; font-size: clamp(0.85rem, 2vw, 0.95rem) !important; resize: vertical !important; min-height: 200px; transition: var(--transition-smooth) !important; }
    textarea:focus { border-color: var(--color-primary) !important; box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.1) !important; }
    .submit-btn { background: var(--gradient-primary); border: none; border-radius: 25px; padding: 18px; font-weight: 600; font-size: clamp(1rem, 2vw, 1.1rem); color: white; transition: var(--transition-smooth); width: 100%; }
    .submit-btn:hover { transform: translateY(-3px); box-shadow: 0 20px 30px -10px var(--color-primary); }
    @keyframes bounce { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-5px); } }
    @keyframes pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.05); } }
    .modal-xl {
        max-width: 1300px;
    }
</style>
@endsection

@section('content')
<div class="glass-card" data-aos="fade-down" data-aos-duration="1000">
    <div class="gradient-header text-center">
        <h1 class="fw-bold"><i class="bi bi-shield-lock-fill me-3"></i> Certificate Parser</h1>
        <p class="lead mb-0"><i class="bi bi-diagram-3 me-2"></i> Parse X.509 certificates and extract domain details</p>
    </div>

    <div class="p-4 p-md-5">
        @if (session('error'))
        <div class="alert alert-danger border-0 rounded-4 d-flex align-items-center gap-3" role="alert" data-aos="fade-up">
            <i class="bi bi-exclamation-triangle-fill fs-3"></i>
            <span class="fw-semibold">{{ session('error') }}</span>
        </div>
        @endif

        @if (session('success') && session()->has('cert_data'))
            @php
                $parsedData = session('cert_data');
                $sanitizedDomain = preg_replace('/[^a-zA-Z0-9.-]/', '_', session('domain_name', 'certificate'));
                $certFilename = $sanitizedDomain . '_cert.txt';
                $detailsFilename = $sanitizedDomain . '_details.txt';
            @endphp

            <div class="text-end mb-4" data-aos="fade-left">
                <button type="button" class="btn btn-outline-primary" onclick="openDomainModal()">
                    <i class="bi bi-globe2 me-2"></i> Check Certificate by Domain
                </button>
            </div>

            <div class="success-alert d-flex align-items-center gap-3" data-aos="fade-down">
                <i class="bi bi-check-circle-fill fs-2 text-success"></i>
                <div>
                    <h5 class="fw-bold mb-1">🔑 Certificate Parsed Successfully!</h5>
                    <p class="mb-0">Download options are available below.</p>
                </div>
            </div>

            <div class="text-end mb-5" data-aos="fade-left">
                <a href="{{ url('/certificate') }}" class="parse-another-btn">
                    <i class="bi bi-arrow-repeat fs-5"></i> Parse Another Certificate <i class="bi bi-box-arrow-up-right ms-2"></i>
                </a>
            </div>

            <div class="row g-4 mb-5">
                <div class="col-12 col-md-6" data-aos="fade-right">
                    <a href="{{ url('/certificate/download/cert') }}" class="download-card">
                        <div class="d-flex align-items-start gap-4">
                            <div class="bg-light p-4 rounded-circle flex-shrink-0">
                                <i class="bi bi-file-lock2-fill fs-1" style="color: var(--color-primary);"></i>
                            </div>
                            <div class="flex-grow-1 min-width-0">
                                <h3 class="h4 fw-bold mb-2">Download Certificate</h3>
                                <p class="small opacity-75 mb-2">Original PEM certificate file</p>
                                <div class="filename">📄 {{ $certFilename }}</div>
                            </div>
                            <i class="bi bi-download fs-1 opacity-50 flex-shrink-0"></i>
                        </div>
                    </a>
                </div>
                <div class="col-12 col-md-6" data-aos="fade-left">
                    <a href="{{ url('/certificate/download/details') }}" class="download-card">
                        <div class="d-flex align-items-start gap-4">
                            <div class="bg-light p-4 rounded-circle flex-shrink-0">
                                <i class="bi bi-file-text-fill fs-1" style="color: var(--color-secondary);"></i>
                            </div>
                            <div class="flex-grow-1 min-width-0">
                                <h3 class="h4 fw-bold mb-2">Download Details</h3>
                                <p class="small opacity-75 mb-2">Parsed certificate information</p>
                                <div class="filename">📄 {{ $detailsFilename }}</div>
                            </div>
                            <i class="bi bi-download fs-1 opacity-50 flex-shrink-0"></i>
                        </div>
                    </a>
                </div>
            </div>

            <h3 class="fw-bold mb-4 d-flex align-items-center gap-2" data-aos="fade-up">
                <i class="bi bi-file-earmark-text-fill fs-1"></i> Extracted Certificate Details
            </h3>

            <div class="row g-4">
                <div class="col-12" data-aos="fade-up">
                    <div class="result-card text-center">
                        <h6 class="fw-bold text-uppercase"><i class="bi bi-patch-check-fill me-2"></i> Certificate Type</h6>
                        <div class="cert-type-badge">
                            {!! $parsedData['Cert_Type_Icon'] !!} {{ $parsedData['Cert_Type'] }}
                        </div>
                        <div class="mt-3 text-muted">
                            <i class="bi bi-info-circle-fill me-2"></i> {{ $parsedData['Cert_Type_Desc'] }}
                        </div>
                    </div>
                </div>

                <div class="col-12" data-aos="fade-up" data-aos-delay="50">
                    <div class="result-card text-center">
                        <h6 class="fw-bold text-uppercase"><i class="bi bi-shield-check me-2"></i> SSL Validation Level</h6>
                        <div class="cert-type-badge text-dark" style="background: rgba(0,0,0,0.05); box-shadow: none; border: 1px solid rgba(0,0,0,0.1);">
                            {!! $parsedData['Validation_Level_Icon'] !!} {{ $parsedData['Validation_Level'] }}
                        </div>
                        <div class="mt-3 text-muted">
                            <i class="bi bi-info-circle-fill me-2"></i> {{ $parsedData['Validation_Level_Desc'] }}
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-lg-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="result-card">
                        <h6 class="fw-bold text-uppercase"><i class="bi bi-person-fill me-2"></i> Common Name</h6>
                        <div class="card-value">{{ $parsedData['Common_Name'] ?? 'N/A' }}</div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-lg-4" data-aos="fade-up" data-aos-delay="150">
                    <div class="result-card">
                        <h6 class="fw-bold text-uppercase"><i class="bi bi-globe2 me-2"></i> SAN</h6>
                        <div class="card-value">{{ $parsedData['SAN'] ?? 'N/A' }}</div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-lg-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="result-card">
                        <h6 class="fw-bold text-uppercase"><i class="bi bi-building me-2"></i> Organization</h6>
                        <div class="card-value">{{ $parsedData['Organization'] ?? 'N/A' }}</div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-lg-4" data-aos="fade-up" data-aos-delay="250">
                    <div class="result-card">
                        <h6 class="fw-bold text-uppercase"><i class="bi bi-geo-alt-fill me-2"></i> Location</h6>
                        <div class="card-value">
                            @php
                                $location = [];
                                if (!empty($parsedData['City'])) $location[] = $parsedData['City'];
                                if (!empty($parsedData['State'])) $location[] = $parsedData['State'];
                                if (!empty($parsedData['Country'])) $location[] = $parsedData['Country'];
                            @endphp
                            {{ implode(', ', $location) ?: 'N/A' }}
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-lg-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="result-card">
                        <h6 class="fw-bold text-uppercase"><i class="bi bi-diagram-2 me-2"></i> Organizational Unit</h6>
                        <div class="card-value">{{ $parsedData['Organization_Unit'] ?? 'N/A' }}</div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-lg-4" data-aos="fade-up" data-aos-delay="350">
                    <div class="result-card">
                        <h6 class="fw-bold text-uppercase"><i class="bi bi-pin-map-fill me-2"></i> Address Details</h6>
                        <div class="card-value">
                            @php
                                $address = [];
                                if (!empty($parsedData['Address']) && $parsedData['Address'] !== 'NA') $address[] = $parsedData['Address'];
                                if (!empty($parsedData['Postal_Code']) && $parsedData['Postal_Code'] !== 'NA') $address[] = $parsedData['Postal_Code'];
                            @endphp
                            {{ implode(', ', $address) ?: 'N/A' }}
                        </div>
                    </div>
                </div>

                @if(!empty($parsedData['ip_address']))
                <div class="col-12 col-sm-6 col-lg-4" data-aos="fade-up" data-aos-delay="400">
                    <div class="result-card">
                        <h2 class="fw-bold" style="font-size:1.6rem; letter-spacing:0.02em; animation: ipPulse 1.2s ease-in-out infinite;">
                            <i class="bi bi-hdd-network-fill me-2"></i> IP Address
                        </h2>
                        <div class="card-value" style="font-weight:900; font-size:1.8rem;">{{ $parsedData['ip_address'] }}</div>
                    </div>
                </div>
                @endif
            </div>

            @if (!empty($parsedData['additional_info']))
            <div class="openssl-section" data-aos="fade-up" style="margin-top: 40px;">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <i class="bi bi-card-list fs-1 text-accent" style="color: var(--color-accent);"></i>
                    <div>
                        <h3 class="h3 fw-bold mb-1">Extended Certificate Details</h3>
                        <p class="small opacity-75 mb-0">In-depth technical properties and extensions</p>
                    </div>
                </div>
                <div class="row g-4">
                    @php $delay = 100; @endphp
                    @foreach ($parsedData['additional_info'] as $label => $value)
                    <div class="col-12 col-sm-6 col-lg-4" data-aos="fade-up" data-aos-delay="{{ $delay }}">
                        <div class="cmd-card">
                            <h6 class="fw-bold text-uppercase mb-3" style="color: var(--color-accent);">
                                <i class="bi bi-asterisk me-2"></i> {{ $label }}
                            </h6>
                            <div class="font-monospace small fw-bold text-break">{{ $value }}</div>
                        </div>
                    </div>
                    @php
                        $delay += 50;
                        if ($delay > 400) $delay = 100;
                    @endphp
                    @endforeach
                </div>
            </div>
            @endif

            @if (!empty($parsedData['fingerprint']))
            <div class="row g-4 mt-4" data-aos="fade-up">
                @foreach ($parsedData['fingerprint'] as $algo => $hash)
                <div class="col-12 col-md-6">
                    <div class="fingerprint-box">
                        <small class="text-uppercase opacity-75">{{ strtoupper($algo) }} Fingerprint</small>
                        <div class="fingerprint-hash">{{ $hash }}</div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif

            <div class="info-section" data-aos="fade-up">
                <h4 class="fw-bold mb-4 d-flex align-items-center gap-2"><i class="bi bi-info-circle-fill"></i> Download Information</h4>
                <div class="row g-4">
                    <div class="col-12 col-md-6">
                        <div class="d-flex align-items-start gap-3">
                            <i class="bi bi-file-earmark-lock2 fs-2 flex-shrink-0"></i>
                            <div class="min-width-0">
                                <strong>Certificate File:</strong><br>
                                <code>{{ $certFilename }}</code><br>
                                <span class="small opacity-75">Original PEM certificate</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="d-flex align-items-start gap-3">
                            <i class="bi bi-file-earmark-text fs-2 flex-shrink-0"></i>
                            <div class="min-width-0">
                                <strong>Details File:</strong><br>
                                <code>{{ $detailsFilename }}</code><br>
                                <span class="small opacity-75">Parsed certificate details</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="text-end mb-4" data-aos="fade-left">
                <button type="button" class="btn btn-outline-primary" onclick="openDomainModal()">
                    <i class="bi bi-globe2 me-2"></i> Check Certificate by Domain
                </button>
            </div>

            <form method="POST" action="{{ url('/certificate/parse') }}" enctype="multipart/form-data" data-aos="fade-up">
                @csrf
                <div class="mb-4">
                    <label class="fw-bold mb-3 d-flex align-items-center gap-2 fs-5">
                        <i class="bi bi-file-earmark-lock-fill"></i> Upload Certificate File or Paste Content (PEM format)
                    </label>
                    <div class="mb-3">
                        <input class="form-control form-control-lg" style="border-radius: 20px; padding: 15px 20px; border: 2px solid rgba(0,0,0,0.1);" type="file" name="cert_file" accept=".pem,.crt,.cer,.txt">
                    </div>
                    <div class="text-center my-3 fw-bold text-muted">OR</div>
                    <textarea name="certificate" class="form-control" rows="8" placeholder="-----BEGIN CERTIFICATE-----&#10;MIIFazCCA1OgAwIBAgIUGFfQj2WmMBi6wYAklE7E8F4V3b8w...&#10;-----END CERTIFICATE-----">{{ old('certificate') }}</textarea>
                    <div class="form-text mt-2"><i class="bi bi-info-circle me-2"></i> Upload a file OR paste the entire certificate including BEGIN and END lines</div>
                </div>
                <button type="submit" class="submit-btn"><i class="bi bi-shield-shaded me-2"></i> Parse Certificate <i class="bi bi-arrow-right ms-2"></i></button>
            </form>
        @endif
    </div>
</div>

    <!-- Domain Lookup Modal -->
    <div class="modal fade" id="domainLookupModal" tabindex="-1" data-bs-backdrop="static" aria-labelledby="domainLookupModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" id="domainLookupModalDialog">
            <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header" style="background: var(--gradient-primary); border-bottom: none;">
                <h5 class="modal-title text-white" id="domainLookupModalLabel">
                    <i class="bi bi-globe2 me-2"></i> Check Certificate by Domain
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding: 30px;">
                <form id="domainLookupForm">
                    <div id="domainLookupResultTop" class="mb-3" style="display:none;">
                        <div class="result-card text-center" style="padding: 22px 18px; border-radius: 25px; border: 1px solid rgba(0,0,0,0.08);">
                            <h6 class="fw-bold text-uppercase" style="font-size: 1.05rem; letter-spacing: 0.03em; margin-bottom: 10px;">
                                <i class="bi bi-hdd-network-fill me-2"></i> IP Address
                            </h6>
                            <div id="domainLookupResultIP" class="card-value" style="font-size: 1.25rem; font-weight: 700;">N/A</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Domain Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="lookupDomain" placeholder="example.com, https://example.com" required>
                        <small class="text-muted">Enter a domain (with or without protocol)</small>
                    </div>
                    <div id="domainLookupError" class="alert alert-danger d-none"></div>
                    <div id="domainLookupLoading" class="text-center py-3 d-none">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2 mb-0">Fetching certificate...</p>
                    </div>
                </form>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #e2e8f0;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="lookupDomainCert()">Fetch Certificate</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    function copyCmd(btn) {
        const code = btn.previousElementSibling.textContent.trim();
        navigator.clipboard.writeText(code).then(() => {
            const originalText = btn.textContent;
            btn.textContent = 'Copied!';
            btn.classList.add('bg-success');
            setTimeout(() => { btn.textContent = originalText; btn.classList.remove('bg-success'); }, 2000);
        });
    }

    function downloadCertificatePem(pemContent, filename) {
        const blob = new Blob([pemContent], { type: 'application/x-pem-file' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
    }

    function handleDownloadPublicCertFromDataset() {
        const btn = document.getElementById('downloadPublicCertBtn');
        if (!btn) return;
        const pem = btn.getAttribute('data-pem') || '';
        const filename = btn.getAttribute('data-filename') || 'certificate_public_cert.pem';

        // data-pem may contain encoded HTML entities due to our escaping.
        const ta = document.createElement('textarea');
        ta.innerHTML = pem;
        const decodedPem = ta.value;

        if (!decodedPem || decodedPem.trim().length < 10) return;
        downloadCertificatePem(decodedPem, filename);
    }

function openDomainModal() {
    const modal = new bootstrap.Modal(document.getElementById('domainLookupModal'));
    // Reset modal size to default
    const modalDialog = document.getElementById('domainLookupModalDialog');
    if (modalDialog) {
        modalDialog.classList.remove('modal-sm', 'modal-lg', 'modal-xl');
    }
    // Clear previous results
    const resultsContainer = document.getElementById('domainLookupResultContainer');
    if (resultsContainer) {
        resultsContainer.remove();
    }
    document.getElementById('lookupDomain').value = '';
    document.getElementById('domainLookupError').classList.add('d-none');
    modal.show();
}

    function lookupDomainCert() {
        const domain = document.getElementById('lookupDomain').value.trim();
        const errorEl = document.getElementById('domainLookupError');
        const loadingEl = document.getElementById('domainLookupLoading');

        if (!domain) {
            errorEl.textContent = 'Please enter a domain name.';
            errorEl.classList.remove('d-none');
            return;
        }

        errorEl.classList.add('d-none');
        loadingEl.classList.remove('d-none');

        fetch('{{ route('certificate.check-domain') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ domain: domain })
        })
        .then(response => {
            loadingEl.classList.add('d-none');
            if (!response.ok) {
                return response.text().then(text => {
                    throw new Error('Server error: ' + response.status);
                });
            }
            return response.json();
        })
        .then(data => {
            loadingEl.classList.add('d-none');
            if (data.success) {
                // Render returned certificate details inside the page (same sections used after session parse)
                // We simply replace the current DOM using server-rendered template by reloading with data via hidden form is avoided.
                // For now, we show a lightweight client-side render in the modal.

                const modalEl = document.getElementById('domainLookupModal');
                const modal = bootstrap.Modal.getInstance(modalEl);

                const cert = data.cert_data || {};

                const fmt = (v) => (v === null || v === undefined || v === '') ? 'N/A' : v;

                const err = document.getElementById('domainLookupError');
                err.classList.add('d-none');

                // Ensure there is a container
                let container = document.getElementById('domainLookupResultContainer');
                if (!container) {
                    container = document.createElement('div');
                    container.id = 'domainLookupResultContainer';
                    container.style.marginTop = '20px';
                    modalEl.querySelector('.modal-body').appendChild(container);
                }

                const additional = (cert.additional_info && typeof cert.additional_info === 'object') ? Object.entries(cert.additional_info) : [];
                const pemData = data.pem || data.cert_data?.public_cert_pem || data.pemCert || '';
                const fileName = (data.domain || 'certificate') + '_public_cert.pem';
                const onClickStr = `downloadCertificatePem(${JSON.stringify(pemData)}, ${JSON.stringify(fileName)})`;

                container.innerHTML = `
                    <div class="result-card text-center" style="margin-top: 10px; padding: 22px 18px;">
                        <h2 style="font-size: 1.35rem; font-weight: 900; letter-spacing: 0.02em; margin-bottom: 10px;">
                            <i class="bi bi-hdd-network-fill me-2"></i> IP Address
                        </h2>
                        <div class="card-value" style="font-size: 1.85rem; font-weight: 900;">
                            ${fmt(cert.ip_address)}
                        </div>
                        <div class="small text-muted mt-2" style="font-size: 0.95rem;">Public IP resolved for the domain</div>
                    </div>

                    <div class="result-card text-center mt-3" style="padding: 22px 18px;">
                        <h6 class="fw-bold text-uppercase" style="font-size: 1rem;"> <i class="bi bi-patch-check-fill me-2"></i> Certificate Type</h6>
                        <div class="cert-type-badge" style="font-size: 1.35rem;">${fmt(cert.Cert_Type_Icon)} ${fmt(cert.Cert_Type)}</div>
                        <div class="mt-3 text-muted">${fmt(cert.Cert_Type_Desc)}</div>
                    </div>

                    <div class="result-card text-center mt-3" style="padding: 22px 18px;">
                        <h6 class="fw-bold text-uppercase" style="font-size: 1rem;"> <i class="bi bi-shield-check me-2"></i> SSL Validation Level</h6>
                        <div class="cert-type-badge text-dark" style="background: rgba(0,0,0,0.05); box-shadow: none; border: 1px solid rgba(0,0,0,0.1); font-size: 1.35rem;">
                            ${fmt(cert.Validation_Level_Icon)} ${fmt(cert.Validation_Level)}
                        </div>
                        <div class="mt-3 text-muted">${fmt(cert.Validation_Level_Desc)}</div>
                    </div>

                    <div class="row g-4 mt-4">
                        <div class="col-12 col-sm-6 col-lg-4"><div class="result-card"><h6 class="fw-bold text-uppercase"><i class="bi bi-person-fill me-2"></i> Common Name</h6><div class="card-value">${fmt(cert.Common_Name)}</div></div></div>
                        <div class="col-12 col-sm-6 col-lg-4"><div class="result-card"><h6 class="fw-bold text-uppercase"><i class="bi bi-globe2 me-2"></i> SAN</h6><div class="card-value">${fmt(cert.SAN)}</div></div></div>
                        <div class="col-12 col-sm-6 col-lg-4"><div class="result-card"><h6 class="fw-bold text-uppercase"><i class="bi bi-building me-2"></i> Organization</h6><div class="card-value">${fmt(cert.Organization)}</div></div></div>
                    </div>

                    ${cert.reverse_domains && Array.isArray(cert.reverse_domains) && cert.reverse_domains.length
                      ? `<div class="result-card mt-3"><h6 class="fw-bold text-uppercase"><i class="bi bi-diagram-3 me-2"></i> Domain(s) for IP</h6><div class="card-value">${cert.reverse_domains.map(d => fmt(d)).join(', ')}</div></div>`
                      : ''}

                    ${cert.ip_address ? `<div class="result-card mt-3"><h6 class="fw-bold text-uppercase"><i class="bi bi-hdd-network-fill me-2"></i> IP Address</h6><div class="card-value">${fmt(cert.ip_address)}</div></div>` : ''}

                    ${additional.length ? `
                        <div class="openssl-section" style="margin-top: 30px;">
                            <h3 class="h3 fw-bold mb-3"><i class="bi bi-card-list fs-1 text-accent" style="color: var(--color-accent);"></i> Extended Certificate Details</h3>
                            <div class="row g-4">${additional.slice(0, 12).map(function (pair) {
                                const k = pair[0];
                                const v = pair[1];
                                return `<div class="col-12 col-sm-6 col-lg-4"><div class="cmd-card"><h6 class="fw-bold text-uppercase mb-3" style="color: var(--color-accent);"> <i class="bi bi-asterisk me-2"></i> ${fmt(k)}</h6><div class="font-monospace small fw-bold text-break">${fmt(v)}</div></div></div>`;
                            }).join('')}</div>
                            ${additional.length > 12 ? `<p class="small opacity-75 mt-3 mb-0">Showing first 12 fields.</p>` : ''}
                        </div>
                    ` : ''}



                    <div class="text-end mt-3">
                        <button type="button" class="btn btn-success me-2" id="downloadPublicCertBtn" data-pem='${String(pemData || "").replace(/'/g, "&#39;")}' data-filename='${String(fileName || "certificate_public_cert.pem").replace(/'/g, "&#39;")}' onclick="handleDownloadPublicCertFromDataset()">📥 Download Public Certificate</button>
                        <button type="button" class="btn btn-secondary" onclick="(function(){
                            bootstrap.Modal.getInstance(document.getElementById('domainLookupModal')).hide();
                        })()">Close</button>
                    </div>
                `;

                 // Change modal size to xl on success
                 const modalDialog = document.getElementById('domainLookupModalDialog');
                 if (modalDialog) {
                     // Remove any existing size classes (modal-sm, modal-lg, modal-xl) and add modal-xl
                     modalDialog.classList.remove('modal-sm', 'modal-lg', 'modal-xl');
                     modalDialog.classList.add('modal-xl');
                 }

                 // Replace current page state by scrolling to top so user sees updated content; keep modal open with results.
                 // If you want the whole page to match upload-state exactly, we would need to rebuild full page HTML server-side.
                 return;
            } else {
                errorEl.textContent = data.error || 'Failed to fetch certificate.';
                errorEl.classList.remove('d-none');
            }
        })
        .catch(error => {
            loadingEl.classList.add('d-none');
            errorEl.textContent = 'Connection error: ' + error.message;
            errorEl.classList.remove('d-none');
        });
    }
</script>
@endsection