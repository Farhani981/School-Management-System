<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FeeVoucherItem extends Model
{
    protected $guarded = [];
    public function voucher() { return $this->belongsTo(FeeVoucher::class, 'fee_voucher_id'); }
}
