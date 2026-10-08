@php
    $workspaceUser = auth()->user();
    $workspaceName = $workspaceUser?->name ?? "Guest";
    $workspaceRole = $workspaceUser?->role ?? "Not signed in";
    $workspaceInitials = collect(preg_split("/\\s+/", trim($workspaceName)))->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode("");
@endphp
<header class="topbar">
    <button type="button" id="menu-toggle" class="icon-button" aria-label="Toggle navigation" aria-controls="sidebar" aria-expanded="false"><x-icon name="menu" /></button>
    <p class="topbar-motto">Learning today. Leading tomorrow.</p>
    @if(request()->routeIs('portal.*'))
    <form id="workspace-search" class="search-box" role="search"><x-icon name="search" size="15" /><input type="search" aria-label="Search navigation" placeholder="Search..." autocomplete="off"><kbd>/</kbd><div id="search-results" class="search-results" hidden></div></form>
    @endif
    <div class="topbar-right">
        @auth
        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="button-secondary topbar-auth-button">Logout</button></form>
        @else
        <a class="button-secondary topbar-auth-button" href="{{ route('login') }}">Login</a>
        @endauth
        @php
            $schoolNotifications = $workspaceUser ? $workspaceUser->notifications()->orderByRaw('CASE WHEN read_at IS NULL THEN 0 ELSE 1 END')->orderByDesc('created_at')->limit(5)->get() : collect();
        @endphp
        <div class="popover-wrap"><button type="button" class="icon-button notification-button" aria-label="Notifications" aria-expanded="false" data-popover="notifications"><x-icon name="bell" size="17" />@if($schoolNotifications->contains(fn($n)=>!$n->read_at))<span class="notification-dot"></span>@endif</button><div id="notifications" class="popover school-notifications" hidden>
            @forelse($schoolNotifications as $notification)
                <article><strong>{{ $notification->data['message'] ?? 'School notification' }}</strong><p>{{ $notification->created_at->format('d M Y, g:i A') }}</p>
                    @foreach($notification->data['papers'] ?? [] as $paper)<p>{{ $paper['subject'] }} / {{ $paper['class'] }} {{ $paper['section'] }}<br>{{ $paper['date'] }}, {{ $paper['time'] }}@if($paper['room']) / {{ $paper['room'] }}@endif</p>@endforeach
                    @if(isset($notification->data['url']))<a href="{{ $notification->data['url'] }}">View schedule</a>@endif
                    @unless($notification->read_at)<form method="POST" action="{{ route('notifications.read',$notification->id) }}">@csrf<button type="submit" class="button-secondary">Mark as read</button></form>@endunless
                </article>
            @empty<strong>You're all caught up</strong><p>New school notifications will appear here.</p>@endforelse
        </div></div>
        <span class="topbar-divider"></span>
        <div class="popover-wrap"><button type="button" class="profile-button" data-popover="profile" aria-expanded="false"><span class="avatar">{{ mb_strtoupper($workspaceInitials) }}</span><span class="profile-name">{{ $workspaceName }} <small>{{ $workspaceRole }}</small></span><x-icon name="chevron" size="12" class="rotate-90" /></button><div id="profile" class="popover" hidden><strong>{{ $workspaceName }}</strong><p>{{ $workspaceRole }}</p>@guest<a href="{{ route('login') }}">Sign In</a>@endguest</div></div>
    </div>
</header>
