        <section class="panel calendar-panel" aria-labelledby="calendar-title">
            <div class="panel-heading">
                <div>
                    <h2 id="calendar-title">Academic Calendar</h2>
                    <p>Important days for your school</p>
                </div><span class="panel-icon"><x-icon name="calendar" size="17" /></span>
            </div>
            <div id="academic-calendar" data-today="{{ $today->toDateString() }}" data-events="{{ json_encode($calendarEvents) }}" data-upcoming="{{ json_encode($events) }}" data-url="{{ route('dashboard.calendar') }}">
                <div class="calendar-toolbar">
                    <h3 id="calendar-month" aria-live="polite">{{ $today->format('F Y') }}</h3>
                    <div class="flex items-center gap-1"><button type="button" class="calendar-today"
                            id="calendar-today">Today</button><button type="button" class="icon-button" id="calendar-prev"
                            aria-label="Previous month"><x-icon name="chevron" size="14"
                                class="rotate-180" /></button><button type="button" class="icon-button" id="calendar-next"
                            aria-label="Next month"><x-icon name="chevron" size="14" /></button></div>
                </div>
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
            <div class="calendar-events" id="calendar-events" aria-live="polite">
                @forelse ($events as $event)
                    <div class="event-item"><span class="event-dot dot-{{ $event['tone'] }}"></span>
                        <div><strong>{{ $event['title'] }}</strong>
                            <p>{{ \Carbon\Carbon::parse($event['date'])->format('d M') }} · {{ $event['detail'] }}</p>
                        </div>
                    </div>
                @empty
                    <p class="muted">No upcoming events</p>
                @endforelse
            </div>
        </section>
