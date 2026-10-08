<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LibraryTransaction extends Model
{
    protected $fillable = ['member_id', 'book_id', 'book_copy_id', 'active_copy_id', 'issue_date', 'due_date', 'return_date', 'status',
        'return_condition', 'notes', 'return_notes', 'created_by', 'returned_by'];

    protected function casts(): array
    {
        return ['issue_date' => 'date', 'due_date' => 'date', 'return_date' => 'date'];
    }

    public function member()
    {
        return $this->belongsTo(LibraryMember::class, 'member_id');
    }

    public function book()
    {
        return $this->belongsTo(LibraryBook::class, 'book_id');
    }

    public function copy()
    {
        return $this->belongsTo(LibraryBookCopy::class, 'book_copy_id');
    }

    public function issuer()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function getDaysLateAttribute(): int
    {
        return max(0, (int) $this->due_date->diffInDays($this->return_date ?? today(), false));
    }

    public function getDisplayStatusAttribute(): string
    {
        return $this->status === 'Issued' && $this->due_date->lt(today()) ? 'Overdue' : $this->status;
    }
}
