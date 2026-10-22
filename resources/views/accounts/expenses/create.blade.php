@extends('layouts.admin')

@section('title', 'Add Expense')

@section('content')
@include('accounts.nav')
<div class="page-heading">
    <div>
        <div class="breadcrumb">Accounts / Expenses</div>
        <h1>Add Expense</h1>
        <p>Record a school expense and its payment details.</p>
    </div>

    <a class="button-secondary"
       href="{{ route('accounts.expenses.index') }}">
        Back to Expenses
    </a>
</div>

@include('students.partials.feedback')

<form method="POST"
      enctype="multipart/form-data"
      action="{{ route('accounts.expenses.store') }}"
      class="panel student-form">
    @csrf

    <div class="student-form-grid">
        <div class="admission-field"><label for="expense-title">Expense Title *</label><input id="expense-title" name="title" value="{{ old('title') }}" maxlength="255" required></div>
        <div class="admission-field"><label for="expense-paid-to">Paid To *</label><input id="expense-paid-to" name="paid_to" value="{{ old('paid_to') }}" maxlength="255" required></div>
        <div class="admission-field"><label for="expense-attachment">Bill / Attachment (PDF, JPG, PNG; max 5 MB)</label><input id="expense-attachment" name="attachment" type="file" accept=".pdf,.jpg,.jpeg,.png"></div>
        <div class="admission-field">
            <label for="expense-category">Category *</label>
            <input
                id="expense-category"
                name="category"
                list="expense-categories"
                value="{{ old('category') }}"
                maxlength="100"
                placeholder="Select or enter category"
                required
            >

            <datalist id="expense-categories">
                <option value="Rent">
                <option value="Electricity">
                <option value="Stationery">
                <option value="Maintenance">
                <option value="Transport">
                <option value="Internet">
                <option value="Other">
            </datalist>
        </div>

        <div class="admission-field">
            <label for="expense-amount">Amount (PKR) *</label>
            <input
                id="expense-amount"
                type="number"
                name="amount"
                value="{{ old('amount') }}"
                min="0.01"
                max="99999999.99"
                step="0.01"
                placeholder="0.00"
                required
            >
        </div>

        <div class="admission-field">
            <label for="expense-date">Expense Date *</label>
            <input
                id="expense-date"
                type="date"
                name="expense_date"
                value="{{ old('expense_date', today()->toDateString()) }}"
                max="{{ today()->toDateString() }}"
                required
            >
        </div>

        <div class="admission-field">
            <label for="expense-method">Payment Method *</label>
            <select id="expense-method" name="payment_method" required>
                <option value="">Select payment method</option>

                @foreach(['Cash', 'Bank Transfer', 'Cheque'] as $method)
                    <option
                        value="{{ $method }}"
                        @selected(old('payment_method') === $method)
                    >
                        {{ $method }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="admission-field">
            <label for="expense-reference">Reference (optional)</label>
            <input
                id="expense-reference"
                name="reference"
                value="{{ old('reference') }}"
                maxlength="150"
                placeholder="Receipt or transaction number"
            >
        </div>

        <div class="admission-field">
            <label for="expense-description">Description *</label>
            <textarea
                id="expense-description"
                name="description"
                rows="4"
                maxlength="5000"
                placeholder="Describe the expense"
                required
            >{{ old('description') }}</textarea>
        </div>
    </div>

    <p class="photo-help">
        Salary payments are tracked through Payroll.
        Avoid recording the same payment twice.
    </p>

    <div class="form-actions">
        <a class="button-secondary"
           href="{{ route('accounts.expenses.index') }}">
            Cancel
        </a>

        <button type="submit" class="button-primary">
            Save Expense
        </button>
    </div>
</form>
@endsection
