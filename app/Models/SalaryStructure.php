<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryStructure extends Model
{
    public const ALLOWANCE_FIELDS = ['house_allowance', 'medical_allowance', 'transport_allowance', 'other_allowance'];

    protected $fillable = ['teacher_id', 'basic_salary', 'house_allowance', 'medical_allowance', 'transport_allowance', 'other_allowance', 'fixed_deductions', 'bank_name', 'account_no', 'allowances', 'deductions'];

    protected function casts(): array
    {
        return array_fill_keys(['basic_salary', ...self::ALLOWANCE_FIELDS, 'fixed_deductions', 'allowances', 'deductions'], 'decimal:2');
    }

    public function getTotalAllowancesAttribute(): float
    {
        return array_sum(array_map(fn ($field) => (int) round((float) $this->$field * 100), self::ALLOWANCE_FIELDS)) / 100;
    }

    public function getGrossSalaryAttribute(): float
    {
        return ((int) round((float) $this->basic_salary * 100) + (int) round($this->total_allowances * 100)) / 100;
    }

    public function getNetSalaryAttribute(): float
    {
        return ((int) round($this->gross_salary * 100) - (int) round((float) $this->fixed_deductions * 100)) / 100;
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class)->withTrashed();
    }
}
