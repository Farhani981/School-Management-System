<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradeScale extends Model
{
    protected $fillable = [
        'min_percent',
        'max_percent',
        'grade',
        'remark',
        'gpa',
    ];

    protected function casts(): array
    {
        return [
            'min_percent' => 'decimal:2',
            'max_percent' => 'decimal:2',
            'gpa' => 'decimal:2',
        ];
    }

    /**
     * Determine the grade record for a given percentage.
     * Grades are evaluated strictly from the grade_scales table.
     */
    public static function forPercentage(?float $percentage): ?self
    {
        if ($percentage === null) {
            return null;
        }

        // Clamp percentage for evaluation
        $pct = round($percentage, 2);

        return static::query()
            ->where('min_percent', '<=', $pct)
            ->where('max_percent', '>=', $pct)
            ->orderByDesc('min_percent')
            ->first();
    }
}
