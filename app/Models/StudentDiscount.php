<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentDiscount extends Model
{
    protected $fillable = ['student_id', 'discount_id', 'fee_type_id', 'valid_from', 'valid_to'];

    protected $casts = ['valid_from' => 'date', 'valid_to' => 'date'];

    public function student()
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function discount()
    {
        return $this->belongsTo(Discount::class);
    }

    public function feeType()
    {
        return $this->belongsTo(FeeType::class);
    }
}
