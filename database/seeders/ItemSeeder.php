<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Item;
use App\Models\Seller;

class ItemSeeder extends Seeder
{
    public function run()
    {
        // Create 20 items, each associated with a random seller
        Item::factory()->count(20)->create();
    }
}
