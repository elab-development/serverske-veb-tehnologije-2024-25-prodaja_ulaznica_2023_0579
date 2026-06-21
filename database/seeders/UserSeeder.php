<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@tickets.test'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        $users = [
            ['name' => 'Marko Petrovic', 'email' => 'marko.petrovic@example.com'],
            ['name' => 'Jovana Ilic', 'email' => 'jovana.ilic@example.com'],
            ['name' => 'Nikola Savic', 'email' => 'nikola.savic@example.com'],
        ];

        foreach ($users as $user) {
            User::query()->updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => Hash::make('password'),
                    'role' => 'user',
                    'email_verified_at' => now(),
                ]
            );
        }

        User::factory()
            ->count(7)
            ->create();
    }
}