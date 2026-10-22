<div class="accounts-metrics">
@foreach(['income'=>'Total Income', 'expenses'=>'Total Expenses', 'salaries'=>'Total Salaries', 'balance'=>'Current Balance'] as $key=>$label)
    <section class="account-metric metric-{{ $key }}">
        <div class="account-metric-top"><span>{{ $label }}</span><span class="account-metric-icon"><x-icon :name="$key === 'salaries' ? 'teacher' : 'wallet'" size="20" /></span></div>
        <h2><small>PKR</small> {{ number_format($totals[$key] / 100, 2) }}</h2>
        <p>{{ match($key) {'income'=>'Received fees & other income', 'expenses'=>'Recorded school expenditure', 'salaries'=>'Completed salary payments', default=>'Income less expenses & salaries'} }}</p>
    </section>
@endforeach
</div>
