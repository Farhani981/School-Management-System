        <section class="panel" aria-labelledby="activities-title">
            <div class="panel-heading">
                <div>
                    <h2 id="activities-title">Recent Activities</h2>
                    <p>Around your school</p>
                </div>
            </div>
            <ol class="activity-list">
                @forelse ($activities as $activity)
                    <li><span class="activity-icon tint-{{ $activity['tone'] }}"><x-icon :name="$activity['icon']"
                                size="16" /></span>
                        <div class="min-w-0">
                            <h3>{{ $activity['title'] }}</h3>
                            <p>{{ $activity['detail'] }}</p><span class="activity-time">{{ $activity['time'] }}</span>
                        </div>
                    </li>
                @empty
                    <li><p class="muted">No recent activity</p></li>
                @endforelse
            </ol>
        </section>
