@extends('layouts.admin')

@section('title', 'School Expenses')

@section('content')
@include('accounts.nav')
<div class="page-heading">
    <div>
        <div class="breadcrumb">Accounts / Expenses</div>
        <h1>School Expenses</h1>
        <p>Review school expenses and payment details.</p>
    </div>

    <a class="button-primary"
       href="{{ route('accounts.expenses.create') }}">
        + Add Expense
    </a>
</div>

@include('students.partials.feedback')

<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>Expense Records</h2>
            <p>Latest expense dates appear first.</p>
        </div>

        <span class="badge badge-neutral">
            {{ $expenses->total() }} records
        </span>
    </div>

    <div class="student-table-scroll">
        <table class="student-table">
            <thead>
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Date</th>
                    <th scope="col">Category</th>
                    <th scope="col">Description</th>
                    <th scope="col">Amount (PKR)</th>
                    <th scope="col">Payment Method</th>
                    <th scope="col">Reference</th>
                    <th scope="col">Recorded By</th>
                </tr>
            </thead>

            <tbody>
                @forelse($expenses as $expense)
                    <tr>
                        <td>
                            {{ $expenses->firstItem() + $loop->index }}
                        </td>

                        <td>
                            {{ $expense->expense_date->format('d M Y') }}
                        </td>

                        <td>
                            <span class="badge badge-neutral">
                                {{ $expense->category }}
                            </span>
                        </td>

                        <td><strong>{{ $expense->title }}</strong><p>{{ $expense->description }}</p><p>Paid To: {{ $expense->paid_to ?: '—' }}</p>@if($expense->attachment_path)<a href="{{ route('accounts.attachments.show', ['expense', $expense->id]) }}">Download Bill</a>@endif</td>

                        <td>
                            <strong>
                                {{ number_format($expense->amount_cents / 100, 2) }}
                            </strong>
                        </td>

                        <td>{{ $expense->payment_method }}</td>

                        <td>{{ $expense->reference ?: '—' }}</td>

                        <td>
                            {{ $expense->creator?->name ?? 'Unavailable' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">
                            <div class="student-form">
                                <strong>No expenses recorded yet.</strong>
                                <p class="photo-help">
                                    Click Add Expense to record your first expense.
                                </p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>

            @if($expenses->count())
                <tfoot>
                    <tr>
                        <td colspan="4">
                            <strong>This page total</strong>
                        </td>

                        <td>
                            <strong>
                                {{ number_format($expenses->sum('amount_cents') / 100, 2) }}
                            </strong>
                        </td>

                        <td colspan="3"></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    <div class="student-pagination">
        {{ $expenses->links() }}
    </div>
</section>
@endsection
