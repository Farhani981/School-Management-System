<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveRequestLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['request_id', 'action', 'actor_id', 'note', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function request()
    {
        return $this->belongsTo(LeaveRequest::class, 'request_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
