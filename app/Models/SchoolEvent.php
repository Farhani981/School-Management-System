<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolEvent extends Model
{
    protected $table = 'events';

    protected $fillable = ['title', 'description', 'date', 'end_date', 'type', 'color'];

    protected $casts = ['date' => 'date', 'end_date' => 'date'];
}
