@extends('layouts.admin')
@section('title', 'Accounts Dashboard')
@section('content')
<div class="page-heading"><div><div class="breadcrumb">Accounts / Dashboard</div><h1>Accounts Dashboard</h1><p>School income, expenses and paid salaries.</p></div></div>
@include('accounts.nav')
@include('accounts.totals')
<div class="accounts-dashboard-grid">
    <section class="panel accounts-overview">
        <div class="panel-heading"><div><span class="accounts-eyebrow">Financial overview</span><h2>Your school’s cash position</h2><p>A clear view of money received and paid.</p></div><span class="badge badge-neutral">All time</span></div>
        <div class="accounts-breakdown">
            @foreach(['income'=>'Income received', 'expenses'=>'School expenses', 'salaries'=>'Staff salaries'] as $key=>$label)
            <div class="accounts-breakdown-row"><span><i class="accounts-dot dot-{{ $key }}"></i>{{ $label }}</span><strong>PKR {{ number_format($totals[$key] / 100, 2) }}</strong></div>
            @endforeach
            <div class="accounts-breakdown-row accounts-balance-row"><span>Current balance</span><strong>PKR {{ number_format($totals['balance'] / 100, 2) }}</strong></div>
        </div>
        <p class="accounts-caption">Current Balance = Income − Expenses − Paid Salaries. Reversed fee receipts are excluded.</p>
    </section>
    <section class="panel accounts-actions">
        <div class="panel-heading"><div><span class="accounts-eyebrow">Quick actions</span><h2>Keep your accounts up to date</h2><p>Go straight to your next task.</p></div></div>
        @foreach([['accounts.income.index','Record income','Donations and other receipts','income','wallet'],['accounts.expenses.create','Add an expense','Bills and school purchases','expenses','clipboard'],['accounts.salaries.index','Manage salaries','Monthly staff payments','salaries','teacher'],['fees.collection.index','Collect student fees','Open fee collection','balance','book']] as [$route,$label,$description,$tone,$icon])
        <a class="accounts-action" href="{{ route($route) }}"><span class="accounts-action-icon action-{{ $tone }}"><x-icon :name="$icon" size="20" /></span><span><strong>{{ $label }}</strong><small>{{ $description }}</small></span><span class="accounts-action-arrow" aria-hidden="true">↗</span></a>
        @endforeach
    </section>
</div>
@endsection
