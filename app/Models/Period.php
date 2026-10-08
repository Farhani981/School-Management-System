<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Period extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'is_break',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_break' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc');
    }

    public function timetableEntries(): HasMany
    {
        return $this->hasMany(TimetableEntry::class, 'period_id');
    }

    public function getFormattedStartTimeAttribute(): string
    {
        return Carbon::parse($this->start_time)->format('g:i A');
    }

    public function getFormattedEndTimeAttribute(): string
    {
        return Carbon::parse($this->end_time)->format('g:i A');
    }

    public function getFormattedTimeRangeAttribute(): string
    {
        return $this->formatted_start_time . ' – ' . $this->formatted_end_time;
    }

    public function getDurationMinutesAttribute(): int
    {
        $start = Carbon::parse($this->start_time);
        $end = Carbon::parse($this->end_time);
        return (int) $start->diffInMinutes($end);
    }

    /**
     * Check if a given start and end time overlaps with any existing active period.
     */
    public static function hasOverlap(string $start, string $end, ?int $excludeId = null): bool
    {
        $startFormatted = Carbon::parse($start)->format('H:i:s');
        $endFormatted = Carbon::parse($end)->format('H:i:s');

        return static::where('is_active', true)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->where(function ($query) use ($startFormatted, $endFormatted) {
                $query->where(function ($q) use ($startFormatted, $endFormatted) {
                    $q->whereTime('start_time', '<', $endFormatted)
                      ->whereTime('end_time', '>', $startFormatted);
                });
            })
            ->exists();
    }
}
