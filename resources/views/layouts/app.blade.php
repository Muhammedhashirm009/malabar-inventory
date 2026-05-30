<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — {{ config('settings.shop_name', 'Malabar Inventory') }}</title>

    {{-- Local Fonts --}}
    <link rel="stylesheet" href="{{ asset('fonts/fonts.css') }}">

    {{-- App Stylesheet --}}
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">

    {{-- Alpine.js --}}
    <script defer src="{{ asset('js/alpine.min.js') }}"></script>

    {{-- Chart.js --}}
    <script src="{{ asset('js/chart.js') }}"></script>

    {{-- Lucide Icons --}}
    <script src="{{ asset('js/lucide.min.js') }}"></script>

    @stack('styles')
    <style>
        .app-layout {
            transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.25s ease, filter 0.25s ease;
            transform-origin: center center;
        }
        .app-layout.minimizing {
            transform: scale(0.94) translateY(20px);
            opacity: 0;
            filter: blur(3px);
        }
        .win-btn {
            background: transparent;
            border: none;
            cursor: pointer;
            color: var(--text-muted, #94a3b8);
            padding: 8px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        .win-btn:hover {
            background: rgba(255, 255, 255, 0.08);
            color: var(--text, #f8fafc);
        }
        .win-btn-close:hover {
            background: #ef4444 !important;
            color: #ffffff !important;
        }
        .win-btn i {
            width: 16px;
            height: 16px;
        }
    </style>
</head>
<body>
    <div class="app-layout">
        {{-- ==================== SIDEBAR ==================== --}}
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header" style="display: flex; align-items: center; gap: 12px; padding: 18px 20px;">
                <img src="{{ asset('brand_logo.png') }}" alt="Logo" style="width: 36px; height: 36px; border-radius: 9px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.25); flex-shrink: 0;">
                <h1 class="logo" style="display: flex; flex-direction: column; align-items: flex-start; gap: 0; font-size: 1.15rem; letter-spacing: -0.01em;">
                    <span style="display: flex; align-items: center; gap: 4px; background: none; -webkit-text-fill-color: var(--text-primary); background-clip: initial;">
                        Lamya <span style="background: var(--gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">Pro</span>
                    </span>
                    <span style="font-size: 0.58rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1.4px; color: var(--text-muted); background: none; -webkit-text-fill-color: var(--text-muted); background-clip: initial; margin-top: 1px;">by ERPAGENT</span>
                </h1>
            </div>

            <nav class="sidebar-nav">
                {{-- Main Navigation --}}
                <a href="/" class="nav-item {{ request()->is('/') ? 'active' : '' }}">
                    <i data-lucide="layout-dashboard"></i>
                    <span>Dashboard</span>
                </a>

                <a href="/products" class="nav-item {{ request()->is('products*') ? 'active' : '' }}">
                    <i data-lucide="package"></i>
                    <span>Products</span>
                </a>

                <a href="/suppliers" class="nav-item {{ request()->is('suppliers*') ? 'active' : '' }}">
                    <i data-lucide="factory"></i>
                    <span>Suppliers</span>
                </a>

                <a href="/purchases" class="nav-item {{ request()->is('purchases*') ? 'active' : '' }}">
                    <i data-lucide="shopping-cart"></i>
                    <span>Purchases</span>
                </a>

                <a href="/inventory" class="nav-item {{ request()->is('inventory*') ? 'active' : '' }}">
                    <i data-lucide="warehouse"></i>
                    <span>Inventory</span>
                </a>

                <a href="/customers" class="nav-item {{ request()->is('customers*') ? 'active' : '' }}">
                    <i data-lucide="users"></i>
                    <span>Customers</span>
                </a>

                <a href="/sales" class="nav-item {{ request()->is('sales*') ? 'active' : '' }}">
                    <i data-lucide="indian-rupee"></i>
                    <span>Sales</span>
                </a>

                <div class="nav-divider"></div>
                <div class="nav-section-title">Returns & Payments</div>

                <a href="/purchase-returns" class="nav-item {{ request()->is('purchase-returns*') ? 'active' : '' }}">
                    <i data-lucide="undo-2"></i>
                    <span>Purchase Returns</span>
                </a>

                <a href="/sale-returns" class="nav-item {{ request()->is('sale-returns*') ? 'active' : '' }}">
                    <i data-lucide="rotate-ccw"></i>
                    <span>Sale Returns</span>
                </a>

                <a href="/payments" class="nav-item {{ request()->is('payments*') ? 'active' : '' }}">
                    <i data-lucide="credit-card"></i>
                    <span>Payments Received</span>
                </a>

                <a href="/supplier-payments" class="nav-item {{ request()->is('supplier-payments*') ? 'active' : '' }}">
                    <i data-lucide="wallet"></i>
                    <span>Supplier Payments</span>
                </a>

                <div class="nav-divider"></div>
                <div class="nav-section-title">Reports</div>

                <a href="/reports/margin" class="nav-item {{ request()->is('reports/margin*') ? 'active' : '' }}">
                    <i data-lucide="trending-up"></i>
                    <span>Margin Report</span>
                </a>

                <a href="/reports/statements" class="nav-item {{ request()->is('reports/statements*') ? 'active' : '' }}">
                    <i data-lucide="file-text"></i>
                    <span>Statements</span>
                </a>

                <div class="nav-divider"></div>
                <a href="/settings" class="nav-item {{ request()->is('settings*') ? 'active' : '' }}">
                    <i data-lucide="settings"></i>
                    <span>Settings</span>
                </a>
            </nav>
        </aside>

        {{-- ==================== MAIN CONTENT ==================== --}}
        <main class="main-content">
            {{-- Topbar --}}
            <header class="topbar">
                <button class="sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">
                    <i data-lucide="menu"></i>
                </button>
                <div class="topbar-title">
                    <h2>@yield('title', 'Dashboard')</h2>
                </div>
                <div class="topbar-right">
                    <div class="ist-clock-widget">
                        <div class="ist-time-group">
                            <i data-lucide="clock" class="clock-icon"></i>
                            <span id="ist-time" class="ist-time">{{ now()->setTimezone('Asia/Kolkata')->format('h:i:s') }}</span>
                            <span id="ist-ampm" class="ist-ampm">{{ now()->setTimezone('Asia/Kolkata')->format('A') }}</span>
                        </div>
                        <span id="ist-date" class="ist-date">{{ now()->setTimezone('Asia/Kolkata')->format('D, d M Y') }}</span>
                    </div>

                    <!-- Window Controls (Only visible in Tauri Desktop App) -->
                    <div id="desktop-window-controls" style="display: none; align-items: center; gap: 8px; margin-left: 16px; border-left: 1px solid var(--border); padding-left: 16px;">
                        <button onclick="window.minimizeWithAnimation()" class="win-btn" title="Minimize">
                            <i data-lucide="minus"></i>
                        </button>
                        <button onclick="window.__TAURI__.core.invoke('close_window')" class="win-btn win-btn-close" title="Exit App">
                            <i data-lucide="x"></i>
                        </button>
                    </div>
                </div>
            </header>

            {{-- Flash Messages --}}
            @if(session('success'))
                <div class="alert alert-success" x-data="{ show: true }" x-show="show" x-transition>
                    <i data-lucide="check-circle"></i>
                    <span>{{ session('success') }}</span>
                    <button class="alert-close" @click="show = false">
                        <i data-lucide="x"></i>
                    </button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger" x-data="{ show: true }" x-show="show" x-transition>
                    <i data-lucide="alert-circle"></i>
                    <span>{{ session('error') }}</span>
                    <button class="alert-close" @click="show = false">
                        <i data-lucide="x"></i>
                    </button>
                </div>
            @endif

            @if(session('warning'))
                <div class="alert alert-warning" x-data="{ show: true }" x-show="show" x-transition>
                    <i data-lucide="alert-triangle"></i>
                    <span>{{ session('warning') }}</span>
                    <button class="alert-close" @click="show = false">
                        <i data-lucide="x"></i>
                    </button>
                </div>
            @endif

            {{-- Page Content --}}
            <div class="content-area">
                @yield('content')
            </div>
        </main>
    </div>

    {{-- Initialize Lucide Icons & Realtime Clock --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            lucide.createIcons();
            
            if (window.__TAURI__) {
                const controls = document.getElementById('desktop-window-controls');
                if (controls) {
                    controls.style.display = 'flex';
                }
                
                // Animated minimize logic
                window.minimizeWithAnimation = function() {
                    const layout = document.querySelector('.app-layout');
                    if (layout) {
                        layout.classList.add('minimizing');
                        setTimeout(() => {
                            window.__TAURI__.core.invoke('minimize_window');
                        }, 220);
                    } else {
                        window.__TAURI__.core.invoke('minimize_window');
                    }
                };

                // Restore transition when window is reopened/focused
                window.addEventListener('focus', () => {
                    const layout = document.querySelector('.app-layout');
                    if (layout && layout.classList.contains('minimizing')) {
                        setTimeout(() => {
                            layout.classList.remove('minimizing');
                        }, 80);
                    }
                });
            }
            
            // IST Realtime Clock
            function updateISTClock() {
                const timeEl = document.getElementById('ist-time');
                const ampmEl = document.getElementById('ist-ampm');
                const dateEl = document.getElementById('ist-date');
                
                if (!timeEl || !dateEl) return;
                
                const now = new Date();
                
                // Format Time in IST (Asia/Kolkata)
                const timeString = now.toLocaleTimeString('en-US', {
                    timeZone: 'Asia/Kolkata',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true
                });
                
                const parts = timeString.split(' ');
                if (parts.length === 2) {
                    timeEl.textContent = parts[0];
                    ampmEl.textContent = parts[1];
                } else {
                    timeEl.textContent = timeString;
                    if (ampmEl) ampmEl.textContent = '';
                }
                
                // Format Date in IST
                const dateString = now.toLocaleDateString('en-GB', {
                    timeZone: 'Asia/Kolkata',
                    weekday: 'short',
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric'
                });
                
                dateEl.textContent = dateString;
            }
            
            // Run immediately and set interval
            updateISTClock();
            setInterval(updateISTClock, 1000);
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function (e) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.sidebar-toggle');
            if (sidebar.classList.contains('open') &&
                !sidebar.contains(e.target) &&
                !toggle.contains(e.target)) {
                sidebar.classList.remove('open');
            }
        });
    </script>

    <!-- Custom Quick Access Context Menu -->
    <div id="custom-context-menu" class="custom-context-menu" style="display: none; position: fixed; z-index: 9999;">
        <a href="/sales/create" class="context-menu-item">
            <i data-lucide="plus-circle" class="context-icon"></i>
            <span>New Sale</span>
        </a>
        <a href="/payments/create" class="context-menu-item">
            <i data-lucide="credit-card" class="context-icon"></i>
            <span>Record Payment</span>
        </a>
        <a href="/purchases/create" class="context-menu-item">
            <i data-lucide="shopping-cart" class="context-icon"></i>
            <span>New Purchase</span>
        </a>
        <div class="context-menu-divider"></div>
        <a href="/" class="context-menu-item">
            <i data-lucide="layout-dashboard" class="context-icon"></i>
            <span>Dashboard</span>
        </a>
    </div>

    <script>
        // Custom Context Menu Logic
        document.addEventListener('contextmenu', function (e) {
            // Prevent default browser context menu (Refresh, reload, inspect, etc.)
            e.preventDefault();
            
            const menu = document.getElementById('custom-context-menu');
            if (!menu) return;

            menu.style.display = 'block';
            
            // Adjust coordinates to prevent menu from going off screen
            const menuWidth = 220; 
            const menuHeight = 180; 
            let posX = e.clientX;
            let posY = e.clientY;

            if (posX + menuWidth > window.innerWidth) {
                posX = window.innerWidth - menuWidth - 10;
            }
            if (posY + menuHeight > window.innerHeight) {
                posY = window.innerHeight - menuHeight - 10;
            }

            menu.style.left = posX + 'px';
            menu.style.top = posY + 'px';
            
            // Create icons inside the context menu dynamically
            if (window.lucide) {
                lucide.createIcons();
            }
        });

        // Hide context menu on left click
        document.addEventListener('click', function (e) {
            const menu = document.getElementById('custom-context-menu');
            if (menu && !menu.contains(e.target)) {
                menu.style.display = 'none';
            }
        });

        // Hide context menu on escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                const menu = document.getElementById('custom-context-menu');
                if (menu) {
                    menu.style.display = 'none';
                }
            }
        });
    </script>

    @stack('scripts')
</body>
</html>
