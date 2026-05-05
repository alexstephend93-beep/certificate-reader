@extends('layouts.app')

@section('title', 'Base64 Codec | Encode & Decode Base64')

@section('styles')
<style>
    /* Base64 Specific Styles */
    .base64-container {
        max-width: 1400px;
        margin: 0 auto;
    }
    
    /* Tab Navigation - Improved */
    .base64-tabs {
        display: flex;
        gap: 12px;
        margin-bottom: 30px;
        flex-wrap: wrap;
        border-bottom: 2px solid #e2e8f0;
        padding-bottom: 0;
    }
    
    .base64-tab {
        background: transparent;
        border: none;
        padding: 12px 28px;
        font-size: 0.95rem;
        font-weight: 600;
        color: #64748b;
        cursor: pointer;
        transition: all 0.3s ease;
        border-radius: 12px 12px 0 0;
        position: relative;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .base64-tab i {
        font-size: 1.1rem;
    }
    
    .base64-tab:hover {
        color: var(--color-primary);
        background: rgba(99, 102, 241, 0.08);
    }
    
    .base64-tab.active {
        color: var(--color-primary);
        background: rgba(99, 102, 241, 0.12);
    }
    
    .base64-tab.active::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--color-primary);
        border-radius: 3px;
    }
    
    /* Tab Panes */
    .base64-pane {
        display: none;
        animation: fadeIn 0.3s ease;
    }
    
    .base64-pane.active {
        display: block;
    }
    
    /* Form Groups */
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-label {
        display: block;
        font-weight: 600;
        margin-bottom: 10px;
        color: var(--color-dark);
        font-size: 0.9rem;
    }
    
    .form-label i {
        margin-right: 8px;
        color: var(--color-primary);
    }
    
    /* Input Areas */
    .base64-input, 
    .base64-textarea {
        width: 100%;
        border: 2px solid #e2e8f0;
        border-radius: 16px;
        padding: 14px 16px;
        font-family: 'Courier New', monospace;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        background: white;
    }
    
    .base64-textarea {
        resize: vertical;
        min-height: 180px;
    }
    
    .base64-input:focus,
    .base64-textarea:focus {
        outline: none;
        border-color: var(--color-primary);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    }
    
    /* Button Styles - Rich & Consistent */
    .btn-primary-custom {
        background: var(--gradient-primary);
        border: none;
        border-radius: 12px;
        padding: 12px 24px;
        font-weight: 600;
        font-size: 0.9rem;
        color: white;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }
    
    .btn-primary-custom:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);
    }
    
    .btn-secondary-custom {
        background: linear-gradient(135deg, #64748b, #475569);
        border: none;
        border-radius: 12px;
        padding: 12px 24px;
        font-weight: 600;
        font-size: 0.9rem;
        color: white;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-secondary-custom:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(71, 85, 105, 0.3);
    }
    
    .btn-outline-custom {
        background: transparent;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        padding: 10px 20px;
        font-weight: 500;
        font-size: 0.85rem;
        color: #475569;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-outline-custom:hover {
        border-color: var(--color-primary);
        color: var(--color-primary);
        background: rgba(99, 102, 241, 0.05);
        transform: translateY(-1px);
    }
    
    .btn-danger-custom {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        border: none;
        border-radius: 12px;
        padding: 10px 20px;
        font-weight: 600;
        font-size: 0.85rem;
        color: white;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-danger-custom:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
    }
    
    .btn-success-custom {
        background: linear-gradient(135deg, #10b981, #059669);
        border: none;
        border-radius: 12px;
        padding: 12px 24px;
        font-weight: 600;
        font-size: 0.9rem;
        color: white;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-success-custom:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
    }
    
    /* Action Button Group */
    .action-buttons {
        display: flex;
        gap: 12px;
        margin-top: 15px;
        flex-wrap: wrap;
    }
    
    /* Result Box */
    .result-box {
        background: #f8fafc;
        border-radius: 16px;
        padding: 20px;
        border: 1px solid #e2e8f0;
        position: relative;
        margin-top: 20px;
    }
    
    .result-content {
        background: white;
        border-radius: 12px;
        padding: 16px;
        font-family: 'Courier New', monospace;
        font-size: 0.85rem;
        word-break: break-all;
        max-height: 250px;
        overflow-y: auto;
        border: 1px solid #e2e8f0;
        line-height: 1.5;
    }
    
    .result-content pre {
        margin: 0;
        white-space: pre-wrap;
        font-family: inherit;
    }
    
    .copy-btn {
        position: absolute;
        top: 15px;
        right: 15px;
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 8px 14px;
        font-size: 0.8rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: #475569;
    }
    
    .copy-btn:hover {
        background: var(--color-primary);
        color: white;
        border-color: var(--color-primary);
    }
    
    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 15px;
        margin-top: 20px;
    }
    
    .stat-card {
        background: white;
        padding: 14px;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        text-align: center;
        transition: all 0.3s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }
    
    .stat-label {
        font-size: 0.7rem;
        text-transform: uppercase;
        color: #64748b;
        font-weight: 600;
        letter-spacing: 0.5px;
    }
    
    .stat-value {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--color-primary);
        margin-top: 6px;
    }
    
    /* File Drop Zone */
    .file-drop-zone {
        border: 2px dashed #cbd5e1;
        border-radius: 16px;
        padding: 50px 20px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        background: #f8fafc;
    }
    
    .file-drop-zone:hover {
        border-color: var(--color-primary);
        background: rgba(99, 102, 241, 0.05);
    }
    
    .file-drop-zone.drag-over {
        border-color: var(--color-primary);
        background: rgba(99, 102, 241, 0.1);
    }
    
    .file-drop-zone i {
        font-size: 3rem;
        color: var(--color-primary);
        margin-bottom: 10px;
    }
    
    /* File Info */
    .file-info {
        background: #f1f5f9;
        border-radius: 12px;
        padding: 15px;
        margin-top: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .file-details {
        flex: 1;
    }
    
    .file-name {
        font-weight: 600;
        color: var(--color-dark);
        word-break: break-all;
    }
    
    .file-size {
        font-size: 0.8rem;
        color: #64748b;
        margin-top: 4px;
    }
    
    /* Preview Image */
    .preview-image {
        max-width: 100%;
        max-height: 200px;
        border-radius: 12px;
        margin-top: 10px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    
    /* Main Action Row */
    .main-action-row {
        display: flex;
        gap: 15px;
        justify-content: center;
        margin-top: 25px;
        flex-wrap: wrap;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .base64-tab {
            padding: 8px 16px;
            font-size: 0.85rem;
        }
        
        .btn-primary-custom,
        .btn-secondary-custom,
        .btn-success-custom {
            padding: 10px 18px;
            font-size: 0.85rem;
        }
        
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .action-buttons {
            justify-content: center;
        }
        
        .file-info {
            flex-direction: column;
            text-align: center;
        }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .main-action-row {
            flex-direction: column;
        }
        
        .main-action-row button {
            width: 100%;
            justify-content: center;
        }
    }
</style>
@endsection

@section('content')
<div class="glass-card" data-aos="fade-down">
    <div class="gradient-header text-center">
        <h1 class="fw-bold">
            <i class="bi bi-code-square me-3"></i>
            Base64 Codec
        </h1>
        <p class="lead mb-0">
            <i class="bi bi-arrow-left-right me-2"></i>
            Encode & Decode Base64 - Text, Files, Images & More
        </p>
    </div>

    <div class="p-4 p-md-5">
        <!-- Tab Navigation -->
        <div class="base64-tabs">
            <button class="base64-tab active" data-tab="text">
                <i class="bi bi-pencil-square"></i> Text
            </button>
            <button class="base64-tab" data-tab="file">
                <i class="bi bi-file-earmark-arrow-up"></i> File Encode
            </button>
            <button class="base64-tab" data-tab="decode-file">
                <i class="bi bi-file-earmark-arrow-down"></i> File Decode
            </button>
            <button class="base64-tab" data-tab="url">
                <i class="bi bi-link-45deg"></i> URL Safe
            </button>
        </div>

        <!-- Tab 1: Text Encode/Decode -->
        <div class="base64-pane active" id="text-pane">
            <div class="row g-4">
                <div class="col-12 col-lg-6">
                    <div class="result-card">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="bi bi-pencil-square"></i> Input Text
                            </label>
                            <textarea id="encodeInput" class="base64-textarea" placeholder="Enter text to encode or decode..."></textarea>
                        </div>
                        
                        <div class="action-buttons">
                            <button class="btn-outline-custom" id="clearTextBtn">
                                <i class="bi bi-eraser"></i> Clear
                            </button>
                            <button class="btn-outline-custom" id="sampleTextBtn">
                                <i class="bi bi-file-text"></i> Sample
                            </button>
                            <button class="btn-outline-custom" id="pasteTextBtn">
                                <i class="bi bi-clipboard"></i> Paste
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="col-12 col-lg-6">
                    <div class="result-card">
                        <label class="form-label">
                            <i class="bi bi-shield-lock"></i> Result
                        </label>
                        <div class="result-box">
                            <div class="result-content" id="encodeResult">Waiting for action...</div>
                            <button class="copy-btn" onclick="copyToClipboard('encodeResult')">
                                <i class="bi bi-clipboard"></i> Copy
                            </button>
                        </div>
                        
                        <div class="stats-grid" id="encodeStats" style="display: none;">
                            <div class="stat-card">
                                <div class="stat-label">Input Size</div>
                                <div class="stat-value" id="inputSize">-</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-label">Output Size</div>
                                <div class="stat-value" id="outputSize">-</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-label">Change</div>
                                <div class="stat-value" id="efficiency">-</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="main-action-row">
                <button class="btn-primary-custom" id="encodeBtn">
                    <i class="bi bi-arrow-up-circle"></i> Encode to Base64
                </button>
                <button class="btn-secondary-custom" id="decodeBtn">
                    <i class="bi bi-arrow-down-circle"></i> Decode from Base64
                </button>
            </div>
        </div>

        <!-- Tab 2: File Encode -->
        <div class="base64-pane" id="file-pane">
            <div class="row">
                <div class="col-12">
                    <div class="result-card">
                        <label class="form-label">
                            <i class="bi bi-upload"></i> Upload File to Encode
                        </label>
                        
                        <div class="file-drop-zone" id="fileDropZone">
                            <i class="bi bi-cloud-upload"></i>
                            <p class="mt-2 mb-0"><strong>Drag & drop file here</strong></p>
                            <p class="text-muted small">or click to browse</p>
                            <small class="text-muted">Max 10MB</small>
                            <input type="file" id="fileInput" style="display: none;">
                        </div>
                        
                        <div id="fileInfo" style="display: none;">
                            <div class="file-info">
                                <div class="file-details">
                                    <div class="file-name" id="fileName"></div>
                                    <div class="file-size" id="fileSize"></div>
                                </div>
                                <button class="btn-danger-custom" id="clearFileBtn">
                                    <i class="bi bi-trash"></i> Clear
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-12 mt-4">
                    <div class="result-card">
                        <label class="form-label">
                            <i class="bi bi-file-lock"></i> Base64 Result
                        </label>
                        <div class="result-box">
                            <div class="result-content" id="fileEncodeResult" style="max-height: 200px;">Waiting for file...</div>
                            <button class="copy-btn" onclick="copyToClipboard('fileEncodeResult')">
                                <i class="bi bi-clipboard"></i> Copy
                            </button>
                        </div>
                        
                        <div class="action-buttons mt-3">
                            <button class="btn-primary-custom" id="downloadBase64Btn" style="display: none;">
                                <i class="bi bi-download"></i> Download as .txt
                            </button>
                            <button class="btn-success-custom" id="copyDataUrlBtn" style="display: none;">
                                <i class="bi bi-link-45deg"></i> Copy Data URL
                            </button>
                        </div>
                        
                        <div class="stats-grid" id="fileStats" style="display: none;">
                            <div class="stat-card">
                                <div class="stat-label">File Size</div>
                                <div class="stat-value" id="fileInputSize">-</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-label">Base64 Size</div>
                                <div class="stat-value" id="fileOutputSize">-</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-label">MIME Type</div>
                                <div class="stat-value" id="fileMime" style="font-size: 0.8rem;">-</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 3: Decode File from Base64 -->
        <div class="base64-pane" id="decode-file-pane">
            <div class="row">
                <div class="col-12">
                    <div class="result-card">
                        <label class="form-label">
                            <i class="bi bi-file-earmark-text"></i> Base64 String to Decode
                        </label>
                        <textarea id="decodeFileInput" class="base64-textarea" placeholder="Paste Base64 string here..."></textarea>
                        
                        <div class="row g-3 mt-2">
                            <div class="col-md-7">
                                <input type="text" id="outputFilename" class="base64-input" placeholder="Output filename (optional)">
                            </div>
                            <div class="col-md-5">
                                <button class="btn-success-custom w-100" id="decodeFileBtn">
                                    <i class="bi bi-arrow-down-circle"></i> Decode & Preview
                                </button>
                            </div>
                        </div>
                        
                        <div id="decodeFilePreview" style="display: none;" class="mt-4">
                            <div class="result-box">
                                <div class="result-content text-center" id="previewContent"></div>
                                <button class="btn-primary-custom mt-3 w-100" id="downloadDecodedBtn">
                                    <i class="bi bi-download"></i> Download File
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 4: URL Safe Base64 -->
        <div class="base64-pane" id="url-pane">
            <div class="row g-4">
                <div class="col-12 col-lg-6">
                    <div class="result-card">
                        <label class="form-label">
                            <i class="bi bi-link"></i> Standard Base64
                        </label>
                        <textarea id="standardBase64" class="base64-textarea" placeholder="Standard Base64 string..."></textarea>
                        <button class="btn-primary-custom w-100 mt-3" id="toUrlSafeBtn">
                            <i class="bi bi-arrow-right"></i> Convert to URL Safe
                        </button>
                    </div>
                </div>
                
                <div class="col-12 col-lg-6">
                    <div class="result-card">
                        <label class="form-label">
                            <i class="bi bi-link-45deg"></i> URL Safe Base64
                        </label>
                        <textarea id="urlSafeBase64" class="base64-textarea" placeholder="URL Safe Base64 string..."></textarea>
                        <button class="btn-secondary-custom w-100 mt-3" id="toStandardBtn">
                            <i class="bi bi-arrow-left"></i> Convert to Standard
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
// Tab switching
document.querySelectorAll('.base64-tab').forEach(btn => {
    btn.addEventListener('click', () => {
        const tabName = btn.dataset.tab;
        
        document.querySelectorAll('.base64-tab').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.base64-pane').forEach(pane => pane.classList.remove('active'));
        
        btn.classList.add('active');
        document.getElementById(`${tabName}-pane`).classList.add('active');
    });
});

// CSRF Token
const csrfToken = '{{ csrf_token() }}';

// Helper Functions
function copyToClipboard(elementId) {
    const element = document.getElementById(elementId);
    const text = element.innerText;
    
    navigator.clipboard.writeText(text).then(() => {
        showToast('Copied to clipboard!', 'success');
    }).catch(() => {
        showToast('Failed to copy', 'error');
    });
}

function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast-notification ${type}`;
    toast.innerHTML = `<i class="bi ${type === 'success' ? 'bi-check-circle' : 'bi-exclamation-triangle'}"></i> ${message}`;
    toast.style.cssText = `
        position: fixed;
        bottom: 100px;
        right: 30px;
        background: ${type === 'success' ? '#10b981' : '#ef4444'};
        color: white;
        padding: 12px 20px;
        border-radius: 10px;
        z-index: 10002;
        animation: slideUp 0.3s ease;
    `;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

function formatBytes(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// ============ TEXT ENCODE/DECODE ============
document.getElementById('encodeBtn').addEventListener('click', async () => {
    const input = document.getElementById('encodeInput').value;
    if (!input) {
        showToast('Please enter text to encode', 'error');
        return;
    }
    
    try {
        const response = await fetch('{{ url("/base64/encode-text") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ text: input })
        });
        
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('encodeResult').innerHTML = `<pre style="margin:0">${escapeHtml(data.result)}</pre>`;
            document.getElementById('inputSize').textContent = formatBytes(data.input_bytes);
            document.getElementById('outputSize').textContent = formatBytes(data.output_bytes);
            const efficiency = ((data.output_bytes - data.input_bytes) / data.input_bytes * 100).toFixed(1);
            const changeText = efficiency > 0 ? `+${efficiency}%` : `${efficiency}%`;
            document.getElementById('efficiency').textContent = changeText;
            document.getElementById('encodeStats').style.display = 'grid';
            showToast('Encoded successfully!', 'success');
        }
    } catch (error) {
        showToast('Error encoding text', 'error');
    }
});

document.getElementById('decodeBtn').addEventListener('click', async () => {
    const input = document.getElementById('encodeInput').value;
    if (!input) {
        showToast('Please enter Base64 to decode', 'error');
        return;
    }
    
    try {
        const response = await fetch('{{ url("/base64/decode-text") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ text: input })
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (data.is_binary) {
                document.getElementById('encodeResult').innerHTML = `<pre style="margin:0">[Binary Data - ${formatBytes(data.output_bytes)}]\nMIME Type: ${data.mime_hint}</pre>`;
                showToast('Decoded binary data', 'info');
            } else {
                document.getElementById('encodeResult').innerHTML = `<pre style="margin:0">${escapeHtml(data.result)}</pre>`;
                showToast('Decoded successfully!', 'success');
            }
            document.getElementById('inputSize').textContent = formatBytes(data.input_bytes);
            document.getElementById('outputSize').textContent = formatBytes(data.output_bytes);
            const efficiency = ((data.output_bytes - data.input_bytes) / data.input_bytes * 100).toFixed(1);
            document.getElementById('efficiency').textContent = efficiency;
            document.getElementById('encodeStats').style.display = 'grid';
        } else {
            showToast(data.error, 'error');
        }
    } catch (error) {
        showToast('Error decoding text', 'error');
    }
});

// Clear text
document.getElementById('clearTextBtn').addEventListener('click', () => {
    document.getElementById('encodeInput').value = '';
    document.getElementById('encodeResult').innerHTML = 'Waiting for action...';
    document.getElementById('encodeStats').style.display = 'none';
});

// Sample text
document.getElementById('sampleTextBtn').addEventListener('click', () => {
    document.getElementById('encodeInput').value = 'Hello World! This is a sample text for Base64 encoding.\n\nBase64 encoding is used to convert binary data to ASCII text format.';
});

// Paste text
document.getElementById('pasteTextBtn').addEventListener('click', async () => {
    const text = await navigator.clipboard.readText();
    document.getElementById('encodeInput').value = text;
    showToast('Pasted from clipboard', 'success');
});

// ============ FILE ENCODE ============
const fileDropZone = document.getElementById('fileDropZone');
const fileInput = document.getElementById('fileInput');

fileDropZone.addEventListener('click', () => fileInput.click());
fileDropZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    fileDropZone.classList.add('drag-over');
});
fileDropZone.addEventListener('dragleave', () => {
    fileDropZone.classList.remove('drag-over');
});
fileDropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    fileDropZone.classList.remove('drag-over');
    const file = e.dataTransfer.files[0];
    if (file) handleFileEncode(file);
});

fileInput.addEventListener('change', (e) => {
    if (e.target.files[0]) handleFileEncode(e.target.files[0]);
});

async function handleFileEncode(file) {
    if (file.size > 10 * 1024 * 1024) {
        showToast('File too large (max 10MB)', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('file', file);
    
    try {
        const response = await fetch('{{ url("/base64/encode-file") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken
            },
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('fileEncodeResult').innerHTML = `<pre style="margin:0; word-break:break-all;">${escapeHtml(data.result)}</pre>`;
            document.getElementById('fileName').textContent = data.filename;
            document.getElementById('fileSize').textContent = formatBytes(data.input_bytes);
            document.getElementById('fileInputSize').textContent = formatBytes(data.input_bytes);
            document.getElementById('fileOutputSize').textContent = formatBytes(data.output_bytes);
            document.getElementById('fileMime').textContent = data.mime;
            document.getElementById('fileInfo').style.display = 'block';
            document.getElementById('fileStats').style.display = 'grid';
            document.getElementById('downloadBase64Btn').style.display = 'inline-flex';
            document.getElementById('copyDataUrlBtn').style.display = 'inline-flex';
            
            window.currentDataUrl = data.data_url;
            
            showToast('File encoded successfully!', 'success');
        }
    } catch (error) {
        showToast('Error encoding file', 'error');
    }
}

document.getElementById('clearFileBtn').addEventListener('click', () => {
    document.getElementById('fileInfo').style.display = 'none';
    document.getElementById('fileEncodeResult').innerHTML = 'Waiting for file...';
    document.getElementById('fileStats').style.display = 'none';
    document.getElementById('downloadBase64Btn').style.display = 'none';
    document.getElementById('copyDataUrlBtn').style.display = 'none';
    fileInput.value = '';
});

document.getElementById('downloadBase64Btn').addEventListener('click', () => {
    const content = document.getElementById('fileEncodeResult').innerText;
    const blob = new Blob([content], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'base64_output.txt';
    a.click();
    URL.revokeObjectURL(url);
});

document.getElementById('copyDataUrlBtn').addEventListener('click', () => {
    if (window.currentDataUrl) {
        navigator.clipboard.writeText(window.currentDataUrl);
        showToast('Data URL copied to clipboard!', 'success');
    }
});

// ============ DECODE FILE FROM BASE64 ============
document.getElementById('decodeFileBtn').addEventListener('click', async () => {
    const base64String = document.getElementById('decodeFileInput').value.trim();
    const filename = document.getElementById('outputFilename').value.trim() || 'decoded_file';
    
    if (!base64String) {
        showToast('Please enter Base64 string', 'error');
        return;
    }
    
    try {
        const response = await fetch('{{ url("/base64/decode-file") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ text: base64String, filename: filename })
        });
        
        const data = await response.json();
        
        if (data.success) {
            const previewDiv = document.getElementById('decodeFilePreview');
            const previewContent = document.getElementById('previewContent');
            
            if (data.mime.startsWith('image/')) {
                previewContent.innerHTML = `<img src="${data.data_url}" class="preview-image" alt="Preview">`;
            } else if (data.mime === 'text/html' || data.mime === 'text/plain') {
                const textContent = atob(base64String);
                previewContent.innerHTML = `<pre style="text-align:left; max-height:300px; overflow:auto;">${escapeHtml(textContent.substring(0, 5000))}${textContent.length > 5000 ? '... (truncated)' : ''}</pre>`;
            } else {
                previewContent.innerHTML = `<div><i class="bi bi-file-earmark fs-1"></i><br><strong>${data.mime}</strong><br>Size: ${formatBytes(data.size_bytes)}</div>`;
            }
            
            previewDiv.style.display = 'block';
            window.decodedDataUrl = data.data_url;
            window.decodedFilename = filename;
            
            showToast('Decoded successfully!', 'success');
        } else {
            showToast(data.error, 'error');
        }
    } catch (error) {
        showToast('Error decoding file', 'error');
    }
});

document.getElementById('downloadDecodedBtn').addEventListener('click', () => {
    if (window.decodedDataUrl) {
        const a = document.createElement('a');
        a.href = window.decodedDataUrl;
        a.download = window.decodedFilename;
        a.click();
        showToast('Download started!', 'success');
    }
});

// ============ URL SAFE BASE64 ============
document.getElementById('toUrlSafeBtn').addEventListener('click', () => {
    let input = document.getElementById('standardBase64').value;
    if (!input) {
        showToast('Please enter standard Base64', 'error');
        return;
    }
    
    let urlSafe = input.replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    document.getElementById('urlSafeBase64').value = urlSafe;
    showToast('Converted to URL safe Base64', 'success');
});

document.getElementById('toStandardBtn').addEventListener('click', () => {
    let input = document.getElementById('urlSafeBase64').value;
    if (!input) {
        showToast('Please enter URL safe Base64', 'error');
        return;
    }
    
    let standard = input.replace(/-/g, '+').replace(/_/g, '/');
    let padding = standard.length % 4;
    if (padding) {
        standard += '='.repeat(4 - padding);
    }
    document.getElementById('standardBase64').value = standard;
    showToast('Converted to standard Base64', 'success');
});
</script>
@endsection