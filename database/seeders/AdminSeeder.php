<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // This deliberately weak development account must never be seeded on a server.
        if (! app()->environment('local', 'testing')) {
            $this->command?->warn('Default admin seeding is available only in local/testing environments.');

            return;
        }

        $user = User::firstOrCreate(
            ['email' => 'admin@csiti.my'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('123..'),
                'role' => UserRole::Admin,
                'email_verified_at' => now(),
            ],
        );

        if ($user->role !== UserRole::Admin) {
            throw new RuntimeException('The default admin email already belongs to a non-admin account. No changes were made.');
        }
    }
}
