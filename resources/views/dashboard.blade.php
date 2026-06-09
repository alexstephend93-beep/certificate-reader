@extends('layouts.app')

@section('title', 'Dashboard | Network Tools')

@section('styles')
<style>
    /* Dashboard Styles - Keep all your existing styles */
    .welcome-banner {
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.05));
        border-radius: 24px;
        padding: 30px;
        margin-bottom: 30px;
        border: 1px solid rgba(99, 102, 241, 0.2);
        animation: fadeInUp 0.6s ease;
    }
    
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    @keyframes slideInLeft {
        from { opacity: 0; transform: translateX(-30px); }
        to { opacity: 1; transform: translateX(0); }
    }
    
    @keyframes slideInRight {
        from { opacity: 0; transform: translateX(30px); }
        to { opacity: 1; transform: translateX(0); }
    }
    
    .stat-card-dashboard {
        background: white;
        border-radius: 20px;
        padding: 20px;
        text-align: center;
        transition: all 0.3s ease;
        border: 1px solid #e2e8f0;
        height: 100%;
        animation: fadeInUp 0.6s ease backwards;
    }
    
    .stat-card-dashboard:nth-child(1) { animation-delay: 0.1s; }
    .stat-card-dashboard:nth-child(2) { animation-delay: 0.2s; }
    .stat-card-dashboard:nth-child(3) { animation-delay: 0.3s; }
    .stat-card-dashboard:nth-child(4) { animation-delay: 0.4s; }
    
    .stat-card-dashboard:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 30px -12px rgba(0, 0, 0, 0.15);
        border-color: var(--color-primary);
    }
    
    .stat-icon {
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 15px;
        transition: all 0.3s ease;
    }
    
    .stat-card-dashboard:hover .stat-icon {
        transform: scale(1.1) rotate(5deg);
    }
    
    .stat-icon i {
        font-size: 28px;
        color: white;
    }
    
    .stat-number {
        font-size: 2rem;
        font-weight: 800;
        color: var(--color-dark);
        font-family: 'Space Grotesk', monospace;
    }
    
    .stat-label {
        font-size: 0.85rem;
        color: #64748b;
        margin-top: 5px;
    }
    
    .tool-card {
        background: white;
        border-radius: 20px;
        padding: 20px;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid #e2e8f0;
        height: 100%;
        cursor: pointer;
        text-decoration: none;
        display: block;
        animation: fadeInUp 0.6s ease backwards;
    }
    
    .tool-card:hover {
        transform: translateY(-8px) scale(1.02);
        box-shadow: 0 25px 35px -12px rgba(99, 102, 241, 0.25);
        border-color: var(--color-primary);
    }
    
    .tool-icon {
        width: 70px;
        height: 70px;
        background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));
        border-radius: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 20px;
        transition: all 0.3s ease;
    }
    
    .tool-card:hover .tool-icon {
        transform: scale(1.05) rotate(3deg);
        box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);
    }
    
    .tool-icon i {
        font-size: 32px;
        color: white;
    }
    
    .tool-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--color-dark);
        margin-bottom: 8px;
    }
    
    .tool-description {
        font-size: 0.85rem;
        color: #64748b;
        line-height: 1.4;
    }
    
    .tool-usage {
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #e2e8f0;
        font-size: 0.75rem;
        color: #94a3b8;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .quick-action-card {
        background: linear-gradient(135deg, #f8fafc, #f1f5f9);
        border-radius: 16px;
        padding: 15px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
        border: 1px solid #e2e8f0;
        animation: slideInLeft 0.6s ease backwards;
    }
    
    .quick-action-card:hover {
        background: white;
        border-color: var(--color-primary);
        transform: translateX(8px) translateY(-3px);
        box-shadow: 0 10px 20px rgba(99, 102, 241, 0.15);
    }
    
    .recent-command-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px;
        background: #f8fafc;
        border-radius: 12px;
        margin-bottom: 10px;
        transition: all 0.3s ease;
        animation: slideInRight 0.6s ease backwards;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .recent-command-item:hover {
        background: white;
        border: 1px solid #e2e8f0;
        transform: translateX(5px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
    }
    
    .recent-command-info {
        flex: 1;
        min-width: 0;
    }
    
    .recent-command-name {
        font-weight: 700;
        font-size: 0.9rem;
        color: var(--color-dark);
        margin-bottom: 4px;
    }
    
    .recent-command-code {
        font-family: 'Courier New', monospace;
        font-size: 0.7rem;
        color: var(--color-primary);
        background: rgba(99, 102, 241, 0.1);
        padding: 4px 8px;
        border-radius: 6px;
        display: inline-block;
        word-break: break-all;
    }
    
    .widget-section {
        background: white;
        border-radius: 20px;
        padding: 20px;
        border: 1px solid #e2e8f0;
        margin-bottom: 30px;
        transition: all 0.3s ease;
        animation: fadeInUp 0.6s ease backwards;
    }
    
    .widget-section:hover {
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
    }
    
    .widget-title {
        font-size: 1rem;
        font-weight: 700;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
        color: var(--color-dark);
        flex-wrap: wrap;
    }
    
    .widget-title i {
        color: var(--color-primary);
        font-size: 1.2rem;
    }
    
    .hash-preview {
        background: linear-gradient(135deg, #0f172a, #1e293b);
        border-radius: 12px;
        padding: 16px;
        margin-top: 10px;
        transition: all 0.3s ease;
    }
    
    .hash-preview:hover {
        transform: scale(1.01);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    }
    
    .hash-preview-content {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    
    .hash-value-wrapper {
        background: rgba(0, 0, 0, 0.3);
        padding: 12px;
        border-radius: 8px;
        overflow-x: auto;
    }
    
    .hash-value-wrapper strong {
        display: block;
        margin-bottom: 8px;
        color: #e2e8f0;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
    }
    
    .hash-value-wrapper strong i {
        margin-right: 6px;
    }
    
    .hash-value {
        font-family: 'Courier New', monospace;
        font-size: 0.7rem;
        color: #a5f3c3;
        word-break: break-all;
        line-height: 1.5;
        display: block;
    }
    
    .hash-actions {
        display: flex;
        justify-content: flex-end;
    }
    
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
    
    .btn-primary-custom:active {
        transform: translateY(0);
    }
    
    .btn-primary-custom:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
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
        text-decoration: none;
    }
    
    .btn-outline-custom:hover {
        border-color: var(--color-primary);
        color: var(--color-primary);
        background: rgba(99, 102, 241, 0.05);
        transform: translateY(-2px);
    }
    
    .bcrypt-table-container {
        overflow-x: auto;
        margin-top: 10px;
    }
    
    .bcrypt-table {
        width: 100%;
        font-size: 0.7rem;
        border-collapse: collapse;
        min-width: 500px;
    }
    
    .bcrypt-table th {
        background: #f8fafc;
        color: var(--color-dark);
        font-weight: 600;
        padding: 12px 10px;
        text-align: left;
        border-bottom: 2px solid #e2e8f0;
    }
    
    .bcrypt-table td {
        padding: 10px;
        vertical-align: middle;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .bcrypt-table tr:hover {
        background: #f1f5f9;
    }
    
    .bcrypt-password-cell {
        font-weight: 600;
        color: var(--color-dark);
        white-space: nowrap;
    }
    
    .bcrypt-hash-cell {
        font-family: 'Courier New', monospace;
        font-size: 0.65rem;
        word-break: break-all;
        max-width: 400px;
    }
    
    .copy-btn-sm {
        background: var(--gradient-primary);
        border: none;
        border-radius: 8px;
        padding: 6px 12px;
        font-size: 0.7rem;
        font-weight: 500;
        color: white;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        white-space: nowrap;
    }
    
    .copy-btn-sm:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
    }
    
    .form-control, .form-select {
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        padding: 10px 15px;
        transition: all 0.3s ease;
        width: 100%;
    }
    
    .form-control:focus, .form-select:focus {
        border-color: var(--color-primary);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        outline: none;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    .loading-spinner {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-top-color: white;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }
    
    @media (max-width: 768px) {
        .welcome-banner { padding: 20px; }
        .stat-number { font-size: 1.5rem; }
        .tool-icon { width: 50px; height: 50px; }
        .tool-icon i { font-size: 24px; }
        .bcrypt-hash-cell { max-width: 200px; }
        .btn-primary-custom { width: 100%; justify-content: center; }
        .recent-command-item { flex-direction: column; align-items: flex-start; }
        .copy-btn-sm { align-self: flex-end; }
        .hash-value-wrapper { overflow-x: scroll; }
    }
    
    .ssh-server-card {
        background: white;
        border-radius: 16px;
        padding: 15px;
        transition: all 0.3s ease;
        border: 1px solid #e2e8f0;
        margin-bottom: 12px;
        cursor: pointer;
    }
    
    .ssh-server-card:hover {
        transform: translateX(5px);
        border-color: var(--color-primary);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
    }
    
    .ssh-server-name {
        font-weight: 700;
        color: var(--color-dark);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .ssh-server-name i {
        color: var(--color-primary);
    }
    
    .ssh-server-details {
        font-size: 0.7rem;
        color: #64748b;
        margin-top: 5px;
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }
    
    .ssh-server-status {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 5px;
    }
    
    .status-online-dot {
        background: #10b981;
        box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
    }
    
    .status-offline-dot {
        background: #ef4444;
    }
    
    .status-unknown-dot {
        background: #f59e0b;
    }
    
    .quick-connect-btn {
        background: var(--gradient-primary);
        border: none;
        border-radius: 10px;
        padding: 8px 16px;
        font-size: 0.75rem;
        color: white;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .quick-connect-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
    }
    
    .view-all-ssh {
        text-align: center;
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #e2e8f0;
    }
    
    .toast-notification {
        position: fixed;
        bottom: 100px;
        right: 30px;
        padding: 12px 20px;
        border-radius: 10px;
        z-index: 10002;
        animation: slideUp 0.3s ease;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    }
    
    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    /* Ensure cursor pointer on buttons */
    #toggleNumlockBtn, 
    #toggleNumlockBtn:hover,
    #toggleNumlockBtn:active,
    #toggleNumlockBtn:focus {
        cursor: pointer !important;
    }

    #toggleNumlockBtn:disabled {
        cursor: wait !important;
        opacity: 0.7;
    }
    /* Button cursor styles */
    #toggleNumlockBtn {
        cursor: pointer !important;
        transition: all 0.3s ease;
    }

    #toggleNumlockBtn:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    #toggleNumlockBtn:active {
        transform: scale(0.95);
    }

    #toggleNumlockBtn:disabled {
        cursor: wait !important;
        opacity: 0.7;
        transform: none;
    }
    /* Button cursor styles - ensures pointer cursor always shows */
#toggleNumlockBtn {
    cursor: pointer !important;
    transition: all 0.3s ease;
    position: relative;
    z-index: 1;
}

#toggleNumlockBtn:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

#toggleNumlockBtn:active {
    transform: scale(0.95);
}

#toggleNumlockBtn:disabled {
    cursor: wait !important;
    opacity: 0.7;
    transform: none;
}

#toggleNumlockBtn i {
    pointer-events: none;
}
</style>
@endsection

@section('content')
<div class="glass-card" data-aos="fade-down">
    <div class="gradient-header text-center">
        <h1 class="fw-bold">
            <i class="bi bi-grid-1x2-fill me-3"></i>
            Dashboard
        </h1>
        <p class="lead mb-0">
            <i class="bi bi-speedometer2 me-2"></i>
            Your Network Security Toolkit Dashboard
        </p>
    </div>

    <div class="p-4 p-md-5">
        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h3 class="fw-bold mb-2">
                        <i class="bi bi-robot me-2"></i>
                        Welcome back, Developer!
                    </h3>
                    <p class="text-muted mb-0">Access all your security tools from one central dashboard. Parse certificates, test APIs, decode JWT tokens, and more.</p>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <div class="d-flex gap-2 justify-content-md-end flex-wrap">
                        <span class="badge bg-primary bg-opacity-10 text-primary p-2">
                            <i class="bi bi-shield-check"></i> Secure
                        </span>
                        <span class="badge bg-success bg-opacity-10 text-success p-2">
                            <i class="bi bi-lightning-charge"></i> Fast
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Stats Row -->
        <div class="row g-4 mb-4">
            <div class="col-6 col-md-3">
                <div class="stat-card-dashboard">
                    <div class="stat-icon"><i class="bi bi-tools"></i></div>
                    <div class="stat-number">8</div>
                    <div class="stat-label">Available Tools</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card-dashboard">
                    <div class="stat-icon"><i class="bi bi-terminal-fill"></i></div>
                    <div class="stat-number">{{ $totalCommands ?? 62 }}</div>
                    <div class="stat-label">Commands Library</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card-dashboard">
                    <div class="stat-icon"><i class="bi bi-star-fill"></i></div>
                    <div class="stat-number">{{ $favoriteCommands ?? 0 }}</div>
                    <div class="stat-label">Favorite Commands</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card-dashboard">
                    <div class="stat-icon"><i class="bi bi-graph-up"></i></div>
                    <div class="stat-number">24/7</div>
                    <div class="stat-label">Available</div>
                </div>
            </div>
        </div>
        
        <!-- Main Tools Grid -->
        <h4 class="fw-bold mb-3">
            <i class="bi bi-grid-3x3-gap-fill me-2" style="color: var(--color-primary);"></i>
            Security Tools
        </h4>
        <div class="row g-4 mb-5">
            @php
                $tools = [
                    ['url' => '/certificate', 'icon' => 'file-earmark-lock2-fill', 'title' => 'Certificate Reader', 'desc' => 'Parse X.509 SSL/TLS certificates and extract domain details.', 'uses' => 1240],
                    ['url' => '/chain-validator', 'icon' => 'diagram-3-fill', 'title' => 'Chain Validator', 'desc' => 'Validate complete certificate chains and verify trust paths.', 'uses' => 890],
                    ['url' => '/hash-toolbox', 'icon' => 'shield-lock-fill', 'title' => 'Hash & Encryption', 'desc' => 'Generate MD5, SHA1, SHA256 hashes and AES encryption.', 'uses' => 2100],
                    ['url' => '/jwt', 'icon' => 'braces-asterisk', 'title' => 'JWT Analyzer', 'desc' => 'Decode and verify JSON Web Tokens, validate signatures.', 'uses' => 1560],
                    ['url' => '/hmac', 'icon' => 'pen-fill', 'title' => 'HMAC Signature', 'desc' => 'Generate HMAC signatures for API authentication.', 'uses' => 430],
                    ['url' => '/api-tester', 'icon' => 'globe2', 'title' => 'API Tester', 'desc' => 'Test HTTP endpoints, add headers, view JSON responses.', 'uses' => 980],
                    ['url' => '/base64', 'icon' => 'code-square', 'title' => 'Base64 Codec', 'desc' => 'Encode and decode Base64 strings, files, and images.', 'uses' => 3420],
                    ['url' => '/command-storage', 'icon' => 'terminal-fill', 'title' => 'Command Storage', 'desc' => 'Your personal developer command library.', 'uses' => 560],
                ];
            @endphp
            @foreach($tools as $tool)
            <div class="col-12 col-sm-6 col-lg-4">
                <a href="{{ url($tool['url']) }}" class="tool-card">
                    <div class="tool-icon"><i class="bi bi-{{ $tool['icon'] }}"></i></div>
                    <h5 class="tool-title">{{ $tool['title'] }}</h5>
                    <p class="tool-description">{{ $tool['desc'] }}</p>
                    <div class="tool-usage"><i class="bi bi-eye"></i> <span>{{ number_format($tool['uses']) }} uses</span></div>
                </a>
            </div>
            @endforeach
        </div>
        
        <!-- Quick Actions & Widgets Row -->
        <div class="row g-4">
            <div class="col-12 col-lg-6">
                <div class="widget-section">
                    <div class="widget-title"><i class="bi bi-lightning-charge-fill"></i> Quick Actions</div>
                    <div class="row g-2">
                        <div class="col-12 col-sm-6"><div class="quick-action-card" onclick="window.location.href='{{ url('/base64') }}'"><i class="bi bi-code-square fs-4" style="color: var(--color-primary);"></i><div class="fw-bold mt-2">Base64 Encode</div><small class="text-muted">Quick encode/decode</small></div></div>
                        <div class="col-12 col-sm-6"><div class="quick-action-card" onclick="window.location.href='{{ url('/hash-toolbox') }}'"><i class="bi bi-shield-lock fs-4" style="color: var(--color-primary);"></i><div class="fw-bold mt-2">Generate Hash</div><small class="text-muted">MD5, SHA1, SHA256</small></div></div>
                        <div class="col-12 col-sm-6"><div class="quick-action-card" onclick="window.location.href='{{ url('/jwt') }}'"><i class="bi bi-braces-asterisk fs-4" style="color: var(--color-primary);"></i><div class="fw-bold mt-2">Decode JWT</div><small class="text-muted">Verify tokens</small></div></div>
                        <div class="col-12 col-sm-6"><div class="quick-action-card" onclick="window.location.href='{{ url('/api-tester') }}'"><i class="bi bi-globe2 fs-4" style="color: var(--color-primary);"></i><div class="fw-bold mt-2">Test API</div><small class="text-muted">HTTP requests</small></div></div>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-lg-6">
                <div class="widget-section">
                    <div class="widget-title"><i class="bi bi-star-fill"></i> Most Used Commands</div>
                    @if(isset($recentCommands) && $recentCommands->count() > 0)
                        @foreach($recentCommands as $cmd)
                            <div class="recent-command-item">
                                <div class="recent-command-info">
                                    <div class="recent-command-name">{{ $cmd->name }}</div>
                                    <div class="recent-command-code">{{ Str::limit($cmd->command, 50) }}</div>
                                </div>
                                <button class="copy-btn-sm" onclick="copyCommand('{{ addslashes($cmd->command) }}')"><i class="bi bi-clipboard"></i> Copy</button>
                            </div>
                        @endforeach
                    @else
                        <p class="text-muted text-center py-3">No commands available. Add some to get started!</p>
                    @endif
                    <div class="text-center mt-3"><a href="{{ url('/command-storage') }}" class="btn-outline-custom">View All Commands →</a></div>
                </div>
            </div>
        </div>

        <!-- Num Lock Toggle -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="widget-section">
                    <div class="widget-title">
                        <i class="bi bi-keyboard"></i> 
                        Num Lock Toggle
                        <button id="toggleNumlockBtn" class="btn btn-sm ms-3" style="border-radius: 50%; width: 45px; height: 45px; background: var(--gradient-primary); color: white; border: none; transition: all 0.3s ease; cursor: pointer;">
                            <i class="bi bi-play-fill" id="toggleIcon" style="font-size: 1.2rem; pointer-events: none;"></i>
                        </button>
                    </div>
                    <div class="text-center">
                        <div class="display-4 fw-bold mb-2">
                            <span id="numlock-count" class="text-primary">0</span>
                            <span class="fs-6 text-muted">toggles</span>
                        </div>
                        <div id="numlock-spinner" class="d-none mb-2">
                            <div class="loading-spinner" style="width: 30px; height: 30px;"></div>
                        </div>
                        <div id="numlock-status" class="text-muted small">
                            Click the <i class="bi bi-play-fill"></i> icon to start toggling Num Lock every 5 seconds
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Hash Generator -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="widget-section">
                    <div class="widget-title"><i class="bi bi-shield-check"></i> Quick Hash Generator</div>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <input type="text" id="quickHashInput" class="form-control" placeholder="Enter text to hash...">
                        </div>
                        <div class="col-md-4">
                            <select id="quickHashType" class="form-select">
                                <option value="md5">MD5</option>
                                <option value="sha1">SHA1</option>
                                <option value="sha256" selected>SHA256</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <button class="btn-primary-custom" onclick="generateQuickHash(event)">
                                <i class="bi bi-shield-check"></i> Generate Hash
                            </button>
                        </div>
                        <div class="col-12" id="quickHashResult" style="display: none;">
                            <div class="hash-preview">
                                <div class="hash-preview-content">
                                    <div class="hash-value-wrapper">
                                        <strong><i class="bi bi-shield-check"></i> Hash Result:</strong>
                                        <div class="hash-value" id="hashValue"></div>
                                    </div>
                                    <div class="hash-actions">
                                        <button class="copy-btn-sm" onclick="copyTextById('hashValue')">
                                            <i class="bi bi-clipboard"></i> Copy Hash
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bcrypt Generator Section - Fixed Layout -->
        <div class="row mt-4">
            <div class="col-12 col-lg-6">
                <div class="widget-section">
                    <div class="widget-title"><i class="bi bi-shield-lock-fill"></i> Bcrypt Hash Generator</div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold">Enter Password to Hash</label>
                            <input type="text" id="bcryptInput" class="form-control" placeholder="Enter password...">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Bcrypt Rounds (4-31)</label>
                            <input type="number" id="bcryptRounds" class="form-control" value="10" min="4" max="31">
                            <small class="text-muted">Higher rounds = more secure but slower (default: 10)</small>
                        </div>
                        <div class="col-12">
<button class="btn-primary-custom" onclick="generateBcryptHash(event)">
                                <i class="bi bi-shield-check"></i> Generate Bcrypt Hash
                            </button>
                        </div>
                        <div class="col-12" id="bcryptResult" style="display: none;">
                            <div class="hash-preview">
                                <div class="hash-preview-content">
                                    <div class="hash-value-wrapper">
                                        <strong><i class="bi bi-shield-lock"></i> Bcrypt Hash:</strong>
                                        <div class="hash-value" id="bcryptHashValue"></div>
                                    </div>
                                    <div class="hash-actions">
                                        <button class="copy-btn-sm" onclick="copyTextById('bcryptHashValue')">
                                            <i class="bi bi-clipboard"></i> Copy Hash
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-lg-6">
                <div class="widget-section">
                    <div class="widget-title"><i class="bi bi-database-fill"></i> Common Password Bcrypt Hashes</div>
                    <div class="bcrypt-table-container">
                        <table class="bcrypt-table">
                            <thead>
                                <tr>
                                    <th>Password</th>
                                    <th>Bcrypt Hash (rounds=10)</th>
                                    <th style="width: 80px">Copy</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($bcryptHashes as $item)
                                    <tr>
                                        <td class="bcrypt-password-cell">{{ $item['password'] }}</td>
                                        <td class="bcrypt-hash-cell"><code>{{ $item['hash'] }}</code></td>
                                        <td>
                                            <button class="copy-btn-sm" onclick="copyText('{{ $item['hash'] }}', 'Hash copied!')">
                                                <i class="bi bi-clipboard"></i> Copy
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        


        <!-- SSH Servers Section -->
        <div class="row mt-4">
            <div class="col-12 col-lg-6">
                <div class="widget-section">
                    <div class="widget-title">
                        <i class="bi bi-server"></i>
                        Recent SSH Connections
                        <span class="badge bg-primary bg-opacity-10 text-primary ms-2">{{ count($recentConnections) }}</span>
                    </div>
                    @if(count($recentConnections) > 0)
                        @foreach($recentConnections as $server)
                            <div class="ssh-server-card" onclick="quickConnect('{{ $server['host'] }}', '{{ addslashes($server['ssh_command'] ?? '') }}')">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="ssh-server-name">
                                        <i class="bi bi-hdd-stack-fill"></i>
                                        <span>{{ $server['host'] }}</span>
                                    </div>
                                    <small class="text-muted">
                                        <i class="bi bi-clock-history"></i>
                                        {{ \Carbon\Carbon::parse($server['last_connected'])->diffForHumans() }}
                                    </small>
                                </div>
                                <div class="ssh-server-details">
                                    <span><i class="bi bi-geo-alt"></i> {{ $server['hostname'] }}</span>
                                    <span><i class="bi bi-person"></i> {{ $server['user'] }}</span>
                                    <span>
                                        <span class="ssh-server-status status-unknown-dot" id="dash-status-{{ $loop->index }}"></span>
                                        <span id="dash-status-text-{{ $loop->index }}">Checking...</span>
                                    </span>
                                </div>
                            </div>
                            
                            <script>
                            (function() {
                                const hostname = '{{ $server['hostname'] }}';
                                const port = {{ $server['port'] ?? 22 }};
                                const statusDot = document.getElementById('dash-status-{{ $loop->index }}');
                                const statusText = document.getElementById('dash-status-text-{{ $loop->index }}');
                                
                                if (statusDot && hostname) {
                                    fetch('/ssh/test', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                        },
                                        body: JSON.stringify({ hostname: hostname, port: port })
                                    })
                                    .then(res => res.json())
                                    .then(data => {
                                        if (data.success) {
                                            statusDot.className = 'ssh-server-status status-online-dot';
                                            statusText.textContent = 'Online';
                                        } else {
                                            statusDot.className = 'ssh-server-status status-offline-dot';
                                            statusText.textContent = 'Offline';
                                        }
                                    })
                                    .catch(() => {
                                        statusDot.className = 'ssh-server-status status-offline-dot';
                                        statusText.textContent = 'Offline';
                                    });
                                }
                            })();
                            </script>
                        @endforeach
                        <div class="view-all-ssh">
                            <a href="{{ url('/ssh') }}" class="btn-outline-custom">
                                <i class="bi bi-arrow-right"></i> View All Servers ({{ $totalServers ?? count($recentConnections) }})
                            </a>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="bi bi-server fs-1 text-muted"></i>
                            <p class="mt-2">No SSH connections yet</p>
                            <a href="{{ url('/ssh') }}" class="btn-outline-custom">Configure Servers</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ============================================
// CSRF Token
// ============================================
const csrfToken = '{{ csrf_token() }}';

// ============================================
// Toast Notification Function
// ============================================
function showToast(message, type) {
    const existingToast = document.querySelector('.toast-notification');
    if (existingToast) existingToast.remove();
    
    const toast = document.createElement('div');
    toast.className = `toast-notification ${type}`;
    toast.innerHTML = `<i class="bi ${type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'} me-2"></i> ${message}`;
    toast.style.background = type === 'success' ? '#10b981' : '#ef4444';
    toast.style.color = 'white';
    toast.style.position = 'fixed';
    toast.style.bottom = '100px';
    toast.style.right = '30px';
    toast.style.padding = '12px 20px';
    toast.style.borderRadius = '10px';
    toast.style.zIndex = '10002';
    toast.style.animation = 'slideUp 0.3s ease';
    toast.style.boxShadow = '0 10px 30px rgba(0,0,0,0.2)';
    document.body.appendChild(toast);
    setTimeout(() => { 
        toast.style.opacity = '0'; 
        toast.style.transform = 'translateY(20px)'; 
        setTimeout(() => toast.remove(), 300); 
    }, 3000);
}

// ============================================
// Copy Functions
// ============================================
function copyCommand(command) {
    navigator.clipboard.writeText(command).then(() => showToast('Command copied to clipboard!', 'success')).catch(() => showToast('Failed to copy', 'error'));
}

function copyText(text, message) {
    navigator.clipboard.writeText(text).then(() => showToast(message || 'Copied to clipboard!', 'success')).catch(() => showToast('Failed to copy', 'error'));
}

function copyTextById(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        navigator.clipboard.writeText(element.textContent).then(() => showToast('Copied to clipboard!', 'success')).catch(() => showToast('Failed to copy', 'error'));
    }
}

function quickConnect(host, sshCommand) {
    showToast('Connecting to ' + host + '...', 'success');
}

// ============================================
// Hash Generation Functions
// ============================================
async function generateQuickHash(event) {
    const btn = event.currentTarget;
    const input = document.getElementById('quickHashInput').value;
    const type = document.getElementById('quickHashType').value;
    
    if (!input) { 
        showToast('Please enter text to hash', 'error'); 
        return; 
    }
    
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<span class="loading-spinner"></span> Generating...';
    btn.disabled = true;
    
    try {
        let hash;
        
        if (type === 'md5') {
            const response = await fetch('{{ url("/hash-toolbox/hash-text") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ text: input, type: 'md5' })
            });
            const data = await response.json();
            hash = data.hash;
        } else {
            const algorithm = type === 'sha1' ? 'SHA-1' : 'SHA-256';
            const msgBuffer = new TextEncoder().encode(input);
            const hashBuffer = await crypto.subtle.digest(algorithm, msgBuffer);
            const hashArray = Array.from(new Uint8Array(hashBuffer));
            hash = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
        }
        
        document.getElementById('hashValue').textContent = hash;
        document.getElementById('quickHashResult').style.display = 'block';
        showToast('Hash generated successfully!', 'success');
        document.getElementById('quickHashResult').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } catch (error) {
        console.error('Error:', error);
        showToast('Error generating hash', 'error');
    } finally {
        btn.innerHTML = originalHtml;
        btn.disabled = false;
    }
}

async function generateBcryptHash(event) {
    const btn = event.currentTarget;
    const password = document.getElementById('bcryptInput').value;
    const rounds = document.getElementById('bcryptRounds').value;
    
    if (!password) { 
        showToast('Please enter a password', 'error'); 
        return; 
    }
    if (rounds < 4 || rounds > 31) { 
        showToast('Rounds must be between 4 and 31', 'error'); 
        return; 
    }
    
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<span class="loading-spinner"></span> Generating...';
    btn.disabled = true;
    
    try {
        const response = await fetch('{{ url("/hash-toolbox/generate-bcrypt") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ password: password, rounds: parseInt(rounds) })
        });
        const data = await response.json();
        
        if (data.success) {
            const hashElement = document.getElementById('bcryptHashValue');
            hashElement.textContent = data.hash;
            document.getElementById('bcryptResult').style.display = 'block';
            showToast('Bcrypt hash generated!', 'success');
            document.getElementById('bcryptResult').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
            showToast(data.error || 'Failed to generate hash', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Error generating bcrypt hash', 'error');
    } finally {
        btn.innerHTML = originalHtml;
        btn.disabled = false;
    }
}

// ============================================
// Num Lock Functions - UPDATED WITH CURSOR FIX
// ============================================
let numlockPollingInterval = null;
let isNumlockRunning = false;

async function toggleNumlock() {
    const btn = document.getElementById('toggleNumlockBtn');
    const icon = document.getElementById('toggleIcon');
    const spinner = document.getElementById('numlock-spinner');
    const statusElement = document.getElementById('numlock-status');
    const countElement = document.getElementById('numlock-count');
    
    if (!btn) return;
    
    if (isNumlockRunning) {
        // Stop the script
        if (spinner) spinner.classList.remove('d-none');
        btn.disabled = true;
        btn.style.cursor = 'wait';
        btn.style.opacity = '0.7';
        
        try {
            const response = await fetch('{{ url("/dashboard/numlock/stop") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });
            const data = await response.json();
            
            if (data.success) {
                isNumlockRunning = false;
                if (icon) icon.className = 'bi bi-play-fill';
                btn.style.background = 'var(--gradient-primary)';
                btn.style.cursor = 'pointer';
                if (statusElement) {
                    statusElement.innerHTML = 'Click the <i class="bi bi-play-fill"></i> icon to start toggling Num Lock every 5 seconds';
                    statusElement.style.color = '#6c757d';
                }
                stopNumlockPolling();
                if (countElement) countElement.textContent = '0';
                showToast(data.message, 'success');
            } else {
                showToast(data.message || 'Error stopping script', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showToast('Error stopping script', 'error');
        } finally {
            if (spinner) spinner.classList.add('d-none');
            btn.disabled = false;
            btn.style.opacity = '1';
            btn.style.cursor = 'pointer';
        }
    } else {
        // Start the script
        if (spinner) spinner.classList.remove('d-none');
        btn.disabled = true;
        btn.style.cursor = 'wait';
        btn.style.opacity = '0.7';
        
        try {
            const response = await fetch('{{ url("/dashboard/numlock/start") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });
            const data = await response.json();
            
            if (data.success) {
                isNumlockRunning = true;
                if (icon) icon.className = 'bi bi-stop-fill';
                btn.style.background = '#dc3545';
                btn.style.cursor = 'pointer';
                if (statusElement) {
                    statusElement.innerHTML = '✓ Running - Toggling Num Lock every 5 seconds. Click the <i class="bi bi-stop-fill"></i> icon to stop.';
                    statusElement.style.color = '#10b981';
                }
                startNumlockPolling();
                showToast(data.message, 'success');
            } else {
                showToast(data.message || 'Failed to start script', 'error');
                if (spinner) spinner.classList.add('d-none');
                btn.disabled = false;
                btn.style.opacity = '1';
                btn.style.cursor = 'pointer';
            }
        } catch (error) {
            console.error('Error:', error);
            showToast('Error starting script: ' + error.message, 'error');
            if (spinner) spinner.classList.add('d-none');
            btn.disabled = false;
            btn.style.opacity = '1';
            btn.style.cursor = 'pointer';
        }
    }
}

function startNumlockPolling() {
    if (numlockPollingInterval) clearInterval(numlockPollingInterval);
    numlockPollingInterval = setInterval(async () => {
        try {
            const response = await fetch('{{ url("/dashboard/numlock/status") }}', {
                method: 'GET',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });
            
            if (!response.ok) {
                console.error('Status endpoint returned:', response.status);
                return;
            }
            
            const data = await response.json();
            const countElement = document.getElementById('numlock-count');
            const icon = document.getElementById('toggleIcon');
            const btn = document.getElementById('toggleNumlockBtn');
            const statusElement = document.getElementById('numlock-status');
            
            if (countElement && data.count !== undefined) {
                countElement.textContent = data.count;
            }
            
            // Sync the running state and ensure cursor is correct
            if (data.is_running !== isNumlockRunning) {
                isNumlockRunning = data.is_running;
                if (isNumlockRunning) {
                    if (icon) icon.className = 'bi bi-stop-fill';
                    if (btn) {
                        btn.style.background = '#dc3545';
                        btn.style.cursor = 'pointer';
                    }
                    if (statusElement) {
                        statusElement.innerHTML = '✓ Running - Toggling Num Lock every 5 seconds. Click the <i class="bi bi-stop-fill"></i> icon to stop.';
                        statusElement.style.color = '#10b981';
                    }
                } else {
                    if (icon) icon.className = 'bi bi-play-fill';
                    if (btn) {
                        btn.style.background = 'var(--gradient-primary)';
                        btn.style.cursor = 'pointer';
                    }
                    if (statusElement) {
                        statusElement.innerHTML = 'Click the <i class="bi bi-play-fill"></i> icon to start toggling Num Lock every 5 seconds';
                        statusElement.style.color = '#6c757d';
                    }
                }
            } else {
                // Ensure cursor is always pointer when not disabled
                if (btn && !btn.disabled && btn.style.cursor !== 'pointer') {
                    btn.style.cursor = 'pointer';
                }
            }
        } catch (error) {
            console.error('Error fetching status:', error);
        }
    }, 1000);
}

function stopNumlockPolling() {
    if (numlockPollingInterval) {
        clearInterval(numlockPollingInterval);
        numlockPollingInterval = null;
    }
}

// ============================================
// DOM Initialization
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    // Setup Num Lock toggle button with proper cursor
    const toggleBtn = document.getElementById('toggleNumlockBtn');
    if (toggleBtn) {
        // Set initial cursor and styling
        toggleBtn.style.cursor = 'pointer';
        
        // Remove any existing listeners to prevent duplicates
        const newBtn = toggleBtn.cloneNode(true);
        toggleBtn.parentNode.replaceChild(newBtn, toggleBtn);
        
        // Add click event listener to new button
        newBtn.addEventListener('click', toggleNumlock);
        
        // Ensure cursor stays pointer on the new button
        newBtn.style.cursor = 'pointer';
        
        // Make icon non-interactive so clicks go to button
        const icon = document.getElementById('toggleIcon');
        if (icon) {
            icon.style.pointerEvents = 'none';
        }
    }
    
    // Check initial status on page load
    async function checkInitialStatus() {
        try {
            const response = await fetch('{{ url("/dashboard/numlock/status") }}', {
                method: 'GET',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
            });
            
            if (!response.ok) {
                console.error('Status check failed:', response.status);
                return;
            }
            
            const data = await response.json();
            
            const countElement = document.getElementById('numlock-count');
            const icon = document.getElementById('toggleIcon');
            const btn = document.getElementById('toggleNumlockBtn');
            const statusElement = document.getElementById('numlock-status');
            
            if (countElement && data.count !== undefined) {
                countElement.textContent = data.count;
            }
            
            if (btn) {
                btn.style.cursor = 'pointer';
            }
            
            if (data.is_running) {
                isNumlockRunning = true;
                if (icon) icon.className = 'bi bi-stop-fill';
                if (btn) btn.style.background = '#dc3545';
                if (statusElement) {
                    statusElement.innerHTML = '✓ Running - Toggling Num Lock every 5 seconds. Click the <i class="bi bi-stop-fill"></i> icon to stop.';
                    statusElement.style.color = '#10b981';
                }
                startNumlockPolling();
            } else {
                isNumlockRunning = false;
                if (icon) icon.className = 'bi bi-play-fill';
                if (btn) btn.style.background = 'var(--gradient-primary)';
                if (statusElement) {
                    statusElement.innerHTML = 'Click the <i class="bi bi-play-fill"></i> icon to start toggling Num Lock every 5 seconds';
                    statusElement.style.color = '#6c757d';
                }
            }
        } catch (error) {
            console.error('Error checking status:', error);
        }
    }
    
    checkInitialStatus();
});
</script>
@endsection