<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeeType extends Model
{
    use SoftDeletes;

    public const FREQUENCIES = ['one_time' => 'One time', 'monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'yearly' => 'Yearly'];

    protected $fillable = ['name', 'frequency', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function structures()
    {
        return $this->hasMany(FeeStructure::class);
    }

    public function studentDiscounts()
    {
        return $this->hasMany(StudentDiscount::class);
    }

    public function overrides()
    {
        return $this->hasMany(StudentFeeOverride::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
