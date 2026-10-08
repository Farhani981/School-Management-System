<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The project's original users/session/cache migrations are absent on fresh installs.
        if (! Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $t) {
                $t->string('id')->primary();
                $t->foreignId('user_id')->nullable()->index();
                $t->string('ip_address', 45)->nullable();
                $t->text('user_agent')->nullable();
                $t->longText('payload');
                $t->integer('last_activity')->index();
            });
        }
        if (! Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $t) {
                $t->string('key')->primary();
                $t->mediumText('value');
                $t->integer('expiration')->index();
            });
        }
        if (! Schema::hasTable('cache_locks')) {
            Schema::create('cache_locks', function (Blueprint $t) {
                $t->string('key')->primary();
                $t->string('owner');
                $t->integer('expiration')->index();
            });
        }
    }

    public function down(): void
    {
        // Shared login/cache infrastructure may predate attendance; preserve it.
    }
};
