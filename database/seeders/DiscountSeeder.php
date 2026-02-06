<?php

namespace Database\Seeders;

use App\Models\Discount;
use Illuminate\Database\Seeder;

class DiscountSeeder extends Seeder
{
    public function run(): void
    {
        $discounts = [
            [
                'code' => 'WELCOME10',
                'name' => 'Welcome Discount 10%',
                'type' => 'percentage',
                'value' => 10,
                'min_purchase' => 50000,
                'max_discount' => 20000,
                'is_active' => true,
                'valid_from' => now(),
                'valid_until' => now()->addMonths(3),
                'usage_limit' => 100,
            ],
            [
                'code' => 'PROMO50K',
                'name' => 'Fixed Discount 50K',
                'type' => 'fixed',
                'value' => 50000,
                'min_purchase' => 200000,
                'max_discount' => null,
                'is_active' => true,
                'valid_from' => now(),
                'valid_until' => now()->addMonth(),
                'usage_limit' => 50,
            ],
            [
                'code' => 'HAPPYHOUR',
                'name' => 'Happy Hour 20%',
                'type' => 'percentage',
                'value' => 20,
                'min_purchase' => 30000,
                'max_discount' => 30000,
                'is_active' => true,
                'valid_from' => now(),
                'valid_until' => now()->addWeek(),
                'usage_limit' => null,
            ],
        ];

        foreach ($discounts as $discount) {
            Discount::create($discount);
        }
    }
}
