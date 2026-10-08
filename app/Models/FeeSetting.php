<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeeSetting extends Model
{
    public $incrementing = false;

    protected $fillable = ['due_day', 'fine_type', 'fine_amount', 'grace_days', 'fine_max_cap', 'voucher_prefix', 'receipt_prefix', 'sibling_discount_enabled', 'sibling_discount_type', 'sibling_discount_value'];

    protected $casts = ['fine_amount' => 'decimal:2', 'fine_max_cap' => 'decimal:2', 'sibling_discount_value' => 'decimal:2', 'sibling_discount_enabled' => 'boolean', 'due_day' => 'integer', 'grace_days' => 'integer'];
}
