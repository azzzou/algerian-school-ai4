<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DefaultAdminSeeder extends Seeder
{
    /**
     * Seed a guaranteed super-admin account so the app is always reachable
     * (e.g. first boot on Render before the full dataset is seeded).
     *
     * Idempotent: uses firstOrCreate by email, so it never wipes or duplicates
     * existing users.
     *
     * @return void
     */
    public function run()
    {
        list($email, $username) = ['admin@school.com', 'admin'];

        if (User::where('email', $email)->exists()) {
            $this->command->info("Default admin already exists ({$email}); skipping.");
            return;
        }

        User::create([
            'name'       => 'System Administrator',
            'email'      => $email,
            'username'   => $username,
            'password'   => Hash::make('password'),
            'user_type'  => 'super_admin',
            'code'       => strtoupper(Str::random(10)),
        ]);

        $this->command->info("Default admin created: {$email} / password");
    }
}