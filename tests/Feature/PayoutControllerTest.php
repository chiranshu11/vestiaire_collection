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
        $seller1 = Seller::factory()->create(['id' => 1, 'base_currency' => 'USD', 'name' => "Torp, Jakubowski and Kerluke"]);

        // Create items with price amounts and currencies for proper validation, including quantities
        Item::factory()->create(['seller_id' => $seller1->id, 'channel_item_code' => 'TF_D655', 'price_amount' => 101.73, 'price_currency' => 'USD', 'quantity' => 1, 'name' => "Tom Ford Dress"]);
        Item::factory()->create(['seller_id' => $seller1->id, 'channel_item_code' => 'PR_D744', 'price_amount' => 477.83, 'price_currency' => 'USD', 'quantity' => 3, 'name' => "Prada Dress"]);

        $items = [
            [
                'seller_reference' => 1,
                'channel_item_code' => 'TF_D655',
            ],
            [
                'seller_reference' => 1,
                'channel_item_code' => 'PR_D744',
            ],
        ];

        // Simulate a POST request with valid data
        $response = $this->postJson('/api/payouts', ['sold_items' => $items]);

        // Assert the correct response structure, including quantity, without checking Item ID
        $response->assertStatus(201)
            ->assertJson([
                'payouts' => [
                    'Torp, Jakubowski and Kerluke' => [
                        'U.S.A Payouts' => [
                            [
                                // 'payout_id' => 1,
                                'seller_reference' => 1,
                                'original_amount' => 579.56,
                                'converted_amount' => 579.56,
                                'original_currency' => 'USD',
                                'converted_currency' => 'USD',
                                'items' => [
                                    [
                                        'Item Channel Code' => 'TF_D655',
                                        'Item Name' => 'Tom Ford Dress',
                                        'Item Amount' => 101.73,
                                        'Item Unit Amount' => 101.73,
                                        'Item Currency' => 'USD',
                                        'Item Quantity' => 1,
                                    ],
                                    [
                                        'Item Channel Code' => 'PR_D744',
                                        'Item Name' => 'Prada Dress',
                                        'Item Amount' => 477.83,
                                        'Item Unit Amount' => 477.83,
                                        'Item Currency' => 'USD',
                                        'Item Quantity' => 1,
                                    ]
                                ]
                            ],
                            [
                                'payout_id' => 1,
                                'seller_reference' => 1,
                                'original_amount' => 955.66,
                                'converted_amount' => 955.66,
                                'original_currency' => 'USD',
                                'converted_currency' => 'USD',
                                'items' => [
                                    [
                                        'Item Channel Code' => 'PR_D744',
                                        'Item Name' => 'Prada Dress',
                                        'Item Amount' => 955.66,
                                        'Item Unit Amount' => 477.83,
                                        'Item Currency' => 'USD',
                                        'Item Quantity' => 2,
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
