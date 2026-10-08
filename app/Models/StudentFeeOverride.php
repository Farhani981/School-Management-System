<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentFeeOverride extends Model
{
    protected $fillable = ['student_id', 'fee_type_id', 'amount', 'reason'];

    protected $casts = ['amount' => 'decimal:2'];

    public function student()
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function feeType()
    {
        return $this->belongsTo(FeeType::class);
    }
}
