<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Table;
use App\Models\Menu;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Users
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

        // Tables
        $tables = [
            ['table_number' => 'T01', 'capacity' => 2, 'status' => 'available'],
            ['table_number' => 'T02', 'capacity' => 2, 'status' => 'available'],
            ['table_number' => 'T03', 'capacity' => 4, 'status' => 'available'],
            ['table_number' => 'T04', 'capacity' => 4, 'status' => 'available'],
            ['table_number' => 'T05', 'capacity' => 4, 'status' => 'available'],
            ['table_number' => 'T06', 'capacity' => 6, 'status' => 'available'],
            ['table_number' => 'T07', 'capacity' => 6, 'status' => 'available'],
            ['table_number' => 'T08', 'capacity' => 8, 'status' => 'available'],
            ['table_number' => 'T09', 'capacity' => 4, 'status' => 'available'],
            ['table_number' => 'T10', 'capacity' => 4, 'status' => 'available'],
        ];

        foreach ($tables as $table) {
            Table::create($table);
        }

        // Menus
        $menus = [
            ['name' => 'Nasi Goreng Spesial', 'description' => 'Nasi goreng dengan telur, ayam, dan sayuran', 'price' => 25000, 'category' => 'makanan', 'is_available' => true],
            ['name' => 'Mie Goreng', 'description' => 'Mie goreng dengan sayuran segar', 'price' => 20000, 'category' => 'makanan', 'is_available' => true],
            ['name' => 'Ayam Geprek', 'description' => 'Ayam goreng geprek dengan sambal pedas', 'price' => 30000, 'category' => 'makanan', 'is_available' => true],
            ['name' => 'Soto Ayam', 'description' => 'Soto ayam dengan kuah bening', 'price' => 22000, 'category' => 'makanan', 'is_available' => true],
            ['name' => 'Gado-Gado', 'description' => 'Sayuran dengan bumbu kacang', 'price' => 18000, 'category' => 'makanan', 'is_available' => true],
            ['name' => 'Es Teh Manis', 'description' => 'Teh manis dingin segar', 'price' => 5000, 'category' => 'minuman', 'is_available' => true],
            ['name' => 'Es Jeruk', 'description' => 'Jus jeruk segar', 'price' => 8000, 'category' => 'minuman', 'is_available' => true],
            ['name' => 'Kopi Hitam', 'description' => 'Kopi hitam original', 'price' => 10000, 'category' => 'minuman', 'is_available' => true],
            ['name' => 'Cappuccino', 'description' => 'Kopi dengan foam susu', 'price' => 18000, 'category' => 'minuman', 'is_available' => true],
            ['name' => 'Thai Tea', 'description' => 'Teh Thailand dengan susu', 'price' => 12000, 'category' => 'minuman', 'is_available' => true],
            ['name' => 'Kentang Goreng', 'description' => 'French fries dengan saus', 'price' => 15000, 'category' => 'snack', 'is_available' => true],
            ['name' => 'Tahu Isi', 'description' => 'Tahu dengan isian sayuran', 'price' => 12000, 'category' => 'snack', 'is_available' => true],
            ['name' => 'Pisang Goreng', 'description' => 'Pisang goreng crispy', 'price' => 10000, 'category' => 'snack', 'is_available' => true],
            ['name' => 'Es Krim Vanilla', 'description' => 'Es krim vanilla premium', 'price' => 15000, 'category' => 'dessert', 'is_available' => true],
            ['name' => 'Puding Coklat', 'description' => 'Puding coklat lembut', 'price' => 12000, 'category' => 'dessert', 'is_available' => true],
        ];

        foreach ($menus as $menu) {
            Menu::create($menu);
        }
    }
}
