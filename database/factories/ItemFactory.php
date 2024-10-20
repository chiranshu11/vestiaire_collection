<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemFactory extends Factory
{
    protected $model = Item::class;

    // Cache to hold shared channel_item_code for selected products and random sellers
    protected static $sharedCodes = [];

    // Limit the number of products that can have shared channel_item_code (e.g., 1 or 2 products)
    protected static $maxSharedProducts = 2;

    // Track the number of products with quantity 1 and 3 per seller
    protected static $quantityMap = [];

    public function definition()
    {
        // List of product categories with short codes
        $categories = [
            'Bag' => 'B',
            'Dress' => 'D',
            'Shoes' => 'S',
            'Watch' => 'W',
            'Sunglasses' => 'G' // Using 'G' for Glasses
        ];

        // List of brands
        $brands = [
            'Louis Vuitton' => 'LV',
            'Tom Ford' => 'TF',
            'Gucci' => 'GC',
            'Prada' => 'PR'
        ];

        // Randomly select a brand and category
        $brand = $this->faker->randomElement(array_keys($brands));
        $brandCode = $brands[$brand];

        $category = $this->faker->randomElement(array_keys($categories));
        $categoryCode = $categories[$category];

        // Randomly select a country and currency
        $countries = ['US', 'Dubai', 'UK', 'Ireland'];
        $currencyMap = ['US' => 'USD', 'Dubai' => 'AED', 'UK' => 'GBP', 'Ireland' => 'EUR'];
        $country = $this->faker->randomElement($countries);
        $currency = $currencyMap[$country];

        // Randomly select an existing seller or create a new one
        $seller = Seller::inRandomOrder()->first() ?? Seller::factory()->create();

        // Generate a product name
        $productName = "{$brand} {$category}";

        // Randomly select 1 or 2 products for shared channel_item_code logic
        if (count(self::$sharedCodes) < self::$maxSharedProducts && !isset(self::$sharedCodes[$productName])) {
            // First time seeing this product, randomly select a few sellers to share the same code
            self::$sharedCodes[$productName] = [
                'code' => "{$brandCode}_{$categoryCode}" . $this->faker->unique()->numberBetween(100, 999), // Shared code
                'shared_sellers' => Seller::inRandomOrder()->limit($this->faker->numberBetween(2, 4))->pluck('id')->toArray() // Random sellers sharing the code
            ];
        }

        // Check if this product is one of the selected shared products
        if (isset(self::$sharedCodes[$productName]) && in_array($seller->id, self::$sharedCodes[$productName]['shared_sellers'])) {
            // Use the shared `channel_item_code`
            $channelItemCode = self::$sharedCodes[$productName]['code'];
        } else {
            // Generate a unique `channel_item_code` for other sellers
            $uniqueNumber = $this->faker->unique()->numberBetween(100, 999); // Unique number for new code
            $channelItemCode = "{$brandCode}_{$categoryCode}{$uniqueNumber}";
        }

        // Ensure at least 3 products with quantity 1 and at least 1 product with quantity 3
        $sellerId = $seller->id;
        if (!isset(self::$quantityMap[$sellerId])) {
            self::$quantityMap[$sellerId] = ['quantity1' => 0, 'quantity3' => 0];
        }

        $quantity = $this->faker->randomElement([1, 2, 3]);

        // Ensure conditions for quantities
        if (self::$quantityMap[$sellerId]['quantity1'] < 3) {
            $quantity = 1;
            self::$quantityMap[$sellerId]['quantity1']++;
        } elseif (self::$quantityMap[$sellerId]['quantity3'] < 1) {
            $quantity = 3;
            self::$quantityMap[$sellerId]['quantity3']++;
        }

        // If quantity is 3, ensure the price is between 400 and 500
        $price = ($quantity === 3) ? $this->faker->randomFloat(2, 400, 500) : $this->faker->randomFloat(2, 50, 200);

        return [
            'name' => $productName, // Product name
            'price_amount' => $price, // Price depending on quantity
            'price_currency' => $currency,
            'channel_item_code' => $channelItemCode, // Shared or dynamically generated
            'quantity' => $quantity, // Random quantity between 1 and 3
            'seller_id' => $seller->id,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
