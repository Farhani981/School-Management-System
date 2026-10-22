<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccountExpenseRequest;
use App\Models\AccountExpense;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAccess($request);

        $expenses = AccountExpense::with('creator')
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate(20);

        return view('accounts.expenses.index', compact('expenses'));
    }

    public function create(Request $request)
    {
        $this->authorizeAccess($request);

        return view('accounts.expenses.create');
    }

    public function store(AccountExpenseRequest $request)
    {
        $data = $request->validated();

        // Convert rupees to paisa without floating-point calculations.
        [$rupees, $paisa] = array_pad(
            explode('.', (string) $data['amount'], 2),
            2,
            ''
        );

        $amountCents = ((int) $rupees * 100)
            + (int) str_pad($paisa, 2, '0');

        AccountExpense::create([
            'title' => $data['title'],
            'paid_to' => $data['paid_to'],
            'attachment_path' => $request->file('attachment')?->store('accounts/expenses', 'local'),
            'category' => $data['category'],
            'description' => $data['description'],
            'amount_cents' => $amountCents,
            'expense_date' => $data['expense_date'],
            'payment_method' => $data['payment_method'],
            'reference' => $data['reference'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('accounts.expenses.index')
            ->with('success', 'Expense added successfully.');
    }

    private function authorizeAccess(Request $request): void
    {
        abort_unless(
            $request->user()?->hasAnyRole(['Admin', 'Principal']),
            403
        );
    }
}
