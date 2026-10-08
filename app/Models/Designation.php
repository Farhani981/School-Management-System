<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Designation extends Model
{
    protected $guarded = [];

    public function teachers()
    {
        return $this->hasMany(Teacher::class);
    }
}
