@extends('layouts.app')

@section('title', 'System Monitor')

@section('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    /* ── Dark/Light Mode Variables ── */
    :root {
        --bg-primary: #f1f5f9;
        --bg-card: #ffffff;
        --bg-card-hover: #f8fafc;
        --text-primary: #1e293b;
        --text-secondary: #64748b;
        --text-muted: #94a3b8;
        --border-color: #e2e8f0;
        --shadow-color: rgba(0,0,0,0.08);
        --card-shadow: 0 10px 25px -5px rgba(0,0,0,0.08);
        --input-bg: #ffffff;
        --table-stripe: #f8fafc;
        --tracker-bg-from: #0f172a;
        --tracker-bg-to: #1e293b;
        --tracker-text: #e2e8f0;
        --tracker-muted: #94a3b8;
        --tracker-border: #334155;
        --alert-bg: #fef3c7;
        --alert-text: #92400e;
        --alert-border: #f59e0b;
        --badge-ram-bg: #dbeafe;
        --badge-ram-text: #1e40af;
        --badge-swap-bg: #fce4ec;
        --badge-swap-text: #c62828;
        --scrollbar-track: #e2e8f0;
        --scrollbar-thumb: #cbd5e1;
        --toast-bg: #1e293b;
        --toast-text: #ffffff;
        --overlay-bg: rgba(255,255,255,0.85);
        --glass-bg: rgba(255,255,255,0.6);
        --glass-border: rgba(255,255,255,0.3);
        /* --gradient-primary: linear-gradient(135deg, #667eea, #764ba2); */
        --stat-value-color: #667eea;
        --memory-used: linear-gradient(135deg, #667eea, #764ba2);
        --memory-free: linear-gradient(135deg, #10b981, #059669);
        --memory-cache: linear-gradient(135deg, #f59e0b, #d97706);
        --memory-swap-used: linear-gradient(135deg, #ef4444, #dc2626);
        --memory-swap-free: linear-gradient(135deg, #3b82f6, #2563eb);
        --health-success: #d1fae5;
        --health-success-text: #059669;
        --health-warning: #fef3c7;
        --health-warning-text: #d97706;
        --health-danger: #fee2e2;
        --health-danger-text: #dc2626;
        --app-card-bg: #ffffff;
        --app-card-border: #e2e8f0;
        --process-hover: #f8fafc;
        --clean-tracker-text: #e2e8f0;
    }

    /* ── Dark Mode ── */
    body.dark-mode {
        --bg-primary: #0f172a;
        --bg-card: #1e293b;
        --bg-card-hover: #2d3748;
        --text-primary: #f1f5f9;
        --text-secondary: #94a3b8;
        --text-muted: #64748b;
        --border-color: #334155;
        --shadow-color: rgba(0,0,0,0.3);
        --card-shadow: 0 10px 25px -5px rgba(0,0,0,0.4);
        --input-bg: #1e293b;
        --table-stripe: #162032;
        --tracker-bg-from: #0f172a;
        --tracker-bg-to: #1e293b;
        --tracker-text: #e2e8f0;
        --tracker-muted: #64748b;
        --tracker-border: #334155;
        --alert-bg: #451a03;
        --alert-text: #fbbf24;
        --alert-border: #78350f;
        --badge-ram-bg: #1e3a5f;
        --badge-ram-text: #93c5fd;
        --badge-swap-bg: #4a1a2a;
        --badge-swap-text: #fca5a5;
        --scrollbar-track: #1e293b;
        --scrollbar-thumb: #334155;
        --toast-bg: #1e293b;
        --toast-text: #f1f5f9;
        --overlay-bg: rgba(15,23,42,0.85);
        --glass-bg: rgba(30,41,59,0.6);
        --glass-border: rgba(51,65,85,0.3);
        --gradient-primary: linear-gradient(135deg, #818cf8, #8b5cf6);
        --stat-value-color: #818cf8;
        --health-success: #064e3b;
        --health-success-text: #34d399;
        --health-warning: #451a03;
        --health-warning-text: #fbbf24;
        --health-danger: #450a0a;
        --health-danger-text: #f87171;
        --app-card-bg: #1e293b;
        --app-card-border: #334155;
        --process-hover: #2d3748;
        --clean-tracker-text: #e2e8f0;
        --memory-used: linear-gradient(135deg, #818cf8, #8b5cf6);
        --memory-free: linear-gradient(135deg, #34d399, #059669);
        --memory-cache: linear-gradient(135deg, #fbbf24, #d97706);
        --memory-swap-used: linear-gradient(135deg, #f87171, #dc2626);
        --memory-swap-free: linear-gradient(135deg, #60a5fa, #2563eb);
    }

    body { background: var(--bg-primary); color: var(--text-primary); transition: background 0.3s ease, color 0.3s ease; }
    .monitor-container { max-width: 1400px; margin: 0 auto; }

    .stat-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 20px;
        text-align: center;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
        height: 100%;
    }
    .stat-card:hover { transform: translateY(-3px); box-shadow: var(--card-shadow); }

    .stat-value {
        font-size: 1.8rem;
        font-weight: 800;
        color: var(--stat-value-color);
        font-family: 'Space Grotesk', monospace;
    }
    .stat-label {
        font-size: 0.75rem;
        color: var(--text-secondary);
        margin-top: 5px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .glass-card {
        background: var(--glass-bg);
        backdrop-filter: blur(10px);
        border: 1px solid var(--glass-border);
        border-radius: 16px;
        padding: 20px;
        transition: background 0.3s ease;
    }

    .gradient-header {
        background: var(--gradient-primary);
        color: white;
        padding: 2rem;
        border-radius: 16px 16px 0 0;
        transition: background 0.3s ease;
    }

    .memory-bar-container {
        background: var(--border-color);
        border-radius: 20px;
        overflow: hidden;
        height: 30px;
        position: relative;
    }
    .memory-bar { height: 100%; transition: width 0.5s ease; border-radius: 20px; }
    .memory-bar.used { background: var(--memory-used); }
    .memory-bar.free { background: var(--memory-free); }
    .memory-bar.cache { background: var(--memory-cache); }
    .memory-bar.swap-used { background: var(--memory-swap-used); }
    .memory-bar.swap-free { background: var(--memory-swap-free); }

    .memory-label {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-primary);
        text-shadow: 0 1px 2px rgba(255,255,255,0.3);
    }

    .status-badge {
        display: inline-block;
        padding: 6px 16px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.85rem;
    }
    .status-success { background: var(--health-success); color: var(--health-success-text); }
    .status-warning { background: var(--health-warning); color: var(--health-warning-text); }
    .status-danger { background: var(--health-danger); color: var(--health-danger-text); }

    .process-table { font-size: 0.85rem; }
    .process-table th {
        background: var(--table-stripe);
        font-weight: 600;
        padding: 12px;
        border-bottom: 2px solid var(--border-color);
        color: var(--text-primary);
    }
    .process-table td {
        padding: 10px 12px;
        border-bottom: 1px solid var(--border-color);
        vertical-align: middle;
        color: var(--text-primary);
    }
    .process-table tr:hover td { background: var(--process-hover); }

    .cpu-bar { width: 100px; height: 8px; background: var(--border-color); border-radius: 4px; overflow: hidden; display: inline-block; }
    .cpu-bar-fill { height: 100%; border-radius: 4px; transition: width 0.3s ease; }
    .cpu-bar-fill.low { background: #10b981; }
    .cpu-bar-fill.medium { background: #f59e0b; }
    .cpu-bar-fill.high { background: #ef4444; }

    .kill-btn {
        padding: 4px 12px;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .kill-btn:hover { transform: scale(1.05); }

    .refresh-btn {
        position: fixed;
        bottom: 30px;
        right: 30px;
        z-index: 100;
        background: var(--gradient-primary);
        border: none;
        border-radius: 50%;
        width: 60px;
        height: 60px;
        color: white;
        font-size: 1.5rem;
        cursor: pointer;
        box-shadow: 0 10px 30px rgba(99, 102, 241, 0.3);
        transition: all 0.3s ease;
    }
    .refresh-btn:hover { transform: scale(1.1) rotate(180deg); box-shadow: 0 15px 40px rgba(99, 102, 241, 0.4); }
    .refresh-btn.spinning { animation: spin 1s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }

    .app-card {
        background: var(--app-card-bg);
        border-radius: 12px;
        padding: 16px;
        border: 1px solid var(--app-card-border);
        transition: all 0.3s ease;
        margin-bottom: 12px;
    }
    .app-card:hover { box-shadow: var(--card-shadow); border-color: #667eea; }
    .app-card .app-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
        flex-wrap: wrap;
        gap: 10px;
    }
    .app-card .app-name {
        font-weight: 700;
        font-size: 1.05rem;
        color: var(--text-primary);
    }
    .app-card .app-stats {
        display: flex;
        gap: 16px;
        font-size: 0.85rem;
        color: var(--text-secondary);
        flex-wrap: wrap;
    }
    .app-card .app-stats span {
        background: var(--table-stripe);
        padding: 2px 10px;
        border-radius: 12px;
    }
    .app-process-list {
        margin-top: 8px;
        padding-left: 16px;
        border-left: 3px solid var(--border-color);
    }
    .app-process-item {
        display: flex;
        justify-content: space-between;
        padding: 3px 0;
        font-size: 0.8rem;
        color: var(--text-secondary);
        flex-wrap: wrap;
        gap: 4px;
    }
    .app-process-item .pid {
        font-weight: 600;
        color: var(--text-primary);
        min-width: 50px;
    }

    .memory-type-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 0.65rem;
        font-weight: 600;
    }
    .memory-type-badge.ram { background: var(--badge-ram-bg); color: var(--badge-ram-text); }
    .memory-type-badge.swap { background: var(--badge-swap-bg); color: var(--badge-swap-text); }

    /* ── Clean Tracker Card ── */
    .clean-tracker-card {
        background: linear-gradient(135deg, var(--tracker-bg-from) 0%, var(--tracker-bg-to) 100%);
        border-radius: 16px;
        padding: 20px 24px;
        border: 1px solid var(--tracker-border);
        color: var(--clean-tracker-text);
        position: relative;
        overflow: hidden;
    }
    .clean-tracker-card::before {
        content: '';
        position: absolute;
        top: -30px;
        right: -30px;
        width: 120px;
        height: 120px;
        background: radial-gradient(circle, rgba(99,102,241,0.25) 0%, transparent 70%);
        border-radius: 50%;
    }
    .clean-stat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
        gap: 16px;
        margin-top: 14px;
    }
    .clean-stat { text-align: center; }
    .clean-stat-value {
        font-size: 1.6rem;
        font-weight: 800;
        font-family: 'Space Grotesk', monospace;
        line-height: 1;
        transition: all 0.2s ease;
    }
    .clean-stat-value.chrome  { color: #60a5fa; }
    .clean-stat-value.standard { color: #34d399; }
    .clean-stat-value.total   { color: #f59e0b; }
    .clean-stat-value.sessions { color: #c084fc; }
    .clean-stat-label {
        font-size: 0.68rem;
        color: var(--tracker-muted);
        text-transform: uppercase;
        letter-spacing: 0.6px;
        margin-top: 4px;
    }
    .clean-last-run {
        font-size: 0.75rem;
        color: var(--tracker-muted);
        margin-top: 10px;
    }

    .pulse-dot {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #10b981;
        animation: pulse-anim 1.5s infinite;
        margin-right: 5px;
    }
    .pulse-dot.orange { background: #f59e0b; }
    .pulse-dot.off    { background: #475569; animation: none; }
    @keyframes pulse-anim {
        0%,100% { opacity: 1; transform: scale(1); }
        50%      { opacity: 0.4; transform: scale(1.4); }
    }

    .freed-badge {
        display: inline-block;
        background: rgba(16,185,129,0.15);
        color: #34d399;
        border: 1px solid rgba(16,185,129,0.3);
        border-radius: 20px;
        padding: 2px 10px;
        font-size: 0.72rem;
        font-weight: 700;
        margin-left: 6px;
        vertical-align: middle;
    }
    .freed-badge.chrome-col {
        background: rgba(96,165,250,0.15);
        color: #60a5fa;
        border-color: rgba(96,165,250,0.3);
    }

    .history-log {
        max-height: 140px;
        overflow-y: auto;
        margin-top: 10px;
        font-size: 0.72rem;
        color: var(--tracker-muted);
        font-family: monospace;
    }
    .history-log::-webkit-scrollbar { width: 4px; }
    .history-log::-webkit-scrollbar-track { background: var(--tracker-bg-to); }
    .history-log::-webkit-scrollbar-thumb { background: var(--tracker-border); border-radius: 2px; }
    .history-entry {
        padding: 2px 0;
        border-bottom: 1px solid var(--tracker-border);
    }
    .history-entry.chrome-entry { color: #60a5fa; }
    .history-entry.standard-entry { color: #34d399; }

    .reset-stats-btn {
        font-size: 0.68rem;
        color: var(--tracker-muted);
        background: none;
        border: none;
        cursor: pointer;
        padding: 0;
        text-decoration: underline;
    }
    .reset-stats-btn:hover { color: #ef4444; }

    .theme-toggle {
        background: none;
        border: none;
        font-size: 1.2rem;
        cursor: pointer;
        padding: 8px;
        border-radius: 50%;
        transition: background 0.3s ease;
        color: white;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .theme-toggle:hover { background: rgba(255,255,255,0.2); }

    #autoCleanWarning {
        background: var(--alert-bg);
        color: var(--alert-text);
        border: 1px solid var(--alert-border);
    }

    ::-webkit-scrollbar { width: 8px; height: 8px; }
    ::-webkit-scrollbar-track { background: var(--scrollbar-track); }
    ::-webkit-scrollbar-thumb { background: var(--scrollbar-thumb); border-radius: 4px; }

    .toast-container {
        position: fixed;
        bottom: 80px;
        right: 20px;
        z-index: 10002;
        display: flex;
        flex-direction: column;
        gap: 8px;
        max-width: 420px;
        width: 100%;
    }
    .toast-notification {
        padding: 12px 20px;
        border-radius: 10px;
        animation: slideInRight 0.3s ease;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        font-weight: 500;
        color: white;
        opacity: 1;
        /* transition: opacity 0.3s ease; */
        cursor: pointer;
        transition: all 0.3s ease;
    }
    .toast-notification.hiding {
        opacity: 0;
        transform: translateX(100%);
    }
    .toast-notification.success { background: #10b981; }
    .toast-notification.warning { background: #f59e0b; }
    .toast-notification.error { background: #ef4444; }
    .toast-notification.info { background: #3b82f6; }
    .toast-notification.hiding { opacity: 0; }

    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }

    #loadingOverlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: var(--overlay-bg);
        z-index: 9999;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(4px);
    }

    .form-check-input {
        background-color: var(--input-bg);
        border-color: var(--border-color);
    }
    .form-check-input:checked { background-color: #0d6efd; border-color: #0d6efd; }
    .form-check-input:focus { box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25); }
    .form-select {
        background-color: var(--input-bg);
        border-color: var(--border-color);
        color: var(--text-primary);
    }
    .form-select:focus { border-color: #667eea; box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25); }
    .form-check-label { color: var(--text-primary); }
    .text-muted { color: var(--text-muted) !important; }
    .border-top { border-color: var(--border-color) !important; }

    .stat-updated { animation: statPulse 0.3s ease; }
    @keyframes statPulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }

    @media (max-width: 768px) {
        .stat-value { font-size: 1.3rem; }
        .process-table { font-size: 0.7rem; }
        .process-table td, .process-table th { padding: 6px 8px; }
        .cpu-bar { width: 60px; }
        .app-card .app-stats { font-size: 0.7rem; gap: 6px; }
        .filter-selector { max-width: 100%; }
        .app-card .app-header { flex-direction: column; align-items: flex-start; }
        .clean-stat-value { font-size: 1.2rem; }
        .theme-toggle { font-size: 1rem; width: 32px; height: 32px; }
    }
</style>
@endsection

@section('content')
<div class="glass-card" data-aos="fade-down">
    <div class="gradient-header text-center position-relative">
        <button class="theme-toggle position-absolute top-0 end-0 m-3" onclick="toggleTheme()" title="Toggle Dark/Light Mode">
            <i class="bi bi-moon-fill" id="themeIcon"></i>
        </button>
        <h1 class="fw-bold">
            <i class="bi bi-cpu-fill me-3"></i>System Monitor
        </h1>
        <p class="lead mb-0">
            <i class="bi bi-activity me-2"></i>Real-time system resource monitoring
        </p>
    </div>

    <div class="p-4 p-md-5">
        <div class="monitor-container">

            <!-- ═══════════════════════════════════════════════════════
                 MEMORY FREED TRACKER CARD
            ════════════════════════════════════════════════════════ -->
            <div class="clean-tracker-card mb-4" id="cleanTrackerCard">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <h6 class="fw-bold mb-0" style="color:var(--clean-tracker-text);">
                            <i class="bi bi-broom-fill me-2" style="color:#f59e0b;"></i>
                            Auto-Clean Memory Tracker
                        </h6>
                        <div class="clean-last-run mt-1">
                            <span class="pulse-dot off" id="trackerPulseDot"></span>
                            <span id="trackerStatusText">Auto-clean is off</span>
                            &nbsp;·&nbsp;
                            Last run: <span id="lastRunTime">—</span>
                            &nbsp;·&nbsp;
                            <button class="reset-stats-btn" onclick="resetStats()">Reset today's stats</button>
                        </div>
                    </div>
                    <div style="font-size:0.72rem; color:var(--tracker-muted);">
                        Resets at midnight &nbsp;🌙
                    </div>
                </div>

                <div class="clean-stat-grid">
                    <div class="clean-stat">
                        <div class="clean-stat-value chrome" id="chromeMbFreed">0 MB</div>
                        <div class="clean-stat-label">Chrome Freed (today)</div>
                    </div>
                    <div class="clean-stat">
                        <div class="clean-stat-value standard" id="standardMbFreed">0 MB</div>
                        <div class="clean-stat-label">Standard Freed (today)</div>
                    </div>
                    <div class="clean-stat">
                        <div class="clean-stat-value total" id="totalMbFreed">0 MB</div>
                        <div class="clean-stat-label">Total Freed (today)</div>
                    </div>
                    <div class="clean-stat">
                        <div class="clean-stat-value" id="cleanRunCount" style="color:#c084fc;">0</div>
                        <div class="clean-stat-label">Runs today</div>
                    </div>
                    <div class="clean-stat">
                        <div class="clean-stat-value" id="lastRunFreed" style="color:#f87171;">0 MB</div>
                        <div class="clean-stat-label">Last run freed</div>
                    </div>
                    <div class="clean-stat">
                        <div class="clean-stat-value sessions" id="sessionMbFreed">0 MB</div>
                        <div class="clean-stat-label">This session</div>
                    </div>
                </div>

                <!-- History log -->
                <div class="history-log" id="cleanHistoryLog">
                    <div style="color:var(--tracker-muted); font-style:italic;">No auto-clean activity yet...</div>
                </div>
            </div>

            <!-- Filter + Quick Stats row -->
            <div class="row mb-4">
                <div class="col-md-6 col-lg-4">
                    <div class="glass-card p-3">
                        <label class="fw-bold mb-2" style="color:var(--text-primary);">
                            <i class="bi bi-funnel-fill me-2"></i>Memory View Filter
                        </label>
                        <select id="memoryFilter" class="form-select filter-selector" onchange="applyFilter()">
                            <option value="both">🔄 Both (RAM + Swap)</option>
                            <option value="ram">🧠 RAM Only</option>
                            <option value="swap">💾 Swap Only</option>
                        </select>
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i>
                            Changes how processes and apps are sorted
                        </small>
                    </div>
                </div>
                <div class="col-md-6 col-lg-8">
                    <div class="glass-card p-3" id="filterStats">
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="fw-bold" style="color:var(--stat-value-color);" id="filterTotalProcesses">0</div>
                                <small class="text-muted">Processes</small>
                            </div>
                            <div class="col-4">
                                <div class="fw-bold text-success" id="filterTotalMemory">0 MB</div>
                                <small class="text-muted">Total Memory</small>
                            </div>
                            <div class="col-4">
                                <div class="fw-bold text-warning" id="filterMemoryType">-</div>
                                <small class="text-muted">Memory Type</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row g-3 mb-4" id="statsCards">
                <div class="col-6 col-md-3 ram-stat">
                    <div class="stat-card">
                        <div class="stat-value" id="totalRam">-</div>
                        <div class="stat-label">Total RAM</div>
                    </div>
                </div>
                <div class="col-6 col-md-3 ram-stat">
                    <div class="stat-card">
                        <div class="stat-value" id="freeRam">-</div>
                        <div class="stat-label">Free RAM</div>
                    </div>
                </div>
                <div class="col-6 col-md-3 ram-stat">
                    <div class="stat-card">
                        <div class="stat-value" id="availableRam">-</div>
                        <div class="stat-label">Available RAM</div>
                    </div>
                </div>
                <div class="col-6 col-md-3 swap-stat">
                    <div class="stat-card">
                        <div class="stat-value" id="swapFree">-</div>
                        <div class="stat-label">Free Swap</div>
                    </div>
                </div>
            </div>

            <!-- Memory Visualization + Health -->
            <div class="row g-4 mb-4">
                <div class="col-md-8">
                    <div class="glass-card p-4">
                        <h5 class="fw-bold mb-3" style="color:var(--text-primary);">
                            <i class="bi bi-memory"></i> Memory Usage Visualization
                            <span id="filterLabel" class="badge bg-primary ms-2">Both</span>
                        </h5>
                        <div id="memoryVisualization">
                            <div class="mb-3 ram-vis" id="ramVisContainer">
                                <div class="d-flex justify-content-between mb-1">
                                    <span><strong style="color:var(--text-primary);">RAM</strong></span>
                                    <span id="ramLabel" style="color:var(--text-secondary);">Loading...</span>
                                </div>
                                <div class="memory-bar-container">
                                    <div id="ramUsedBar"  class="memory-bar used"  style="width:0%;"></div>
                                    <div id="ramFreeBar"  class="memory-bar free"  style="width:0%;"></div>
                                    <div id="ramCacheBar" class="memory-bar cache" style="width:0%;"></div>
                                    <span class="memory-label" id="ramPercentLabel">0%</span>
                                </div>
                                <div class="d-flex justify-content-between mt-1 small text-muted">
                                    <span>Used: <span id="ramUsedLabel">0%</span></span>
                                    <span>Free: <span id="ramFreeLabel">0%</span></span>
                                    <span>Cache: <span id="ramCacheLabel">0%</span></span>
                                </div>
                            </div>
                            <div class="swap-vis" id="swapVisContainer">
                                <div class="d-flex justify-content-between mb-1">
                                    <span><strong style="color:var(--text-primary);">Swap</strong></span>
                                    <span id="swapLabel" style="color:var(--text-secondary);">Loading...</span>
                                </div>
                                <div class="memory-bar-container">
                                    <div id="swapUsedBar" class="memory-bar swap-used" style="width:0%;"></div>
                                    <div id="swapFreeBar" class="memory-bar swap-free" style="width:0%;"></div>
                                    <span class="memory-label" id="swapPercentLabel">0%</span>
                                </div>
                                <div class="d-flex justify-content-between mt-1 small text-muted">
                                    <span>Used: <span id="swapUsedLabel">0%</span></span>
                                    <span>Free: <span id="swapFreeLabel">0%</span></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="glass-card p-4">
                        <h5 class="fw-bold mb-3" style="color:var(--text-primary);">
                            <i class="bi bi-heart-pulse"></i> Health Status
                        </h5>
                        <div id="healthStatus">
                            <div class="mb-2 ram-health" id="healthRamFreeContainer">
                                <span class="fw-bold" style="color:var(--text-primary);">RAM Free:</span>
                                <span id="healthRamFree" class="status-badge status-success">✅ Good</span>
                            </div>
                            <div class="mb-2 ram-health" id="healthRamAvailContainer">
                                <span class="fw-bold" style="color:var(--text-primary);">RAM Available:</span>
                                <span id="healthRamAvail" class="status-badge status-success">✅ Excellent</span>
                            </div>
                            <div class="mb-2 swap-health" id="healthSwapFreeContainer">
                                <span class="fw-bold" style="color:var(--text-primary);">Swap Free:</span>
                                <span id="healthSwapFree" class="status-badge status-success">✅ Good</span>
                            </div>
                            <div class="mt-3 pt-3 border-top">
                                <span class="fw-bold" style="color:var(--text-primary);">Overall:</span>
                                <span id="healthOverall" class="status-badge status-success">🟢 Healthy</span>
                            </div>
                            <div class="mt-2">
                                <span class="fw-bold" style="color:var(--text-primary);">Real Issue:</span>
                                <span id="healthRealIssue" class="text-muted small">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Processes -->
            <div class="glass-card p-4 mb-4" id="topProcessesSection">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h5 class="fw-bold mb-0" style="color:var(--text-primary);">
                        <i class="bi bi-list-ul"></i> Top CPU Consumers
                        <span class="text-muted small fw-normal d-block d-md-inline ms-md-2" style="font-size:0.7rem;">
                            (system-critical processes are hidden and cannot be selected or killed here)
                        </span>
                    </h5>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <!-- Chrome Auto-Clean Toggle -->
                        <div class="form-check form-switch d-flex align-items-center gap-1 me-1"
                             title="Every 2 seconds: Kill top 5 low-priority Chrome processes">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="chromeAutoCleanToggle" onchange="toggleChromeAutoClean(this.checked)">
                            <label class="form-check-label small fw-bold" for="chromeAutoCleanToggle">
                                <span class="text-primary">Chrome</span>
                                <span id="chromeAutoCleanStatus" class="text-muted">(off)</span>
                                <span class="freed-badge chrome-col" id="chromeBadge" style="display:none;">0 MB freed</span>
                            </label>
                        </div>

                        <!-- Standard Auto-Clean Toggle -->
                        <div class="form-check form-switch d-flex align-items-center gap-1 me-2"
                             title="Every 2 seconds: Kill low-priority processes (SAFE)">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="standardAutoCleanToggle" onchange="toggleStandardAutoClean(this.checked)">
                            <label class="form-check-label small fw-bold" for="standardAutoCleanToggle">
                                <span class="text-success">Standard</span>
                                <span id="standardAutoCleanStatus" class="text-muted">(off)</span>
                                <span class="freed-badge" id="standardBadge" style="display:none;">0 MB freed</span>
                            </label>
                        </div>

                        <button class="btn btn-sm btn-primary" onclick="manualRefresh()">
                            <i class="bi bi-arrow-clockwise"></i> Refresh
                        </button>
                        <button class="btn btn-sm btn-warning" onclick="clearLowPriority()">
                            <i class="bi bi-broom"></i> Clear Low Priority
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="killSelectedProcesses()">
                            <i class="bi bi-trash"></i> Kill Selected
                        </button>
                        <button class="btn btn-sm btn-outline-success" onclick="freeMemory()">
                            <i class="bi bi-brush"></i> Clear Cache
                        </button>
                    </div>
                </div>

                <!-- Auto-Clean Warning Banner -->
                <div id="autoCleanWarning" class="alert small mb-3 py-2" style="display:none;">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>Auto-Clean Active</strong> — running every <strong>2 seconds</strong>:
                    <ul class="mb-0 mt-1 ps-3">
                        <li id="chromeCleanInfo"   style="display:none;">🗑️ <strong>Chrome:</strong> Kills top 5 low-priority Chrome processes (CPU &lt; 0.5%, Memory &gt; 100 MB)</li>
                        <li id="standardCleanInfo" style="display:none;">🧹 <strong>Standard:</strong> Kills idle background processes (SAFE — no system services touched)</li>
                        <li id="safetyInfo"        style="display:none;">✅ Does NOT touch systemd, display managers, SSH, web servers, DBs, or any restart-sensitive service</li>
                    </ul>
                    <span class="text-danger">⚠️ Turn off toggles if you need background processes to stay alive!</span>
                </div>

                <div class="table-responsive">
                    <table class="process-table table">
                        <thead>
                            <tr>
                                <th style="width:30px;">
                                    <input type="checkbox" id="selectAllProcesses" onchange="toggleAllProcesses()">
                                </th>
                                <th>PID</th>
                                <th>User</th>
                                <th>Command</th>
                                <th>CPU %</th>
                                <th>Memory</th>
                                <th>Type</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="processTableBody">
                            <tr><td colspan="8" class="text-center" style="color:var(--text-secondary);">Loading processes...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Application CPU Usage -->
            <div class="glass-card p-4 mb-4">
                <h5 class="fw-bold mb-3" style="color:var(--text-primary);">
                    <i class="bi bi-app-indicator"></i> Application CPU Usage (Top 10)
                    <span class="text-muted small fw-normal">(sum of all processes per app)</span>
                </h5>
                <div id="appProcessContainer">
                    <div class="text-center text-muted">Loading applications...</div>
                </div>
            </div>

            <!-- System Info -->
            <div class="row g-3 mt-3">
                <div class="col-md-4">
                    <div class="glass-card p-3 text-center">
                        <small class="text-muted">Hostname</small>
                        <div class="fw-bold" style="color:var(--text-primary);" id="hostname">-</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="glass-card p-3 text-center">
                        <small class="text-muted">OS</small>
                        <div class="fw-bold" style="color:var(--text-primary);" id="os">-</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="glass-card p-3 text-center">
                        <small class="text-muted">Uptime</small>
                        <div class="fw-bold" style="color:var(--text-primary);" id="uptime">-</div>
                    </div>
                </div>
            </div>

        </div><!-- /monitor-container -->
    </div>
</div>

<!-- Floating refresh -->
<button class="refresh-btn" id="refreshBtn" onclick="refreshData()">
    <i class="bi bi-arrow-repeat"></i>
</button>

<!-- Loading overlay -->
<div id="loadingOverlay" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:var(--overlay-bg); z-index:9999; align-items:center; justify-content:center;">
    <div class="text-center">
        <div class="spinner-border text-primary" role="status" style="width:3rem; height:3rem;">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-3 fw-bold" style="color:var(--text-primary);">Refreshing system data...</p>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>
@endsection

@section('scripts')
<script>
const csrfToken = '{{ csrf_token() }}';
let selectedPids = new Set();
let autoRefreshInterval = null;
let isRefreshing = false;
let currentFilter = 'both';
let chromeAutoCleanInterval = null;
let standardAutoCleanInterval = null;
let isChromeAutoCleanRunning = false;
let isStandardAutoCleanRunning = false;
let isChromeRunning = false;
let isStandardRunning = false;

// ── Theme Toggle ──
function toggleTheme() {
    const body = document.body;
    const icon = document.getElementById('themeIcon');
    body.classList.toggle('dark-mode');
    if (body.classList.contains('dark-mode')) {
        icon.className = 'bi bi-sun-fill';
        localStorage.setItem('theme', 'dark');
    } else {
        icon.className = 'bi bi-moon-fill';
        localStorage.setItem('theme', 'light');
    }
}

function loadTheme() {
    const saved = localStorage.getItem('theme');
    const icon = document.getElementById('themeIcon');
    if (saved === 'dark') {
        document.body.classList.add('dark-mode');
        icon.className = 'bi bi-sun-fill';
    } else {
        document.body.classList.remove('dark-mode');
        icon.className = 'bi bi-moon-fill';
    }
}

// ── MEMORY FREED STATS ─────────────────────────────────────────────────────

const STATS_KEY = 'sysmon_clean_stats';
const todayKey = () => new Date().toISOString().slice(0, 10);

function loadStats() {
    try {
        const raw = localStorage.getItem(STATS_KEY);
        if (!raw) return null;
        const obj = JSON.parse(raw);
        if (obj.date !== todayKey()) return null;
        return obj;
    } catch { return null; }
}

function defaultStats() {
    return {
        date: todayKey(),
        chromeMb: 0,
        standardMb: 0,
        sessionMb: 0,
        runs: 0,
        lastRunMb: 0,
        lastRunAt: null,
        lastRunType: null,
        history: [],
    };
}

let stats = loadStats() || defaultStats();
let sessionMb = 0;

function saveStats() {
    localStorage.setItem(STATS_KEY, JSON.stringify(stats));
}

function recordClean(type, mb, count) {
    if (mb <= 0 && count <= 0) return;

    const now = new Date();
    if (type === 'chrome') stats.chromeMb = round1(stats.chromeMb + mb);
    if (type === 'standard') stats.standardMb = round1(stats.standardMb + mb);
    stats.runs++;
    stats.lastRunMb = round1(mb);
    stats.lastRunAt = now.toISOString();
    stats.lastRunType = type;

    sessionMb = round1(sessionMb + mb);

    const entry = {
        ts: now.toLocaleTimeString(),
        type: type,
        mb: round1(mb),
        count: count,
    };
    stats.history.unshift(entry);
    if (stats.history.length > 50) stats.history.pop();
    saveStats();
    renderStats();
    updateTrackerStatus();
}

function round1(n) { return Math.round(n * 10) / 10; }

function resetStats() {
    if (!confirm('Reset today\'s memory-freed stats?')) return;
    stats = defaultStats();
    sessionMb = 0;
    saveStats();
    renderStats();
    updateTrackerStatus();
}

function renderStats() {
    const total = round1(stats.chromeMb + stats.standardMb);

    const elements = {
        'chromeMbFreed': fmtMb(stats.chromeMb),
        'standardMbFreed': fmtMb(stats.standardMb),
        'totalMbFreed': fmtMb(total),
        'cleanRunCount': stats.runs,
        'lastRunFreed': fmtMb(stats.lastRunMb),
        'sessionMbFreed': fmtMb(sessionMb),
    };

    Object.keys(elements).forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.textContent = elements[id];
            // Add pulse animation on update
            if (id === 'totalMbFreed' || id === 'sessionMbFreed' || id === 'cleanRunCount') {
                el.classList.remove('stat-updated');
                void el.offsetWidth; // Force reflow
                el.classList.add('stat-updated');
            }
        }
    });

    // Update badges
    const chromeBadge = document.getElementById('chromeBadge');
    const standardBadge = document.getElementById('standardBadge');
    if (chromeBadge) chromeBadge.textContent = fmtMb(stats.chromeMb) + ' freed';
    if (standardBadge) standardBadge.textContent = fmtMb(stats.standardMb) + ' freed';

    // Last run time
    const lrEl = document.getElementById('lastRunTime');
    if (lrEl) {
        lrEl.textContent = stats.lastRunAt
            ? new Date(stats.lastRunAt).toLocaleTimeString()
            : '—';
    }

    // History log
    const logEl = document.getElementById('cleanHistoryLog');
    if (logEl) {
        if (stats.history.length === 0) {
            logEl.innerHTML = '<div style="color:var(--tracker-muted);font-style:italic;">No auto-clean activity yet...</div>';
        } else {
            logEl.innerHTML = stats.history.map(e => {
                const cls = e.type === 'chrome' ? 'chrome-entry' : 'standard-entry';
                const label = e.type === 'chrome' ? '🗑️ Chrome' : '🧹 Standard';
                const badge = e.mb > 0 ? ` — freed <strong>${fmtMb(e.mb)}</strong>` : '';
                const cnt = e.count > 0 ? ` (${e.count} proc)` : '';
                return `<div class="history-entry ${cls}">[${e.ts}] ${label}${cnt}${badge}</div>`;
            }).join('');
        }
    }
}

function fmtMb(mb) {
    if (mb >= 1024) return round1(mb / 1024) + ' GB';
    return mb + ' MB';
}

function setText(id, val) {
    const el = document.getElementById(id);
    if (el) el.textContent = val;
}

function updateTrackerStatus() {
    const dot = document.getElementById('trackerPulseDot');
    const text = document.getElementById('trackerStatusText');
    const chromeBadge = document.getElementById('chromeBadge');
    const standardBadge = document.getElementById('standardBadge');

    const anyOn = isChromeAutoCleanRunning || isStandardAutoCleanRunning;
    if (dot) { dot.className = 'pulse-dot' + (anyOn ? '' : ' off'); }

    const parts = [];
    if (isChromeAutoCleanRunning) parts.push('Chrome');
    if (isStandardAutoCleanRunning) parts.push('Standard');
    if (text) text.textContent = anyOn ? 'Auto-clean ON: ' + parts.join(' + ') : 'Auto-clean is off';

    if (chromeBadge) chromeBadge.style.display = isChromeAutoCleanRunning ? '' : 'none';
    if (standardBadge) standardBadge.style.display = isStandardAutoCleanRunning ? '' : 'none';

    // Show/hide warning banner
    const warningEl = document.getElementById('autoCleanWarning');
    if (warningEl) {
        warningEl.style.display = anyOn ? 'block' : 'none';
    }
}

// ── UI HELPERS ──────────────────────────────────────────────────────────────

function toggleAllProcesses() {
    const checked = document.getElementById('selectAllProcesses').checked;
    document.querySelectorAll('.process-checkbox').forEach(cb => {
        cb.checked = checked;
        updateSelectedPids(cb);
    });
}

function updateSelectedPids(checkbox) {
    const pid = checkbox.dataset.pid;
    if (checkbox.checked) selectedPids.add(pid);
    else selectedPids.delete(pid);
}

// ── Toast System ──
let toastTimeout = null;

function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');

    const iconMap = {
        success: 'check-circle-fill',
        warning: 'exclamation-triangle-fill',
        error: 'x-circle-fill',
        info: 'info-circle-fill'
    };

    toast.className = 'toast-notification ' + type;
    toast.style.cursor = 'pointer';

    toast.innerHTML = `
        <i class="bi bi-${iconMap[type] || 'info-circle-fill'} me-2"></i>
        ${message}
    `;

    container.appendChild(toast);

    const closeToast = () => {
        if (toastTimeout) {
            clearTimeout(toastTimeout);
            toastTimeout = null;
        }

        toast.classList.add('hiding');
        setTimeout(() => toast.remove(), 300);
    };

    // Close on click
    toast.addEventListener('click', closeToast);

    // Auto close after 3 seconds
    toastTimeout = setTimeout(closeToast, 3000);
}

function getCpuBarClass(cpu) {
    if (cpu < 10) return 'low';
    if (cpu < 50) return 'medium';
    return 'high';
}

function showLoadingOverlay() {
    const el = document.getElementById('loadingOverlay');
    if (el) el.style.display = 'flex';
}
function hideLoadingOverlay() {
    const el = document.getElementById('loadingOverlay');
    if (el) el.style.display = 'none';
}

function applyFilter() {
    currentFilter = document.getElementById('memoryFilter').value;
    updateVisibility(currentFilter);
    refreshData(true);
}

function updateVisibility(filter) {
    const showRam = filter === 'both' || filter === 'ram';
    const showSwap = filter === 'both' || filter === 'swap';
    document.querySelectorAll('.ram-stat').forEach(el => el.style.display = showRam ? 'block' : 'none');
    document.querySelectorAll('.swap-stat').forEach(el => el.style.display = showSwap ? 'block' : 'none');
    document.getElementById('ramVisContainer').style.display = showRam ? 'block' : 'none';
    document.getElementById('swapVisContainer').style.display = showSwap ? 'block' : 'none';
    document.querySelectorAll('.ram-health').forEach(el => el.style.display = showRam ? 'block' : 'none');
    document.querySelectorAll('.swap-health').forEach(el => el.style.display = showSwap ? 'block' : 'none');
    const labels = { both: 'Both (RAM + Swap)', ram: 'RAM Only', swap: 'Swap Only' };
    document.getElementById('filterLabel').textContent = labels[filter] || 'Both';
    const types = { both: 'RAM + Swap', ram: 'RAM', swap: 'Swap' };
    document.getElementById('filterMemoryType').textContent = types[filter] || '-';
}

// ── AUTO-CLEAN TOGGLES ────────────────────────────────────────────────────

function toggleChromeAutoClean(enabled) {
    const statusEl = document.getElementById('chromeAutoCleanStatus');
    const infoEl = document.getElementById('chromeCleanInfo');

    if (chromeAutoCleanInterval) {
        clearInterval(chromeAutoCleanInterval);
        chromeAutoCleanInterval = null;
    }

    if (enabled) {
        statusEl.textContent = '(on)';
        statusEl.style.color = '#0d6efd';
        infoEl.style.display = 'block';
        isChromeAutoCleanRunning = true;

        // Run immediately
        runChromeAutoClean();
        // Then every 2 seconds
        chromeAutoCleanInterval = setInterval(runChromeAutoClean, 2000);
        showToast('Chrome Auto-Clean ENABLED (every 2s)', 'success');
    } else {
        statusEl.textContent = '(off)';
        statusEl.style.color = '';
        infoEl.style.display = 'none';
        isChromeAutoCleanRunning = false;
        showToast('Chrome Auto-Clean DISABLED', 'warning');
    }
    updateTrackerStatus();
}

function toggleStandardAutoClean(enabled) {
    const statusEl = document.getElementById('standardAutoCleanStatus');
    const infoEl = document.getElementById('standardCleanInfo');
    const safetyEl = document.getElementById('safetyInfo');

    if (standardAutoCleanInterval) {
        clearInterval(standardAutoCleanInterval);
        standardAutoCleanInterval = null;
    }

    if (enabled) {
        statusEl.textContent = '(on)';
        statusEl.style.color = '#198754';
        infoEl.style.display = 'block';
        safetyEl.style.display = 'block';
        isStandardAutoCleanRunning = true;

        // Run immediately
        runStandardAutoClean();
        // Then every 2 seconds
        standardAutoCleanInterval = setInterval(runStandardAutoClean, 2000);
        showToast('Standard Auto-Clean ENABLED (every 2s)', 'success');
    } else {
        statusEl.textContent = '(off)';
        statusEl.style.color = '';
        infoEl.style.display = 'none';
        safetyEl.style.display = 'none';
        isStandardAutoCleanRunning = false;
        showToast('Standard Auto-Clean DISABLED', 'warning');
    }
    updateTrackerStatus();
}

// ── AUTO-CLEAN EXECUTION ────────────────────────────────────────────────────

async function runChromeAutoClean() {
    // Only run if enabled
    if (!isChromeAutoCleanRunning) return;
    if (isChromeRunning) return;
    isChromeRunning = true;

    try {
        const resp = await fetch('/system-monitor/clear-chrome-low-priority', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            signal: AbortSignal.timeout(3000)
        });
        const data = await resp.json();
        const mb = parseFloat(data.memory_freed_mb) || 0;
        const count = parseInt(data.count) || 0;

        if (count > 0 || mb > 0) {
            recordClean('chrome', mb, count);
            // Show toast for every clean operation
            if (count > 0) {
                showToast(`🗑️ Chrome: killed ${count} proc — freed ${fmtMb(mb)}`, 'info');
            }
            await refreshData(false);
        }
    } catch (e) {
        console.debug('Chrome auto-clean error:', e.message);
    } finally {
        isChromeRunning = false;
    }
}

async function runStandardAutoClean() {
    // Only run if enabled
    if (!isStandardAutoCleanRunning) return;
    if (isStandardRunning) return;
    isStandardRunning = true;

    try {
        const resp = await fetch('/system-monitor/clear-low-priority', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            signal: AbortSignal.timeout(3000)
        });
        const data = await resp.json();
        const mb = parseFloat(data.memory_freed_mb) || 0;
        const count = parseInt(data.count) || 0;

        if (count > 0 || mb > 0) {
            recordClean('standard', mb, count);
            // Show toast for every clean operation
            if (count > 0) {
                showToast(`🧹 Standard: killed ${count} proc — freed ${fmtMb(mb)}`, 'info');
            }
            await refreshData(false);
        }
    } catch (e) {
        console.debug('Standard auto-clean error:', e.message);
    } finally {
        isStandardRunning = false;
    }
}

// ── DATA REFRESH ─────────────────────────────────────────────────────────────

async function refreshData(showLoading = true) {
    if (isRefreshing) return;
    const btn = document.getElementById('refreshBtn');
    if (showLoading) { showLoadingOverlay(); if (btn) btn.classList.add('spinning'); }
    isRefreshing = true;
    try {
        const controller = new AbortController();
        const tid = setTimeout(() => controller.abort(), 10000);
        const resp = await fetch(`/system-monitor/data?filter=${currentFilter}`, {
            signal: controller.signal,
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        clearTimeout(tid);
        if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
        const data = await resp.json();
        if (data.success) updateUI(data);
        else throw new Error(data.message || 'Failed');
    } catch (e) {
        if (e.name === 'AbortError') showToast('Request timed out. Retrying...', 'warning');
        else showToast('Failed to refresh data.', 'error');
    } finally {
        isRefreshing = false;
        if (btn) btn.classList.remove('spinning');
        hideLoadingOverlay();
    }
}

function updateUI(data) {
    try {
        const { systemInfo: info, processes, appProcesses, healthStatus: health, filter } = data;
        updateVisibility(filter);

        if (filter !== 'swap') {
            setText('totalRam', info.total_ram || 'N/A');
            setText('freeRam', info.free_ram || 'N/A');
            setText('availableRam', info.available_ram || 'N/A');
        }
        if (filter !== 'ram') setText('swapFree', info.swap_free || 'N/A');

        setText('hostname', info.hostname || 'N/A');
        setText('os', info.os || 'N/A');
        setText('uptime', info.uptime || 'N/A');

        if (processes) {
            setText('filterTotalProcesses', processes.length);
            let totalMem = 0;
            processes.forEach(p => {
                if (filter === 'ram') totalMem += p.mem_mb || 0;
                else if (filter === 'swap') totalMem += p.swap_mb || 0;
                else totalMem += (p.mem_mb || 0) + (p.swap_mb || 0);
            });
            setText('filterTotalMemory', Math.round(totalMem) + ' MB');
        }

        if (filter !== 'swap') {
            document.getElementById('ramUsedBar').style.width = info.used_percent + '%';
            document.getElementById('ramFreeBar').style.width = info.free_percent + '%';
            const cachePct = ((info.buff_cache_gb || 0) / (info.total_ram_gb || 1) * 100).toFixed(1);
            document.getElementById('ramCacheBar').style.width = cachePct + '%';
            setText('ramPercentLabel', info.used_percent + '%');
            setText('ramLabel', `${info.used_ram || '0'} / ${info.total_ram || '0'}`);
            setText('ramUsedLabel', info.used_percent + '%');
            setText('ramFreeLabel', info.free_percent + '%');
            setText('ramCacheLabel', cachePct + '%');
        }

        if (filter !== 'ram') {
            if (info.swap_total !== 'N/A') {
                document.getElementById('swapUsedBar').style.width = (info.swap_used_percent || 0) + '%';
                document.getElementById('swapFreeBar').style.width = (info.swap_free_percent || 0) + '%';
                setText('swapPercentLabel', (info.swap_used_percent || 0) + '%');
                setText('swapLabel', `${info.swap_used || '0'} / ${info.swap_total || '0'}`);
                setText('swapUsedLabel', (info.swap_used_percent || 0) + '%');
                setText('swapFreeLabel', (info.swap_free_percent || 0) + '%');
            } else {
                document.getElementById('swapUsedBar').style.width = '0%';
                document.getElementById('swapFreeBar').style.width = '100%';
                setText('swapPercentLabel', '0%');
                setText('swapLabel', 'N/A');
            }
        }

        if (health) {
            if (filter !== 'swap') {
                setHealthBadge('healthRamFree', health.ram_free);
                setHealthBadge('healthRamAvail', health.ram_available);
            }
            if (filter !== 'ram') setHealthBadge('healthSwapFree', health.swap_free);
            setHealthBadge('healthOverall', health.overall);
            setText('healthRealIssue', health.real_issue || 'No issues detected');
        }

        const tbody = document.getElementById('processTableBody');
        if (processes && processes.length > 0) {
            const top10 = processes.slice(0, 10);
            tbody.innerHTML = top10.map(p => {
                const cpuCls = getCpuBarClass(p.cpu);
                const checked = selectedPids.has(String(p.pid)) ? 'checked' : '';
                let memVal, memType;
                if (filter === 'ram') {
                    memVal = (p.mem_mb ? p.mem_mb.toFixed(0) : '0') + ' MB';
                    memType = `<span class="memory-type-badge ram">RAM</span>`;
                } else if (filter === 'swap') {
                    memVal = (p.swap_mb ? p.swap_mb.toFixed(0) : '0') + ' MB';
                    memType = `<span class="memory-type-badge swap">Swap</span>`;
                } else {
                    const combined = (p.mem_mb || 0) + (p.swap_mb || 0);
                    memVal = combined.toFixed(0) + ' MB';
                    memType = `<span class="memory-type-badge ram">RAM: ${(p.mem_mb||0).toFixed(0)}MB</span>
                               <span class="memory-type-badge swap">Swap: ${(p.swap_mb||0).toFixed(0)}MB</span>`;
                }
                return `<tr>
                    <td><input type="checkbox" class="process-checkbox" data-pid="${p.pid}" ${checked} onchange="updateSelectedPids(this)"></td>
                    <td><strong style="color:var(--text-primary);">${p.pid}</strong></td>
                    <td style="color:var(--text-secondary);">${escapeHtml(p.user)}</td>
                    <td><small class="text-muted">${escapeHtml(p.command.substring(0, 50))}</small></td>
                    <td>
                        <div class="cpu-bar"><div class="cpu-bar-fill ${cpuCls}" style="width:${Math.min(p.cpu,100)}%;"></div></div>
                        ${p.cpu.toFixed(1)}%
                    </td>
                    <td style="color:var(--text-secondary);">${memVal}</td>
                    <td>${memType}</td>
                    <td>
                        <button class="kill-btn btn-danger" onclick="killProcess(${p.pid})">
                            <i class="bi bi-x-circle"></i> Kill
                        </button>
                    </td>
                </tr>`;
            }).join('');
        } else {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No processes found</td></tr>';
        }

        const appContainer = document.getElementById('appProcessContainer');
        if (appProcesses && appProcesses.length > 0) {
            appContainer.innerHTML = appProcesses.slice(0, 10).map(app => {
                const procRows = app.processes.slice(0, 5).map(p => {
                    let memDisplay;
                    if (filter === 'ram') memDisplay = `RAM: ${p.mem_mb.toFixed(0)} MB`;
                    else if (filter === 'swap') memDisplay = `Swap: ${p.swap_mb.toFixed(0)} MB`;
                    else memDisplay = `RAM: ${p.mem_mb.toFixed(0)} MB | Swap: ${p.swap_mb.toFixed(0)} MB`;
                    return `<div class="app-process-item">
                        <span>
                            <span class="pid">PID ${p.pid}</span>
                            <span class="text-muted ms-2">${escapeHtml(p.command.substring(0, 60))}</span>
                        </span>
                        <span>
                            CPU: ${p.cpu.toFixed(1)}% | ${memDisplay}
                            <button class="btn btn-xs btn-danger ms-2" onclick="killProcess(${p.pid})" style="padding:0 6px;font-size:0.7rem;">
                                <i class="bi bi-x-circle"></i>
                            </button>
                        </span>
                    </div>`;
                }).join('');
                const more = app.process_count > 5 ? `<div class="text-muted small mt-1">... and ${app.process_count - 5} more processes</div>` : '';
                return `<div class="app-card">
                    <div class="app-header">
                        <span class="app-name">
                            <i class="bi bi-app"></i> ${escapeHtml(app.app)}
                            <span class="badge bg-secondary ms-2">${app.process_count} process${app.process_count > 1 ? 'es' : ''}</span>
                        </span>
                        <div class="app-stats">
                            <span><i class="bi bi-cpu"></i> ${app.total_cpu.toFixed(1)}%</span>
                            ${filter !== 'swap' ? `<span><i class="bi bi-memory"></i> RAM: ${app.total_ram_mb} MB</span>` : ''}
                            ${filter !== 'ram'  ? `<span><i class="bi bi-hdd"></i> Swap: ${app.total_swap_mb} MB</span>` : ''}
                            ${filter === 'both' ? `<span><i class="bi bi-arrow-left-right"></i> ${app.total_combined_mb} MB total</span>` : ''}
                            <button class="btn btn-danger btn-sm" onclick="killApplication('${escapeHtml(app.app)}')">
                                <i class="bi bi-trash"></i> Kill App
                            </button>
                        </div>
                    </div>
                    <div class="app-process-list">${procRows}${more}</div>
                </div>`;
            }).join('');
        } else {
            appContainer.innerHTML = '<div class="text-center text-muted">No application processes found</div>';
        }

        // Always refresh stats display
        renderStats();
        updateTrackerStatus();

    } catch (e) {
        console.error('UI Update error:', e);
        showToast('Error updating UI', 'error');
    }
}

function setHealthBadge(id, obj) {
    const el = document.getElementById(id);
    if (!el) return;
    el.className = `status-badge status-${obj.class}`;
    el.textContent = obj.status;
}

function escapeHtml(text) {
    if (!text) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}

// ── KILL FUNCTIONS ───────────────────────────────────────────────────────────

async function killProcess(pid) {
    if (!confirm(`Kill process PID: ${pid}?`)) return;
    const btn = event?.target?.closest?.('.kill-btn');
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>'; }
    try {
        const resp = await fetch('/system-monitor/kill-process', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ pid })
        });
        const data = await resp.json();
        showToast(data.message, data.success ? 'success' : 'error');
        if (data.success) { await sleep(1000); await refreshData(true); }
    } catch (e) { showToast('Error killing process', 'error'); }
    finally { if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-x-circle"></i> Kill'; } }
}

async function killApplication(appName) {
    if (!confirm(`Kill all processes for "${appName}"?`)) return;
    showLoadingOverlay();
    try {
        const resp = await fetch('/system-monitor/kill-application', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ app_name: appName })
        });
        const data = await resp.json();
        hideLoadingOverlay();
        let msg = `✅ Killed ${data.killed_count} processes for ${appName}`;
        if (data.failed_count > 0) msg += ` — ${data.failed_count} would not die.`;
        showToast(msg, data.failed_count > 0 ? 'warning' : 'success');
        await sleep(1500);
        await refreshData(true);
    } catch (e) { hideLoadingOverlay(); showToast('Error killing application', 'error'); }
}

async function killSelectedProcesses() {
    const pids = Array.from(selectedPids);
    if (!pids.length) { showToast('No processes selected', 'warning'); return; }
    if (!confirm(`Kill ${pids.length} process(es)?`)) return;
    try {
        const resp = await fetch('/system-monitor/kill-multiple', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ pids })
        });
        const data = await resp.json();
        showToast(data.message, data.success ? 'success' : 'error');
        if (data.success) {
            selectedPids.clear();
            document.getElementById('selectAllProcesses').checked = false;
            await sleep(1000);
            await refreshData(true);
        }
    } catch (e) { showToast('Error killing processes', 'error'); }
}

async function clearLowPriority() {
    if (!confirm('This will kill low-priority background processes. Continue?')) return;
    try {
        const resp = await fetch('/system-monitor/clear-low-priority', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
        });
        const data = await resp.json();
        const mb = parseFloat(data.memory_freed_mb) || 0;
        recordClean('standard', mb, data.count || 0);
        showToast(`✅ Cleared ${data.count} processes — freed ${fmtMb(mb)}`, 'success');
        await sleep(1000);
        await refreshData(true);
    } catch (e) { showToast('Error clearing processes', 'error'); }
}

async function freeMemory() {
    showLoadingOverlay();
    try {
        const resp = await fetch('/system-monitor/free-memory', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
        });
        const data = await resp.json();
        hideLoadingOverlay();
        showToast(data.message, data.success ? 'success' : 'error');
        if (data.success) await refreshData(true);
    } catch (e) { hideLoadingOverlay(); showToast('Error freeing memory', 'error'); }
}

function manualRefresh() { refreshData(true); }
function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

// ── SAFETY: Critical process protection ──

function isCriticalPid(pid) {
    const criticalPids = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];
    if (criticalPids.includes(pid)) return true;
    return false;
}

// Override killProcess with safety check
const originalKillProcess = killProcess;
killProcess = async function(pid) {
    if (isCriticalPid(parseInt(pid))) {
        showToast('⚠️ Cannot kill system-critical process (PID ' + pid + ')', 'warning');
        return;
    }
    await originalKillProcess(pid);
};

// ── INIT ─────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    loadTheme();
    renderStats();
    updateTrackerStatus();
    refreshData(true);

    // Refresh data every 3 seconds for real-time updates
    autoRefreshInterval = setInterval(() => refreshData(false), 3000);

    // Update stats display every 1 second for smooth updates
    setInterval(() => {
        renderStats();
        updateTrackerStatus();
    }, 1000);

    console.log('Auto-Clean ready: 2-second intervals (only when toggled ON)');
});

window.addEventListener('beforeunload', () => {
    clearInterval(autoRefreshInterval);
    clearInterval(chromeAutoCleanInterval);
    clearInterval(standardAutoCleanInterval);
    isRefreshing = false;
});
</script>
@endsection