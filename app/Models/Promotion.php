<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    public const ACTIONS = ['promote' => 'Promote', 'detain' => 'Detain', 'left' => 'Left', 'passed_out' => 'Passed out'];
    protected $guarded = ['id'];
    protected $casts = ['date' => 'date'];
    public function student() { return $this->belongsTo(Student::class)->withTrashed(); }
    public function actor() { return $this->belongsTo(User::class, 'promoted_by'); }
}
