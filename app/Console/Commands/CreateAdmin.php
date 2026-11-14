<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin';

    protected $description = 'Securely provision a new administrator using an interactive password prompt';

    public function handle(): int
    {
        $data = [
            'name' => $this->ask('Full name'),
            'email' => strtolower(trim((string) $this->ask('Email address'))),
            'password' => $this->secret('Password (at least 12 characters, mixed case, numbers and symbols)'),
        ];
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        $user = new User($data);
        $user->role = UserRole::Admin;
        $user->email_verified_at = Carbon::now();
        $user->save();
        $this->info('Administrator created successfully.');

        return self::SUCCESS;
    }
}
