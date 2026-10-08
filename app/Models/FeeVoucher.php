<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Services\FeeBillingService;

class FeeVoucher extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['due_date' => 'date', 'settled_on' => 'date', 'cancelled_at' => 'datetime', 'total_cents' => 'integer', 'subtotal_cents' => 'integer', 'discount_cents' => 'integer', 'fine_amount_cents' => 'integer', 'fine_cap_cents' => 'integer', 'grace_days' => 'integer']; }
    public function student() { return $this->belongsTo(Student::class)->withTrashed(); }
    public function items() { return $this->hasMany(FeeVoucherItem::class); }
    public function payments() { return $this->hasMany(FeePayment::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function getBalanceCentsAttribute(): int { return app(FeeBillingService::class)->balance($this); }
    public function getDisplayStatusAttribute(): string {
        if ($this->cancelled_at) return 'Cancelled';
        if ($this->balance_cents === 0) return 'Paid';
        if ($this->due_date->lt(today())) return 'Overdue';
        return $this->payments->whereNull('reversed_at')->sum('amount_cents') > 0 ? 'Partial' : 'Unpaid';
    }
}
