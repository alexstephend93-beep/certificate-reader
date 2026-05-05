@extends('layouts.app')

@section('title', 'JWT Token Analyzer | NetTools')

@section('styles')
<style>
    .part-indicator { display: inline-flex; align-items: center; gap: 8px; font-size: 0.85rem; font-weight: 600; padding: 5px 14px; border-radius: 20px; }
    .part-header  { background: rgba(168, 85, 247, 0.15); color: #7c3aed; }
    .part-payload { background: rgba(var(--bs-success-rgb), 0.12); color: #059669; }
    .part-sig     { background: rgba(var(--bs-danger-rgb), 0.12); color: #dc2626; }

    .jwt-visual { font-family: 'SF Mono', monospace; font-size: 0.85rem; word-break: break-all; padding: 20px; background: #0f172a; color: #e2e8f0; border-radius: 16px; line-height: 1.8; }
    .jwt-visual .h { color: #a78bfa; }
    .jwt-visual .p { color: #34d399; }
    .jwt-visual .s { color: #f87171; }
    .jwt-visual .dot { color: #64748b; }

    .result-panel { border-radius: 20px; border: 1px solid rgba(0,0,0,0.06); background: white; padding: 25px; }
    .result-panel h5 { font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.07em; font-weight: 700; margin-bottom: 15px; }

    .json-pre { background: #f8fafc; border-radius: 12px; padding: 18px; font-family: 'SF Mono', monospace; font-size: 0.82rem; color: #1e293b; overflow-x: auto; white-space: pre-wrap; word-break: break-word; border: 1px solid #e2e8f0; }

    .claim-badge { display: inline-flex; flex-direction: column; align-items: center; background: #f1f5f9; border-radius: 14px; padding: 10px 18px; min-width: 110px; }
    .claim-badge .label { font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; }
    .claim-badge .value { font-size: 0.95rem; font-weight: 700; color: #1e293b; margin-top: 4px; }

    .expiry-banner { border-radius: 18px; padding: 18px 25px; display: flex; align-items: center; gap: 15px; }
    .expiry-valid   { background: rgba(16, 185, 129, 0.1); border: 2px solid #10b981; }
    .expiry-expired { background: rgba(239, 68, 68, 0.1);  border: 2px solid #ef4444; }
    .expiry-no-exp  { background: rgba(148, 163, 184, 0.1); border: 2px solid #94a3b8; }
</style>
@endsection

@section('content')
<div class="glass-card" data-aos="fade-down">
    <div class="gradient-header text-center">
        <h1 class="fw-bold"><i class="bi bi-braces-asterisk me-3"></i> JWT Token Analyzer</h1>
        <p class="lead mb-0">Decode and inspect JSON Web Tokens — header, payload, signature & expiry</p>
    </div>

    <div class="p-4 p-md-5">
        @if(session('error'))
        <div class="alert alert-danger rounded-4 border-0 d-flex align-items-center gap-3 mb-4">
            <i class="bi bi-exclamation-octagon-fill fs-4"></i>
            <span class="fw-medium">{{ session('error') }}</span>
        </div>
        @endif

        <form action="{{ url('/jwt/analyze') }}" method="POST">
            @csrf
            <div class="mb-4">
                <label class="fw-bold mb-2 d-flex align-items-center gap-2">
                    <i class="bi bi-three-dots text-primary"></i> Paste Your JWT Token
                </label>
                <textarea name="token" class="form-control font-monospace" rows="4"
                    placeholder="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c"
                    required>{{ old('token') }}</textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5 fw-bold w-100">
                <i class="bi bi-search me-2"></i> Analyze Token
            </button>
        </form>

        @php $result = session('jwt_result'); @endphp
        @if($result)
        <div class="mt-5">
            {{-- Visual Token Breakdown --}}
            <div class="jwt-visual mb-4">
                @php $parts = explode('.', $result['original_token']); @endphp
                <span class="h">{{ $parts[0] ?? '' }}</span><span class="dot">.</span><span class="p">{{ $parts[1] ?? '' }}</span><span class="dot">.</span><span class="s">{{ $parts[2] ?? '' }}</span>
            </div>
            <div class="d-flex flex-wrap gap-2 mb-5">
                <span class="part-indicator part-header"><i class="bi bi-square-fill"></i> Header</span>
                <span class="part-indicator part-payload"><i class="bi bi-square-fill"></i> Payload</span>
                <span class="part-indicator part-sig"><i class="bi bi-square-fill"></i> Signature</span>
                <span class="ms-auto badge rounded-pill bg-primary px-4 py-2 fs-6">{{ $result['tokenType'] }} ({{ $result['algorithm'] }})</span>
            </div>

            {{-- Expiry Banner --}}
            @php
                $bannerClass = $result['expiry_status'] === 'valid' ? 'expiry-valid' : ($result['expiry_status'] === 'expired' ? 'expiry-expired' : 'expiry-no-exp');
                $bannerIcon  = $result['expiry_status'] === 'valid' ? 'bi-check-circle-fill text-success' : ($result['expiry_status'] === 'expired' ? 'bi-x-circle-fill text-danger' : 'bi-info-circle-fill text-secondary');
            @endphp
            <div class="expiry-banner {{ $bannerClass }} mb-5">
                <i class="bi {{ $bannerIcon }} fs-2"></i>
                <div>
                    <div class="fw-bold fs-5">{{ $result['expiry_status'] === 'no_exp' ? 'No Expiry Claim' : ($result['expiry_status'] === 'valid' ? 'Token Valid' : 'Token Expired') }}</div>
                    <div class="text-muted">{{ $result['expiry_message'] }}</div>
                    @if($result['expiry_time'])
                    <div class="small mt-1 fw-medium">Expires: {{ $result['expiry_time'] }}</div>
                    @endif
                </div>
            </div>

            {{-- Key claims --}}
            @if($result['issued_at'] || $result['not_before'] || isset($result['payload']['sub']) || isset($result['payload']['iss']))
            <div class="d-flex flex-wrap gap-3 mb-5">
                @if(isset($result['payload']['sub']))
                <div class="claim-badge"><span class="label">Subject</span><span class="value">{{ Str::limit($result['payload']['sub'], 20) }}</span></div>
                @endif
                @if(isset($result['payload']['iss']))
                <div class="claim-badge"><span class="label">Issuer</span><span class="value">{{ Str::limit($result['payload']['iss'], 20) }}</span></div>
                @endif
                @if($result['issued_at'])
                <div class="claim-badge"><span class="label">Issued At</span><span class="value" style="font-size:0.75rem">{{ $result['issued_at'] }}</span></div>
                @endif
                @if($result['not_before'])
                <div class="claim-badge"><span class="label">Not Before</span><span class="value" style="font-size:0.75rem">{{ $result['not_before'] }}</span></div>
                @endif
            </div>
            @endif

            {{-- Decoded Panels --}}
            <div class="row g-4">
                <div class="col-12 col-lg-6">
                    <div class="result-panel h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0 text-primary"><i class="bi bi-braces me-2"></i> HEADER</h5>
                            <a href="data:application/json;charset=utf-8,{{ rawurlencode($result['header_raw']) }}" download="jwt_header.json" class="btn btn-sm btn-outline-primary rounded-pill">
                                <i class="bi bi-download me-1"></i> JSON
                            </a>
                        </div>
                        <pre class="json-pre">{{ $result['header_raw'] }}</pre>
                    </div>
                </div>
                <div class="col-12 col-lg-6">
                    <div class="result-panel h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0 text-success"><i class="bi bi-braces me-2"></i> PAYLOAD</h5>
                            <a href="data:application/json;charset=utf-8,{{ rawurlencode($result['payload_raw']) }}" download="jwt_payload.json" class="btn btn-sm btn-outline-success rounded-pill">
                                <i class="bi bi-download me-1"></i> JSON
                            </a>
                        </div>
                        <pre class="json-pre">{{ $result['payload_raw'] }}</pre>
                    </div>
                </div>
                <div class="col-12">
                    <div class="result-panel">
                        <h5 class="text-danger"><i class="bi bi-fingerprint me-2"></i> SIGNATURE (Base64URL)</h5>
                        <div class="json-pre" style="word-break:break-all">{{ $result['signature'] }}</div>
                        <p class="mt-3 mb-0 text-muted small"><i class="bi bi-info-circle me-1"></i> Signature verification requires the secret key or public key and cannot be done client-side. This tool decodes and inspects the structure only.</p>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
