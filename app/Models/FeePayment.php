<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FeePayment extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['payment_date' => 'date', 'reversed_at' => 'datetime', 'amount_cents' => 'integer', 'fine_cents' => 'integer', 'balance_after_cents' => 'integer']; }
    public function voucher() { return $this->belongsTo(FeeVoucher::class, 'fee_voucher_id'); }
    public function receiver() { return $this->belongsTo(User::class, 'received_by'); }
    public function reverser() { return $this->belongsTo(User::class, 'reversed_by'); }
}
