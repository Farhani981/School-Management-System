<section class="panel activities-panel" aria-labelledby="activities-title">
    <div class="panel-heading">
        <div class="panel-heading-left">
            <span class="panel-icon panel-icon-blue"><x-icon name="bell" size="16" /></span>
            <div>
                <h2 id="activities-title">Recent Activities</h2>
                <p>Latest updates from your school.</p>
            </div>
        </div>
        <a href="{{ route('events.index') }}" class="activities-view-all">View All &rarr;</a>
    </div>

    @php
        $hasActivities = count($activities) > 0;
        $sampleActivities = [
            ['icon' => 'users', 'tone' => 'green', 'title' => 'New student added', 'detail' => 'Ali Raza (Class 6) has been added', 'time' => '10:24 AM'],
            ['icon' => 'wallet', 'tone' => 'blue', 'title' => 'Fee payment received', 'detail' => 'Rafia Khan - Rs. 8,000', 'time' => '10:12 AM'],
            ['icon' => 'classes', 'tone' => 'purple', 'title' => 'Student attendance marked', 'detail' => 'Class 10 - 28 students present', 'time' => '09:45 AM'],
            ['icon' => 'clipboard', 'tone' => 'amber', 'title' => 'Exam result published', 'detail' => 'Class 9 - Mid Term Exam', 'time' => '09:20 AM'],
            ['icon' => 'wallet', 'tone' => 'teal', 'title' => 'Fee updated', 'detail' => 'Ahmed Raza - Rs. 5,000', 'time' => 'Yesterday'],
        ];
        $displayActivities = $hasActivities ? $activities : $sampleActivities;
    @endphp

    <ol class="activity-list">
        @foreach ($displayActivities as $activity)
            <li class="activity-item">
                <span class="activity-avatar avatar-tone-{{ $activity['tone'] }}">
                    <x-icon :name="$activity['icon']" size="15" />
                </span>
                <div class="activity-info">
                    <h3>{{ $activity['title'] }}</h3>
                    <p>{{ $activity['detail'] }}</p>
                </div>
                <span class="activity-time">{{ $activity['time'] }}</span>
            </li>
        @endforeach
    </ol>
</section>
