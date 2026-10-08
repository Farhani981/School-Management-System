<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_paid' => 'boolean', 'annual_quota' => 'decimal:2', 'max_consecutive_days' => 'decimal:2', 'min_notice_days' => 'integer', 'requires_attachment' => 'boolean', 'allow_half_day' => 'boolean', 'allow_backdated' => 'boolean', 'is_active' => 'boolean'];
    }

    public function requests()
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
