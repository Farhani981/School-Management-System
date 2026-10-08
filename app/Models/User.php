<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, \Spatie\Permission\Traits\HasRoles;

    protected static function booted(): void
    {
        static::saved(function (User $user) {
            if (\Illuminate\Support\Facades\Schema::hasTable('roles') && ($user->wasRecentlyCreated || $user->wasChanged('role'))) {
                $role = match (strtolower($user->role ?? '')) {
                    'admin', 'administrator' => 'Admin', 'principal' => 'Principal', 'teacher' => 'Teacher', 'student'=>'Student', 'parent'=>'Parent', default => null,
                };
                if ($role) {
                    $user->syncRoles(\Spatie\Permission\Models\Role::findOrCreate($role, 'web'));
                } elseif ($user->wasChanged('role')) {
                    $user->syncRoles([]);
                }
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function teacher()
    {
        return $this->hasOne(Teacher::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
