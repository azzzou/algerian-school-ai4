<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DefaultAdminSeeder extends Seeder
{
    /**
     * Ensure a guaranteed admin account exists so login always works
     * (e.g. Render free tier, no shell access).
     *
     * Target account: username=admin, email=admin@school.com,
     * password=password (hashed), user_type=super_admin.
     *
     * Uses updateOrCreate-style logic keyed on email, falling back to username,
     * so it cleans up previously-seeded variants and never leaves a duplicate
     * (both email and username are UNIQUE on the users table).
     *
     * @return void
     */
    public function run()
    {
        $email    = 'admin@school.com';
        $username = 'admin';
        $password = 'password';

        $data = [
            'name'       => 'System Administrator',
            'email'      => $email,
            'username'   => $username,
            'user_type'  => 'super_admin',
            'password'   => Hash::make($password),
            'code'       => strtoupper(Str::random(10)),
        ];

        // Prefer the row by email; otherwise reuse a row that already claims
        // the "admin" username (so we don't trip the UNIQUE(username) index).
        $admin = User::where('email', $email)->first()
                 ?? User::where('username', $username)->first();

        if ($admin) {
            // Update the existing row in place (preserve its unique `code`).
            unset($data['code']);
            $admin->update($data);
        } else {
            User::create($data);
        }

        $this->command->info("Admin ensured: {$email} / {$password} (username: {$username})");
    }
}