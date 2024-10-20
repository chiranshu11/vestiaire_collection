<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Seller;
use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PayoutControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function store_creates_payout_successfully()
    {
        // Arrange: Create sellers and items using factories
        $seller1 = Seller::factory()->create(['id' => 1, 'base_currency' => 'USD', 'name' => "Price-Kunze"]);
        $seller2 = Seller::factory()->create(['id' => 2, 'base_currency' => 'EUR', 'name' => "Dibbert, Boyer and Quigley"]);
        $seller5 = Seller::factory()->create(['id' => 5, 'base_currency' => 'GBP', 'name' => "Collier, Greenholt and Blanda"]);

        // Create items with price amounts and currencies for proper validation
        Item::factory()->create(['seller_id' => $seller1->id, 'channel_item_code' => 'Test_W739', 'price_amount' => 300, 'price_currency' => 'EUR', 'name'=> "Prada Bag"]);
        Item::factory()->create(['seller_id' => $seller2->id, 'channel_item_code' => 'TF_S723', 'price_amount' => 500, 'price_currency' => 'AED','name'=> "Tom Ford Shoes"]);
        Item::factory()->create(['seller_id' => $seller5->id, 'channel_item_code' => 'GC_G201', 'price_amount' => 200, 'price_currency' => 'USD','name'=> "Tom Ford Bag"]);

        $items = [
            [
                'seller_reference' => 1,
                'channel_item_code' => 'Test_W739',
            ],
            [
                'seller_reference' => 2,
                'channel_item_code' => 'TF_S723',
            ],
            [
                'seller_reference' => 5,
                'channel_item_code' => 'GC_G201',
            ],
        ];

        // Simulate a POST request with valid data
        $response = $this->postJson('/api/payouts', ['sold_items' => $items]);

        // Assert the correct response structure
        $response->assertStatus(201)
            ->assertJson([
                'payouts' => [
                    'Price-Kunze' => [
                        'Europe Payouts' => [
                            [
                                'payout_id' => 1,
                                'seller_reference' => 1,
                                'original_amount' => '300 EUR',
                                'converted_amount' => '354 USD',
                                'items' => [
                                    [
                                        'Item Name' => 'Prada Bag',
                                        'Item Amount' => '300.00',
                                        'Item Currency' => 'EUR',
                                        'Item Code' => 'Test_W739'
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'Dibbert, Boyer and Quigley' => [
                        'Middle East Payouts' => [
                            [
                                'payout_id' => 2,
                                'seller_reference' => 2,
                                'original_amount' => '500 AED',
                                'converted_amount' => '115 EUR',
                                'items' => [
                                    [
                                        'Item Name' => 'Tom Ford Shoes',
                                        'Item Amount' => '500.00',
                                        'Item Currency' => 'AED',
                                        'Item Code' => 'TF_S723'
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'Collier, Greenholt and Blanda' => [
                        'U.S.A Payouts' => [
                            [
                                'payout_id' => 3,
                                'seller_reference' => 5,
                                'original_amount' => '200 USD',
                                'converted_amount' => '150 GBP',
                                'items' => [
                                    [
                                        'Item Name' => 'Tom Ford Bag',
                                        'Item Amount' => '200.00',
                                        'Item Currency' => 'USD',
                                        'Item Code' => 'GC_G201'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]);
    }

    /** @test */
    public function store_fails_with_invalid_input()
    {
        // Simulate a POST request with invalid data (empty sold_items array)
        $response = $this->postJson('/api/payouts', ['sold_items' => []]);

        // Assert: Check response status and error message
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => [
                    'sold_items' => ['The sold_items field is required.']
                ]
            ]);
    }
}
