<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') &middot; School Management</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if(request()->routeIs('accounts.*'))
        @vite('resources/css/accounts.css')
    @endif
</head>
<body class="admin-dashboard-shell">
    <a href="#main-content" class="skip-link">Skip to content</a>
    <div class="app-shell">
        <button class="drawer-backdrop" aria-label="Close navigation" tabindex="-1" hidden></button>
        <x-sidebar />
        <div class="workspace min-w-0">
            <x-topbar />
            <main id="main-content" @class(['p-4 lg:p-5', 'admin-dashboard' => request()->routeIs('dashboard', 'dashboard.home'), 'admin-report-screen' => request()->routeIs('attendance.reports.*', 'fees.reports.*', 'fees.students.history', 'exams.results.*', 'teachers.payroll.*'), 'report-tone-teal' => request()->routeIs('attendance.reports.*'), 'report-tone-blue' => request()->routeIs('fees.reports.*', 'fees.students.history'), 'report-tone-purple' => request()->routeIs('exams.results.*'), 'report-tone-amber' => request()->routeIs('teachers.payroll.*')]) tabindex="-1">
                @if(request()->routeIs('accounts.*'))<div class="accounts-page">@endif
                @yield('content')
                @if(request()->routeIs('accounts.*'))</div>@endif
                <footer class="page-footer"><span>School Management System</span><span>Built for a better school day.</span></footer>
            </main>
        </div>
    </div>
    <div id="ui-message" class="toast" role="status" hidden></div>
</body>
</html>
