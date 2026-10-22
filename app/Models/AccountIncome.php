<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountIncome extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'income_date' => 'date'];
    }
}
