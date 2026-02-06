<?php

namespace Database\Seeders;

use App\Models\Table;
use Illuminate\Database\Seeder;

class TableSeeder extends Seeder
{
    public function run(): void
    {
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
    }
}
