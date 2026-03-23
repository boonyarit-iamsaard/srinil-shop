<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

final class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $email = env('ADMIN_EMAIL');
        $name = env('ADMIN_NAME');
        $password = env('ADMIN_PASSWORD');

        if (! $email || ! $name || ! $password) {
            $this->command->warn('AdminSeeder skipped: ADMIN_EMAIL, ADMIN_NAME, and ADMIN_PASSWORD must all be set.');

            return;
        }

        if (User::where('email', $email)->exists()) {
            $this->command->info("AdminSeeder skipped: admin user [{$email}] already exists.");

            return;
        }

        User::factory()->admin()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'email_verified_at' => now(),
        ]);
    }
}
