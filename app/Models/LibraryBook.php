<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LibraryBook extends Model
{
    protected $fillable = ['title', 'isbn', 'category_id', 'author_id', 'publisher_id', 'edition', 'publication_year', 'language', 'pages', 'description', 'cover', 'shelf', 'rack', 'location', 'price', 'purchase_date', 'supplier', 'status'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'purchase_date' => 'date'];
    }

    public function category()
    {
        return $this->belongsTo(LibraryCategory::class, 'category_id');
    }

    public function author()
    {
        return $this->belongsTo(LibraryAuthor::class, 'author_id');
    }

    public function publisher()
    {
        return $this->belongsTo(LibraryPublisher::class, 'publisher_id');
    }

    public function copies()
    {
        return $this->hasMany(LibraryBookCopy::class, 'book_id');
    }

    public function scopeWithInventoryCounts($query): void
    {
        $query->select('library_books.*');
        foreach (['copies_count' => null, 'available_count' => 'Available', 'issued_count' => 'Issued', 'lost_count' => 'Lost', 'damaged_count' => 'Damaged'] as $alias => $status) {
            if (Schema::hasTable('library_book_copies')) {
                $copies = DB::table('library_book_copies')->selectRaw('COUNT(*)')->whereColumn('book_id', 'library_books.id');
                if ($status) {
                    $copies->where('status', $status);
                }
                $query->selectSub($copies, $alias);
            } else {
                $query->selectRaw('0 as '.$alias);
            }
        }
    }
}
