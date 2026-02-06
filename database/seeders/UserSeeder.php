<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Budi Pelayan', 'email' => 'pelayan1@restaurant.com', 'role' => 'pelayan'],
            ['name' => 'Siti Pelayan', 'email' => 'pelayan2@restaurant.com', 'role' => 'pelayan'],
            ['name' => 'Andi Kasir', 'email' => 'kasir1@restaurant.com', 'role' => 'kasir'],
            ['name' => 'Dewi Kasir', 'email' => 'kasir2@restaurant.com', 'role' => 'kasir'],
        ];

        foreach ($users as $userData) {
            User::create(array_merge($userData, [
                'password' => Hash::make('password'),
            ]));
        }
    }
}
