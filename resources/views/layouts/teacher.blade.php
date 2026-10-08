<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>@yield('title', 'Teacher Dashboard') · School Management</title>@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="teacher-dashboard-page">
<a href="#main-content" class="skip-link">Skip to content</a>
<div class="app-shell">
    <button class="drawer-backdrop" aria-label="Close navigation" tabindex="-1" hidden></button>
    @include('teacher.partials.sidebar')
    <div class="workspace min-w-0">
        @include('teacher.partials.topbar')
        <main id="main-content" class="p-4 lg:p-5" tabindex="-1">
            @if(session('success'))<div class="panel student-form" role="status">{{ session('success') }}</div>@endif
            @if($errors->any())<div class="panel student-form" role="alert"><strong>Please check the following:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('content')
            <footer class="page-footer"><span>School Management System · Teacher Panel</span><span>Learning today. Leading tomorrow.</span></footer>
        </main>
    </div>
</div><div id="ui-message" class="toast" role="status" hidden></div>
</body></html>
