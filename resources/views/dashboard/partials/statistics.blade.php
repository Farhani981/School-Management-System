<div class="dashboard-stats-grid">
    @foreach ($statistics as $statistic)
        <x-stat-card :label="$statistic['label']" :value="$statistic['value']" :icon="$statistic['icon']" :tone="$statistic['tone']" :note="$statistic['note']" :trend="$statistic['trend'] ?? 'up'" :href="$statistic['href']" />
    @endforeach
</div>
