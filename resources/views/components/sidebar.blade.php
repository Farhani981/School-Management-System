<aside id="sidebar" class="sidebar" aria-label="Main navigation">
    <a href="{{ route('dashboard') }}" class="brand"><span class="brand-mark"><x-icon name="school" size="21" /></span><span>School<span class="brand-caption">MANAGEMENT</span></span></a>
    <nav class="navigation">
        @foreach (['Dashboard' => 'dashboard', 'Students' => 'users', 'Teachers' => 'teacher', 'Leaves' => 'calendar', 'Academics' => 'classes', 'Timetable' => 'clock', 'Attendance' => 'calendar', 'Fees' => 'wallet', 'Examinations'=>'clipboard', 'Library' => 'book'] as $label => $icon)
            @if ($label === 'Students')
                @php
                    $admissionsActive = request()->routeIs('admissions.*', 'students.create', 'students.store');
                    $promotionActive = request()->routeIs('promotion.*', 'students.promotion', 'students.promote');
                    $studentsActive = request()->routeIs('students.*') && !$admissionsActive && !$promotionActive;
                    $expanded = $admissionsActive || $promotionActive || $studentsActive;
                @endphp
                <div @class(['nav-group', 'is-expanded' => $expanded])>
                    <button type="button" @class(['nav-item', 'nav-group-toggle', 'active-parent' => $expanded]) aria-expanded="{{ $expanded ? 'true' : 'false' }}" aria-controls="students-submenu" data-nav-toggle><x-icon name="users" size="16" /><span>Students</span><svg class="nav-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg></button>
                    <div id="students-submenu" class="nav-submenu" @if(!$expanded) inert @endif><div class="nav-submenu-inner">
                        @foreach([['All Students', 'students.index', 'users', $studentsActive], ['Admissions', 'admissions.index', 'clipboard', $admissionsActive], ['Promotion', 'promotion.index', 'classes', $promotionActive]] as [$child, $route, $childIcon, $isActive])
                        <a @class(['nav-item', 'nav-child', 'active' => $isActive]) href="{{ route($route) }}" @if($isActive) aria-current="page" @endif><x-icon :name="$childIcon" size="15" /><span>{{ $child }}</span>@if($isActive)<span class="active-dot"></span>@endif</a>
                        @endforeach
                    </div></div>
                </div>
            @elseif ($label === 'Teachers')
                @php
                    $expanded = request()->routeIs('teachers.*');
                @endphp
                <div @class(['nav-group', 'is-expanded' => $expanded])>
                    <button type="button" @class(['nav-item', 'nav-group-toggle', 'active-parent' => $expanded]) aria-expanded="{{ $expanded ? 'true' : 'false' }}" aria-controls="teachers-submenu" data-nav-toggle><x-icon name="teacher" size="16" /><span>Teachers</span><svg class="nav-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg></button>
                    <div id="teachers-submenu" class="nav-submenu" @if(!$expanded) inert @endif><div class="nav-submenu-inner">
                        @foreach(['index' => ['All Teachers','teacher'], 'assignments.index' => ['Assignments','classes'], 'leaves.index' => ['Leaves','calendar'], 'payroll.index' => ['Payroll','wallet']] as $suffix => [$child, $childIcon])
                            @php
                                $active = $suffix === 'index' ? $expanded && !request()->routeIs('teachers.assignments.*','teachers.leaves.*','teachers.payroll.*') : request()->routeIs('teachers.'.str_replace('.index', '.*', $suffix));
                            @endphp
                            <a @class(['nav-item','nav-child','active' => $active]) href="{{ route('teachers.'.$suffix) }}" @if($active) aria-current="page" @endif><x-icon :name="$childIcon" size="15" /><span>{{ $child }}</span>@if($active)<span class="active-dot"></span>@endif</a>
                        @endforeach
                    </div></div>
                </div>
            @elseif ($label === 'Leaves')
                @php
                    $expanded = request()->routeIs('leaves.*');
                @endphp
                <div @class(['nav-group', 'is-expanded' => $expanded])>
                    <button type="button" @class(['nav-item', 'nav-group-toggle', 'active-parent' => $expanded]) aria-expanded="{{ $expanded ? 'true' : 'false' }}" aria-controls="leaves-submenu" data-nav-toggle><x-icon name="calendar" size="16"/><span>Leaves</span><svg class="nav-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg></button>
                    <div id="leaves-submenu" class="nav-submenu" @if(!$expanded) inert @endif><div class="nav-submenu-inner">
                        @foreach(['balances.index'=>'Leave Balances', 'types.index'=>'Leave Types'] as $suffix=>$child)<a @class(['nav-item','nav-child','active'=>request()->routeIs('leaves.'.str_replace('.index','.*',$suffix))]) href="{{ route('leaves.'.$suffix) }}" @if(request()->routeIs('leaves.'.str_replace('.index','.*',$suffix))) aria-current="page" @endif>{{ $child }}</a>@endforeach
                    </div></div>
                </div>
            @elseif ($label === 'Examinations')
                @php
                    $expanded = request()->routeIs('exams.*');
                @endphp
                <div @class(['nav-group','is-expanded'=>$expanded])>
                    <button type="button" @class(['nav-item','nav-group-toggle','active-parent'=>$expanded]) aria-expanded="{{ $expanded ? 'true' : 'false' }}" aria-controls="exams-submenu" data-nav-toggle><x-icon name="clipboard" size="16"/><span>Examinations</span><svg class="nav-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg></button>
                    <div id="exams-submenu" class="nav-submenu" @if(!$expanded) inert @endif><div class="nav-submenu-inner"><a @class(['nav-item','nav-child','active'=>$expanded && !request()->routeIs('exams.approvals','exams.review*','exams.deadline')]) href="{{ route('exams.index') }}" @if($expanded && !request()->routeIs('exams.approvals','exams.review*','exams.deadline')) aria-current="page" @endif><x-icon name="book" size="15"/><span>Exams</span>@if($expanded && !request()->routeIs('exams.approvals','exams.review*','exams.deadline'))<span class="active-dot"></span>@endif</a><a @class(['nav-item','nav-child','active'=>request()->routeIs('exams.approvals','exams.review*','exams.deadline')]) href="{{ route('exams.approvals') }}"><x-icon name="clipboard" size="15"/><span>Marks Review</span></a></div></div>
                </div>
            @elseif ($label === 'Attendance')
                @php
                    $expanded = request()->routeIs('attendance.*');
                @endphp
                <div @class(['nav-group','is-expanded'=>$expanded])>
                    <button type="button" @class(['nav-item','nav-group-toggle','active-parent'=>$expanded]) aria-expanded="{{ $expanded ? 'true' : 'false' }}" aria-controls="attendance-submenu" data-nav-toggle><x-icon name="calendar" size="16" /><span>Attendance</span><svg class="nav-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg></button>
                    <div id="attendance-submenu" class="nav-submenu" @if(!$expanded) inert @endif><div class="nav-submenu-inner">@foreach(['students.index'=>['Student Attendance','users'],'staff.index'=>['Staff Attendance','teacher'],'reports.index'=>['Reports','chart']] as $suffix=>[$child,$childIcon])<a @class(['nav-item','nav-child','active'=>request()->routeIs('attendance.'.str_replace('.index','.*',$suffix))]) href="{{ route('attendance.'.$suffix) }}"><x-icon :name="$childIcon" size="15" /><span>{{ $child }}</span></a>@endforeach</div></div>
                </div>
            @elseif ($label === 'Fees')
                @php
                    $expanded = request()->routeIs('fees.*');
                @endphp
                <div @class(['nav-group', 'is-expanded' => $expanded])>
                    <button type="button" @class(['nav-item','nav-group-toggle','active-parent'=>$expanded]) aria-expanded="{{ $expanded ? 'true' : 'false' }}" aria-controls="fees-submenu" data-nav-toggle><x-icon name="wallet" size="16" /><span>Fees</span><svg class="nav-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg></button>
                    <div id="fees-submenu" class="nav-submenu" @if(!$expanded) inert @endif><div class="nav-submenu-inner">
                    @foreach(['setup.index'=>'Fee Setup', 'vouchers.index'=>'Vouchers', 'collection.index'=>'Collection', 'defaulters.index'=>'Defaulters', 'reports.index'=>'Reports'] as $suffix=>$child)
                        @php
                            $active = $suffix === 'setup.index' ? $expanded && !request()->routeIs('fees.vouchers.*','fees.collection.*','fees.defaulters.*','fees.reports.*') : request()->routeIs('fees.'.str_replace('.index','.*',$suffix));
                        @endphp
                        <a @class(['nav-item','nav-child','active'=>$active]) href="{{ route('fees.'.$suffix) }}" @if($active) aria-current="page" @endif><x-icon name="wallet" size="15" /><span>{{ $child }}</span></a>
                    @endforeach
                    </div></div>
                </div>
            @elseif ($label === 'Subjects')
                @php
                    $active = request()->routeIs('subjects.*');
                @endphp
                <a @class(['nav-item', 'active' => $active]) href="{{ route('subjects.index') }}" @if($active) aria-current="page" @endif><x-icon name="book" size="16" /><span>Subjects</span>@if($active)<span class="active-dot"></span>@endif</a>
            @elseif ($label === 'Timetable')
                @php
                    $expanded = request()->routeIs('timetable.*');
                @endphp
                <div @class(['nav-group', 'is-expanded' => $expanded])>
                    <button type="button" @class(['nav-item', 'nav-group-toggle', 'active-parent' => $expanded]) aria-expanded="{{ $expanded ? 'true' : 'false' }}" aria-controls="timetable-submenu" data-nav-toggle><x-icon name="clock" size="16" /><span>Timetable</span><svg class="nav-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg></button>
                    <div id="timetable-submenu" class="nav-submenu" @if(!$expanded) inert @endif><div class="nav-submenu-inner">
                        @foreach([['Overview', 'timetable.index', 'calendar', request()->routeIs('timetable.index')], ['Class Builder', 'timetable.builder', 'classes', request()->routeIs('timetable.builder')], ['Teacher Daily Routine', 'timetable.teacher-routine', 'teacher', request()->routeIs('timetable.teacher-routine*')], ['Period Slots', 'timetable.periods.index', 'clock', request()->routeIs('timetable.periods.*')], ['Substitutions', 'timetable.overrides.index', 'teacher', request()->routeIs('timetable.overrides.*')]] as [$child, $route, $childIcon, $isActive])
                            <a @class(['nav-item', 'nav-child', 'active' => $isActive]) href="{{ route($route) }}" @if($isActive) aria-current="page" @endif><x-icon :name="$childIcon" size="15" /><span>{{ $child }}</span>@if($isActive)<span class="active-dot"></span>@endif</a>
                        @endforeach
                    </div></div>
                </div>
            @elseif ($label === 'Library')
                @can('viewAny', \App\Models\LibraryBook::class)
                    @php
                        $expanded = request()->routeIs('library.*');
                    @endphp
                    <div @class(['nav-group', 'library-nav', 'is-expanded' => $expanded])>
                        <button type="button" @class(['nav-item','nav-group-toggle','active-parent'=>$expanded]) aria-expanded="{{ $expanded ? 'true' : 'false' }}" aria-controls="library-submenu" data-nav-toggle><x-icon name="book" size="16"/><span>Library</span><svg class="nav-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg></button>
                        <div id="library-submenu" class="nav-submenu" @if(!$expanded) inert @endif><div class="nav-submenu-inner">
                            <a @class(['nav-item','nav-child','active'=>request()->routeIs('library.books.*', 'library.copies.*')]) href="{{ route('library.books.index') }}" @if(request()->routeIs('library.books.*', 'library.copies.*')) aria-current="page" @endif><x-icon name="book" size="15"/><span>Books List</span></a>
                            @can('viewAny', \App\Models\LibraryMember::class)<a @class(['nav-item', 'nav-child', 'active' => request()->routeIs('library.members.*')]) href="{{ route('library.members.index') }}" @if(request()->routeIs('library.members.*')) aria-current="page" @endif><x-icon name="users" size="15"/><span>Members List</span></a>@endcan
                            @can('viewAny', \App\Models\LibraryTransaction::class)<a @class(['nav-item', 'nav-child', 'active' => request()->routeIs('library.borrowing.*')]) href="{{ route('library.borrowing.index') }}" @if(request()->routeIs('library.borrowing.*')) aria-current="page" @endif><x-icon name="book" size="15"/><span>Issue Return</span></a>@endcan
                        </div></div>
                    </div>
                @endcan
            @else
                @php
                    $items = match ($label) {
                        'Dashboard' => [['Overview', 'dashboard', ['dashboard']], ['Notices', 'events.index', ['events.*']]],
                        'Academics' => [['Classes & Settings', 'academic-setup.index', ['academic-setup.*']], ['Subjects', 'subjects.index', ['subjects.*']]],
                    };
                    $expanded = collect($items)->contains(fn ($item) => request()->routeIs(...$item[2]));
                    $submenuId = strtolower($label).'-submenu';
                @endphp
                <div @class(['nav-group', 'is-expanded' => $expanded])>
                    <button type="button" @class(['nav-item', 'nav-group-toggle', 'active-parent' => $expanded]) aria-expanded="{{ $expanded ? 'true' : 'false' }}" aria-controls="{{ $submenuId }}" data-nav-toggle><x-icon :name="$icon" size="16" /><span>{{ $label }}</span><svg class="nav-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg></button>
                    <div id="{{ $submenuId }}" class="nav-submenu" @if(!$expanded) inert @endif><div class="nav-submenu-inner">
                        @foreach($items as [$child, $childRoute, $patterns])
                            @php($active = request()->routeIs(...$patterns))
                            <a @class(['nav-item', 'nav-child', 'active' => $active]) href="{{ route($childRoute) }}" @if($active) aria-current="page" @endif><span>{{ $child }}</span></a>
                        @endforeach
                    </div></div>
                </div>
            @endif
        @endforeach
        @if(auth()->user()?->hasAnyRole(['Admin', 'Principal']))
    <a
        @class([
            'nav-item',
            'active' => request()->routeIs('accounts.*'),
        ])
        href="{{ route('accounts.expenses.index') }}"
        @if(request()->routeIs('accounts.*')) aria-current="page" @endif
    >
        <x-icon name="wallet" size="16" />
        <span>Accounts</span>
    </a>
@endif
    </nav>
    <div class="sidebar-bottom"><span class="school-status"><span></span> School workspace</span><div class="sidebar-note">A little more organized.<br>A lot more possible.</div><button type="button" class="help-button" data-message="Use the sidebar to manage students, teachers, academics, attendance and calendar events."><x-icon name="help" size="15" /> Need a hand?</button></div>
</aside>
