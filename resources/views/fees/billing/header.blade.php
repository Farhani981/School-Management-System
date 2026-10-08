<div class="page-heading"><div><div class="breadcrumb">Fees / {{ $title }}</div><h1>{{ $title }}</h1><p>{{ $description ?? 'Manage school fees, payments and outstanding balances.' }}</p></div></div>
@include('students.partials.feedback')
<nav class="profile-tabs fee-navigation fee-no-print" aria-label="Fee management">
@foreach(['setup.index' => 'Fee Setup', 'vouchers.index' => 'Vouchers', 'collection.index' => 'Collection', 'defaulters.index' => 'Defaulters', 'reports.index' => 'Reports'] as $route => $label)<a class="workflow-tab fee-nav-{{ str_replace('.index', '', $route) }} {{ request()->routeIs('fees.'.str_replace('.index', '.*', $route)) ? 'active' : '' }}" href="{{ route('fees.'.$route) }}">{{ $label }}</a>@endforeach
</nav>
