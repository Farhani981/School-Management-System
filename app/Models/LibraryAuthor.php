<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LibraryAuthor extends Model
{
    protected $fillable = ['name', 'biography', 'email', 'phone', 'country', 'status'];

    public function books()
    {
        return $this->hasMany(LibraryBook::class, 'author_id');
    }
}
