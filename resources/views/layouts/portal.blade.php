<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Student Portal') &middot; {{ config('school.name', 'School Management') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/portal.css', 'resources/js/portal.js'])
</head>
<body class="portal-body">
    <div class="portal-shell">
        <!-- Desktop Sidebar -->
        <aside class="portal-sidebar" aria-label="Portal Navigation">
            <a href="{{ route('portal.home') }}" class="portal-brand">
                <div class="portal-brand-icon">
                    <x-icon name="school" size="20" />
                </div>
                <div class="portal-brand-text">
                    <span class="portal-brand-title">{{ config('school.name', 'School Portal') }}</span>
                    <span class="portal-brand-subtitle">{{ ($portalRole ?? (auth()->user()?->hasRole('Parent') ? 'parent' : 'student')) === 'parent' ? 'Parent Portal' : 'Student Portal' }}</span>
                </div>
            </a>

            <nav class="portal-nav">
                <span class="portal-nav-section-title">Academics & Daily</span>

                <a href="{{ route('portal.home') }}" class="portal-nav-link {{ request()->routeIs('portal.home') ? 'active' : '' }}">
                    <x-icon name="dashboard" size="18" />
                    <span>Dashboard</span>
                </a>

                <a href="{{ route('portal.attendance') }}" class="portal-nav-link {{ request()->routeIs('portal.attendance') ? 'active' : '' }}">
                    <x-icon name="calendar" size="18" />
                    <span>My Attendance</span>
                </a>

                <a href="{{ route('portal.performance') }}" class="portal-nav-link {{ request()->routeIs('portal.performance') ? 'active' : '' }}">
                    <x-icon name="chart" size="18" />
                    <span>My Performance</span>
                </a>

                <a href="{{ route('portal.quizzes') }}" class="portal-nav-link {{ request()->routeIs('portal.quizzes') ? 'active' : '' }}">
                    <x-icon name="clipboard" size="18" />
                    <span>Online Quizzes</span>
                </a>

                <a href="{{ route('portal.results') }}" class="portal-nav-link {{ request()->routeIs('portal.results') ? 'active' : '' }}">
                    <x-icon name="award" size="18" />
                    <span>Results</span>
                </a>

                <a href="{{ route('portal.study-material') }}" class="portal-nav-link {{ request()->routeIs('portal.study-material') ? 'active' : '' }}">
                    <x-icon name="book" size="18" />
                    <span>Study Material</span>
                </a>

                <span class="portal-nav-section-title">Finance & Utilities</span>

                <a href="{{ route('portal.fees') }}" class="portal-nav-link {{ request()->routeIs('portal.fees') ? 'active' : '' }}">
                    <x-icon name="wallet" size="18" />
                    <span>Fees & Billing</span>
                </a>

                <a href="{{ route('portal.timetable') }}" class="portal-nav-link {{ request()->routeIs('portal.timetable') ? 'active' : '' }}">
                    <x-icon name="clock" size="18" />
                    <span>Timetable</span>
                </a>

                <a href="{{ route('portal.library') }}" class="portal-nav-link {{ request()->routeIs('portal.library') ? 'active' : '' }}">
                    <x-icon name="classes" size="18" />
                    <span>Library</span>
                </a>

                <span class="portal-nav-section-title">Account</span>

                <a href="{{ route('portal.profile') }}" class="portal-nav-link {{ request()->routeIs('portal.profile') ? 'active' : '' }}">
                    <x-icon name="user" size="18" />
                    <span>My Profile</span>
                </a>
            </nav>
        </aside>

        <!-- Main Workspace -->
        <div class="portal-workspace">
            <!-- Topbar (Shared Admin Dashboard Header Design) -->
            <x-topbar />

            <!-- Main Content Area -->
            <main class="portal-content">
                @if(session('status'))
                    <div class="portal-toast-container">
                        <x-portal.toast type="success" :message="session('status')" />
                    </div>
                @endif

                @if($errors->any())
                    <div class="portal-toast-container">
                        <x-portal.toast type="danger" :message="$errors->first()" />
                    </div>
                @endif

                @yield('content')
            </main>
        </div>

        <!-- Mobile Bottom Navigation -->
        <nav class="portal-bottom-nav" aria-label="Mobile Navigation">
            <a href="{{ route('portal.home') }}" class="portal-bottom-link {{ request()->routeIs('portal.home') ? 'active' : '' }}">
                <x-icon name="dashboard" size="20" />
                <span>Home</span>
            </a>

            <a href="{{ route('portal.attendance') }}" class="portal-bottom-link {{ request()->routeIs('portal.attendance') ? 'active' : '' }}">
                <x-icon name="calendar" size="20" />
                <span>Attendance</span>
            </a>

            <a href="{{ route('portal.quizzes') }}" class="portal-bottom-link {{ request()->routeIs('portal.quizzes') ? 'active' : '' }}">
                <x-icon name="clipboard" size="20" />
                <span>Quiz</span>
            </a>

            <a href="{{ route('portal.results') }}" class="portal-bottom-link {{ request()->routeIs('portal.results') ? 'active' : '' }}">
                <x-icon name="award" size="20" />
                <span>Results</span>
            </a>

            <a href="#more" id="portal-mobile-more-btn" class="portal-bottom-link">
                <x-icon name="dots" size="20" />
                <span>More</span>
            </a>
        </nav>

        <!-- Mobile "More" Drawer Sheet -->
        <div id="portal-more-sheet" class="portal-more-sheet">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h4 style="margin: 0; font-size: 15px; font-weight: 700;">More Services</h4>
                <button type="button" onclick="document.getElementById('portal-more-sheet').classList.remove('open')" style="background:none;border:none;font-size:18px;cursor:pointer;">&times;</button>
            </div>
            <div class="portal-more-grid">
                <a href="{{ route('portal.performance') }}" class="portal-more-card">
                    <x-icon name="chart" size="22" />
                    <span>Performance</span>
                </a>
                <a href="{{ route('portal.study-material') }}" class="portal-more-card">
                    <x-icon name="book" size="22" />
                    <span>Study Material</span>
                </a>
                <a href="{{ route('portal.fees') }}" class="portal-more-card">
                    <x-icon name="wallet" size="22" />
                    <span>Fees</span>
                </a>
                <a href="{{ route('portal.timetable') }}" class="portal-more-card">
                    <x-icon name="clock" size="22" />
                    <span>Timetable</span>
                </a>
                <a href="{{ route('portal.library') }}" class="portal-more-card">
                    <x-icon name="classes" size="22" />
                    <span>Library</span>
                </a>
                <a href="{{ route('portal.profile') }}" class="portal-more-card">
                    <x-icon name="user" size="22" />
                    <span>Profile</span>
                </a>
            </div>
        </div>
    </div>
</body>
</html>
