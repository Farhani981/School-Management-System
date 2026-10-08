    <div class="grid grid-cols-1 gap-3 mb-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($statistics as $statistic)
            <x-stat-card :label="$statistic['label']" :value="$statistic['value']" :icon="$statistic['icon']" :tone="$statistic['tone']" :note="$statistic['note']" :href="$statistic['href']" />
        @endforeach
    </div>
