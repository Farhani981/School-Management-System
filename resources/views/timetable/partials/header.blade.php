<div class="page-heading">
    <div>
        <div class="breadcrumb">Workspace <span>/</span> Timetable</div>
        <h1>{{ $heading }}</h1>
        <p>{{ $description ?? 'Manage school timetable schedules, periods, and teacher allocations.' }}</p>
    </div>
    <div class="student-actions timetable-navigation">
        <a @class(['button-secondary', 'timetable-nav-overview', 'active-tab' => request()->routeIs('timetable.index')]) href="{{ route('timetable.index') }}">
            Overview
        </a>
        <a @class(['button-secondary', 'timetable-nav-builder', 'active-tab' => request()->routeIs('timetable.builder')]) href="{{ route('timetable.builder') }}">
            Class Builder
        </a>
        <a @class(['button-secondary', 'timetable-nav-periods', 'active-tab' => request()->routeIs('timetable.periods.*')]) href="{{ route('timetable.periods.index') }}">
            Period Slots
        </a>
        <a @class(['button-secondary', 'timetable-nav-overrides', 'active-tab' => request()->routeIs('timetable.overrides.*')]) href="{{ route('timetable.overrides.index') }}">
            Substitutions
        </a>
    </div>
</div>

@if(session('success'))
    <div class="panel student-form" role="status" style="border-left: 4px solid var(--ok); background: var(--ok-soft, #f0fdf4); color: var(--ok, #15803d); padding: 12px 18px; margin-bottom: 20px;">
        {{ session('success') }}
    </div>
@endif

@if(session('error'))
    <div class="panel student-form" role="alert" style="border-left: 4px solid var(--danger); background: var(--danger-soft, #fef2f2); color: var(--danger, #b91c1c); padding: 12px 18px; margin-bottom: 20px;">
        {{ session('error') }}
    </div>
@endif

@if($errors->any())
    <div class="panel student-form" role="alert" style="border-left: 4px solid var(--danger); background: var(--danger-soft, #fef2f2); color: var(--danger, #b91c1c); padding: 12px 18px; margin-bottom: 20px;">
        <strong>Please correct the following:</strong>
        <ul style="margin: 6px 0 0 18px;">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
