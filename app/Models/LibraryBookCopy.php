<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LibraryBookCopy extends Model
{
    public const CONDITIONS = ['New', 'Good', 'Fair', 'Damaged'];

    protected $fillable = ['book_id', 'accession_number', 'barcode', 'shelf', 'rack', 'condition', 'status'];

    public function book()
    {
        return $this->belongsTo(LibraryBook::class, 'book_id');
    }

    public function transactions()
    {
        return $this->hasMany(LibraryTransaction::class, 'book_copy_id');
    }
}
