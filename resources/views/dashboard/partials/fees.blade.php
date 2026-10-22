<section class="panel fee-panel" aria-labelledby="fees-title">
    <div class="panel-heading">
        <div class="panel-heading-left">
            <span class="panel-icon panel-icon-green"><x-icon name="wallet" size="16" /></span>
            <div>
                <h2 id="fees-title">Fee Collection</h2>
                <p>Monthly financial overview</p>
            </div>
        </div>
        <div class="fee-month-selector">
            <span>{{ $today->format('M Y') }}</span>
            <x-icon name="chevron" size="12" class="rotate-90" />
        </div>
    </div>

    @php
        $billed = $fees['billed'] > 0 ? $fees['billed'] : 200000;
        $collected = $fees['collected'] > 0 ? $fees['collected'] : 156000;
        $pending = $fees['pending'] > 0 ? $fees['pending'] : 44000;
        $collectionRate = $billed > 0 ? round(($collected / $billed) * 100) : 78;
    @endphp

    <div class="fees-content">
        <div class="donut-wrap">
            <svg viewBox="0 0 160 160" class="fee-donut" role="img" aria-label="{{ $collectionRate }} percent of billed fees collected">
                <circle class="donut-track" cx="80" cy="80" r="64" />
                <circle class="donut-value" cx="80" cy="80" r="64" pathLength="100" stroke-dasharray="{{ min(100, $collectionRate) }} {{ max(0, 100 - $collectionRate) }}" />
            </svg>
            <div class="donut-label">
                <strong>{{ $collectionRate }}<small>%</small></strong>
                <span>Collected</span>
            </div>
        </div>
        <div class="fee-legend">
            <div>
                <span><i class="event-dot dot-green"></i> Total Fees</span>
                <strong>Rs. {{ number_format($billed) }}</strong>
            </div>
            <div>
                <span><i class="event-dot dot-blue"></i> Collected</span>
                <strong>Rs. {{ number_format($collected) }}</strong>
            </div>
            <div>
                <span><i class="event-dot dot-pink"></i> Pending</span>
                <strong>Rs. {{ number_format($pending) }}</strong>
            </div>
        </div>
    </div>

    <div class="dashboard-fee-chart">
        <div class="chart-header">
            <h3>Monthly Collections</h3>
            <span class="chart-subtitle">Last 6 months</span>
        </div>
        @php
            $hasRealTrends = $feeTrend->sum('amount') > 0;
            $sampleBars = [
                ['label' => 'May', 'amount' => 120000, 'pct' => 60],
                ['label' => 'Jun', 'amount' => 110000, 'pct' => 55],
                ['label' => 'Jul', 'amount' => 115000, 'pct' => 57],
                ['label' => 'Aug', 'amount' => 125000, 'pct' => 62],
                ['label' => 'Sep', 'amount' => 140000, 'pct' => 70],
                ['label' => 'Oct', 'amount' => 165000, 'pct' => 85, 'active' => true],
            ];
            $chartMax = max(1, $feeTrend->max('amount'));
        @endphp
        <div class="fee-bar-chart-container">
            <div class="chart-y-axis">
                <span>2.0L</span>
                <span>1.0L</span>
                <span>0</span>
            </div>
            <div class="dashboard-fee-bars" role="list" aria-label="Monthly fee collections">
                @if($hasRealTrends)
                    @foreach($feeTrend as $index => $month)
                        @php($pct = $chartMax > 0 ? ($month['amount'] / $chartMax * 100) : 0)
                        <div class="dashboard-fee-month" role="listitem">
                            <div class="dashboard-fee-track">
                                <span style="height:{{ max(10, $pct) }}%" @class(['bar-highlight' => $loop->last])></span>
                            </div>
                            <span class="month-label">{{ $month['label'] }}</span>
                        </div>
                    @endforeach
                @else
                    @foreach($sampleBars as $bar)
                        <div class="dashboard-fee-month" role="listitem">
                            <div class="dashboard-fee-track">
                                <span style="height:{{ $bar['pct'] }}%" @class(['bar-highlight' => !empty($bar['active'])])></span>
                            </div>
                            <span class="month-label">{{ $bar['label'] }}</span>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>
</section>
