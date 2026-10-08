<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimetableOverride extends Model
{
    use HasFactory;

    public const TYPE_SUBSTITUTE = 'substitute';
    public const TYPE_CANCELLED = 'cancelled';
    public const TYPE_SWAPPED = 'swapped';

    public const TYPES = [
        self::TYPE_SUBSTITUTE => 'Substitute Teacher',
        self::TYPE_CANCELLED => 'Cancelled Period',
        self::TYPE_SWAPPED => 'Swapped Period',
    ];

    protected $fillable = [
        'entry_id',
        'date',
        'type',
        'substitute_teacher_id',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(TimetableEntry::class, 'entry_id');
    }

    public function substituteTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'substitute_teacher_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }
}
