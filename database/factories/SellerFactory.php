<?php

namespace Database\Factories;

use App\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;

class SellerFactory extends Factory
{
    protected $model = Seller::class;

    public function definition()
    {
        $currencies = ["USD","GBP","EUR"];
        
        return [
            'name' => $this->faker->company,
            'email' => $this->faker->unique()->safeEmail,
            'phone' => $this->faker->phoneNumber,
            'address' => $this->faker->address,
            'city' => $this->faker->city,
            'pincode' => $this->faker->postcode,
            'billing_name' => $this->faker->company,
            'billing_email' => $this->faker->unique()->safeEmail,
            'billing_phone' => $this->faker->phoneNumber,
            'billing_address' => $this->faker->address,
            'base_currency' => $this->faker->randomElement($currencies),
            'status' => $this->faker->randomElement([0,1]),
            'cin' => $this->faker->unique()->bothify('U12345????0000#'),
        ];
    }
}
