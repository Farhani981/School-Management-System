<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LibraryPublisher extends Model
{
    protected $fillable = ['name', 'contact_person', 'email', 'phone', 'address', 'website', 'status'];

    public function books()
    {
        return $this->hasMany(LibraryBook::class, 'publisher_id');
    }
}
