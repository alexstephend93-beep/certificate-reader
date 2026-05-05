@extends('layouts.app')

@section('title', 'HMAC Signature Generator | NetTools')

@section('styles')
<style>
    .algo-btn input[type="radio"] { display: none; }
    .algo-btn label { display: inline-flex; align-items: center; gap: 8px; padding: 10px 22px; border: 2px solid rgba(var(--bs-primary-rgb), 0.2); border-radius: 30px; cursor: pointer; font-weight: 600; transition: all 0.2s; }
    .algo-btn input:checked + label { background: var(--gradient-primary); color: white; border-color: transparent; box-shadow: 0 8px 20px rgba(var(--bs-primary-rgb), 0.3); }
    .algo-btn label:hover { border-color: var(--color-primary); color: var(--color-primary); }

    .sig-card { background: white; border-radius: 20px; border: 1px solid rgba(0,0,0,0.06); padding: 22px 25px; }
    .sig-label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.07em; font-weight: 700; color: var(--color-primary); margin-bottom: 8px; display: block; }
    .sig-value { font-family: 'SF Mono', monospace; font-size: 0.85rem; background: #f8fafc; border: 1px solid #e2e8f0; padding: 14px; border-radius: 12px; word-break: break-all; display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; }

    .verify-match    { background: rgba(16, 185, 129, 0.1); border: 2px solid #10b981; color: #059669; padding: 14px 20px; border-radius: 16px; }
    .verify-mismatch { background: rgba(239, 68, 68, 0.1);  border: 2px solid #ef4444; color: #dc2626; padding: 14px 20px; border-radius: 16px; }
</style>
@endsection

@section('content')
<div class="glass-card" data-aos="fade-down">
    <div class="gradient-header text-center">
        <h1 class="fw-bold"><i class="bi bi-pen-fill me-3"></i> HMAC Signature Generator</h1>
        <p class="lead mb-0">Generate & verify API signatures with HMAC — the standard for payment gateways and webhooks</p>
    </div>

    <div class="p-4 p-md-5">
        @if(session('error'))
        <div class="alert alert-danger rounded-4 border-0 d-flex align-items-center gap-3 mb-4">
            <i class="bi bi-exclamation-octagon-fill fs-4"></i>
            <span class="fw-medium">{{ session('error') }}</span>
        </div>
        @endif

        <form action="{{ url('/hmac/generate') }}" method="POST">
            @csrf
            <div class="row g-4 mb-4">
                <div class="col-12 col-md-7">
                    <label class="fw-bold mb-2 d-flex align-items-center gap-2">
                        <i class="bi bi-code-slash text-primary"></i> Payload / Message
                    </label>
                    <textarea name="payload" class="form-control" rows="6"
                        placeholder='{"amount": 1000, "currency": "USD", "order_id": "ORD-001"}'
                        required>{{ old('payload') }}</textarea>
                </div>
                <div class="col-12 col-md-5">
                    <label class="fw-bold mb-2 d-flex align-items-center gap-2">
                        <i class="bi bi-key-fill text-primary"></i> Secret Key
                    </label>
                    <input type="text" name="secret" class="form-control mb-3" placeholder="your_secret_key" value="{{ old('secret') }}" required>

                    <label class="fw-bold mb-2">Algorithm</label>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        @foreach(['sha256' => 'SHA-256', 'sha512' => 'SHA-512', 'sha1' => 'SHA-1', 'md5' => 'MD5'] as $val => $label)
                        <div class="algo-btn">
                            <input type="radio" name="algo" id="algo_{{ $val }}" value="{{ $val }}" {{ old('algo', 'sha256') === $val ? 'checked' : '' }}>
                            <label for="algo_{{ $val }}">{{ $label }}</label>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Optional verification --}}
            <div class="mb-4">
                <label class="fw-bold mb-2 d-flex align-items-center gap-2">
                    <i class="bi bi-shield-check text-success"></i> Verify Existing Signature <span class="badge bg-secondary ms-2 fw-normal">Optional</span>
                </label>
                <input type="text" name="verify_signature" class="form-control font-monospace" placeholder="Paste an existing HMAC hex signature to compare" value="{{ old('verify_signature') }}">
            </div>

            <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5 fw-bold w-100">
                <i class="bi bi-pen me-2"></i> Generate HMAC Signature
            </button>
        </form>

        @php $result = session('hmac_result'); @endphp
        @if($result)
        <div class="mt-5">
            <div class="d-flex align-items-center gap-3 mb-4">
                <h4 class="fw-bold mb-0"><i class="bi bi-check2-all me-2 text-success"></i> Generated Signatures</h4>
                <span class="badge rounded-pill bg-primary px-4 py-2 fs-6">HMAC-{{ $result['algorithm'] }}</span>
            </div>

            @if($result['verify'])
            <div class="{{ $result['verify']['status'] === 'match' ? 'verify-match' : 'verify-mismatch' }} mb-4 fw-bold fs-5">
                {{ $result['verify']['label'] }}
            </div>
            @endif

            <div class="row g-3">
                @foreach(['hex' => 'Hexadecimal (Standard)', 'base64' => 'Base64 (REST APIs)', 'base64url' => 'Base64URL (JWT / OAuth)'] as $enc => $label)
                <div class="col-12">
                    <div class="sig-card">
                        <span class="sig-label">{{ $label }}</span>
                        <div class="sig-value">
                            <span>{{ $result['signatures'][$enc] }}</span>
                            <button class="btn btn-sm btn-outline-primary rounded-pill flex-shrink-0" onclick="copyToClipboard('{{ $result['signatures'][$enc] }}', this)">
                                <i class="bi bi-clipboard"></i>
                            </button>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            <div class="mt-4 p-4 rounded-4" style="background: #f8fafc; border: 1px dashed #cbd5e1;">
                <h6 class="fw-bold mb-3"><i class="bi bi-code-slash me-2 text-primary"></i> PHP Integration Snippet</h6>
                <pre class="mb-0" style="font-size: 0.82rem; font-family: 'SF Mono', monospace; white-space: pre-wrap; word-break: break-all; color: #1e293b;">$signature = hash_hmac('{{ strtolower($result['algorithm']) }}', $payload, $secret_key);</pre>
            </div>
        </div>
        @endif
    </div>
</div>

<script>
    function copyToClipboard(text, btn) {
        navigator.clipboard.writeText(text).then(() => {
            const orig = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check2"></i>';
            btn.classList.add('btn-success');
            btn.classList.remove('btn-outline-primary');
            setTimeout(() => { btn.innerHTML = orig; btn.classList.remove('btn-success'); btn.classList.add('btn-outline-primary'); }, 2000);
        });
    }
</script>
@endsection
