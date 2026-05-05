@extends('layouts.app')

@section('title', 'Command Storage | Developer Command Library')

@section('styles')
<style>
    /* Command Storage Styles - Complete Fix */
    .command-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card-modern {
        background: white;
        border-radius: 20px;
        padding: 20px;
        text-align: center;
        border: 1px solid #e2e8f0;
        transition: all 0.3s ease;
    }
    
    .stat-card-modern:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        border-color: var(--color-primary);
    }
    
    .stat-number-modern {
        font-size: 2.5rem;
        font-weight: 800;
        color: var(--color-primary);
        font-family: 'Space Grotesk', monospace;
        line-height: 1;
    }
    
    .stat-label-modern {
        font-size: 0.85rem;
        color: #64748b;
        margin-top: 8px;
        font-weight: 500;
    }
    
    /* Search Section */
    .search-wrapper {
        background: white;
        border-radius: 60px;
        padding: 5px;
        display: flex;
        gap: 10px;
        margin-bottom: 25px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
    }
    
    .search-input-modern {
        flex: 1;
        border: none;
        padding: 14px 24px;
        border-radius: 60px;
        font-size: 0.95rem;
        outline: none;
        background: transparent;
    }
    
    .search-input-modern:focus {
        box-shadow: none;
    }
    
    .search-btn-modern {
        background: var(--gradient-primary);
        border: none;
        border-radius: 50px;
        padding: 10px 32px;
        color: white;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        white-space: nowrap;
    }
    
    .search-btn-modern:hover {
        transform: scale(1.02);
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
    }
    
    /* Category Chips */
    .categories-wrapper {
        margin-bottom: 30px;
    }
    
    .categories-title {
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #64748b;
        margin-bottom: 12px;
        font-weight: 600;
    }
    
    .category-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .chip {
        background: #f1f5f9;
        padding: 8px 18px;
        border-radius: 40px;
        font-size: 0.85rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        color: #475569;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .chip:hover {
        background: #e2e8f0;
        transform: translateY(-2px);
    }
    
    .chip.active {
        background: var(--gradient-primary);
        color: white;
        box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
    }
    
    /* Command Cards */
    .commands-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 24px;
        margin-bottom: 30px;
    }
    
    .cmd-card {
        background: white;
        border-radius: 20px;
        overflow: hidden;
        transition: all 0.3s ease;
        border: 1px solid #e2e8f0;
        display: flex;
        flex-direction: column;
    }
    
    .cmd-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 30px -12px rgba(0, 0, 0, 0.15);
        border-color: var(--color-primary);
    }
    
    .cmd-card-header {
        padding: 16px 20px;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .cmd-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 700;
        font-size: 1rem;
        color: var(--color-dark);
    }
    
    .cmd-title i {
        color: var(--color-primary);
        font-size: 1.2rem;
    }
    
    .fav-btn {
        background: none;
        border: none;
        cursor: pointer;
        font-size: 1.1rem;
        color: #cbd5e1;
        transition: all 0.3s ease;
        padding: 5px;
        border-radius: 8px;
    }
    
    .fav-btn:hover {
        transform: scale(1.1);
        background: rgba(0,0,0,0.05);
    }
    
    .fav-btn.active {
        color: #f59e0b;
    }
    
    .cmd-card-body {
        padding: 20px;
        flex: 1;
        display: flex;
        flex-direction: column;
    }
    
    /* Terminal Style Command Block */
    .terminal-block {
        background: #0f172a;
        border-radius: 12px;
        overflow: hidden;
        margin-bottom: 16px;
    }
    
    .terminal-header {
        background: #1e293b;
        padding: 8px 12px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #334155;
    }
    
    .terminal-label {
        font-size: 0.7rem;
        color: #94a3b8;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .copy-btn-sm {
        background: rgba(255, 255, 255, 0.1);
        border: none;
        border-radius: 6px;
        padding: 4px 12px;
        font-size: 0.7rem;
        color: #e2e8f0;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    
    .copy-btn-sm:hover {
        background: var(--color-primary);
        color: white;
    }
    
    .terminal-body {
        padding: 14px;
        overflow-x: auto;
    }
    
    .terminal-body code {
        font-family: 'SF Mono', 'Fira Code', monospace;
        font-size: 0.75rem;
        line-height: 1.5;
        color: #a5f3c3;
        display: block;
        white-space: pre-wrap;
        word-break: break-all;
    }
    
    .cmd-description {
        font-size: 0.85rem;
        color: #475569;
        line-height: 1.5;
        margin-bottom: 16px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .cmd-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 16px;
    }
    
    .tag {
        background: #f1f5f9;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 500;
        color: #475569;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    
    .tag i {
        font-size: 0.7rem;
    }
    
    .tag-usage {
        background: #e0e7ff;
        color: var(--color-primary);
    }
    
    .tag-danger-low {
        background: #d1fae5;
        color: #059669;
    }
    
    .tag-danger-medium {
        background: #fed7aa;
        color: #ea580c;
    }
    
    .tag-danger-high {
        background: #fee2e2;
        color: #dc2626;
    }
    
    .view-details-btn {
        background: transparent;
        border: 1.5px solid #e2e8f0;
        border-radius: 12px;
        padding: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        color: #475569;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        width: 100%;
        margin-top: auto;
    }
    
    .view-details-btn:hover {
        border-color: var(--color-primary);
        color: var(--color-primary);
        background: rgba(99, 102, 241, 0.05);
        transform: translateY(-2px);
    }
    
    /* Pagination */
    .pagination-wrapper {
        display: flex;
        justify-content: center;
        margin-top: 20px;
    }
    
    .pagination-custom {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .page-btn {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 8px 14px;
        font-size: 0.85rem;
        font-weight: 500;
        color: #475569;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    
    .page-btn:hover {
        background: #f1f5f9;
        border-color: var(--color-primary);
        color: var(--color-primary);
    }
    
    .page-btn.active {
        background: var(--gradient-primary);
        border-color: var(--color-primary);
        color: white;
    }
    
    .page-btn.disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    /* Modal - Fixed Close Button */
    .modal-custom {
        background: white;
        border-radius: 24px;
        overflow: hidden;
        max-width: 750px;
        width: 90%;
        margin: 50px auto;
        position: relative;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    }
    
    .modal-header-custom {
        background: var(--gradient-primary);
        padding: 20px 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    
    .modal-header-custom h3 {
        color: white;
        margin: 0;
        font-size: 1.25rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .modal-close {
        background: rgba(255, 255, 255, 0.2);
        border: none;
        border-radius: 50%;
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
        color: white;
    }
    
    .modal-close:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: rotate(90deg);
    }
    
    .modal-body-custom {
        padding: 25px;
        max-height: 70vh;
        overflow-y: auto;
    }
    
    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
    }
    
    .empty-state i {
        font-size: 4rem;
        color: #cbd5e1;
    }
    
    .empty-state h4 {
        margin-top: 20px;
        color: var(--color-dark);
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .commands-grid {
            grid-template-columns: 1fr;
        }
        
        .search-wrapper {
            flex-direction: column;
            border-radius: 20px;
        }
        
        .search-btn-modern {
            width: 100%;
            text-align: center;
            justify-content: center;
        }
        
        .command-stats-grid {
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }
        
        .stat-number-modern {
            font-size: 1.8rem;
        }
        
        .modal-custom {
            width: 95%;
            margin: 20px auto;
        }
    }
    
    @media (max-width: 480px) {
        .command-stats-grid {
            grid-template-columns: 1fr;
        }
        
        .cmd-card-header {
            padding: 12px 16px;
        }
        
        .cmd-card-body {
            padding: 16px;
        }
    }

    /* Horizontal Stats Bar */
    .stats-horizontal {
        background: white;
        border-radius: 16px;
        padding: 20px 30px;
        margin-bottom: 30px;
        display: flex;
        align-items: center;
        justify-content: space-around;
        border: 1px solid #e2e8f0;
        gap: 20px;
        flex-wrap: wrap;
    }

    .stat-item-horizontal {
        text-align: center;
        flex: 1;
    }

    .stat-number-horizontal {
        font-size: 2rem;
        font-weight: 800;
        color: var(--color-primary);
        font-family: 'Space Grotesk', monospace;
        line-height: 1;
    }

    .stat-label-horizontal {
        font-size: 0.85rem;
        color: #64748b;
        margin-top: 8px;
        font-weight: 500;
    }

    .stat-divider {
        width: 1px;
        height: 40px;
        background: #e2e8f0;
    }

    @media (max-width: 768px) {
        .stats-horizontal {
            flex-direction: column;
            gap: 15px;
        }
        
        .stat-divider {
            width: 80%;
            height: 1px;
        }
        
        .stat-number-horizontal {
            font-size: 1.8rem;
        }
    }
    /* Modal - Fixed Width & Scroll */
    .modal-dialog.modal-xl {
        max-width: 900px;
    }

    @media (min-width: 1200px) {
        .modal-dialog.modal-xl {
            max-width: 900px;
        }
    }

    .modal-custom {
        background: white;
        border-radius: 24px;
        overflow: hidden;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    }

    .modal-header-custom {
        background: var(--gradient-primary);
        padding: 20px 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .modal-header-custom h3 {
        color: white;
        margin: 0;
        font-size: 1.25rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .modal-close {
        background: rgba(255, 255, 255, 0.2);
        border: none;
        border-radius: 50%;
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
        color: white;
    }

    .modal-close:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: rotate(90deg);
    }

    .modal-body-custom {
        padding: 25px;
        max-height: 70vh;
        overflow-y: auto;
        scroll-behavior: smooth;
    }

    /* Custom scrollbar for modal */
    .modal-body-custom::-webkit-scrollbar {
        width: 8px;
    }

    .modal-body-custom::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 10px;
    }

    .modal-body-custom::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 10px;
    }

    .modal-body-custom::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .modal-custom {
            width: 95%;
            margin: 20px auto;
        }
        
        .modal-body-custom {
            max-height: 60vh;
            padding: 20px;
        }
        
        .modal-header-custom h3 {
            font-size: 1.1rem;
        }
        
        .modal-close {
            width: 32px;
            height: 32px;
        }
    }
    .modal-close {
        background: rgba(255, 255, 255, 0.2);
        border: none;
        border-radius: 50%;
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
        color: white;
    }

    .modal-close:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: rotate(90deg);
    }

    /* Ensure modal backdrop is clickable */
    .modal-backdrop {
        cursor: pointer;
    }
</style>
@endsection

@section('content')
<div class="glass-card" data-aos="fade-down">
    <div class="gradient-header text-center">
        <h1 class="fw-bold">
            <i class="bi bi-terminal-fill me-3"></i>
            Command Storage
        </h1>
        <p class="lead mb-0">
            <i class="bi bi-database me-2"></i>
            Your Personal Developer Command Library
        </p>
    </div>

    <div class="p-4 p-md-5">
        <!-- Stats Cards -->
        <div class="stats-horizontal">
            <div class="stat-item-horizontal">
                <div class="stat-number-horizontal">{{ $commands->total() }}</div>
                <div class="stat-label-horizontal">Total Commands</div>
            </div>
            <div class="stat-divider"></div>
            <div class="stat-item-horizontal">
                <div class="stat-number-horizontal">{{ $categories->count() }}</div>
                <div class="stat-label-horizontal">Categories</div>
            </div>
            <div class="stat-divider"></div>
            <div class="stat-item-horizontal">
                <div class="stat-number-horizontal">{{ $favoriteCommands }}</div>
                <div class="stat-label-horizontal">Favorites</div>
            </div>
        </div>
        
        <!-- Search -->
        <form method="GET" action="{{ url('/command-storage') }}">
            <div class="search-wrapper">
                <input type="text" name="search" class="search-input-modern" 
                       placeholder="🔍 Search commands by name, description, or tags..." 
                       value="{{ $search }}">
                <input type="hidden" name="category" value="{{ $category }}">
                <input type="hidden" name="favorites" value="{{ $favorites ? 1 : 0 }}">
                <button type="submit" class="search-btn-modern">
                    <i class="bi bi-search"></i> Search
                </button>
            </div>
        </form>
        
        <!-- Categories -->
        <div class="categories-wrapper">
            <div class="categories-title">FILTER BY CATEGORY</div>
            <div class="category-chips">
                <div class="chip {{ $category == 'all' && !$favorites ? 'active' : '' }}" 
                     onclick="filterCategory('all')">📁 All</div>
                <div class="chip {{ $favorites ? 'active' : '' }}" 
                     onclick="filterFavorites()">
                    ⭐ Favorites
                </div>
                @foreach($categories as $cat)
                    <div class="chip {{ $category == $cat ? 'active' : '' }}" 
                         onclick="filterCategory('{{ $cat }}')">
                        {!! getCategoryIconHtml($cat) !!} {{ ucfirst($cat) }}
                    </div>
                @endforeach
            </div>
        </div>
        
        <!-- Commands Grid -->
        @if($commands->count() > 0)
            <div class="commands-grid">
                @foreach($commands as $command)
                    <div class="cmd-card">
                        <div class="cmd-card-header">
                            <div class="cmd-title">
                                <i class="bi bi-{{ $command->icon ?? 'terminal' }}"></i>
                                <span>{{ $command->name }}</span>
                            </div>
                            <button class="fav-btn {{ $command->is_favorite ? 'active' : '' }}" 
                                    onclick="toggleFavorite({{ $command->id }}, this)">
                                <i class="bi bi-star{{ $command->is_favorite ? '-fill' : '' }}"></i>
                            </button>
                        </div>
                        
                        <div class="cmd-card-body">
                            <!-- Terminal Block -->
                            <div class="terminal-block">
                                <div class="terminal-header">
                                    <span class="terminal-label">
                                        <i class="bi bi-terminal"></i> COMMAND
                                    </span>
                                    <button class="copy-btn-sm" onclick="copyCommand('{{ addslashes($command->command) }}')">
                                        <i class="bi bi-clipboard"></i> Copy
                                    </button>
                                </div>
                                <div class="terminal-body">
                                    <code>{{ Str::limit($command->command, 80) }}</code>
                                </div>
                            </div>
                            
                            <!-- Description -->
                            <div class="cmd-description">
                                {{ Str::limit($command->description, 100) }}
                            </div>
                            
                            <!-- Tags -->
                            <div class="cmd-tags">
                                <span class="tag">
                                    {!! getCategoryIconHtml($command->category) !!} {{ ucfirst($command->category) }}
                                </span>
                                @if($command->sub_category)
                                    <span class="tag">
                                        <i class="bi bi-folder"></i> {{ ucfirst($command->sub_category) }}
                                    </span>
                                @endif
                                <span class="tag tag-usage">
                                    <i class="bi bi-eye"></i> {{ $command->usage_count }}
                                </span>
                                <span class="tag tag-danger-{{ $command->danger_level ?? 'low' }}">
                                    <i class="bi bi-shield-exclamation"></i> {{ ucfirst($command->danger_level ?? 'Low') }}
                                </span>
                            </div>
                            
                            <!-- View Button -->
                            <button class="view-details-btn" onclick="showCommandDetails({{ $command->id }})">
                                <i class="bi bi-info-circle"></i> View Details
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
            
            <!-- Pagination -->
            <div class="pagination-wrapper">
                {{ $commands->appends(request()->query())->links('pagination::bootstrap-5') }}
            </div>
        @else
            <div class="empty-state">
                <i class="bi bi-search"></i>
                <h4>No commands found</h4>
                <p class="text-muted">Try different search terms or clear filters</p>
                <a href="{{ url('/command-storage') }}" class="search-btn-modern" style="display: inline-block; text-decoration: none;">Clear Filters</a>
            </div>
        @endif
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="commandModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="true" aria-labelledby="commandModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
        <div class="modal-content" style="border-radius: 24px; overflow: hidden; max-width: 900px; margin: 0 auto;">
            <div class="modal-header-custom" style="background: var(--gradient-primary); padding: 20px 25px; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="color: white; margin: 0; font-size: 1.25rem; font-weight: 600; display: flex; align-items: center; gap: 10px;">
                    <i class="bi bi-terminal-fill"></i>
                    Command Details
                </h3>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="filter: none; opacity: 1;"></button>
            </div>
            <div class="modal-body-custom" id="modalBody" style="padding: 25px; max-height: 70vh; overflow-y: auto;">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary"></div>
                    <p class="mt-3">Loading...</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const csrfToken = '{{ csrf_token() }}';

function filterCategory(category) {
    const url = new URL(window.location.href);
    url.searchParams.set('category', category);
    url.searchParams.delete('favorites');
    window.location.href = url.toString();
}

function filterFavorites() {
    const url = new URL(window.location.href);
    url.searchParams.set('favorites', '1');
    url.searchParams.delete('category');
    window.location.href = url.toString();
}

async function toggleFavorite(id, btn) {
    try {
        const response = await fetch(`/command-storage/${id}/favorite`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            const icon = btn.querySelector('i');
            if (data.is_favorite) {
                icon.classList.remove('bi-star');
                icon.classList.add('bi-star-fill');
                btn.classList.add('active');
                showToast('Added to favorites', 'success');
            } else {
                icon.classList.remove('bi-star-fill');
                icon.classList.add('bi-star');
                btn.classList.remove('active');
                showToast('Removed from favorites', 'info');
            }
        }
    } catch (error) {
        showToast('Error toggling favorite', 'error');
    }
}

async function showCommandDetails(id) {
    const modal = new bootstrap.Modal(document.getElementById('commandModal'));
    const modalBody = document.getElementById('modalBody');
    
    modalBody.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary"></div>
            <p class="mt-3">Loading command details...</p>
        </div>
    `;
    
    modal.show();
    
    try {
        const response = await fetch(`/command-storage/${id}`);
        const data = await response.json();
        
        if (data.success) {
            const cmd = data.command;
            let alternateCommands = [];
            try {
                alternateCommands = cmd.alternate_commands ? JSON.parse(cmd.alternate_commands) : [];
            } catch(e) {
                alternateCommands = [];
            }
            
            let html = `
                <div style="margin-bottom: 24px;">
                    <h4 style="font-size: 1.25rem; font-weight: 700; margin: 0; color: var(--color-dark);">
                        <i class="bi bi-${cmd.icon || 'terminal'}" style="color: var(--color-primary); margin-right: 10px;"></i>
                        ${escapeHtml(cmd.name)}
                    </h4>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px;">
                        <span class="tag"><i class="bi bi-tag"></i> ${escapeHtml(cmd.category)}</span>
                        ${cmd.sub_category ? `<span class="tag"><i class="bi bi-folder"></i> ${escapeHtml(cmd.sub_category)}</span>` : ''}
                        <span class="tag tag-usage"><i class="bi bi-eye"></i> ${cmd.usage_count} uses</span>
                        <span class="tag tag-danger-${cmd.danger_level || 'low'}">
                            <i class="bi bi-shield-exclamation"></i> ${(cmd.danger_level || 'LOW').toUpperCase()} RISK
                        </span>
                    </div>
                </div>
                
                <div style="margin-bottom: 24px;">
                    <div class="terminal-block">
                        <div class="terminal-header">
                            <span class="terminal-label"><i class="bi bi-terminal"></i> COMMAND</span>
                            <button class="copy-btn-sm" onclick="copyCommand('${escapeHtml(cmd.command).replace(/'/g, "\\'")}')">
                                <i class="bi bi-clipboard"></i> Copy
                            </button>
                        </div>
                        <div class="terminal-body">
                            <code>${escapeHtml(cmd.command)}</code>
                        </div>
                    </div>
                </div>
                
                <div style="margin-bottom: 24px;">
                    <div style="font-weight: 600; margin-bottom: 8px; color: var(--color-dark);">
                        <i class="bi bi-info-circle" style="color: var(--color-primary);"></i> Description
                    </div>
                    <p style="line-height: 1.6; color: #334155; margin: 0;">${escapeHtml(cmd.description)}</p>
                </div>
            `;
            
            if (alternateCommands.length > 0) {
                html += `
                    <div style="margin-bottom: 24px;">
                        <div style="font-weight: 600; margin-bottom: 8px; color: var(--color-dark);">
                            <i class="bi bi-arrow-repeat" style="color: var(--color-primary);"></i> Alternate Commands
                        </div>
                        <div style="background: #f8fafc; border-radius: 12px; padding: 12px;">
                            ${alternateCommands.map(alt => `
                                <div style="display: flex; justify-content: space-between; align-items: center; background: white; padding: 10px 12px; border-radius: 8px; margin-bottom: 8px; border: 1px solid #e2e8f0;">
                                    <code style="font-size: 0.8rem; flex: 1; word-break: break-all;">${escapeHtml(alt)}</code>
                                    <button class="copy-btn-sm" style="background: var(--color-primary);" onclick="copyCommand('${escapeHtml(alt).replace(/'/g, "\\'")}')">
                                        <i class="bi bi-clipboard"></i> Copy
                                    </button>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
            }
            
            if (cmd.example_usage && cmd.example_usage !== 'null' && cmd.example_usage !== '') {
                html += `
                    <div style="margin-bottom: 24px;">
                        <div style="font-weight: 600; margin-bottom: 8px; color: var(--color-dark);">
                            <i class="bi bi-lightbulb" style="color: var(--color-primary);"></i> Example Usage
                        </div>
                        <div class="terminal-block">
                            <div class="terminal-header">
                                <span class="terminal-label">bash</span>
                                <button class="copy-btn-sm" onclick="copyCommand('${escapeHtml(cmd.example_usage).replace(/'/g, "\\'")}')">
                                    <i class="bi bi-clipboard"></i> Copy
                                </button>
                            </div>
                            <div class="terminal-body">
                                <code>${escapeHtml(cmd.example_usage)}</code>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            if (cmd.notes && cmd.notes !== 'null' && cmd.notes !== '') {
                html += `
                    <div style="margin-bottom: 24px;">
                        <div style="font-weight: 600; margin-bottom: 8px; color: var(--color-dark);">
                            <i class="bi bi-pin-angle" style="color: var(--color-primary);"></i> Notes & Tips
                        </div>
                        <div style="background: #e0e7ff; color: #3730a3; padding: 14px 16px; border-radius: 12px; border-left: 4px solid var(--color-primary);">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            ${escapeHtml(cmd.notes)}
                        </div>
                    </div>
                `;
            }
            
            modalBody.innerHTML = html;
            
            await fetch(`/command-storage/${id}/increment`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                }
            });
        }
    } catch (error) {
        modalBody.innerHTML = `
            <div class="text-center py-5 text-danger">
                <i class="bi bi-exclamation-triangle-fill fs-1"></i>
                <h5 class="mt-3">Error Loading Command</h5>
                <p>${error.message}</p>
            </div>
        `;
    }
}

function copyCommand(command) {
    navigator.clipboard.writeText(command).then(() => {
        showToast('✓ Command copied to clipboard!', 'success');
    }).catch(() => {
        showToast('Failed to copy', 'error');
    });
}

function showToast(message, type) {
    const toast = document.createElement('div');
    toast.className = `toast-notification ${type}`;
    toast.innerHTML = `<i class="bi ${type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'} me-2"></i> ${message}`;
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

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
// Ensure modal scroll works properly
// Fix modal close button
document.addEventListener('DOMContentLoaded', function() {
    // Method 1: Using Bootstrap's built-in close
    const closeButtons = document.querySelectorAll('[data-bs-dismiss="modal"]');
    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const modal = bootstrap.Modal.getInstance(document.getElementById('commandModal'));
            if (modal) {
                modal.hide();
            }
        });
    });
    
    // Method 2: Manual close button handler
    const modalCloseBtn = document.querySelector('.modal-close');
    if (modalCloseBtn) {
        modalCloseBtn.addEventListener('click', function() {
            const modalElement = document.getElementById('commandModal');
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) {
                modal.hide();
            } else {
                // Fallback
                const modalInstance = new bootstrap.Modal(modalElement);
                modalInstance.hide();
            }
        });
    }
    
    // Close on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modalElement = document.getElementById('commandModal');
            if (modalElement && modalElement.classList.contains('show')) {
                const modal = bootstrap.Modal.getInstance(modalElement);
                if (modal) {
                    modal.hide();
                }
            }
        }
    });
});
</script>
@endsection