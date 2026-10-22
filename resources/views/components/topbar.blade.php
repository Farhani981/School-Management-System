@php
    $workspaceUser = auth()->user();
    $workspaceName = $workspaceUser?->name ?? "School Admin";
    $workspaceRole = $workspaceUser?->role ?? ($workspaceUser?->roles?->first()?->name ?? "Admin");
    $workspaceInitial = mb_strtoupper(mb_substr(trim($workspaceName), 0, 1));
    if (empty($workspaceInitial)) $workspaceInitial = "S";

    $profileUrl = '#';
    if ($workspaceUser?->hasRole('Teacher')) {
        $profileUrl = route('teacher.profile.index');
    } elseif ($workspaceUser?->hasAnyRole(['Student', 'Parent'])) {
        $profileUrl = route('portal.profile');
    } elseif (Route::has('dashboard')) {
        $profileUrl = route('dashboard');
    }

    $changePasswordUrl = '#';
    if ($workspaceUser?->hasAnyRole(['Student', 'Parent'])) {
        $changePasswordUrl = route('portal.password.change');
    } elseif (Route::has('password.request')) {
        $changePasswordUrl = route('password.request');
    }

    $isParentUser = ($portalRole ?? '') === 'parent' || ($workspaceUser && $workspaceUser->hasRole('Parent'));
    $parentChildrenList = $isParentUser ? ($parentChildren ?? $workspaceUser?->children ?? collect()) : collect();
    $currentActiveChild = $isParentUser ? ($currentStudent ?? $parentChildrenList->first()) : null;
@endphp
<header class="topbar">
    <button type="button" id="menu-toggle" class="icon-button topbar-menu-toggle" aria-label="Toggle navigation" aria-controls="sidebar" aria-expanded="false"><x-icon name="menu" /></button>
    
    <form id="workspace-search" class="search-box" role="search">
        <x-icon name="search" size="15" />
        <input type="search" aria-label="Search school modules" placeholder="Search students, teachers, classes..." autocomplete="off">
        <div id="search-results" class="search-results" hidden></div>
    </form>

    <div class="topbar-right">
        @if($isParentUser && $parentChildrenList->isNotEmpty())
        <div class="popover-wrap portal-child-switcher">
            <button type="button" class="portal-child-switcher-btn" aria-label="Switch Child" data-popover="portal-child-dropdown" aria-expanded="false">
                <span class="portal-child-switcher-avatar">
                    {{ strtoupper(substr($currentActiveChild?->first_name ?? 'S', 0, 1)) }}
                </span>
                <span>{{ $currentActiveChild?->full_name ?? 'Select Child' }}</span>
                <x-icon name="chevron" size="14" />
            </button>

            <div id="portal-child-dropdown" class="popover portal-child-dropdown" hidden>
                <div class="portal-child-dropdown-header">Linked Children ({{ $parentChildrenList->count() }})</div>
                @foreach($parentChildrenList as $child)
                    <form method="POST" action="{{ route('portal.switch-child') }}">
                        @csrf
                        <input type="hidden" name="student_id" value="{{ $child->id }}">
                        <button type="submit" class="portal-child-item {{ ($currentActiveChild?->id === $child->id) ? 'active' : '' }}">
                            <div>
                                <div style="font-weight: 600;">{{ $child->full_name }}</div>
                                <div style="font-size: 11px; color: #64748b;">
                                    Class {{ $child->schoolClass?->name ?? 'N/A' }} &middot; Roll: {{ $child->roll_number ?? $child->admission_no }}
                                </div>
                            </div>
                            @if($currentActiveChild?->id === $child->id)
                                <x-icon name="check" size="16" />
                            @endif
                        </button>
                    </form>
                @endforeach
            </div>
        </div>
        @endif

        @guest
        <a class="button-secondary topbar-auth-button" href="{{ route('login') }}">Login</a>
        @endguest

        @php
            $schoolNotifications = $workspaceUser ? \Illuminate\Support\Facades\Cache::remember(
                'user_topbar_notifs_'.$workspaceUser->id,
                30,
                fn () => $workspaceUser->notifications()->orderByRaw('CASE WHEN read_at IS NULL THEN 0 ELSE 1 END')->orderByDesc('created_at')->limit(5)->get()
            ) : collect();
        @endphp
        <div class="popover-wrap">
            <button type="button" class="notification-button" aria-label="Notifications" aria-expanded="false" data-popover="notifications">
                <x-icon name="bell" size="18" />
                <span class="notification-dot"></span>
            </button>
            <div id="notifications" class="popover school-notifications" hidden>
                <div class="popover-notifications-header">
                    <strong>Notifications</strong>
                </div>
                @forelse($schoolNotifications as $notification)
                    <article><strong>{{ $notification->data['message'] ?? 'School notification' }}</strong><p>{{ $notification->created_at->format('d M Y, g:i A') }}</p>
                        @foreach($notification->data['papers'] ?? [] as $paper)<p>{{ $paper['subject'] }} / {{ $paper['class'] }} {{ $paper['section'] }}<br>{{ $paper['date'] }}, {{ $paper['time'] }}@if($paper['room']) / {{ $paper['room'] }}@endif</p>@endforeach
                        @if(isset($notification->data['url']))<a href="{{ $notification->data['url'] }}">View schedule</a>@endif
                        @unless($notification->read_at)<form method="POST" action="{{ route('notifications.read',$notification->id) }}">@csrf<button type="submit" class="button-secondary">Mark as read</button></form>@endunless
                    </article>
                @empty
                    <div class="notifications-empty">
                        <strong>You're all caught up</strong>
                        <p>New school notifications will appear here.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="popover-wrap">
            <button type="button" class="profile-button" data-popover="profile" aria-expanded="false">
                <span class="avatar">{{ $workspaceInitial }}</span>
                <span class="profile-info">
                    <span class="profile-name">{{ $workspaceName }}</span>
                    <span class="profile-role">{{ $workspaceRole }}</span>
                </span>
                <x-icon name="chevron" size="14" class="profile-chevron" />
            </button>
            <div id="profile" class="popover profile-popover" hidden>
                <div class="popover-profile-header">
                    <strong class="popover-user-name">{{ $workspaceName }}</strong>
                    <span class="popover-user-email">{{ $workspaceUser?->email ?? ($workspaceRole . ' Portal') }}</span>
                </div>
                <div class="popover-menu-items">
                    <a href="{{ $profileUrl }}" class="popover-item">
                        <x-icon name="user" size="16" />
                        <span>My Profile</span>
                    </a>
                    <a href="{{ $changePasswordUrl }}" class="popover-item">
                        <x-icon name="lock" size="16" />
                        <span>Change Password</span>
                    </a>
                    @auth
                    <form method="POST" action="{{ route('logout') }}" class="popover-logout-form">
                        @csrf
                        <button type="submit" class="popover-item popover-logout-btn">
                            <x-icon name="logout" size="16" />
                            <span>Sign Out</span>
                        </button>
                    </form>
                    @else
                    <a href="{{ route('login') }}" class="popover-item">
                        <x-icon name="user" size="16" />
                        <span>Sign In</span>
                    </a>
                    @endauth
                </div>
            </div>
        </div>
    </div>
</header>
