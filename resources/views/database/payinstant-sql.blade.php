@extends('layouts.app')

@section('title', 'PayInstant SQL Generator')

@section('styles')
<style>
    :root {
        --gradient-start: #667eea;
        --gradient-end: #764ba2;
    }

    .sql-generator-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 20px;
    }

    .form-section {
        background: white;
        border-radius: 16px;
        padding: 24px;
        margin-bottom: 12px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        transition: box-shadow 0.3s ease, border-color 0.3s ease;
        overflow: hidden;
    }

    .form-section:hover {
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
        border-color: #c7d2fe;
    }

    .form-section.collapsed .form-section-content {
        display: none;
    }

    .form-section.collapsed {
        margin-bottom: 8px;
    }

    .form-section.collapsed .form-section-title {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: none;
    }

    .collapse-icon {
        margin-left: auto;
        cursor: pointer;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        transition: all 0.3s ease;
        color: #64748b;
    }

    .collapse-icon:hover {
        background: #f1f5f9;
        color: #1e293b;
    }

    .collapse-icon i {
        transition: transform 0.3s ease;
    }

    .form-section.collapsed .collapse-icon i {
        transform: rotate(-90deg);
    }

    .form-section-title {
        font-weight: 700;
        font-size: 1.1rem;
        color: #1e293b;
        margin-bottom: 20px;
        padding-bottom: 12px;
        border-bottom: 2px solid #e2e8f0;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .form-section-title i {
        color: var(--gradient-start);
        font-size: 1.3rem;
    }

    .form-section-title .badge-auto {
        background: linear-gradient(135deg, #d1fae5, #a7f3d0);
        color: #059669;
        font-size: 0.6rem;
        padding: 2px 10px;
        border-radius: 20px;
        font-weight: 600;
    }

    .form-control, .form-select {
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        padding: 10px 14px;
        transition: all 0.3s ease;
        text-transform: uppercase;
    }

    /* Endpoint URLs must display as-is (lowercase, no text-transform) */
    #proxy_endpoint, #admin_endpoint, #detected_ip {
        text-transform: none !important;
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--gradient-start);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .form-control.auto-filled {
        background: #f0fdf4 !important;
        border-color: #86efac !important;
    }

    .form-label {
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 6px;
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
    }

    .form-label .text-danger {
        color: #ef4444;
        font-weight: 700;
    }

    .form-label .badge-auto-label {
        background: #dbeafe;
        color: #2563eb;
        font-size: 0.6rem;
        padding: 1px 8px;
        border-radius: 12px;
        font-weight: 500;
    }

    .form-label .badge-auto-label::before {
        content: "⚡ ";
    }

    .input-group-text {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px 0 0 12px;
        font-family: monospace;
        font-size: 0.7rem;
        color: #64748b;
    }

    .form-control:disabled {
        background: #f1f5f9;
        cursor: not-allowed;
    }

    .gateway-selector {
        margin-bottom: 20px;
    }

    .gateway-selector .input-group {
        position: relative;
    }

    .gateway-selector .input-group .form-control {
        border-radius: 12px 0 0 12px;
        text-transform: none;
    }

    .gateway-selector .input-group .btn {
        border-radius: 0 12px 12px 0;
        background: var(--gradient-start);
        color: white;
        border: none;
        padding: 10px 24px;
    }

    .gateway-selector .input-group .btn:hover {
        background: var(--gradient-end);
    }

    .template-selector {
        margin-top: 10px;
    }

    .template-selector .btn-template {
        background: #e2e8f0;
        border: none;
        border-radius: 20px;
        padding: 6px 18px;
        font-size: 0.78rem;
        color: #475569;
        cursor: pointer;
        transition: all 0.3s ease;
        margin: 3px;
        font-weight: 500;
    }

    .template-selector .btn-template:hover {
        background: #c7d2fe;
        color: #1e293b;
        transform: translateY(-2px);
    }

    .template-selector .btn-template.active {
        background: var(--gradient-start);
        color: white;
    }

    .template-selector .template-label {
        font-size: 0.75rem;
        color: #64748b;
        display: block;
        margin-bottom: 6px;
    }

    /* ===== Accounts List (elegant cards) ===== */
    .accounts-container {
        margin-top:  ‌0px;
        border-radius: 14px;
        background: linear-gradient(135deg, #f8faff, #eef2ff);
        border:  ‌1px solid #dbe3fe;
        padding:  ‌14px 16px;
        margin-bottom:  ‌0px;
    }

    .accounts-container .accounts-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom:  ‌12px;
        gap:  ‌8px;
        flex-wrap: wrap;
    }

    .accounts-container .accounts-title {
        font-weight:  ‌700;
        font-size:  ‌0.95rem;
        color: #1e293b;
    }

    .accounts-container .accounts-title i {
        color: var(--gradient-start);
        margin-right: ‌ ‌6px;
    }

    .accounts-container .accounts-count {
        background: #dbeafe;
        color: #1e40af;
        font-size:  ‌0.7rem;
        font-weight:  ‌600;
        padding:  ‌3px 12px;
        border-radius:  ‌20px;
    }

    .accounts-list {
        display: flex;
        flex-direction: column;
        gap:  ‌8px;
    }

    #accountsList {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 12px;
    }

    .account-card {
        display: flex;
        flex-direction: column;
        background: #ffffff;
        border: 1px solid #e9edf5;
        border-radius: 14px;
        overflow: hidden;
        padding: 0;
        transition: box-shadow 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.05);
    }

    .account-card:hover {
        border-color: #f3cdd9;
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.10);
        transform: translateY(-2px);
    }

    .account-card-header {
        display: flex;
        align-items: center;
        gap: 7px;
        padding: 9px 11px;
        background: #f8f9fc;
        border-bottom: 1px solid #eef1f7;
    }

    .account-card-icon {
        width: 26px;
        height: 26px;
        border-radius: 8px;
        background: #fdeef2;
        color: #e11d48;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        flex-shrink: 0;
    }

    .account-card .account-name {
        font-weight: 700;
        font-size: 0.78rem;
        color: #0f172a;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        flex: 1 1 auto;
        text-transform: none;
    }

    .account-card .badge {
        font-size: 0.58rem;
        padding: 2px 7px;
        border-radius: 10px;
        font-weight: 700;
        flex-shrink: 0;
    }

    .account-card .badge-latest {
        background: #dcfce7;
        color: #15803d;
    }

    .account-card .badge-older {
        background: #eef2f7;
        color: #64748b;
    }

    .ac-icon-btn {
        width: 24px;
        height: 24px;
        border: 1px solid #e5e9f2;
        background: #ffffff;
        border-radius: 7px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.68rem;
        color: #64748b;
        cursor: pointer;
        flex-shrink: 0;
        transition: all 0.15s ease;
        padding: 0;
    }

    .ac-icon-btn:hover {
        border-color: #c7d2fe;
        color: #4f46e5;
        background: #f5f6ff;
    }

    .ac-icon-btn.danger {
        color: #e11d48;
    }

    .ac-icon-btn.danger:hover {
        border-color: #fecdd3;
        background: #fff1f2;
        color: #be123c;
    }

    .account-card-body {
        padding: 9px 12px;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .ac-row {
        display: flex;
        align-items: center;
        gap: 7px;
        font-size: 0.72rem;
        color: #334155;
        min-width: 0;
    }

    .ac-row > i {
        color: #e11d48;
        opacity: 0.75;
        font-size: 0.76rem;
        width: 13px;
        text-align: center;
        flex-shrink: 0;
    }

    .ac-row .ac-label {
        color: #94a3b8;
        font-weight: 600;
        flex-shrink: 0;
    }

    .ac-row .ac-value {
        font-weight: 600;
        color: #0f172a;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        min-width: 0;
        flex: 1 1 auto;
    }

    .ac-row .ac-value.mono {
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        font-size: 0.66rem;
    }

    .ac-status-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        display: inline-block;
        flex-shrink: 0;
    }

    .account-card-footer {
        margin-top: auto;
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 7px 11px;
        border-top: 1px solid #eef1f7;
        background: #fbfcfe;
    }

    .btn-load-account {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 0.68rem;
        font-weight: 700;
        color: #4f46e5;
        background: #eef2ff;
        border: 1px solid #dbe1ff;
        padding: 3px 10px;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .btn-load-account:hover {
        background: #4f46e5;
        border-color: #4f46e5;
        color: #ffffff;
    }


    /* Strategy Badges */
    .strategy-badges {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 8px;
    }

    .strategy-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }

    .strategy-badge.static {
        background: #fef3c7;
        color: #d97706;
    }

    .strategy-badge.static.active {
        border-color: #d97706;
        background: #fde68a;
    }

    .strategy-badge.dynamic {
        background: #dbeafe;
        color: #2563eb;
    }

    .strategy-badge.dynamic.active {
        border-color: #2563eb;
        background: #bfdbfe;
    }

    .strategy-badge.custom {
        background: #e0e7ff;
        color: #4f46e5;
    }

    .strategy-badge.custom.active {
        border-color: #4f46e5;
        background: #c7d2fe;
    }

        /* Env Output */
    .env-output-section {
        background: #f8fafc;
        border-radius: 12px;
        padding: 16px 20px;
        margin-top: 16px;
        border: 1px solid #e2e8f0;
        font-family: 'JetBrains Mono', 'SF Mono', 'Fira Code', Consolas, monospace;
        font-size: 13px;
        font-weight: 600;
        color: #0f172a;
        white-space: pre-wrap;
        word-wrap: break-word;
        max-height: 350px;
        overflow-y: auto;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }

    .env-output-section .env-key {
        color: #475569;
        font-weight: 700;
    }

    .env-output-section .env-value {
        color: #0f172a;
        font-weight: 700;
    }

    .env-output-section .env-equals {
        color: #94a3b8;
    }

    /* SQL Output */
    .sql-output-section {
        background: white;
        border-radius: 20px;
        padding: 0;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        margin-top: 20px;
    }

    .sql-output-header {
        background: linear-gradient(135deg, #1e1e2e, #2d2d3d);
        padding: 16px 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #3d3d4d;
        flex-wrap: wrap;
        gap: 10px;
    }

    .sql-output-header h5 {
        color: #e2e8f0;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.95rem;
    }

    .sql-output-header h5 i {
        color: #fbbf24;
    }

        .sql-output-body {
        background: #f8fafc;
        color: #0f172a;
        padding: 20px 24px;
        font-family: 'JetBrains Mono', 'Fira Code', 'SF Mono', Consolas, monospace;
        font-size: 13px;
        font-weight: 500;
        max-height: 600px;
        overflow-y: auto;
        white-space: pre-wrap;
        word-wrap: break-word;
        line-height: 1.7;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }

    .sql-output-body::-webkit-scrollbar {
        width: 8px;
    }

    .sql-output-body::-webkit-scrollbar-track {
        background: #e2e8f0;
    }

    .sql-output-body::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }

    .sql-output-body::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }

    .sql-statement {
        margin-bottom: 20px;
        border-left: 3px solid #667eea;
        padding-left: 16px;
    }

    .sql-statement:last-child {
        margin-bottom: 0;
    }

    .sql-statement .table-name {
        color: #b45309;
        font-weight: 600;
        font-size: 13px;
        display: block;
        margin-bottom: 8px;
    }

    .sql-statement .table-name i {
        margin-right: 6px;
    }

    .sql-keyword {
        color: #6d28d9;
        font-weight: 700;
    }

    .sql-string {
        color: #047857;
        font-weight: 600;
    }

    .sql-number {
        color: #1d4ed8;
        font-weight: 600;
    }

    .sql-null {
        color: #b91c1c;
        font-weight: 700;
        font-style: italic;
    }

    .sql-comment {
        color: #64748b;
        font-style: italic;
    }

    .sql-variable {
        color: #b45309;
        font-weight: 700;
    }

    .sql-column {
        color: #0891b2;
        font-weight: 600;
    }

    /* Column Tags */
    .columns-highlight {
        background: #f8fafc;
        border-radius: 12px;
        padding: 16px 20px;
        margin-top: 16px;
        border: 1px solid #e2e8f0;
    }

    .columns-highlight-title {
        font-weight: 600;
        font-size: 0.85rem;
        color: #1e293b;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .columns-highlight-title i {
        color: var(--gradient-start);
    }

    .column-tag {
        display: inline-block;
        background: white;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        border: 1px solid #e2e8f0;
        margin: 4px 6px 4px 0;
        transition: all 0.2s ease;
        font-family: monospace;
        max-width: 100%;
        word-break: break-all;
        white-space: normal;
    }

    .column-tag:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .column-tag .col-name {
        color: #4f46e5;
        font-weight: 600;
    }

    .column-tag .col-value {
        color: #059669;
        word-break: break-all;
        white-space: normal;
    }

    .column-tag .col-value.null {
        color: #ef4444;
        font-style: italic;
    }

    .column-tag .col-arrow {
        color: #94a3b8;
        margin: 0 4px;
    }

    /* Buttons */
    .action-buttons {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 20px;
    }

    .btn-generate {
        background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
        border: none;
        border-radius: 14px;
        padding: 14px 32px;
        color: white;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        font-size: 1rem;
        position: relative;
        overflow: hidden;
    }

    .btn-generate::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        transition: left 0.5s ease;
    }

    .btn-generate:hover::before {
        left: 100%;
    }

    .btn-generate:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
    }

    .btn-generate:active {
        transform: scale(0.97);
    }

        .btn-generate:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }

    .btn-save-db {
        background: linear-gradient(135deg, #10b981, #059669);
        border: none;
        border-radius: 14px;
        padding: 14px 32px;
        color: white;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        font-size: 1rem;
    }

    .btn-save-db:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
    }

    .btn-copy {
        background: #10b981;
        border: none;
        border-radius: 14px;
        padding: 14px 28px;
        color: white;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }

    .btn-copy:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
    }

    .btn-reset {
        background: #e2e8f0;
        border: none;
        border-radius: 14px;
        padding: 14px 28px;
        color: #475569;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }

    .btn-reset:hover {
        background: #cbd5e1;
        transform: translateY(-2px);
    }

    .btn-export {
        background: #8b5cf6;
        border: none;
        border-radius: 14px;
        padding: 14px 28px;
        color: white;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }

    .btn-export:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(139, 92, 246, 0.4);
    }

    .btn-load {
        background: #3b82f6;
        border: none;
        border-radius: 14px;
        padding: 10px 20px;
        color: white;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 0.9rem;
    }

    .btn-load:hover {
        background: #2563eb;
        transform: translateY(-2px);
    }

    /* Toast */
    .toast-notification {
        animation: slideInRight 0.3s ease;
    }

    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(100px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    @keyframes highlightPulse {
        0% { border-color: #10b981; box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); }
        50% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
        100% { border-color: #10b981; }
    }

    .sql-generated {
        animation: highlightPulse 0.6s ease;
    }

    .gateway-summary {
        background: #f8fafc;
        border-radius: 12px;
        padding: 16px 20px;
        border: 1px solid #e2e8f0;
        margin-top: 16px;
    }

    .gateway-summary .summary-item {
        display: flex;
        justify-content: space-between;
        padding: 6px 0;
        border-bottom: 1px solid #e2e8f0;
        font-size: 0.85rem;
    }

    .gateway-summary .summary-item:last-child {
        border-bottom: none;
    }

    .gateway-summary .summary-label {
        color: #64748b;
        font-weight: 500;
    }

    .gateway-summary .summary-value {
        color: #1e293b;
        font-weight: 600;
        font-family: monospace;
        text-transform: uppercase;
    }

    .alert-info-custom {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 12px;
        padding: 12px 16px;
    }

    @media (max-width: 768px) {
        .form-section {
            padding: 20px;
        }
        .action-buttons {
            flex-direction: column;
        }
        .action-buttons .btn {
            width: 100%;
            justify-content: center;
        }
        .sql-output-body {
            font-size: 11px;
            padding: 16px;
        }
        .column-tag {
            font-size: 0.7rem;
        }
    }

    .sql-count {
        background: rgba(255,255,255,0.1);
        padding: 2px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        color: #94a3b8;
        border: 1px solid rgba(255,255,255,0.1);
    }

    .badge-required {
        background: #fee2e2;
        color: #dc2626;
        font-size: 0.6rem;
        padding: 2px 10px;
        border-radius: 20px;
        font-weight: 600;
    }

    .text-uppercase-input {
        text-transform: uppercase;
    }
</style>
@endsection

@section('content')
<div class="glass-card" data-aos="fade-down">
    <div class="gradient-header text-center">
        <h1 class="fw-bold">
            <i class="bi bi-database-fill-gear me-3"></i>
            PayInstant SQL & Env Generator
        </h1>
        <p class="lead mb-0">
            <i class="bi bi-plus-circle me-2"></i>
            Generate INSERT queries and Environment Variables for payment gateway integration
        </p>
    </div>

    <div class="p-4 p-md-5">
        <div class="sql-generator-container">
            <form id="sqlGeneratorForm">
                @csrf

                <!-- Gateway Selector -->
                <div class="form-section" id="section-selector">
                    <div class="form-section-title">
                        <i class="bi bi-search"></i>
                        Select Existing Gateway (Optional)
                        <span class="badge-auto">Load configuration</span>
                        <span class="collapse-icon" onclick="toggleSection('section-selector')">
                            <i class="bi bi-chevron-down"></i>
                        </span>
                    </div>
                    <div class="form-section-content">
                        <div class="gateway-selector">
                            <div class="input-group">
                                <select id="gatewaySelector" class="form-control" onchange="onGatewaySelect()" style="text-transform: none;">
                                    <option value="">-- Select Gateway --</option>
                                    @foreach($gateways as $gateway)
                                        <option value="{{ $gateway->gateway_identifier }}">{{ $gateway->gateway_identifier }}</option>
                                    @endforeach
                                </select>
                                <button class="btn" type="button" onclick="loadGateway()">
                                    <i class="bi bi-arrow-clockwise"></i> Load
                                </button>
                            </div>
                        </div>

                        <!-- Accounts for selected gateway -->
                        <div id="accountsContainer" style="display:none; margin-top: 12px;">
                            <div class="alert alert-info" style="border-radius: 12px; padding: 12px 16px; background: #eff6ff; border: 1px solid #bfdbfe;">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>Accounts for <span id="selectedGatewayName"></span>:</strong>
                                <div id="accountsList" class="mt-2 accounts-list"></div>
                            </div>
                        </div>

                        @if($quickTemplates->count() > 0)
                            <div class="template-selector">
                                <span class="template-label">
                                    <i class="bi bi-grid-3x3-gap-fill me-1"></i>
                                    Gateways (Click to load):
                                </span>
                                <div id="quickTemplatesList">
                                    @foreach($quickTemplates as $template)
                                        <button type="button" class="btn-template" onclick="loadGatewayByPrefix('{{ $template->gateway_prefix }}')" title="{{ $template->gateway_identifier }}">
                                            {{ $template->virtual_account_name }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Gateway Configuration -->
                <div class="form-section" id="section-gateway">
                    <div class="form-section-title">
                        <i class="bi bi-shield-fill-check"></i>
                        Gateway Configuration
                        <span class="badge-auto">Required for Env Params</span>
                        <span class="collapse-icon" onclick="toggleSection('section-gateway')">
                            <i class="bi bi-chevron-down"></i>
                        </span>
                    </div>
                    <div class="form-section-content">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="bi bi-arrow-right-circle"></i> Main Gateway Name <span class="text-danger">*</span>
                                    <span class="badge-auto-label">Auto-detects prefix</span>
                                </label>
                                <input type="text" id="main_gateway_name" name="main_gateway_name" class="form-control text-uppercase-input"
                                       placeholder="e.g., UTKRSA_KALPR" required
                                       oninput="updateGatewayPreview(); autoDetectPrefix();">
                                <small class="text-muted">Used in: PROXY_IP, MERCHANT_BANKS. Prefix auto-detected from this field</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="bi bi-shield-lock"></i> Admin Gateway Name <span class="text-danger">*</span>
                                    <span class="badge-auto-label">With _ADM_</span>
                                </label>
                                <input type="text" id="adm_gateway_name" name="adm_gateway_name" class="form-control text-uppercase-input"
                                       placeholder="e.g., UTKRSA_ADM_KALPR" required
                                       oninput="updateGatewayPreview(); autoDetectPrefix();">
                                <small class="text-muted">Used in: ADMIN_GATEWAYS. Prefix auto-detected from this field</small>
                            </div>
                        </div>

                        <!-- Auto-detected Prefix Display -->
                        <div class="row">
                            <div class="col-12">
                                <div class="alert-info-custom">
                                    <i class="bi bi-info-circle me-2"></i>
                                    <strong>Auto-detected Gateway Prefix:</strong>
                                    <span id="detectedPrefixDisplay" style="font-family: monospace; font-weight: 700; color: #1e40af;">Will be auto-detected</span>
                                    <span class="text-muted ms-2" style="font-size: 0.85rem;">(Extracted from Main or Admin Gateway name)</span>
                                </div>
                            </div>
                        </div>

                        <div class="gateway-summary" id="gatewaySummary">
                            <div class="summary-item">
                                <span class="summary-label">Generated ENV Keys:</span>
                                <span class="summary-value" id="envKeysPreview">PREFIX_MAIN_GATEWAY, PREFIX_ADM_GATEWAY</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">PROXY_IP Key:</span>
                                <span class="summary-value" id="proxyIPKeyPreview">MAIN_GATEWAY_NAME</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Bank Mapping:</span>
                                <span class="summary-value" id="bankMappingPreview">{"1":"MAIN_GATEWAY_NAME"}</span>
                            </div>
                            <div class="summary-item" style="background: #e0f2fe; padding: 6px 12px; border-radius: 8px;">
                                <span class="summary-label">Detected Prefix:</span>
                                <span class="summary-value" id="prefixPreview" style="color: #1e40af;">Not detected yet</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Common Section -->
                <div class="form-section" id="section-common">
                    <div class="form-section-title">
                        <i class="bi bi-building"></i>
                        Common Configuration
                        <span class="badge-auto">Required</span>
                        <span class="collapse-icon" onclick="toggleSection('section-common')">
                            <i class="bi bi-chevron-down"></i>
                        </span>
                    </div>
                    <div class="form-section-content">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="bi bi-building"></i> Full Display Name <span class="text-danger">*</span>
                                    <span class="badge-auto-label">All tables</span>
                                </label>
                                <input type="text" id="full_display_name" name="full_display_name" class="form-control text-uppercase-input"
                                       placeholder="e.g., QUANTUMWEB SOFTWARE PRIVATE LIMITED" required>
                                <small class="text-muted">Used in: payinstant_accounts.name, payinstant_accounts_live.name, virtual_accounts.name</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="bi bi-bank2"></i> Bank <span class="text-danger">*</span>
                                    <span class="badge-auto-label">Gateway bank</span>
                                </label>
                                <input type="text" id="bank" name="bank" class="form-control text-uppercase-input" placeholder="e.g., UTKRSA_ADM_KALPR" required>
                                <small class="text-muted">Used in: payinstant_accounts.bank</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Key Generation Strategy -->
                <div class="form-section" id="section-keys">
                    <div class="form-section-title">
                        <i class="bi bi-key"></i>
                        Key & Salt Generation Strategy
                        <span class="badge-auto">Choose strategy</span>
                        <span class="collapse-icon" onclick="toggleSection('section-keys')">
                            <i class="bi bi-chevron-down"></i>
                        </span>
                    </div>
                    <div class="form-section-content">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="bi bi-key-fill"></i> Key Generation <span class="text-danger">*</span>
                                </label>
                                <div class="strategy-badges">
                                    <span class="strategy-badge static active" data-target="key" data-value="static" onclick="selectStrategy(this, 'key', 'static')">
                                        <i class="bi bi-pencil"></i> Static
                                    </span>
                                    <span class="strategy-badge dynamic" data-target="key" data-value="dynamic_mysql_32" onclick="selectStrategy(this, 'key', 'dynamic_mysql_32')">
                                        <i class="bi bi-lightning"></i> MySQL 32-char
                                    </span>
                                    <span class="strategy-badge dynamic" data-target="key" data-value="dynamic_mysql_16" onclick="selectStrategy(this, 'key', 'dynamic_mysql_16')">
                                        <i class="bi bi-lightning-charge"></i> MySQL 16-char
                                    </span>
                                    <span class="strategy-badge custom" data-target="key" data-value="custom" onclick="selectStrategy(this, 'key', 'custom')">
                                        <i class="bi bi-gear"></i> Custom
                                    </span>
                                </div>
                                <input type="hidden" id="key_generation_type" name="key_generation_type" value="static">
                                <div id="keyInputContainer" class="mt-2">
                                    <input type="text" id="merchant_key" name="merchant_key" class="form-control text-uppercase-input"
                                           placeholder="Enter merchant key (static)">
                                    <small class="text-muted">Static: Manual entry | Dynamic: Generated by MySQL SET @key</small>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="bi bi-droplet"></i> Salt Generation <span class="text-danger">*</span>
                                </label>
                                <div class="strategy-badges">
                                    <span class="strategy-badge static active" data-target="salt" data-value="static" onclick="selectStrategy(this, 'salt', 'static')">
                                        <i class="bi bi-pencil"></i> Static
                                    </span>
                                    <span class="strategy-badge dynamic" data-target="salt" data-value="dynamic_mysql_16" onclick="selectStrategy(this, 'salt', 'dynamic_mysql_16')">
                                        <i class="bi bi-lightning"></i> MySQL 16-char
                                    </span>
                                    <span class="strategy-badge custom" data-target="salt" data-value="custom" onclick="selectStrategy(this, 'salt', 'custom')">
                                        <i class="bi bi-gear"></i> Custom
                                    </span>
                                </div>
                                <input type="hidden" id="salt_generation_type" name="salt_generation_type" value="static">
                                <div id="saltInputContainer" class="mt-2">
                                    <input type="text" id="merchant_salt" name="merchant_salt" class="form-control text-uppercase-input"
                                           placeholder="Enter merchant salt (static)">
                                    <small class="text-muted">Static: Manual entry | Dynamic: Generated by MySQL SET @salt</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Endpoints Section -->
                <div class="form-section" id="section-endpoints">
                    <div class="form-section-title">
                        <i class="bi bi-hdd-network"></i>
                        Endpoints & IP Configuration
                        <span class="badge-auto">Auto-detects IP</span>
                        <span class="collapse-icon" onclick="toggleSection('section-endpoints')">
                            <i class="bi bi-chevron-down"></i>
                        </span>
                    </div>
                    <div class="form-section-content">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">
                                    <i class="bi bi-box-arrow-up-right"></i> Proxy Endpoint <span class="text-danger">*</span>
                                    <span class="badge-auto-label">https enforced</span>
                                </label>
                                <input type="text" id="proxy_endpoint" name="proxy_endpoint" class="form-control"
                                       placeholder="https://payout.example.com/api/v1/" required
                                       oninput="this.value = this.value.toLowerCase();"
                                       onblur="formatEndpoint(this); extractIPFromEndpoint(this);">
                                <small class="text-muted">payinstant_accounts.endpoint</small>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">
                                    <i class="bi bi-speedometer2"></i> Admin Endpoint <span class="text-danger">*</span>
                                    <span class="badge-auto-label">https enforced</span>
                                </label>
                                <input type="text" id="admin_endpoint" name="admin_endpoint" class="form-control"
                                       placeholder="https://portal.admin.example.com/api/process/" required
                                       oninput="this.value = this.value.toLowerCase();"
                                       onblur="formatEndpoint(this)">
                                <small class="text-muted">payinstant_accounts_live.endpoint</small>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">
                                    <i class="bi bi-globe"></i> Detected Proxy IP
                                    <span class="badge-auto-label">Auto-detected</span>
                                </label>
                                <input type="text" id="detected_ip" class="form-control" readonly
                                       placeholder="IP will be detected from endpoint">
                                <small class="text-muted">Used in: PROXY_IP environment variable</small>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">
                                    <i class="bi bi-hash"></i> Account Number <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="account_number" name="account_number" class="form-control" placeholder="e.g., 1020120083007" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">
                                    <i class="bi bi-arrow-left-right"></i> IFSC <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="ifsc" name="ifsc" class="form-control text-uppercase-input" placeholder="e.g., KCCB0RTGS4C" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">
                                    <i class="bi bi-person-badge"></i> Client ID
                                </label>
                                <input type="text" id="client_id" name="client_id" class="form-control text-uppercase-input" placeholder="Optional">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">
                                    <i class="bi bi-tag"></i> Account ID <span class="text-danger">*</span>
                                </label>
                                <input type="number" id="account_id" name="account_id" class="form-control" placeholder="e.g., 10" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Account & User Section -->
                <div class="form-section" id="section-account">
                    <div class="form-section-title">
                        <i class="bi bi-person-circle"></i>
                        Account & User Configuration
                        <span class="badge-auto">Virtual Accounts</span>
                        <span class="collapse-icon" onclick="toggleSection('section-account')">
                            <i class="bi bi-chevron-down"></i>
                        </span>
                    </div>
                    <div class="form-section-content">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">
                                    <i class="bi bi-person"></i> User ID <span class="text-danger">*</span>
                                </label>
                                <input type="number" id="user_id" name="user_id" class="form-control" placeholder="e.g., 14" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">
                                    <i class="bi bi-person-vcard"></i> Virtual Account Name <span class="text-danger">*</span>
                                    <span class="badge-auto-label">virtual_accounts.account_name</span>
                                </label>
                                <input type="text" id="virtual_account_name" name="virtual_account_name" class="form-control text-uppercase-input"
                                       placeholder="e.g., QUANTUMWEB KALUPUR" required>
                                <small class="text-muted">Short name for account identification</small>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">
                                    <i class="bi bi-tags"></i> Debit Account
                                </label>
                                <input type="text" id="debit_account" name="debit_account" class="form-control" placeholder="Same as account number">
                                <small class="text-muted">virtual_accounts.debit_account</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Optional Fields -->
                <div class="form-section" id="section-optional">
                    <div class="form-section-title">
                        <i class="bi bi-gear"></i>
                        Optional Configuration
                        <span class="badge-auto">Defaults applied</span>
                        <span class="collapse-icon" onclick="toggleSection('section-optional')">
                            <i class="bi bi-chevron-down"></i>
                        </span>
                    </div>
                    <div class="form-section-content">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">
                                    <i class="bi bi-coin"></i> Daily Limit
                                </label>
                                <input type="text" id="daily_limit" name="daily_limit" class="form-control" placeholder="e.g., 5000000">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">
                                    <i class="bi bi-shield-check"></i> Admin Gateway
                                </label>
                                <input type="text" id="admin_gateway" name="admin_gateway" class="form-control text-uppercase-input" placeholder="NULL">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">
                                    <i class="bi bi-arrow-right"></i> Tran ID
                                </label>
                                <input type="text" id="tran_id" name="tran_id" class="form-control" value="0000">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">
                                    <i class="bi bi-hash"></i> Serial No
                                </label>
                                <input type="text" id="serial_no" name="serial_no" class="form-control" value="000">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">
                                    <i class="bi bi-currency-dollar"></i> Balance
                                </label>
                                <input type="text" id="balance" name="balance" class="form-control" value="0.00">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status Section -->
                <div class="form-section" id="section-status">
                    <div class="form-section-title">
                        <i class="bi bi-toggle-on"></i>
                        Status Configuration
                        <span class="badge-auto">Active by default</span>
                        <span class="collapse-icon" onclick="toggleSection('section-status')">
                            <i class="bi bi-chevron-down"></i>
                        </span>
                    </div>
                    <div class="form-section-content">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">
                                    <i class="bi bi-check-circle"></i> Status <span class="text-danger">*</span>
                                </label>
                                <select id="status" name="status" class="form-select">
                                    <option value="1">Active (1)</option>
                                    <option value="0">Inactive (0)</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">
                                    <i class="bi bi-arrow-repeat"></i> Queue Status
                                </label>
                                <select id="queue_status" name="queue_status" class="form-select">
                                    <option value="0">Disabled (0)</option>
                                    <option value="1">Enabled (1)</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">
                                    <i class="bi bi-file-text"></i> VPA
                                </label>
                                <input type="text" id="vpa" name="vpa" class="form-control text-uppercase-input" placeholder="NULL">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Save Behaviour Help -->
                <div class="alert alert-info mt-3" style="font-size: 13px; border-radius: 12px; margin-bottom: 0;">
                    <div class="fw-bold mb-1"><i class="bi bi-info-circle-fill"></i> How "Save to Database" decides INSERT vs UPDATE</div>
                    <ul class="mb-0 ps-3">
                        <li><b>Load an account first</b> (Load button on an account card). Without a loaded account, everything is saved as <b>new data</b>.</li>
                        <li><b>Merchant Key changed</b> &rarr; <b>new rows in all 3 tables</b> (new credential entry for the account).</li>
                        <li><b>Admin Gateway Name changed</b> &rarr; <b>new rows in all 3 tables</b> (brand-new gateway).</li>
                        <li><b>Any other field</b> (name, salt, endpoints, account no, IFSC, VPA, balance, status, user/account id, virtual account name &hellip;) &rarr; <b>updates</b> the loaded account's existing rows in all 3 tables.</li>
                        <li><i class="bi bi-magic"></i> If either <b>Account Number</b> or <b>Debit Account</b> is left empty, the non-empty value is pasted into it automatically (existing values are never overwritten).</li>
                    </ul>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                                        <button type="button" class="btn-generate" id="generateBtn" onclick="generateSQL()">
                        <i class="bi bi-play-fill"></i> Generate SQL & Env Params
                    </button>
                    <button type="button" class="btn-save-db" id="saveDbBtn" onclick="saveToDatabase()" style="display:none;">
                        <i class="bi bi-database-down"></i> Save to Database
                    </button>
                    <button type="button" class="btn-copy" id="copyBtn" onclick="copyAll()" style="display:none;">
                        <i class="bi bi-clipboard"></i> Copy All
                    </button>
                    <button type="button" class="btn-export" id="exportBtn" onclick="exportAll()" style="display:none;">
                        <i class="bi bi-download"></i> Export
                    </button>
                    <button type="button" class="btn-reset" onclick="resetForm()">
                        <i class="bi bi-arrow-counterclockwise"></i> Reset
                    </button>
                </div>

                <!-- Loading -->
                <div id="loadingIndicator" style="display:none; margin-top: 16px;">
                    <div class="d-flex align-items-center gap-3 p-3" style="background: #f8fafc; border-radius: 12px;">
                        <div class="spinner-border text-primary" role="status"></div>
                        <div>
                            <strong>Generating SQL & Env Params...</strong>
                            <p class="mb-0 text-muted small">Please wait while we prepare your queries</p>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Output Sections -->
            <div id="outputContainer" style="display:none;">
                <!-- Environment Variables -->
                <div class="form-section" id="envOutputSection" style="border-color: #fbbf24; background: #fffbeb;">
                    <div class="form-section-title">
                        <i class="bi bi-envelope-paper"></i>
                        Environment Variables (for .env)
                        <span class="badge-auto" style="background: #fef3c7; color: #d97706;">Copy to .env</span>
                    </div>
                    <div class="form-section-content">
                        <div class="env-output-section" id="envOutputBody">
                            <span class="text-muted">Environment variables will appear here...</span>
                        </div>
                        <div class="mt-3">
                            <button class="btn btn-sm btn-outline-secondary" onclick="copyEnv()" style="border-radius: 8px;">
                                <i class="bi bi-clipboard"></i> Copy Env Params
                            </button>
                        </div>
                    </div>
                </div>

                <!-- SQL Output -->
                <div class="sql-output-section" id="sqlOutputSection">
                    <div class="sql-output-header">
                        <h5>
                            <i class="bi bi-code-square"></i>
                            Generated SQL
                            <span class="sql-count" id="sqlCount">0 statements</span>
                        </h5>
                        <div>
                            <button class="btn btn-sm btn-outline-light" onclick="copySQL()" style="border-radius: 8px;">
                                <i class="bi bi-clipboard"></i> Copy SQL
                            </button>
                            <button class="btn btn-sm btn-outline-light ms-2" onclick="exportSQL()" style="border-radius: 8px;">
                                <i class="bi bi-download"></i> Export SQL
                            </button>
                        </div>
                    </div>
                    <div class="sql-output-body" id="sqlOutputBody">
                        <span class="text-muted">SQL will appear here...</span>
                    </div>
                </div>

                <!-- Columns Summary -->
                <div class="form-section mt-3" id="columnsHighlightSection" style="border-color: #c7d2fe; background: #f8fafc;">
                    <div class="form-section-title">
                        <i class="bi bi-list-ul"></i>
                        Column Values Summary
                        <span class="badge-auto">Each table</span>
                    </div>
                    <div id="columnsHighlightContent"></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    let generatedData = null;
    let loadedAccountData = null;

    // ============ Gateway Load Functions ============
    function loadGateway() {
        const selector = document.getElementById('gatewaySelector');
        const identifier = selector.value;

        if (!identifier) {
            return;
        }

        showLoading('Loading gateway configuration...');

        fetch(`/database/payinstant-sql/gateway/${encodeURIComponent(identifier)}`)
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    populateForm(data.data);
                    showToast('✅ Gateway loaded successfully!', 'success');
                } else {
                    showToast('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                hideLoading();
                showToast('Failed to load gateway', 'error');
            });
    }

    function loadGatewayByPrefix(prefix) {
        showLoading('Loading gateway...');

        fetch(`/database/payinstant-sql/gateway-prefix/${encodeURIComponent(prefix)}`)
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    populateForm(data.data);
                    showToast('✅ Gateway loaded from template!', 'success');
                } else {
                    showToast('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                hideLoading();
                showToast('Failed to load gateway', 'error');
            });
    }

    // Fill the empty one of Account Number / Debit Account with the non-empty value.
    // They are usually the same, but can legitimately differ — an existing value is
    // never overwritten, only a blank side gets pasted.
    function syncAccountNumberAndDebit() {
        const accEl = document.getElementById('account_number');
        const debEl = document.getElementById('debit_account');
        const acc = accEl.value.trim();
        const deb = debEl.value.trim();
        if (!acc && deb) {
            accEl.value = deb;
        } else if (!deb && acc) {
            debEl.value = acc;
        }
    }

    function populateForm(data) {
        document.getElementById('full_display_name').value = data.company_name || '';
        document.getElementById('bank').value = data.gateway_identifier || '';
        document.getElementById('merchant_key').value = data.merchant_key || '';
        document.getElementById('merchant_salt').value = data.merchant_salt || '';
        document.getElementById('proxy_endpoint').value = (data.proxy_endpoint || '').toLowerCase();
        document.getElementById('admin_endpoint').value = (data.admin_endpoint || '').toLowerCase();
        document.getElementById('account_number').value = data.account_number || '';
        document.getElementById('client_id').value = data.client_id || '';
        document.getElementById('user_id').value = data.user_id || '';
        document.getElementById('account_id').value = data.virtual_account_id || data.account_id || '';
        document.getElementById('virtual_account_name').value = data.virtual_account_name || '';
        document.getElementById('debit_account').value = data.debit_account || data.account_number || '';
        document.getElementById('ifsc').value = data.ifsc || '';
        document.getElementById('vpa').value = data.vpa || '';
        document.getElementById('tran_id').value = data.tran_id || '0000';
        document.getElementById('serial_no').value = data.serial_no || '000';
        document.getElementById('daily_limit').value = data.daily_limit || '';
        document.getElementById('admin_gateway').value = data.admin_gateway || '';
        document.getElementById('main_gateway_name').value = data.main_gateway_name || '';
        document.getElementById('adm_gateway_name').value = data.adm_gateway_name || '';
        document.getElementById('balance').value = data.account_balance || '0.00';
        document.getElementById('detected_ip').value = data.proxy_ip || data.proxy_host || '';

        // Set status (API returns "status"; fall back safely for 0 = Inactive)
        document.getElementById('status').value = (data.status ?? data.account_status ?? 1);
        document.getElementById('queue_status').value = data.queue_status || 0;

        // If either Account Number or Debit Account is empty, paste the non-empty value
        syncAccountNumberAndDebit();

        // Auto-detect prefix and update preview
        autoDetectPrefix();
        updateGatewayPreview();

        // Mark fields as auto-filled
        document.querySelectorAll('.form-control').forEach(el => {
            if (el.value) {
                el.classList.add('auto-filled');
            }
        });
    }

    // ============ Auto-detect Prefix ============
    function autoDetectPrefix() {
        const mainGateway = document.getElementById('main_gateway_name').value;
        const admGateway = document.getElementById('adm_gateway_name').value;
        let detectedPrefix = '';

        // Try from main_gateway_name
        if (mainGateway) {
            const parts = mainGateway.split('_');
            if (parts.length >= 1) {
                detectedPrefix = parts[0];
            }
        }

        // If not found, try from adm_gateway_name
        if (!detectedPrefix && admGateway) {
            const parts = admGateway.split('_');
            if (parts.length >= 1) {
                detectedPrefix = parts[0];
            }
        }

        // Update display
        const prefixDisplay = document.getElementById('detectedPrefixDisplay');
        const prefixPreview = document.getElementById('prefixPreview');

        if (detectedPrefix) {
            prefixDisplay.textContent = detectedPrefix.toUpperCase();
            prefixDisplay.style.color = '#1e40af';
            prefixPreview.textContent = detectedPrefix.toUpperCase();
            prefixPreview.style.color = '#1e40af';

            // Update env keys preview with detected prefix
            updateGatewayPreviewWithPrefix(detectedPrefix);
        } else {
            prefixDisplay.textContent = 'Not detected yet (enter Main or Admin Gateway name)';
            prefixDisplay.style.color = '#64748b';
            prefixPreview.textContent = 'Not detected yet';
            prefixPreview.style.color = '#64748b';
        }
    }

    // ============ Update Gateway Preview with Prefix ============
    function updateGatewayPreviewWithPrefix(prefix) {
        const main = document.getElementById('main_gateway_name').value || 'MAIN_GATEWAY';
        const adm = document.getElementById('adm_gateway_name').value || 'ADM_GATEWAY';
        const detectedPrefix = prefix || 'PREFIX';

        document.getElementById('envKeysPreview').textContent =
            `${detectedPrefix}_MAIN_GATEWAY, ${detectedPrefix}_ADM_GATEWAY`.toUpperCase();
        document.getElementById('proxyIPKeyPreview').textContent = main.toUpperCase();
        document.getElementById('bankMappingPreview').textContent =
            `{"1":"${main}"}`.toUpperCase();
    }

    // ============ Update Gateway Preview ============
    function updateGatewayPreview() {
        const main = document.getElementById('main_gateway_name').value || 'MAIN_GATEWAY';
        const adm = document.getElementById('adm_gateway_name').value || 'ADM_GATEWAY';

        // Auto-detect prefix
        let prefix = '';
        if (main) {
            const parts = main.split('_');
            if (parts.length >= 1) {
                prefix = parts[0];
            }
        }
        if (!prefix && adm) {
            const parts = adm.split('_');
            if (parts.length >= 1) {
                prefix = parts[0];
            }
        }
        if (!prefix) {
            prefix = 'PREFIX';
        }

        document.getElementById('envKeysPreview').textContent =
            `${prefix}_MAIN_GATEWAY, ${prefix}_ADM_GATEWAY`.toUpperCase();
        document.getElementById('proxyIPKeyPreview').textContent = main.toUpperCase();
        document.getElementById('bankMappingPreview').textContent =
            `{"1":"${main}"}`.toUpperCase();

        // Update prefix display
        const prefixDisplay = document.getElementById('detectedPrefixDisplay');
        const prefixPreview = document.getElementById('prefixPreview');
        if (prefix && prefix !== 'PREFIX') {
            prefixDisplay.textContent = prefix.toUpperCase();
            prefixDisplay.style.color = '#1e40af';
            prefixPreview.textContent = prefix.toUpperCase();
            prefixPreview.style.color = '#1e40af';
        } else if (prefix === 'PREFIX') {
            prefixDisplay.textContent = 'Not detected yet (enter Main or Admin Gateway name)';
            prefixDisplay.style.color = '#64748b';
            prefixPreview.textContent = 'Not detected yet';
            prefixPreview.style.color = '#64748b';
        }
    }

    // ============ Strategy Selection ============
    function selectStrategy(element, target, value) {
        document.querySelectorAll(`.strategy-badge[data-target="${target}"]`).forEach(badge => {
            badge.classList.remove('active');
        });
        element.classList.add('active');

        document.getElementById(`${target}_generation_type`).value = value;

        const container = document.getElementById(`${target}InputContainer`);
        const input = document.getElementById(`merchant_${target}`);

        if (value === 'static') {
            input.placeholder = `Enter merchant ${target} (static)`;
            input.disabled = false;
            container.style.display = 'block';
        } else if (value === 'custom') {
            input.placeholder = `Enter custom ${target} value`;
            input.disabled = false;
            container.style.display = 'block';
        } else {
            input.placeholder = `Auto-generated by MySQL SET @${target}`;
            input.disabled = true;
            input.value = '';
            container.style.display = 'block';
        }
    }

    // ============ Endpoint Functions ============
    function formatEndpoint(input) {
        let url = input.value.trim();
        if (!url) return;

        if (!/^https?:\/\//i.test(url)) {
            url = 'https://' + url;
        }
        url = url.replace(/^http:\/\//i, 'https://');
        url = url.toLowerCase();
        if (!url.endsWith('/')) {
            url += '/';
        }
        input.value = url;

        // Re-detect IP after formatting
        extractIPFromEndpoint(input);
    }

        // Fetch with a hard timeout — aborts the request and rejects after `ms` so a
    // hung / slow server response can never leave a spinner stuck forever.
    function fetchWithTimeout(url, options = {}, ms = 25000) {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), ms);
        return fetch(url, { ...options, signal: controller.signal })
            .finally(() => clearTimeout(timer));
    }

    // Run `fn` at most once per `wait` ms (trailing edge) — used to coalesce the
    // rapid resolve-ip calls that fire on every keystroke in the endpoint fields.
    function debounce(fn, wait) {
        let t;
        return function (...args) {
            clearTimeout(t);
            t = setTimeout(() => fn.apply(this, args), wait);
        };
    }

    const extractIPFromEndpoint = debounce(function (input) {
        const url = input.value.trim();
        if (!url) return;

        try {
            const parsed = new URL(url);
            const host = parsed.hostname;

            // Try to resolve host to numeric IP (bounded by a short timeout so a
            // slow / unresolvable host never blocks the UI)
            fetchWithTimeout(`/database/payinstant-sql/resolve-ip?host=${encodeURIComponent(host)}`, {}, 8000)
                .then(response => response.json())
                .then(res => {
                    if (res.success && res.ip) {
                        document.getElementById('detected_ip').value = res.ip;
                    } else {
                        document.getElementById('detected_ip').value = host;
                    }
                })
                .catch(() => {
                    document.getElementById('detected_ip').value = host;
                });
        } catch (e) {
            // Invalid URL
        }
    }, 500);

    // ============ Form Auto-fill ============
    document.getElementById('bank').addEventListener('input', function() {
        const bank = this.value.toUpperCase();
        if (bank) {
            const parts = bank.split('_');
            if (parts.length >= 2) {
                const suffix = parts.slice(1).join('_');
                if (!document.getElementById('main_gateway_name').value) {
                    document.getElementById('main_gateway_name').value = bank.replace('_ADM_', '_');
                }
                if (!document.getElementById('adm_gateway_name').value) {
                    document.getElementById('adm_gateway_name').value = bank;
                }
            }
            updateGatewayPreview();
            autoDetectPrefix();
        }
    });

    document.getElementById('main_gateway_name').addEventListener('input', function() {
        updateGatewayPreview();
        autoDetectPrefix();
    });

    document.getElementById('adm_gateway_name').addEventListener('input', function() {
        updateGatewayPreview();
        autoDetectPrefix();
    });

    document.getElementById('user_id').addEventListener('input', function() {
        const accountId = document.getElementById('account_id');
        if (!accountId.value) {
            const val = parseInt(this.value);
            if (!isNaN(val) && val > 0) {
                accountId.value = val - 4;
            }
        }
    });

    // ============ Main Generate Function ============
    function generateSQL() {
        const btn = document.getElementById('generateBtn');
        const loading = document.getElementById('loadingIndicator');

        // Restore helper — runs on EVERY path so the "Generating..." spinner
        // can never get stuck on the button
        const restoreBtn = () => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-play-fill"></i> Generate SQL & Env Params';
            loading.style.display = 'none';
        };

        try {
            const formData = collectFormData();

            // Validate
            if (!validateForm(formData)) {
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Generating...';
            loading.style.display = 'block';

            fetchWithTimeout('/database/payinstant-sql/generate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                },
                body: JSON.stringify(formData)
            }, 30000)
            .then(response => response.json())
            .then(data => {
                try {
                    if (data.success) {
                        generatedData = data;
                        displayOutput(data);
                        showToast('✅ SQL & Env Params generated successfully!', 'success');
                    } else {
                        showToast('Error: ' + (data.message || 'Failed to generate'), 'error');
                    }
                } catch (e) {
                    console.error('Render failed:', e);
                } finally {
                    restoreBtn();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Failed to generate. Please try again.', 'error');
                restoreBtn();
            })
            .finally(restoreBtn);
        } catch (e) {
            console.error('Error:', e);
            showToast('Generate failed: ' + e.message, 'error');
            restoreBtn();
        }
    }

    function saveToDatabase() {
        const btn = document.getElementById('saveDbBtn');
        const btnText = btn.innerHTML;

        // Restore helper — runs on EVERY path (success, server error, network error or a
        // JS error) so the "Saving..." spinner can never get stuck on the button
        const restoreBtn = () => {
            btn.disabled = false;
            btn.innerHTML = btnText;
        };

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Saving...';

        try {
            // Safety net: if either Account Number or Debit Account is empty,
            // paste the non-empty value before collecting the form data
            if (typeof syncAccountNumberAndDebit === 'function') {
                syncAccountNumberAndDebit();
            }

            const formData = collectFormData();

            fetchWithTimeout('/database/payinstant-sql/save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                },
                body: JSON.stringify({
                    ...formData,
                    original_bank: loadedAccountData ? loadedAccountData.bank : null,
                    original_key: loadedAccountData ? loadedAccountData.merchant_key : null,
                    original_virtual_account_name: loadedAccountData ? loadedAccountData.virtual_account_name : null,
                })
            }, 30000)
            .then(response => response.json())
            .then(data => {
                try {
                    const selector = document.getElementById('gatewaySelector');
                    if (data.success) {
                        showToast('✅ ' + data.message, 'success');
                        // Refresh the accounts list if a gateway is selected
                        if (selector && selector.value) {
                            onGatewaySelect();
                        }
                        // Clear loaded account data so subsequent saves are treated as new
                        loadedAccountData = null;
                    } else {
                        showToast('Error: ' + (data.message || 'Failed to save'), 'error');
                    }
                } catch (e) {
                    console.error('Post-save refresh failed:', e);
                } finally {
                    restoreBtn();
                }
            })
            .catch(error => {
                console.error('Save failed:', error);
                showToast('Failed to save to database', 'error');
                restoreBtn();
            })
            .finally(restoreBtn);
        } catch (e) {
            console.error('Save failed:', e);
            showToast('Save failed: ' + e.message, 'error');
            restoreBtn();
        }
    }

    function collectFormData() {
        return {
            full_display_name: document.getElementById('full_display_name').value.trim(),
            merchant_key: document.getElementById('merchant_key').value.trim(),
            merchant_salt: document.getElementById('merchant_salt').value.trim(),
            bank: document.getElementById('bank').value.trim(),
            ifsc: document.getElementById('ifsc').value.trim(),
            proxy_endpoint: document.getElementById('proxy_endpoint').value.trim(),
            admin_endpoint: document.getElementById('admin_endpoint').value.trim(),
            account_number: document.getElementById('account_number').value.trim(),
            client_id: document.getElementById('client_id').value.trim() || null,
            user_id: document.getElementById('user_id').value.trim(),
            account_id: document.getElementById('account_id').value.trim(),
            virtual_account_name: document.getElementById('virtual_account_name').value.trim(),
            debit_account: document.getElementById('debit_account').value.trim(),
            daily_limit: document.getElementById('daily_limit').value.trim() || null,
            admin_gateway: document.getElementById('admin_gateway').value.trim() || null,
            tran_id: document.getElementById('tran_id').value.trim(),
            serial_no: document.getElementById('serial_no').value.trim(),
            status: document.getElementById('status').value,
            queue_status: document.getElementById('queue_status').value,
            vpa: document.getElementById('vpa').value.trim() || null,
            balance: document.getElementById('balance').value.trim(),
            main_gateway_name: document.getElementById('main_gateway_name').value.trim(),
            adm_gateway_name: document.getElementById('adm_gateway_name').value.trim(),
            key_generation_type: document.getElementById('key_generation_type').value,
            salt_generation_type: document.getElementById('salt_generation_type').value,
        };
    }

    function validateForm(data) {
        const required = [
            'full_display_name', 'bank', 'ifsc', 'proxy_endpoint', 'admin_endpoint',
            'account_number', 'user_id', 'account_id', 'virtual_account_name',
            'main_gateway_name', 'adm_gateway_name'
        ];

        for (const field of required) {
            if (!data[field]) {
                showToast(`Please fill required field: ${field.replace(/_/g, ' ')}`, 'error');
                return false;
            }
        }

        const keyType = document.getElementById('key_generation_type').value;
        if (keyType === 'static' || keyType === 'custom') {
            if (!data.merchant_key) {
                showToast('Please enter merchant key (static/custom mode)', 'error');
                return false;
            }
        }

        const saltType = document.getElementById('salt_generation_type').value;
        if (saltType === 'static' || saltType === 'custom') {
            if (!data.merchant_salt) {
                showToast('Please enter merchant salt (static/custom mode)', 'error');
                return false;
            }
        }

        return true;
    }

    // ============ Display Output ============
    function displayOutput(data) {
        const container = document.getElementById('outputContainer');
        container.style.display = 'block';

        displayEnvParams(data.env_params);
        displaySQL(data);
        displayColumns(data);

                document.getElementById('copyBtn').style.display = 'inline-flex';
        document.getElementById('exportBtn').style.display = 'inline-flex';
        document.getElementById('saveDbBtn').style.display = 'inline-flex';
    }

    function displayEnvParams(envParams) {
        const body = document.getElementById('envOutputBody');
        let html = '';
        for (const [key, value] of Object.entries(envParams)) {
            html += `<span class="env-key">${escapeHtml(key)}</span><span class="env-equals"> = </span><span class="env-value">${escapeHtml(value)}</span>\n`;
        }
        body.innerHTML = html || '<span class="text-muted">No environment variables generated</span>';
    }

    function displaySQL(data) {
        const body = document.getElementById('sqlOutputBody');
        const count = document.getElementById('sqlCount');

        count.textContent = data.statements.length + ' statements';

        let html = '';

        const fullSQL = data.full_sql;
        if (fullSQL.includes('SET @key') || fullSQL.includes('SET @salt')) {
            const varMatch = fullSQL.match(/-- Auto-generated variables\n([\s\S]*?)\n\n/);
            if (varMatch) {
                html += `
                    <div class="sql-statement" style="border-left-color: #f59e0b;">
                        <span class="table-name" style="color: #f59e0b;">
                            <i class="bi bi-gear-fill"></i> Auto-Generated Variables
                            <span style="color: #f87171; font-size: 10px; margin-left: 8px;">⚡ KEY & SALT WILL BE GENERATED BY MySQL</span>
                        </span>
                        <div>${highlightSQL(varMatch[1])}</div>
                    </div>
                `;
            }
        }

        const statementSQL = fullSQL.replace(/-- Auto-generated variables\n([\s\S]*?)\n\n/, '');
        if (statementSQL.trim()) {
            const lines = statementSQL.split('\n');
            let currentStmt = [];
            let tableName = '';
            for (const line of lines) {
                if (line.includes('INSERT INTO `')) {
                    if (currentStmt.length > 0) {
                        html += formatSQLStatement(currentStmt.join('\n'), tableName);
                        currentStmt = [];
                    }
                    tableName = line.match(/INSERT INTO `([^`]+)`/)?.[1] || '';
                    currentStmt.push(line);
                } else if (line.trim()) {
                    currentStmt.push(line);
                }
            }
            if (currentStmt.length > 0) {
                html += formatSQLStatement(currentStmt.join('\n'), tableName);
            }
        }

        body.innerHTML = html || '<span class="text-muted">SQL will appear here...</span>';
    }

    function formatSQLStatement(sql, tableName) {
        const colors = {
            'payinstant_accounts': { border: '#8b5cf6', bg: '#ede9fe', text: '#7c3aed' },
            'payinstant_accounts_live': { border: '#06b6d4', bg: '#ecfeff', text: '#0891b2' },
            'virtual_accounts': { border: '#10b981', bg: '#ecfdf5', text: '#059669' },
        };
        const color = colors[tableName] || { border: '#667eea', bg: '#f1f5f9', text: '#4f46e5' };

        return `
            <div class="sql-statement" style="border-left-color: ${color.border}; background: ${color.bg};">
                <span class="table-name" style="color: ${color.text};">
                    <i class="bi bi-table"></i> ${tableName}
                </span>
                <div>${highlightSQL(sql)}</div>
            </div>
        `;
    }

    function highlightSQL(sql) {
        let escaped = sql
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');

        escaped = escaped.replace(/(--[^\n]*)/g, '<span class="sql-comment">$1</span>');

        const keywords = ['INSERT INTO', 'VALUES', 'NULL', 'UPPER', 'SUBSTRING', 'REPLACE', 'UUID', 'SET'];
        keywords.forEach(kw => {
            const regex = new RegExp('\\b' + kw + '\\b', 'gi');
            escaped = escaped.replace(regex, `<span class="sql-keyword">$&</span>`);
        });

        escaped = escaped.replace(/(@\w+)/g, '<span class="sql-variable">$1</span>');
        escaped = escaped.replace(/`([^`]+)`/g, '<span class="sql-column">`$1`</span>');
        escaped = escaped.replace(/'([^']+)'/g, '<span class="sql-string">\'$1\'</span>');
        escaped = escaped.replace(/\b(\d+\.\d+)\b/g, '<span class="sql-number">$1</span>');
        escaped = escaped.replace(/\b(\d+)\b/g, '<span class="sql-number">$1</span>');

        return escaped;
    }

    function displayColumns(data) {
        const content = document.getElementById('columnsHighlightContent');

        let html = '';
        data.statements.forEach((stmt, index) => {
            if (Object.keys(stmt.columns).length === 0) return;

            const tableName = stmt.table;
            const columns = stmt.columns;

            // Count columns with values (non-NULL)
            const columnsWithValues = Object.entries(columns).filter(([_, value]) => value !== 'NULL' && value !== null);
            const totalColumns = Object.keys(columns).length;

            html += `
                <div class="mb-3" style="background: #f1f5f9; border-radius: 12px; padding: 16px; border: 2px solid #e2e8f0;">
                    <div style="font-weight: 700; color: #1e293b; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                        <span style="background: #667eea; color: white; padding: 2px 10px; border-radius: 12px; font-size: 0.7rem; font-weight: 600;">#${index + 1}</span>
                        <span style="font-size: 0.95rem;">${tableName}</span>
                        <span style="font-size: 0.7rem; color: #94a3b8; font-weight: 400;">(${totalColumns} columns)</span>
                        <span style="background: #10b981; color: white; padding: 2px 10px; border-radius: 12px; font-size: 0.7rem; font-weight: 600;">${columnsWithValues.length} with values</span>
                    </div>
                    <div style="display: flex; flex-wrap: wrap; gap: 6px;">
            `;

            for (const [key, value] of Object.entries(columns)) {
                const hasValue = value !== 'NULL' && value !== null;
                const displayValue = hasValue ?
                    `<span>${escapeHtml(String(value))}</span>` :
                    '<span class="null">NULL</span>';

                let bgColor = '#f1f5f9';
                let borderColor = '#e2e8f0';
                if (['key', 'salt'].includes(key)) {
                    bgColor = '#fef3c7';
                    borderColor = '#f59e0b';
                } else if (['name', 'bank', 'endpoint', 'account_name', 'user_id', 'account_id'].includes(key)) {
                    bgColor = '#dbeafe';
                    borderColor = '#3b82f6';
                }
                if (!hasValue) {
                    bgColor = '#fee2e2';
                    borderColor = '#ef4444';
                }

                // Add indicator for columns with values
                const valueIndicator = hasValue ?
                    '<span style="color: #10b981; font-weight: 700; margin-right: 4px;">✓</span>' :
                    '<span style="color: #ef4444; font-weight: 700; margin-right: 4px;">✗</span>';

                html += `
                    <span class="column-tag" style="background: ${bgColor}; border-color: ${borderColor};">
                        ${valueIndicator}
                        <span class="col-name" style="font-weight: ${hasValue ? '700' : '400'};">${escapeHtml(key)}</span>
                        <span class="col-arrow">→</span>
                        <span class="col-value ${hasValue ? '' : 'null'}">
                            ${displayValue}
                        </span>
                    </span>
                `;
            }

            html += `
                    </div>
                </div>
            `;
        });

        content.innerHTML = html || '<p class="text-muted">No column data available</p>';
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ============ Copy Functions ============
    function copyAll() {
        if (!generatedData) {
            showToast('Please generate first!', 'error');
            return;
        }

        let output = '# ===== Environment Variables =====\n';
        for (const [key, value] of Object.entries(generatedData.env_params)) {
            output += `${key}=${value}\n`;
        }
        output += '\n# ===== SQL Statements =====\n';
        output += generatedData.full_sql;

        copyToClipboard(output, 'All content copied to clipboard!');
    }

    function copySQL() {
        if (!generatedData) {
            showToast('Please generate SQL first!', 'error');
            return;
        }
        copyToClipboard(generatedData.full_sql, 'SQL copied to clipboard!');
    }

    function copyEnv() {
        if (!generatedData) {
            showToast('Please generate first!', 'error');
            return;
        }

        let output = '';
        for (const [key, value] of Object.entries(generatedData.env_params)) {
            output += `${key}=${value}\n`;
        }
        copyToClipboard(output, 'Env params copied to clipboard!');
    }

    function copyToClipboard(text, successMessage) {
        navigator.clipboard.writeText(text).then(() => {
            showToast('✅ ' + successMessage, 'success');
        }).catch(() => {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            showToast('✅ ' + successMessage, 'success');
        });
    }

    // ============ Export Functions ============
    function exportAll() {
        if (!generatedData) {
            showToast('Please generate first!', 'error');
            return;
        }

        let output = '# ===== PayInstant Gateway Configuration =====\n';
        output += `# Generated at: ${new Date().toISOString()}\n\n`;
        output += '# ---- Environment Variables ----\n';
        for (const [key, value] of Object.entries(generatedData.env_params)) {
            output += `${key}=${value}\n`;
        }
        output += '\n# ---- SQL Statements ----\n';
        output += generatedData.full_sql;

        downloadFile(output, `payinstant_config_${new Date().toISOString().slice(0,10)}.txt`);
    }

    function exportSQL() {
        if (!generatedData) {
            showToast('Please generate SQL first!', 'error');
            return;
        }
        downloadFile(generatedData.full_sql, `payinstant_sql_${new Date().toISOString().slice(0,10)}.sql`);
    }

    function downloadFile(content, filename) {
        const blob = new Blob([content], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        showToast('✅ Exported successfully!', 'success');
    }

    // ============ Reset ============
    function resetForm() {
        document.querySelectorAll('#full_display_name, #merchant_key, #merchant_salt, #virtual_account_name, #client_id, #user_id, #account_id, #bank, #ifsc, #proxy_endpoint, #admin_endpoint, #account_number, #debit_account, #vpa, #main_gateway_name, #adm_gateway_name, #daily_limit, #admin_gateway, #tran_id, #serial_no, #balance, #detected_ip').forEach(el => el.value = '');

        document.getElementById('tran_id').value = '0000';
        document.getElementById('serial_no').value = '000';
        document.getElementById('balance').value = '0.00';
        document.getElementById('status').value = '1';
        document.getElementById('queue_status').value = '0';
        document.getElementById('gatewaySelector').value = '';

        // Reset strategies
        document.querySelectorAll('.strategy-badge').forEach(b => b.classList.remove('active'));
        document.querySelector('.strategy-badge.static[data-target="key"]').classList.add('active');
        document.querySelector('.strategy-badge.static[data-target="salt"]').classList.add('active');
        document.getElementById('key_generation_type').value = 'static';
        document.getElementById('salt_generation_type').value = 'static';
        document.getElementById('merchant_key').disabled = false;
        document.getElementById('merchant_salt').disabled = false;
        document.getElementById('merchant_key').placeholder = 'Enter merchant key (static)';
        document.getElementById('merchant_salt').placeholder = 'Enter merchant salt (static)';

        // Hide output
        document.getElementById('outputContainer').style.display = 'none';
        document.getElementById('copyBtn').style.display = 'none';
        document.getElementById('exportBtn').style.display = 'none';
        generatedData = null;

        // Reset auto-filled class
        document.querySelectorAll('.form-control').forEach(el => {
            el.classList.remove('auto-filled');
        });

        // Reset prefix display
        autoDetectPrefix();
        updateGatewayPreview();

        showToast('Form reset', 'info');
    }

    // ============ Toggle Section ============
    function toggleSection(sectionId) {
        const section = document.getElementById(sectionId);
        if (section) {
            section.classList.toggle('collapsed');
        }
    }

    // ============ Loading ============
    function showLoading(message) {
        const loading = document.getElementById('loadingIndicator');
        loading.style.display = 'block';
        const strong = loading.querySelector('strong');
        if (strong) {
            strong.textContent = message || 'Loading...';
        }
    }

    function hideLoading() {
        document.getElementById('loadingIndicator').style.display = 'none';
    }

    // ============ Toast ============
    function showToast(message, type) {
        const toast = document.createElement('div');
        toast.className = `toast-notification ${type}`;
        const icon = type === 'success' ? 'check-circle-fill' :
                     type === 'error' ? 'exclamation-triangle-fill' : 'info-circle-fill';
        toast.innerHTML = `<i class="bi bi-${icon} me-2"></i> ${message}`;
        toast.style.cssText = `
            position: fixed; bottom: 20px; right: 20px;
            background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
            color: white; padding: 12px 20px; border-radius: 10px;
            z-index: 10002; animation: slideInRight 0.3s ease;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2); max-width: 90%;
        `;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 4000);
    }

    // ============ Keyboard Shortcuts ============
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            generateSQL();
        }
    });

    // ============ Init ============
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-detect prefix on load
        autoDetectPrefix();
        updateGatewayPreview();

        // Auto-fill debit account when account number changes
        document.getElementById('account_number').addEventListener('input', function() {
            const debit = document.getElementById('debit_account');
            if (!debit.value) {
                debit.value = this.value;
            }
        });

        // Mirror: auto-fill account number when debit account changes
        document.getElementById('debit_account').addEventListener('input', function() {
            const acc = document.getElementById('account_number');
            if (!acc.value) {
                acc.value = this.value;
            }
        });

        // Also auto-fill debit account from account number on load
        const accountNumber = document.getElementById('account_number').value;
        const debitAccount = document.getElementById('debit_account');
        if (accountNumber && !debitAccount.value) {
            debitAccount.value = accountNumber;
        }
    });

        function onGatewaySelect() {
            const selector = document.getElementById('gatewaySelector');
        const identifier = selector.value;
        const container = document.getElementById('accountsContainer');
        const accountsList = document.getElementById('accountsList');
        const gatewayName = document.getElementById('selectedGatewayName');

        if (!identifier) {
            container.style.display = 'none';
            return;
        }

        // Show loading
        container.style.display = 'block';
        gatewayName.textContent = identifier;
        renderAccountCards([], accountsList);

        fetch(`/database/payinstant-sql/gateway-accounts/${encodeURIComponent(identifier)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data && data.data.length > 0) {
                    // Show ALL accounts for the selected gateway as individual cards
                    renderAccountCards(data.data, accountsList);
                } else {
                    // Fallback: try loading gateway directly
                    fetch(`/database/payinstant-sql/gateway/${encodeURIComponent(identifier)}`)
                        .then(response => response.json())
                        .then(gatewayData => {
                            if (gatewayData.success && gatewayData.data) {
                                renderAccountCards([gatewayData.data], accountsList);
                            } else {
                                accountsList.innerHTML = '<span class="text-muted">No accounts found for this gateway</span>';
                            }
                        })
                        .catch(() => {
                            accountsList.innerHTML = '<span class="text-muted">No accounts found for this gateway</span>';
                        });
                }
            })
            .catch(() => {
                accountsList.innerHTML = '<span class="text-danger">Failed to load accounts</span>';
            });
        }

    function getAccountLabel(account, index) {
        const displayName = account.virtual_account_name || account.company_name || account.gateway_identifier;
        if (index === 0) {
            return displayName + ' <span class="badge badge-latest">Latest</span>';
        }
        return displayName + ' <span class="badge badge-older">#' + (index + 1) + '</span>';
    }

    // Escape a value for safe output inside HTML text and double-quoted attributes
    function escHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    // Escape a value for safe use inside a single-quoted JS string in an onclick attribute
    function escJs(value) {
        return String(value ?? '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
    }

    // Copy text to clipboard with a small toast
    function copyText(text) {
        if (!text) return;
        const done = () => showToast('📋 ' + (text.length > 28 ? text.substring(0, 28) + '…' : text), 'success');
        const fallback = () => {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); done(); } catch (e) { showToast('Copy failed', 'error'); }
            document.body.removeChild(ta);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done).catch(fallback);
        } else {
            fallback();
        }
    }

    function renderAccountCards(accounts, container) {
        if (!accounts || accounts.length === 0) {
            container.innerHTML = '<span class="text-muted">No accounts found for this gateway</span>';
            return;
        }
        let html = '';
        accounts.forEach((account, index) => {
            const name = account.virtual_account_name || account.company_name || account.gateway_identifier;
            const key = account.merchant_key || '';
            const shortKey = key ? (key.length > 18 ? key.substring(0, 8) + '…' + key.slice(-4) : key) : '—';
            const badge = index === 0
                ? '<span class="badge badge-latest">Latest</span>'
                : '<span class="badge badge-older">#' + (index + 1) + '</span>';
            const active = Number(account.status) === 1;
            const safeName = escJs(name);
            const safeKey = escJs(key);
            const safeBank = escJs(account.bank || '');
            const safeVaName = escJs(account.virtual_account_name || '');
            const safeAccNo = escJs(account.account_number || '');

            html += `
                <div class="account-card">
                    <div class="account-card-header">
                        <span class="account-card-icon"><i class="bi bi-hdd-network"></i></span>
                        <span class="account-name" title="${escHtml(name)}">${escHtml(name)}</span>
                        ${badge}
                        <button type="button" class="ac-icon-btn" title="Load into form"
                                onclick="loadAccountByVirtualName('${safeName}', '${safeKey}')">
                            <i class="bi bi-pencil-square"></i>
                        </button>
                        <button type="button" class="ac-icon-btn danger" title="Delete this account"
                                onclick="deleteAccountFromList('${safeBank}', '${safeVaName}', '${safeName}', '${safeKey}')">
                            <i class="bi bi-trash3"></i>
                        </button>
                    </div>
                    <div class="account-card-body">
                        <div class="ac-row"><i class="bi bi-building"></i><span class="ac-value" title="Company">${escHtml(account.company_name || '—')}</span></div>
                        <div class="ac-row"><i class="bi bi-credit-card-2-front"></i><span class="ac-label">Acc:</span><span class="ac-value mono" title="Account Number">${escHtml(account.account_number || '—')}</span></div>
                        <div class="ac-row"><i class="bi bi-bank2"></i><span class="ac-label">IFSC:</span><span class="ac-value" title="IFSC">${escHtml(account.ifsc || '—')}</span></div>
                        <div class="ac-row"><i class="bi bi-at"></i><span class="ac-label">VPA:</span><span class="ac-value" title="VPA">${escHtml(account.vpa || '—')}</span></div>
                        <div class="ac-row"><i class="bi bi-cash-stack"></i><span class="ac-label">Bal:</span><span class="ac-value" title="Balance">${escHtml(account.account_balance || (account.live_balance || '—'))}</span></div>
                        <div class="ac-row"><i class="bi bi-key"></i><span class="ac-value mono" title="${escHtml(key)}">${escHtml(shortKey)}</span></div>
                        <div class="ac-row"><i class="bi bi-activity"></i><span class="ac-status-dot" style="background: ${active ? '#22c55e' : '#cbd5e1'}"></span><span class="ac-value">${active ? 'Active' : 'Inactive'}</span></div>
                    </div>
                    <div class="account-card-footer">
                        <button type="button" class="btn-load-account"
                                onclick="loadAccountByVirtualName('${safeName}', '${safeKey}')"
                                title="Load this account into the form">
                            <i class="bi bi-box-arrow-in-down"></i> Load
                        </button>
                        <span style="flex: 1 1 auto;"></span>
                        <button type="button" class="ac-icon-btn" title="Copy account number"
                                onclick="copyText('${safeAccNo}')"><i class="bi bi-clipboard"></i></button>
                        <button type="button" class="ac-icon-btn" title="Copy merchant key"
                                onclick="copyText('${safeKey}')"><i class="bi bi-key"></i></button>
                    </div>
                </div>
            `;
        });
        container.innerHTML = html;
    }

    function deleteAccountFromList(bank, virtualAccountName, displayName, merchantKey) {
        if (!confirm('Delete account "' + displayName + '"? This action cannot be undone.')) {
            return;
        }
        fetch(`/database/payinstant-sql/account`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ bank: bank, name: virtualAccountName, key: merchantKey || null })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Account "' + displayName + '" deleted successfully', 'success');
                    onGatewaySelect();
                } else {
                    showToast('Error: ' + data.message, 'error');
                }
            })
            .catch(() => {
                showToast('Failed to delete account', 'error');
            });
    }

    function loadAccountByVirtualName(virtualAccountName, merchantKey) {
        showLoading('Loading account...');

        // With multi-key accounts the same account name exists several times —
        // when a merchant key is given, load exactly that credential entry
        let url = `/database/payinstant-sql/gateway-by-virtual-name/${encodeURIComponent(virtualAccountName)}`;
        if (merchantKey) {
            url += `?key=${encodeURIComponent(merchantKey)}`;
        }

        fetch(url)
            .then(response => response.json())
            .then(data => {
                hideLoading();
                                if (data.success) {
                    loadedAccountData = data.data;
                    populateForm(data.data);
                    showToast('✅ Account loaded successfully!', 'success');
                } else {
                    showToast('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                hideLoading();
                showToast('Failed to load account', 'error');
            });
    }

    function loadAccount(identifier) {
        showLoading('Loading account...');

        fetch(`/database/payinstant-sql/gateway/${encodeURIComponent(identifier)}`)
            .then(response => response.json())
                        .then(data => {
                hideLoading();
                if (data.success) {
                    loadedAccountData = data.data;
                    populateForm(data.data);
                    showToast('✅ Account loaded successfully!', 'success');
                } else {
                    showToast('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                hideLoading();
                showToast('Failed to load account', 'error');
            });
    }

</script>
@endsection