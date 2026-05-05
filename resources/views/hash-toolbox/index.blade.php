@extends('layouts.app')

@section('title', 'Hash & Encryption Toolbox | Network Tools')

@section('styles')
<style>
    .nav-tabs-custom { border-bottom: 2px solid rgba(0,0,0,0.05); margin-bottom: 30px; gap: 10px; display: flex; flex-wrap: wrap; }
    .nav-link-custom { padding: 12px 25px; border-radius: 15px; font-weight: 600; color: var(--color-dark); transition: var(--transition-smooth); border: 2px solid transparent; cursor: pointer; text-decoration: none; display: flex; align-items: center; gap: 10px; background: rgba(0,0,0,0.02); }
    .nav-link-custom:hover { background: rgba(var(--bs-primary-rgb), 0.05); color: var(--color-primary); }
    .nav-link-custom.active { background: var(--gradient-primary); color: white; border-color: transparent; box-shadow: 0 10px 20px rgba(var(--bs-primary-rgb), 0.2); }
    
    .tab-content-custom { animation: fadeIn 0.4s ease-out; }
    
    .hash-result-card { background: white; border-radius: 20px; padding: 25px; margin-bottom: 20px; box-shadow: 0 10px 30px -10px rgba(0,0,0,0.1); border: 1px solid rgba(0,0,0,0.05); }
    .hash-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-primary); font-weight: 700; margin-bottom: 8px; display: block; }
    .hash-value { font-family: 'SF Mono', 'Courier New', monospace; font-size: 0.9rem; word-break: break-all; background: rgba(0,0,0,0.03); padding: 12px 15px; border-radius: 10px; display: flex; align-items: center; justify-content: space-between; gap: 10px; }
    
    .drop-zone { border: 2px dashed rgba(var(--bs-primary-rgb), 0.3); border-radius: 25px; padding: 50px 30px; text-align: center; transition: var(--transition-smooth); background: rgba(var(--bs-primary-rgb), 0.02); cursor: pointer; }
    .drop-zone:hover, .drop-zone.dragover { background: rgba(var(--bs-primary-rgb), 0.05); border-color: var(--color-primary); }
    .drop-zone i { font-size: 3rem; color: var(--color-primary); margin-bottom: 15px; display: block; }
    
    .history-item { background: white; border-radius: 15px; padding: 15px 20px; margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between; border-left: 5px solid var(--color-primary); transition: var(--transition-smooth); }
    .history-item:hover { transform: translateX(5px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
    
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>
@endsection

@section('content')
<div class="glass-card" data-aos="fade-down">
    <div class="gradient-header text-center">
        <h1 class="fw-bold"><i class="bi bi-shield-lock-fill me-3"></i> Hash & Encryption Toolbox</h1>
        <p class="lead mb-0">Generate, verify, and secure your data with expert cryptographic tools</p>
    </div>

    <div class="p-4 p-md-5">
        @php $activeTab = session('active_tab', 'text'); @endphp

        <div class="nav-tabs-custom">
            <button class="nav-link-custom {{ $activeTab == 'text' ? 'active' : '' }}" onclick="switchTab('text')">
                <i class="bi bi-fonts"></i> Text Hash
            </button>
            <button class="nav-link-custom {{ $activeTab == 'file' ? 'active' : '' }}" onclick="switchTab('file')">
                <i class="bi bi-file-earmark-binary"></i> File Hash
            </button>
            <button class="nav-link-custom {{ $activeTab == 'aes' ? 'active' : '' }}" onclick="switchTab('aes')">
                <i class="bi bi-key-fill"></i> AES Crypto
            </button>
            <button class="nav-link-custom {{ $activeTab == 'password' ? 'active' : '' }}" onclick="switchTab('password')">
                <i class="bi bi-shield-shaded"></i> Password Gen
            </button>
            <button class="nav-link-custom {{ $activeTab == 'history' ? 'active' : '' }}" onclick="switchTab('history')">
                <i class="bi bi-clock-history"></i> Recent Activity
            </button>
        </div>

        @if(session('error'))
            <div class="alert alert-danger rounded-4 border-0 mb-4 d-flex align-items-center gap-3">
                <i class="bi bi-exclamation-octagon-fill fs-4"></i>
                <span class="fw-medium">{{ session('error') }}</span>
            </div>
        @endif

        {{-- Text Hashing Tab --}}
        <div id="tab-text" class="tab-content-custom {{ $activeTab == 'text' ? '' : 'd-none' }}">
            <form action="{{ url('/hash-toolbox/hash-text') }}" method="POST">
                @csrf
                <div class="mb-4">
                    <label class="fw-bold mb-2">Input Text</label>
                    <textarea name="text" class="form-control" rows="5" placeholder="Enter text to hash..." required>{{ old('text') }}</textarea>
                </div>
                <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5 fw-bold w-100">
                    <i class="bi bi-lightning-fill me-2"></i> Generate Multi-Hashes
                </button>
            </form>

            @if(session('hash_results') && session('active_tab') == 'text')
                <div class="mt-5">
                    <h4 class="fw-bold mb-4"><i class="bi bi-cpu me-2"></i> Generation Results</h4>
                    <div class="row g-3">
                        @foreach(session('hash_results')['hashes'] as $algo => $val)
                            <div class="col-12">
                                <div class="hash-result-card">
                                    <span class="hash-label">{{ $algo }}</span>
                                    <div class="hash-value">
                                        <span class="text-truncate">{{ $val }}</span>
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-sm btn-outline-primary rounded-pill border-0" onclick="copyToClipboard('{{ $val }}', this)">
                                                <i class="bi bi-clipboard"></i>
                                            </button>
                                            <a href="data:text/plain;charset=utf-8,{{ rawurlencode($val) }}" download="{{ $algo }}_hash.txt" class="btn btn-sm btn-outline-secondary rounded-pill border-0">
                                                <i class="bi bi-download"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- File Hashing Tab --}}
        <div id="tab-file" class="tab-content-custom {{ $activeTab == 'file' ? '' : 'd-none' }}">
            <form action="{{ url('/hash-toolbox/hash-file') }}" method="POST" enctype="multipart/form-data" id="fileHashForm">
                @csrf
                <div class="drop-zone" onclick="document.getElementById('fileInput').click()" id="dropZone">
                    <i class="bi bi-cloud-arrow-up-fill"></i>
                    <h5 class="fw-bold">Drag & Drop File Here</h5>
                    <p class="text-muted mb-0">or click to browse from your device</p>
                    <input type="file" name="file" id="fileInput" class="d-none" onchange="this.form.submit()">
                </div>
            </form>

            @if(session('hash_results') && session('active_tab') == 'file')
                <div class="mt-5">
                    <h4 class="fw-bold mb-4"><i class="bi bi-file-earmark-check me-2"></i> File Integrity: {{ session('hash_results')['input'] }}</h4>
                    <div class="row g-3">
                        @foreach(session('hash_results')['hashes'] as $algo => $val)
                            <div class="col-12">
                                <div class="hash-result-card">
                                    <span class="hash-label">{{ $algo }}</span>
                                    <div class="hash-value">
                                        <span class="text-truncate">{{ $val }}</span>
                                        <button class="btn btn-sm btn-outline-primary rounded-pill border-0" onclick="copyToClipboard('{{ $val }}', this)">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- AES Tab --}}
        <div id="tab-aes" class="tab-content-custom {{ $activeTab == 'aes' ? '' : 'd-none' }}">
            <form action="{{ url('/hash-toolbox/aes') }}" method="POST">
                @csrf
                <div class="row g-4">
                    <div class="col-md-8">
                        <label class="fw-bold mb-2">Message Content</label>
                        <textarea name="text" class="form-control" rows="5" placeholder="Enter text to encrypt/decrypt..." required>{{ old('text') }}</textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="fw-bold mb-2">Secret Key (AES-256)</label>
                        <input type="password" name="key" class="form-control mb-3" placeholder="Minimum 8 characters" required>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" name="action" value="encrypt" class="btn btn-primary rounded-pill fw-bold">
                                <i class="bi bi-lock-fill me-2"></i> Encrypt
                            </button>
                            <button type="submit" name="action" value="decrypt" class="btn btn-outline-primary rounded-pill fw-bold">
                                <i class="bi bi-unlock-fill me-2"></i> Decrypt
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            @if(session('aes_results'))
                <div class="mt-5">
                    <div class="hash-result-card" style="border-left: 6px solid var(--color-primary);">
                        <span class="hash-label">{{ session('aes_results')['type'] }} Result</span>
                        <div class="hash-value mt-2">
                            <span class="text-break">{{ session('aes_results')['output'] }}</span>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-primary rounded-circle" onclick="copyToClipboard('{{ session('aes_results')['output'] }}', this)">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                                <a href="data:text/plain;charset=utf-8,{{ rawurlencode(session('aes_results')['output']) }}" download="aes_result.txt" class="btn btn-sm btn-secondary rounded-circle">
                                    <i class="bi bi-download"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Password Tab --}}
        <div id="tab-password" class="tab-content-custom {{ $activeTab == 'password' ? '' : 'd-none' }}">
            <form action="{{ url('/hash-toolbox/password') }}" method="POST">
                @csrf
                <div class="row g-4 align-items-end">
                    <div class="col-md-3">
                        <label class="fw-bold mb-2">Length</label>
                        <input type="number" name="length" class="form-control" value="16" min="8" max="128">
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex flex-wrap gap-3 mb-2">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="upper" id="upper" {{ !session()->has('password_results') || old('upper') ? 'checked' : '' }}>
                                <label class="form-check-label" for="upper">ABC</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="lower" id="lower" {{ !session()->has('password_results') || old('lower') ? 'checked' : '' }}>
                                <label class="form-check-label" for="lower">abc</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="numbers" id="numbers" {{ !session()->has('password_results') || old('numbers') ? 'checked' : '' }}>
                                <label class="form-check-label" for="numbers">123</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="symbols" id="symbols" {{ !session()->has('password_results') || old('symbols') ? 'checked' : '' }}>
                                <label class="form-check-label" for="symbols">!@#</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100 rounded-pill fw-bold">
                            <i class="bi bi-shuffle me-2"></i> Generate
                        </button>
                    </div>
                </div>
            </form>

            @if(session('password_results'))
                <div class="mt-5 text-center">
                    <div class="display-6 fw-bold text-primary mb-3 font-monospace p-4 bg-light rounded-4 border d-inline-block">
                        {{ session('password_results')['output'] }}
                    </div>
                    <div>
                        <button class="btn btn-outline-primary rounded-pill px-4" onclick="copyToClipboard('{{ session('password_results')['output'] }}', this)">
                            <i class="bi bi-clipboard me-2"></i> Copy Secure Password
                        </button>
                    </div>
                </div>
            @endif
        </div>

        {{-- History Tab --}}
        <div id="tab-history" class="tab-content-custom {{ $activeTab == 'history' ? '' : 'd-none' }}">
            @if(empty($history))
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                    <p>No recent activity found. Start hashing some data!</p>
                </div>
            @else
                @foreach($history as $item)
                    <div class="history-item">
                        <div>
                            <span class="badge rounded-pill bg-primary me-2">{{ $item['type'] }}</span>
                            <span class="fw-medium">{{ $item['input'] ?? 'Generic Operation' }}</span>
                            <div class="small text-muted mt-1">{{ $item['timestamp'] }}</div>
                        </div>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</div>

<script>
    function switchTab(tabId) {
        document.querySelectorAll('.tab-content-custom').forEach(tab => tab.classList.add('d-none'));
        document.querySelectorAll('.nav-link-custom').forEach(link => link.classList.remove('active'));
        
        document.getElementById('tab-' + tabId).classList.remove('d-none');
        event.currentTarget.classList.add('active');
    }

    function copyToClipboard(text, btn) {
        navigator.clipboard.writeText(text).then(() => {
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check2"></i>';
            btn.classList.add('btn-success');
            btn.classList.remove('btn-outline-primary', 'btn-primary');
            
            setTimeout(() => {
                btn.innerHTML = originalHtml;
                btn.classList.remove('btn-success');
                btn.classList.add(btn.classList.contains('border-0') ? 'btn-outline-primary' : 'btn-primary');
            }, 2000);
        });
    }

    // Drag and drop logic
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');

    if(dropZone) {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, e => {
                e.preventDefault();
                e.stopPropagation();
            }, false);
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => dropZone.classList.add('dragover'), false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragover'), false);
        });

        dropZone.addEventListener('drop', e => {
            const dt = e.dataTransfer;
            const files = dt.files;
            fileInput.files = files;
            fileInput.form.submit();
        }, false);
    }
</script>
@endsection
