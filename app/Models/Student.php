<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use SoftDeletes;

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function section()
    {
        return $this->belongsTo(SchoolSection::class, 'section_id');
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(Promotion::class);
    }

    public const STATUSES = ['Active', 'Inactive', 'Graduated', 'Left School', 'Left', 'Passed out'];

    public const RELATIONSHIPS = ['Father', 'Mother', 'Uncle', 'Brother', 'Other'];

    public function siblings()
    {
        return static::query()->whereKeyNot($this->id)->where(function ($query) {
            if ($this->family_id) {
                $query->where('family_id', $this->family_id);
            }
            $query->orWhere(function ($match) {
                $match->whereRaw('LOWER(TRIM(father_name)) = ?', [mb_strtolower(trim($this->father_name))])->where('phone', $this->phone);
            });
        });
    }

    protected $fillable = [
        'admission_no', 'first_name', 'last_name', 'father_name',
        'date_of_birth', 'gender', 'phone', 'address', 'admission_date',
        'class_id', 'section_id', 'roll_number', 'photo', 'status',
        'b_form_number', 'mother_name', 'guardian_name', 'guardian_relationship',
        'guardian_phone', 'emergency_contact', 'permanent_address', 'academic_session_id', 'family_id',
        'user_id', 'email', 'previous_school', 'last_class', 'leaving_reason',
        'blood_group', 'allergies', 'height', 'weight', 'remarks',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function session()
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function academicSession()
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth ? (int) $this->date_of_birth->age : null;
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    protected $casts = [
        'date_of_birth' => 'date',
        'admission_date' => 'date',
    ];

    public function attendances(): HasMany
    {
        return $this->hasMany(StudentAttendance::class);
    }

    public function feeDiscounts(): HasMany
    {
        return $this->hasMany(StudentDiscount::class);
    }

    public function feeOverrides(): HasMany
    {
        return $this->hasMany(StudentFeeOverride::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(StudentDocument::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(StudentHistory::class);
    }
}
