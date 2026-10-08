<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentHistory extends Model
{
    protected $fillable = ['event', 'changes', 'notes'];
    protected $casts = ['changes' => 'array'];
}
