<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $menus = [
            // Makanan
            [
                'name' => 'Nasi Goreng Spesial',
                'description' => 'Nasi goreng dengan telur, ayam, dan sayuran',
                'price' => 25000,
                'category' => 'makanan',
                'is_available' => true,
            ],
            [
                'name' => 'Mie Goreng',
                'description' => 'Mie goreng dengan sayuran segar',
                'price' => 20000,
                'category' => 'makanan',
                'is_available' => true,
            ],
            [
                'name' => 'Ayam Geprek',
                'description' => 'Ayam goreng geprek dengan sambal pedas',
                'price' => 30000,
                'category' => 'makanan',
                'is_available' => true,
            ],
            [
                'name' => 'Soto Ayam',
                'description' => 'Soto ayam dengan kuah bening',
                'price' => 22000,
                'category' => 'makanan',
                'is_available' => true,
            ],
            [
                'name' => 'Gado-Gado',
                'description' => 'Sayuran dengan bumbu kacang',
                'price' => 18000,
                'category' => 'makanan',
                'is_available' => true,
            ],

            // Minuman
            [
                'name' => 'Es Teh Manis',
                'description' => 'Teh manis dingin segar',
                'price' => 5000,
                'category' => 'minuman',
                'is_available' => true,
            ],
            [
                'name' => 'Es Jeruk',
                'description' => 'Jus jeruk segar',
                'price' => 8000,
                'category' => 'minuman',
                'is_available' => true,
            ],
            [
                'name' => 'Kopi Hitam',
                'description' => 'Kopi hitam original',
                'price' => 10000,
                'category' => 'minuman',
                'is_available' => true,
            ],
            [
                'name' => 'Cappuccino',
                'description' => 'Kopi dengan foam susu',
                'price' => 18000,
                'category' => 'minuman',
                'is_available' => true,
            ],
            [
                'name' => 'Thai Tea',
                'description' => 'Teh Thailand dengan susu',
                'price' => 12000,
                'category' => 'minuman',
                'is_available' => true,
            ],

            // Snack
            [
                'name' => 'Kentang Goreng',
                'description' => 'French fries dengan saus',
                'price' => 15000,
                'category' => 'snack',
                'is_available' => true,
            ],
            [
                'name' => 'Tahu Isi',
                'description' => 'Tahu dengan isian sayuran',
                'price' => 12000,
                'category' => 'snack',
                'is_available' => true,
            ],
            [
                'name' => 'Pisang Goreng',
                'description' => 'Pisang goreng crispy',
                'price' => 10000,
                'category' => 'snack',
                'is_available' => true,
            ],

            // Dessert
            [
                'name' => 'Es Krim Vanilla',
                'description' => 'Es krim vanilla premium',
                'price' => 15000,
                'category' => 'dessert',
                'is_available' => true,
            ],
            [
                'name' => 'Puding Coklat',
                'description' => 'Puding coklat lembut',
                'price' => 12000,
                'category' => 'dessert',
                'is_available' => true,
            ],
        ];

        foreach ($menus as $menu) {
            Menu::create($menu);
        }
    }
}
