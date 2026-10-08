<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeeStructure extends Model
{
    protected $fillable = ['academic_session_id', 'school_class_id', 'fee_type_id', 'amount'];

    protected $casts = ['amount' => 'decimal:2'];

    public function academicSession()
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function feeType()
    {
        return $this->belongsTo(FeeType::class);
    }
}
