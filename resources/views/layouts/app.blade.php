<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — {{ config('settings.shop_name', 'Malabar Inventory') }}</title>

    {{-- Google Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    {{-- App Stylesheet --}}
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">

    {{-- Alpine.js --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    {{-- Chart.js --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    {{-- Lucide Icons --}}
    <script src="https://unpkg.com/lucide@latest"></script>

    @stack('styles')
</head>
<body>
    <div class="app-layout">
        {{-- ==================== SIDEBAR ==================== --}}
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                @php
                    $logoName = config('settings.shop_name', 'Malabar Inventory');
                    $words = explode(' ', $logoName);
                    if (count($words) > 1) {
                        $lastWord = array_pop($words);
                        $firstPart = implode(' ', $words);
                        $logoHtml = $firstPart . '<span>' . $lastWord . '</span>';
                    } else {
                        $logoHtml = '<span>' . $logoName . '</span>';
                    }
                @endphp
                <h1 class="logo">{!! $logoHtml !!}</h1>
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

    @stack('scripts')
</body>
</html>
