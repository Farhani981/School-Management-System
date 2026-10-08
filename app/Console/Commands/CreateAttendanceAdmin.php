<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateAttendanceAdmin extends Command
{
    protected $signature = 'attendance:admin {email} {--name=School Admin}';

    protected $description = 'Create an Admin account for attendance with a securely prompted password';

    public function handle(): int
    {
        $email = $this->argument('email');
        if (User::where('email', $email)->exists()) {
            $this->error('That account already exists. Choose a new email; existing accounts are preserved.');

            return self::FAILURE;
        }
        $password = $this->secret('Password (at least 8 characters)');
        $confirm = $this->secret('Confirm password');
        $validator = Validator::make(['email' => $email, 'password' => $password, 'password_confirmation' => $confirm], ['email' => 'required|email', 'password' => 'required|string|min:8|confirmed']);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }
        User::create(['name' => $this->option('name'), 'email' => $email, 'password' => $password, 'role' => 'Admin']);
        $this->info('Admin created. Sign in at /login.');

        return self::SUCCESS;
    }
}
