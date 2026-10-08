<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['Admin', 'Principal', 'Teacher'] as $name) {
            Role::findOrCreate($name, 'web');
        }
        DB::table('users')->orderBy('id')->chunkById(100, function ($users) {
            foreach ($users as $user) {
                $role = match (strtolower($user->role ?? '')) {
                    'admin', 'administrator' => 'Admin', 'principal' => 'Principal', 'teacher' => 'Teacher', default => null,
                };
                if ($role) {
                    DB::table('model_has_roles')->insertOrIgnore(['role_id' => Role::findByName($role, 'web')->id, 'model_type' => App\Models\User::class, 'model_id' => $user->id]);
                }
            }
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Existing user role values remain intact; permission-table rollback removes pivots.
    }
};
