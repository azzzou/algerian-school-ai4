<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DefaultAdminSeeder extends Seeder
{
    /**
     * Ensure admin account(s) exist to match THIS template's authentication
     * system and remain reachable on every startup (e.g. Render free tier,
     * no shell access).
     *
     * Template's out-of-the-box super admin (per UsersTableSeeder):
     *   email: cj@cj.com, password: cj, username: cj, user_type: super_admin
     *
     * Uses updateOrCreate keyed on email, so it never duplicates rows and
     * always refreshes the password hash to the expected value — self-healing
     * if a full db:seed (UsersTableSeeder) later wipes or overwrites the row.
     *
     * @return void
     */
    public function run()
    {
        $accounts = [
            // The template's default super admin — exact match.
            ['email'    => 'cj@cj.com',
             'username' => 'cj',
             'name'     => 'CJ Inspired',
             'password' => 'cj',
             'user_type'=> 'super_admin'],

            // Convenience alias with an easy-to-remember password.
            ['email'    => 'admin@school.com',
             'username' => 'super_admin',
             'name'     => 'System Administrator',
             'password' => 'password',
             'user_type'=> 'super_admin'],
        ];

        foreach ($accounts as $a) {
            $user = User::updateOrCreate(
                ['email' => $a['email']],
                [
                    'name'      => $a['name'],
                    'username'  => $a['username'],
                    'user_type' => $a['user_type'],
                    'code'      => strtoupper(Str::random(10)),
                ]
            );

            // Guarantee the intended password (avoid re-hashing unnecessarily).
            if (! app('hash')->check($a['password'], $user->password)) {
                $user->update(['password' => Hash::make($a['password'])]);
            }

            $this->command->info("Default admin ensured: {$a['email']} / {$a['password']}");
        }
    }
}