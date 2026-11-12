<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach (['Dresses', 'Gowns', 'Suits', 'Tuxedos', 'Barong Tagalog', 'Other formalwear'] as $name) {
            DB::table('product_categories')->updateOrInsert(['name' => $name], ['created_at' => now(), 'updated_at' => now()]);
        }
    }
}
