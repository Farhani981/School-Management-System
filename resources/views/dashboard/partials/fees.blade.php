        <section class="panel" aria-labelledby="fees-title">
            <div class="panel-heading">
                <div>
                    <h2 id="fees-title">Fee Collection</h2>
                    <p>Monthly financial overview</p>
                </div><span class="badge badge-neutral">{{ $today->format('M Y') }}</span>
            </div>
            <div class="fees-content">
                @php($collectionRate = $fees['percentage'])
                <div class="donut-wrap"><svg viewBox="0 0 160 160" class="fee-donut" role="img"
                        aria-label="{{ $collectionRate }} percent of billed fees collected">
                        <circle class="donut-track" cx="80" cy="80" r="64" />
                        <circle class="donut-value" cx="80" cy="80" r="64" pathLength="100"
                            stroke-dasharray="{{ min(100, $collectionRate) }} {{ max(0, 100 - $collectionRate) }}" />
                    </svg>
                    <div class="donut-label"><strong>{{ $collectionRate }}<small>%</small></strong><span>Collected</span>
                    </div>
                </div>
                <div class="fee-legend">
                    <div><span><i class="event-dot dot-pink"></i>Total Fees</span><strong>Rs. {{ number_format($fees['billed']) }}</strong></div>
                    <div><span><i class="event-dot dot-blue"></i>Total collected</span><strong>Rs.
                            {{ number_format($fees['collected']) }}</strong></div>
                    <div><span><i class="event-dot dot-coral"></i>Pending</span><strong>Rs.
                            {{ number_format($fees['pending']) }}</strong></div>
                </div>
            </div>
            <div class="dashboard-fee-chart">
                <h3>Monthly collections <small>Last 6 months</small></h3>
                @php($chartMax = max(1, $feeTrend->max('amount')))
                <div class="dashboard-fee-bars" role="list" aria-label="Monthly fee collections">
                    @foreach($feeTrend as $month)
                    <div class="dashboard-fee-month" role="listitem" aria-label="{{ $month['month'] }}: Rs. {{ number_format($month['amount'], 2) }}">
                        <span class="dashboard-fee-amount">{{ $month['amount'] ? number_format($month['amount']) : '0' }}</span>
                        <div class="dashboard-fee-track"><span style="height:{{ $month['amount'] / $chartMax * 100 }}%" title="{{ $month['month'] }}: Rs. {{ number_format($month['amount'], 2) }}"></span></div>
                        <span>{{ $month['label'] }}</span>
                    </div>
                    @endforeach
                </div>
                @if(!$feeTrend->sum('amount'))<p class="dashboard-chart-empty">No payments recorded in these months. Collections will appear here as fees are received.</p>@endif
            </div>
            <div class="fee-footer"><span class="small-check"><x-icon name="check" size="13" /></span>
                <p>{{ $fees['empty'] ? 'No fee data yet' : 'Collection progress against billed fees.' }}<br><span>Expenses: Rs. {{ number_format($fees['expenses']) }} · Tracked separately.</span>@if($fees['empty'])<br><a href="{{ route('fees.vouchers.index') }}">Generate fee vouchers</a>@endif</p>
            </div>
        </section>
