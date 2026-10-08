<aside id="sidebar" class="sidebar" aria-label="Main navigation">
    <a href="<?php echo e(route('dashboard')); ?>" class="brand"><span class="brand-mark"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'school','size' => '21']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'school','size' => '21']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?></span><span>School<span class="brand-caption">MANAGEMENT</span></span></a>
    <nav class="navigation">
        <?php $__currentLoopData = ['Dashboard' => 'dashboard', 'Students' => 'users', 'Teachers' => 'teacher', 'Classes' => 'classes', 'Subjects' => 'book', 'Timetable' => 'clock', 'Attendance' => 'calendar', 'Fees' => 'wallet', 'Exams' => 'clipboard', 'Library' => 'book', 'Notices' => 'bell', 'Reports' => 'chart', 'Settings' => 'settings']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $label => $icon): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php if($label === 'Students'): ?>
                <?php
                    $admissionsActive = request()->routeIs('admissions.*', 'students.create', 'students.store');
                    $promotionActive = request()->routeIs('promotion.*', 'students.promotion', 'students.promote');
                    $studentsActive = request()->routeIs('students.*') && !$admissionsActive && !$promotionActive;
                    $expanded = $admissionsActive || $promotionActive || $studentsActive;
                ?>
                <div class="<?php echo \Illuminate\Support\Arr::toCssClasses(['nav-group', 'is-expanded' => $expanded]); ?>">
                    <button type="button" class="<?php echo \Illuminate\Support\Arr::toCssClasses(['nav-item', 'nav-group-toggle', 'active-parent' => $expanded]); ?>" aria-expanded="<?php echo e($expanded ? 'true' : 'false'); ?>" aria-controls="students-submenu" data-nav-toggle><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'users','size' => '16']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'users','size' => '16']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?><span>Students</span><svg class="nav-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg></button>
                    <div id="students-submenu" class="nav-submenu" <?php if(!$expanded): ?> inert <?php endif; ?>><div class="nav-submenu-inner">
                        <?php $__currentLoopData = [['All Students', 'students.index', 'users', $studentsActive], ['Admissions', 'admissions.index', 'clipboard', $admissionsActive], ['Promotion', 'promotion.index', 'classes', $promotionActive]]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$child, $route, $childIcon, $isActive]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <a class="<?php echo \Illuminate\Support\Arr::toCssClasses(['nav-item', 'nav-child', 'active' => $isActive]); ?>" href="<?php echo e(route($route)); ?>" <?php if($isActive): ?> aria-current="page" <?php endif; ?>><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => $childIcon,'size' => '15']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($childIcon),'size' => '15']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?><span><?php echo e($child); ?></span><?php if($isActive): ?><span class="active-dot"></span><?php endif; ?></a>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div></div>
                </div>
            <?php elseif($label === 'Teachers'): ?>
                <?php($expanded = request()->routeIs('teachers.*'))
                <div @class(['nav-group', 'is-expanded' => $expanded])>
                    <button type="button" @class(['nav-item', 'nav-group-toggle', 'active-parent' => $expanded]) aria-expanded="{{ $expanded ? 'true' : 'false' }}" aria-controls="teachers-submenu" data-nav-toggle><x-icon name="teacher" size="16" /><span>Teachers</span><svg class="nav-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg></button>
                    <div id="teachers-submenu" class="nav-submenu" @if(!$expanded) inert @endif><div class="nav-submenu-inner">
                        @foreach(['index' => ['All Teachers','teacher'], 'assignments.index' => ['Assignments','classes'], 'leaves.index' => ['Leaves','calendar'], 'payroll.index' => ['Payroll','wallet']] as $suffix => [$child, $childIcon])
                            @php($active = $suffix === 'index' ? $expanded && !request()->routeIs('teachers.assignments.*','teachers.leaves.*','teachers.payroll.*') : request()->routeIs('teachers.'.str_replace('.index', '.*', $suffix)))
                            <a @class(['nav-item','nav-child','active' => $active]) href="{{ route('teachers.'.$suffix) }}" @if($active) aria-current="page" @endif><x-icon :name="$childIcon" size="15" /><span>{{ $child }}</span>@if($active)<span class="active-dot"></span>@endif</a>
                        @endforeach
                    </div></div>
                </div>
            @elseif ($label === 'Attendance')
                @php($expanded = request()->routeIs('attendance.*'))
                <div @class(['nav-group','is-expanded'=>$expanded])>
                    <button type="button" @class(['nav-item','nav-group-toggle','active-parent'=>$expanded]) aria-expanded="{{ $expanded ? 'true' : 'false' }}" aria-controls="attendance-submenu" data-nav-toggle><x-icon name="calendar" size="16" /><span>Attendance</span><svg class="nav-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg></button>
                    <div id="attendance-submenu" class="nav-submenu" @if(!$expanded) inert @endif><div class="nav-submenu-inner">@foreach(['students.index'=>['Student Attendance','users'],'staff.index'=>['Staff Attendance','teacher'],'reports.index'=>['Reports','chart']] as $suffix=>[$child,$childIcon])<a @class(['nav-item','nav-child','active'=>request()->routeIs('attendance.'.str_replace('.index','.*',$suffix))]) href="{{ route('attendance.'.$suffix) }}"><x-icon :name="$childIcon" size="15" /><span>{{ $child }}</span></a>@endforeach</div></div>
                </div>
            @elseif ($label === 'Fees')
                @php($expanded = request()->routeIs('fees.*'))
                <div @class(['nav-group', 'is-expanded' => $expanded])>
                    <button type="button" @class(['nav-item','nav-group-toggle','active-parent'=>$expanded]) aria-expanded="{{ $expanded ? 'true' : 'false' }}" aria-controls="fees-submenu" data-nav-toggle><x-icon name="wallet" size="16" /><span>Fees</span><svg class="nav-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg></button>
                    <div id="fees-submenu" class="nav-submenu" @if(!$expanded) inert @endif><div class="nav-submenu-inner">
                    @foreach(['setup.index'=>'Fee Setup', 'vouchers.index'=>'Vouchers', 'collection.index'=>'Collection', 'defaulters.index'=>'Defaulters', 'reports.index'=>'Reports'] as $suffix=>$child)
                        @php($active = $suffix === 'setup.index' ? $expanded && !request()->routeIs('fees.vouchers.*','fees.collection.*','fees.defaulters.*','fees.reports.*') : request()->routeIs('fees.'.str_replace('.index','.*',$suffix)))
                        <a @class(['nav-item','nav-child','active'=>$active]) href="{{ route('fees.'.$suffix) }}" @if($active) aria-current="page" @endif><x-icon name="wallet" size="15" /><span>{{ $child }}</span></a>
                    @endforeach
                    </div></div>
                </div>
            @elseif ($label === 'Subjects')
                @php($active = request()->routeIs('subjects.*'))
                <a @class(['nav-item', 'active' => $active]) href="{{ route('subjects.index') }}" @if($active) aria-current="page" @endif><x-icon name="book" size="16" /><span>Subjects</span>@if($active)<span class="active-dot"></span>@endif</a>
            @elseif ($label === 'Timetable')
                @php
                    $expanded = request()->routeIs('timetable.*');
                ?>
                <div class="<?php echo \Illuminate\Support\Arr::toCssClasses(['nav-group', 'is-expanded' => $expanded]); ?>">
                    <button type="button" class="<?php echo \Illuminate\Support\Arr::toCssClasses(['nav-item', 'nav-group-toggle', 'active-parent' => $expanded]); ?>" aria-expanded="<?php echo e($expanded ? 'true' : 'false'); ?>" aria-controls="timetable-submenu" data-nav-toggle><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'clock','size' => '16']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'clock','size' => '16']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?><span>Timetable</span><svg class="nav-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg></button>
                    <div id="timetable-submenu" class="nav-submenu" <?php if(!$expanded): ?> inert <?php endif; ?>><div class="nav-submenu-inner">
                        <?php $__currentLoopData = [['Overview', 'timetable.index', 'calendar', request()->routeIs('timetable.index')], ['Class Builder', 'timetable.builder', 'classes', request()->routeIs('timetable.builder')], ['Period Slots', 'timetable.periods.index', 'clock', request()->routeIs('timetable.periods.*')], ['Substitutions', 'timetable.overrides.index', 'teacher', request()->routeIs('timetable.overrides.*')]]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$child, $route, $childIcon, $isActive]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <a class="<?php echo \Illuminate\Support\Arr::toCssClasses(['nav-item', 'nav-child', 'active' => $isActive]); ?>" href="<?php echo e(route($route)); ?>" <?php if($isActive): ?> aria-current="page" <?php endif; ?>><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => $childIcon,'size' => '15']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($childIcon),'size' => '15']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?><span><?php echo e($child); ?></span><?php if($isActive): ?><span class="active-dot"></span><?php endif; ?></a>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div></div>
                </div>
            <?php elseif($label === 'Notices'): ?>
                <a class="<?php echo \Illuminate\Support\Arr::toCssClasses(['nav-item', 'active' => request()->routeIs('events.*')]); ?>" href="<?php echo e(route('events.index')); ?>"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'bell','size' => '16']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'bell','size' => '16']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?><span><?php echo e($label); ?></span></a>
            <?php elseif($label === 'Dashboard'): ?>
                <?php ($active = request()->routeIs('dashboard')); ?>
                <a class="<?php echo \Illuminate\Support\Arr::toCssClasses(['nav-item', 'active' => $active]); ?>" href="<?php echo e(route('dashboard')); ?>" <?php if($active): ?> aria-current="page" <?php endif; ?>><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => $icon,'size' => '16']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($icon),'size' => '16']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?><span><?php echo e($label); ?></span><?php if($active): ?><span class="active-dot"></span><?php endif; ?></a>
            <?php elseif(in_array($label, ['Classes', 'Settings'])): ?>
                <a class="nav-item" href="<?php echo e(route('academic-setup.index')); ?>"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => $icon,'size' => '16']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($icon),'size' => '16']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?><span><?php echo e($label); ?></span></a>
            <?php else: ?>
                <button class="nav-item" type="button" data-module="<?php echo e($label); ?>"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => $icon,'size' => '16']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($icon),'size' => '16']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?><span><?php echo e($label); ?></span></button>
            <?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </nav>
    <div class="sidebar-bottom"><span class="school-status"><span></span> School workspace</span><div class="sidebar-note">A little more organized.<br>A lot more possible.</div><button type="button" class="help-button" data-message="Use the sidebar to manage students, teachers, academics, attendance and calendar events."><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'help','size' => '15']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'help','size' => '15']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?> Need a hand?</button></div>
</aside>
