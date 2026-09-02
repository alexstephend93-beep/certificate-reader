<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Certificate Tools')</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <!-- Select2 CSS for rich searchable dropdowns -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

    <style>
        @php
        $primaryHex = $theme['primary'] ?? '#0d6efd';
        $primaryHex = ltrim($primaryHex, '#');
        if (strlen($primaryHex) === 3) {
            $r = hexdec(str_repeat($primaryHex[0], 2));
            $g = hexdec(str_repeat($primaryHex[1], 2));
            $b = hexdec(str_repeat($primaryHex[2], 2));
        } else {
            $r = hexdec(substr($primaryHex, 0, 2));
            $g = hexdec(substr($primaryHex, 2, 2));
            $b = hexdec(substr($primaryHex, 4, 2));
        }
        @endphp
        :root {
            --gradient-primary: {{ $theme['gradient'] }};
            --color-primary: {{ $theme['primary'] }};
            --color-secondary: {{ $theme['secondary'] }};
            --color-accent: {{ $theme['accent'] }};
            --color-dark: {{ $theme['dark'] }};
            --color-light: {{ $theme['light'] }};
            --font-primary: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            --font-display: 'Space Grotesk', 'Inter', sans-serif;
            --transition-smooth: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --sidebar-width: 280px;
            --color-primary-rgb: {{ $r }}, {{ $g }}, {{ $b }};
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        /* CRITICAL: Fix for white flickering on scroll */
        html {
            height: 100%;
            overflow-y: scroll;
            overflow-x: hidden;
            -webkit-overflow-scrolling: touch;
        }
        
        body { 
             font-family: var(--font-primary); 
             min-height: 100vh; 
             transition: background 0.5s ease; 
             position: relative; 
             overflow-x: hidden; 
             line-height: 1.6; 
             -webkit-font-smoothing: antialiased; 
             display: flex;
             background: var(--gradient-primary);
          background-attachment: fixed;
         }
         
         /* Fixed background layer - prevents white flash */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--gradient-primary);
            z-index: -2;
            pointer-events: none;
        }
        
        /* Second layer for smoothness */
        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 50% 50%, rgba(255,255,255,0.06) 0%, transparent 100%);
            z-index: -1;
            pointer-events: none;
        }
        
        /* Particles Background */
        .particles { 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            z-index: -1; 
            overflow: hidden; 
            pointer-events: none; 
        }
        .particle { 
            position: absolute; 
            background: rgba(255, 255, 255, 0.1); 
            border-radius: 50%; 
            animation: float 20s infinite; 
            pointer-events: none; 
        }
        @keyframes float { 
            0%, 100% { transform: translateY(0) rotate(0deg) scale(1); } 
            25% { transform: translateY(-20px) rotate(90deg) scale(1.1); } 
            50% { transform: translateY(0) rotate(180deg) scale(1); } 
            75% { transform: translateY(20px) rotate(270deg) scale(0.9); } 
        }
        
        /* Sidebar Styling */
        .sidebar { 
            width: var(--sidebar-width); 
            height: 100vh; 
            position: fixed; 
            left: 0; 
            top: 0; 
            background: rgba(255, 255, 255, 0.1); 
            backdrop-filter: blur(20px); 
            -webkit-backdrop-filter: blur(20px); 
            border-right: 1px solid rgba(255, 255, 255, 0.2); 
            z-index: 1000; 
            display: flex; 
            flex-direction: column; 
            transition: transform 0.3s ease;
            overflow-y: auto;
            overflow-x: hidden;
        }
        
        /* Custom scrollbar for sidebar */
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-track { background: transparent; }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 4px; }
        
        .sidebar-header { 
            padding: 30px 20px; 
            text-align: center; 
            border-bottom: 1px solid rgba(255, 255, 255, 0.1); 
            flex-shrink: 0;
        }
        .sidebar-header h2 { 
            font-family: var(--font-display); 
            color: white; 
            font-weight: 800; 
            font-size: 1.5rem; 
            margin: 0; 
        }
        .sidebar-nav { 
            padding: 20px 0; 
            flex: 1;
        }
        .nav-item { 
            margin: 5px 15px; 
        }
        .nav-link { 
            display: flex; 
            align-items: center; 
            gap: 15px; 
            padding: 12px 20px; 
            color: rgba(255, 255, 255, 0.8); 
            text-decoration: none; 
            border-radius: 15px; 
            font-weight: 500; 
            transition: var(--transition-smooth); 
        }
        .nav-link:hover, .nav-link.active { 
            background: rgba(255, 255, 255, 0.2); 
            color: white; 
            transform: translateX(5px); 
            box-shadow: 0 4px 15px rgba(0,0,0,0.1); 
        }
        .nav-link i { font-size: 1.25rem; }
        .nav-link.active i { 
            color: var(--color-accent); 
            text-shadow: 0 0 10px rgba(255,255,255,0.5); 
        }
        
        /* Main Content Layout */
        .main-wrapper { 
            flex-grow: 1; 
            margin-left: var(--sidebar-width); 
            padding: 20px; 
            min-height: 100vh; 
            display: flex; 
            flex-direction: column; 
            transition: margin-left 0.3s ease; 
        }
        .container-custom { 
            max-width: 1400px; 
            margin: 0 auto; 
            width: 100%; 
        }
        
        /* Theme Switcher in Layout */
        .theme-switcher { 
            display: flex; 
            gap: 10px; 
            flex-wrap: wrap; 
            justify-content: center; 
            padding: 20px; 
            border-top: 1px solid rgba(255, 255, 255, 0.1); 
            background: rgba(0,0,0,0.1); 
            flex-shrink: 0;
        }
        .theme-option { 
            width: 35px; 
            height: 35px; 
            border-radius: 50%; 
            border: 2px solid rgba(255, 255, 255, 0.5); 
            cursor: pointer; 
            transition: var(--transition-smooth); 
        }
        .theme-option:hover { 
            transform: scale(1.2); 
            border-color: white; 
            box-shadow: 0 0 15px currentColor; 
        }
        .theme-option.active { 
            border-color: white; 
            transform: scale(1.1); 
            box-shadow: 0 0 20px currentColor; 
        }

        /* Mobile Controls */
        .mobile-toggle { 
            position: fixed; 
            top: 15px; 
            left: 15px; 
            z-index: 1001; 
            background: rgba(255,255,255,0.2); 
            border: none; 
            font-size: 1.5rem; 
            color: white; 
            padding: 10px 15px; 
            border-radius: 12px; 
            backdrop-filter: blur(10px); 
            display: none; 
            cursor: pointer; 
        }

        /* Shared Component Styles */
        .glass-card { 
            background: rgba(255, 255, 255, 0.95); 
            backdrop-filter: blur(20px); 
            border-radius: 40px; 
            box-shadow: 0 30px 60px -15px rgba(0, 0, 0, 0.3); 
            border: 1px solid rgba(255, 255, 255, 0.5); 
            transition: var(--transition-smooth); 
            overflow: hidden; 
            width: 100%; 
        }
        .gradient-header { 
            background: var(--gradient-primary); 
            color: white; 
            padding: 40px 30px; 
            position: relative; 
            overflow: hidden; 
        }
        .gradient-header h1 { 
            font-family: var(--font-display); 
            font-size: clamp(2rem, 5vw, 3.5rem); 
            font-weight: 800; 
            letter-spacing: -0.02em; 
            line-height: 1.2; 
            margin-bottom: 15px; 
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2); 
            animation: slideInDown 0.8s ease; 
        }
        .gradient-header p { 
            font-size: clamp(1rem, 2vw, 1.25rem); 
            opacity: 0.95; 
            font-weight: 400; 
            letter-spacing: -0.01em; 
        }
        .footer { 
            padding: 25px; 
            text-align: center; 
            color: rgba(255, 255, 255, 0.8); 
            font-size: 0.9rem; 
            margin-top: auto; 
        }

        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); width: 260px; }
            .sidebar.show { transform: translateX(0); box-shadow: 0 0 50px rgba(0,0,0,0.5); }
            .main-wrapper { margin-left: 0; padding-top: 70px; }
            .mobile-toggle { display: block; }
        }

        /* Animations */
        @keyframes slideInDown { 
            from { transform: translateY(-30px); opacity: 0; } 
            to { transform: translateY(0); opacity: 1; } 
        }
        @keyframes fadeInUp { 
            from { transform: translateY(30px); opacity: 0; } 
            to { transform: translateY(0); opacity: 1; } 
        }
        
        .top-bar-icons {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .top-icon {
            color: white;
            transition: all 0.3s ease;
        }

        .top-icon:hover {
            transform: translateY(-2px);
            color: var(--color-accent);
        }

        .text-accent {
            color: var(--color-accent) !important;
        }
</style>
    @yield('styles')
</head>
<body>
    <div class="particles" id="particles"></div>

    <button class="mobile-toggle" onclick="document.getElementById('sidebar').classList.toggle('show')">
        <i class="bi bi-list"></i>
    </button>

    <aside class="sidebar" id="sidebar">
        <a href="{{ url('/dashboard') }}" class="sidebar-header" style="text-decoration: none;">
            <h2><i class="bi bi-shield-lock-fill me-2"></i>NetTools</h2>
        </a>
        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="{{ url('/dashboard') }}" class="nav-link {{ request()->is('dashboard') || request()->is('/') ? 'active' : '' }}">
                    <i class="bi bi-grid-1x2-fill"></i>
                    <span>Dashboard</span>
                </a>
                <a href="{{ url('/system-monitor') }}" class="nav-link {{ request()->is('system-monitor*') ? 'active' : '' }}">
                    <i class="bi bi-cpu-fill"></i>
                    <span>System Monitor</span>
                </a>
            </div>
            
            <!-- Security Tools Section -->
            <div class="nav-item">
                <div class="nav-section-title">Security Tools</div>
                <a href="{{ url('/certificate') }}" class="nav-link {{ request()->is('certificate*') ? 'active' : '' }}">
                    <i class="bi bi-file-earmark-lock-fill"></i>
                    <span>Certificate Reader</span>
                </a>
                <a href="{{ url('/ssl-matcher') }}" class="nav-link {{ request()->is('ssl-matcher*') ? 'active' : '' }}">
                    <i class="bi bi-shield-lock-fill"></i>
                    <span>SSL Matcher</span>
                </a>
                <a href="{{ url('/chain-validator') }}" class="nav-link {{ request()->is('chain-validator*') ? 'active' : '' }}">
                    <i class="bi bi-diagram-3-fill"></i>
                    <span>Chain Validator</span>
                </a>
                <a href="{{ url('/hash-toolbox') }}" class="nav-link {{ request()->is('hash-toolbox*') ? 'active' : '' }}">
                    <i class="bi bi-shield-lock-fill"></i>
                    <span>Hash & Encryption</span>
                </a>
                <a href="{{ url('/jwt') }}" class="nav-link {{ request()->is('jwt*') ? 'active' : '' }}">
                    <i class="bi bi-braces-asterisk"></i>
                    <span>JWT Analyzer</span>
                </a>
                <a href="{{ url('/hmac') }}" class="nav-link {{ request()->is('hmac*') ? 'active' : '' }}">
                    <i class="bi bi-pen-fill"></i>
                    <span>HMAC Signature</span>
                </a>
            </div>

            <!-- Development Tools Section -->
            <div class="nav-item">
                <div class="nav-section-title">Development Tools</div>
                <a href="{{ url('/api-tester') }}" class="nav-link {{ request()->is('api-tester*') ? 'active' : '' }}">
                    <i class="bi bi-globe2"></i>
                    <span>API Tester</span>
                </a>
                <a href="{{ url('/base64') }}" class="nav-link {{ request()->is('base64*') ? 'active' : '' }}">
                    <i class="bi bi-code-square"></i>
                    <span>Base64 Codec</span>
                </a>
                <a href="{{ url('/command-storage') }}" class="nav-link {{ request()->is('command-storage*') ? 'active' : '' }}">
                    <i class="bi bi-terminal-fill"></i>
                    <span>Command Storage</span>
                </a>
            </div>

            <!-- Server Management Section -->
            <div class="nav-item">
                <div class="nav-section-title">Server Management</div>
                <a href="{{ url('/ssh') }}" class="nav-link {{ request()->is('ssh*') ? 'active' : '' }}">
                    <i class="bi bi-server"></i>
                    <span>SSH Manager</span>
                </a>
                <!-- NEW: Database Manager -->
                <a href="{{ url('/database') }}" class="nav-link {{ request()->is('database*') ? 'active' : '' }}">
                    <i class="bi bi-database-fill-gear"></i>
                    <span>Database Manager</span>
                </a>
                <a href="{{ url('/admin-credentials') }}" class="nav-link {{ request()->is('admin-credentials*') ? 'active' : '' }}">
                    <i class="bi bi-key-fill"></i>
                    <span>Admin Credentials</span>
                </a>
            </div>
        </nav>
        
        <div class="theme-switcher">
            @foreach ($themes as $key => $themeOption)
            <div class="theme-option {{ $key === $currentTheme ? 'active' : '' }}"
                style="background: {{ $themeOption['gradient'] }};" onclick="changeTheme('{{ $key }}')"
                title="{{ $themeOption['name'] }}">
            </div>
            @endforeach
        </div>
    </aside>

    <main class="main-wrapper">
        <div class="container-custom py-4">
            @yield('content')
            
            <div class="footer mt-5">
                <p class="small mb-0"><i class="bi bi-shield-check me-2"></i> Network Security Tools Suite. No data is stored on our servers.</p>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ===== Global Modal Cleanup =====
        // Fixes "dim screen after closing modals" caused by lingering Bootstrap
        // backdrops that remain when a modal instance is re-created or its hide
        // transition is interrupted. This runs for ALL modals on ALL pages.
        (function () {
            function cleanupModalState() {
                var openModals = document.querySelectorAll('.modal.show').length;
                var backdrops = Array.prototype.slice.call(document.querySelectorAll('.modal-backdrop'));

                if (openModals === 0) {
                    // No modal open -> remove every lingering backdrop and restore body state
                    backdrops.forEach(function (b) { if (b.parentNode) b.parentNode.removeChild(b); });
                    document.body.classList.remove('modal-open');
                    document.body.style.overflow = '';
                    document.body.style.paddingRight = '';
                } else if (backdrops.length > openModals) {
                    // Modal(s) still open -> remove only the excess backdrops
                    var excess = backdrops.length - openModals;
                    for (var i = backdrops.length - 1; i >= 0 && excess > 0; i--) {
                        backdrops[i].parentNode && backdrops[i].parentNode.removeChild(backdrops[i]);
                        excess--;
                    }
                }
            }

            // Clean up immediately in case a backdrop is already stuck on page load
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', cleanupModalState);
            } else {
                cleanupModalState();
            }

            // Clean up after ANY modal in the app hides
            document.addEventListener('hidden.bs.modal', function () {
                // Wait a tick so Bootstrap's own hide sequence (incl. transition) completes
                setTimeout(cleanupModalState, 50);
            });

            // If a modal is shown, strip any duplicate backdrops Bootstrap may have doubled
            document.addEventListener('shown.bs.modal', cleanupModalState);
        })();
    </script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <!-- jQuery (required for Select2) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        AOS.init({ duration: 800, once: true, offset: 50, easing: 'ease-in-out' });
        
        function changeTheme(themeKey) { 
            window.location.href = '{{ url("/theme") }}/' + themeKey; 
        }

        // Particle System
        function createParticles() {
            const particlesContainer = document.getElementById('particles');
            const particleCount = 20;
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                const size = Math.random() * 100 + 50;
                particle.style.width = size + 'px';
                particle.style.height = size + 'px';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.top = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 5 + 's';
                particle.style.animationDuration = Math.random() * 10 + 15 + 's';
                particlesContainer.appendChild(particle);
            }
        }
        window.addEventListener('load', createParticles);
        
        // Close sidebar on mobile when clicking outside
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.mobile-toggle');
            if (window.innerWidth <= 992) {
                if (!sidebar.contains(event.target) && !toggle.contains(event.target) && sidebar.classList.contains('show')) {
                    sidebar.classList.remove('show');
                }
            }
        });
    </script>
    @yield('scripts')
    @include('components.ai-chat-widget')

    <!-- Electron Event Fix -->
    @if(env('APP_ENV') !== 'production')
    <script>
        // Fix for Electron click events
        document.addEventListener('DOMContentLoaded', function() {
            // Fix for all click events
            document.addEventListener('click', function(e) {
                // Allow all click events to propagate
                console.log('Click event allowed in Electron');
                
                // If it's a button or link, ensure it works
                if (e.target.tagName === 'BUTTON' || e.target.tagName === 'A') {
                    e.target.click();
                }
            }, true);
            
            // Fix for form submissions
            document.addEventListener('submit', function(e) {
                console.log('Form submission allowed in Electron');
                // Allow form to submit normally
            }, true);
            
            // Fix for Laravel Livewire/Alpine.js if used
            if (typeof Livewire !== 'undefined') {
                Livewire.on('click', function() {
                    console.log('Livewire click event');
                });
            }
            
            console.log('✅ Electron event fixes applied');
        });
    </script>
    @endif
</body>
</html>
