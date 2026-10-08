<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>@yield('title', 'Login') · {{ config('school.name') }}</title>@vite(['resources/css/auth.css', 'resources/js/auth.js'])</head>
<body class="auth-page">
<main class="auth-shell">
    <section class="auth-welcome" style="background-image: linear-gradient(180deg, rgba(10,24,38,.45) 0%, rgba(10,24,38,.35) 45%, rgba(10,24,38,.68) 100%), url('{{ asset('images/auth-school-campus.jpg') }}');" aria-label="School welcome">
        <div class="auth-shape auth-shape-one" aria-hidden="true"></div><div class="auth-shape auth-shape-two" aria-hidden="true"></div><div class="auth-shape auth-shape-three" aria-hidden="true"></div>
        <svg class="auth-decoration" viewBox="0 0 700 360" preserveAspectRatio="xMidYMax slice" aria-hidden="true"><defs><linearGradient id="welcome-pill" x1="0" y1="1" x2="1" y2="0"><stop stop-color="#ffbd72"/><stop offset=".55" stop-color="#ff8499"/><stop offset="1" stop-color="#a675f4"/></linearGradient></defs><g fill="url(#welcome-pill)"><rect x="-80" y="195" width="285" height="64" rx="32" transform="rotate(-45 62 227)"/><rect x="190" y="220" width="280" height="66" rx="33" transform="rotate(-45 330 253)"/><rect x="420" y="190" width="230" height="62" rx="31" transform="rotate(-45 535 221)"/></g><g stroke="#ffb486" stroke-width="7" stroke-linecap="round" opacity=".85"><path d="m30 318 114-114M145 337l65-65M275 178l95-95M380 325l82-82M568 326l83-83M32 245l57-57"/></g><g stroke="#dc8bdf" stroke-width="15" stroke-linecap="round" opacity=".55"><path d="m93 354 146-146M360 354l100-100M520 300l95-95"/></g></svg>
        <div class="auth-brand">@include('auth.partials.logo')<span>{{ config('school.name') }}</span></div>
        <div class="auth-introduction"><span class="auth-eyebrow">YOUR SCHOOL. YOUR COMMUNITY.</span><h1>Welcome to<br>{{ config('school.name') }}</h1><p>Learning today. Leading tomorrow.</p><span class="auth-divider"></span><p class="auth-caption">One place to connect, teach and grow.</p></div>
        <p class="auth-welcome-footer">School Management · Admin & Teacher Portal</p>
    </section>
    <section class="auth-form-area" aria-label="Account access">
        <div class="auth-card">
            <div class="auth-mobile-brand">@include('auth.partials.logo')<span>{{ config('school.name') }}</span></div>
            @if(session('status'))<div class="auth-flash" role="status">{{ session('status') }}</div>@endif
            @yield('content')
            <p class="auth-footer">{{ config('school.name') }}<br><span>Learning today. Leading tomorrow.</span></p>
        </div>
    </section>
</main>
</body></html>
