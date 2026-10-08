<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountExpense extends Model
{
    protected $table = 'expenses_tables';

    protected $fillable = [
        'category',
        'description',
        'amount_cents',
        'expense_date',
        'payment_method',
        'reference',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'expense_date' => 'date',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}