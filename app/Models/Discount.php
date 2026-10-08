<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    protected $fillable = ['name', 'type', 'value', 'is_active'];

    protected $casts = ['value' => 'decimal:2', 'is_active' => 'boolean'];

    public function assignments()
    {
        return $this->hasMany(StudentDiscount::class);
    }
}
