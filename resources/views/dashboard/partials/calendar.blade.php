<section class="panel calendar-panel" aria-labelledby="calendar-title">
    <div class="panel-heading">
        <div class="panel-heading-left">
            <span class="panel-icon panel-icon-blue"><x-icon name="calendar" size="16" /></span>
            <div>
                <h2 id="calendar-title">Academic Calendar</h2>
                <p>Important days for your school.</p>
            </div>
        </div>
        <div class="calendar-nav-toolbar">
            <button type="button" class="icon-button" id="calendar-prev" aria-label="Previous month">
                <x-icon name="chevron" size="13" class="rotate-180" />
            </button>
            <button type="button" class="icon-button" id="calendar-next" aria-label="Next month">
                <x-icon name="chevron" size="13" />
            </button>
            <h3 id="calendar-month" aria-live="polite">{{ $today->format('F Y') }}</h3>
            <button type="button" class="calendar-today" id="calendar-today" hidden>Today</button>
            <a href="{{ route('events.index') }}" class="calendar-arrow-link" aria-label="Go to events">&rarr;</a>
        </div>
    </div>

    <div id="academic-calendar" data-today="{{ $today->toDateString() }}" data-events="{{ json_encode($calendarEvents) }}" data-upcoming="{{ json_encode($events) }}" data-url="{{ route('dashboard.calendar') }}">
        <div class="weekdays" aria-hidden="true">
            @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $day)
                <span>{{ $day }}</span>
            @endforeach
        </div>
        <div id="calendar-days" class="calendar-days" role="group" aria-label="Calendar dates"></div>
        <noscript>
            <p class="p-4">Enable JavaScript to browse the calendar. School events are listed below.</p>
        </noscript>
    </div>

    <div class="calendar-legend">
        <span><i class="event-dot dot-coral"></i> School Holiday</span>
        <span><i class="event-dot dot-amber"></i> Exam</span>
        <span><i class="event-dot dot-purple"></i> Event</span>
        <span><i class="event-dot dot-blue"></i> Today</span>
    </div>

    <div class="calendar-today-box">
        <div class="calendar-today-header">
            <span class="calendar-mini-icon"><x-icon name="calendar" size="13" /></span>
            <strong>Today's Events</strong>
        </div>
        <div class="calendar-events-list" id="calendar-events" aria-live="polite">
            @forelse ($events as $event)
                <div class="calendar-event-row">
                    <span class="event-dot dot-{{ $event['tone'] }}"></span>
                    <span class="event-time">{{ \Carbon\Carbon::parse($event['date'])->format('h:i A') }}</span>
                    <span class="event-title">{{ $event['title'] }}</span>
                </div>
            @empty
                <div class="calendar-event-row">
                    <span class="event-dot dot-amber"></span>
                    <span class="event-time">09:00 AM</span>
                    <span class="event-title">Class 8 - Mathematics Exam</span>
                </div>
                <div class="calendar-event-row">
                    <span class="event-dot dot-purple"></span>
                    <span class="event-time">02:00 PM</span>
                    <span class="event-title">Parent Teacher Meeting</span>
                </div>
            @endforelse
        </div>
        <div class="calendar-view-all">
            <a href="{{ route('events.index') }}">View Full Calendar &rarr;</a>
        </div>
    </div>
</section>
