<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DefaultAdminSeeder extends Seeder
{
    /**
     * Ensure a guaranteed super-admin account exists with a known password so
     * the login page is always reachable (e.g. Render free tier, no shell).
     *
     * Uses updateOrCreate by email, so it never duplicates and always refreshes
     * the password hash to the intended value — self-healing if a full
     * db:seed (UsersTableSeeder) later wipes or overwrites the row.
     *
     * @return void
     */
    public function run()
    {
        list($email, $username) = ['admin@school.com', 'super_admin'];

        $admin = User::updateOrCreate(
            ['email'        => $email],
            [
                'name'       => 'System Administrator',
                'username'   => $username,
                'user_type'  => 'super_admin',
                'code'       => strtoupper(Str::random(10)),
            ]
        );

        if (! Hash::check('password', $admin->password)) {
            $admin->update(['password' => Hash::make('password')]);
        }

        $this->command->info("Default admin ensured: {$email} / password");
    }
}