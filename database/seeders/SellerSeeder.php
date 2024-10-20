<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Seller;

class SellerSeeder extends Seeder
{
    public function run()
    {
        Seller::factory()->count(5)->create();
    }
}
