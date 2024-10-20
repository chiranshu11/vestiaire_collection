<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\PayoutServiceV2;
use App\Models\Seller;
use App\Models\Item;
use App\Repositories\PayoutRepository;
use App\Repositories\TransactionRepository;
use App\Repositories\ItemPayoutRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use App\Exceptions\PayoutException;
use Mockery;

class PayoutServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $payoutRepository;
    protected $transactionRepository;
    protected $itemPayoutRepository;
    protected $payoutService;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the repositories
        $this->payoutRepository = Mockery::mock(PayoutRepository::class);
        $this->transactionRepository = Mockery::mock(TransactionRepository::class);
        $this->itemPayoutRepository = Mockery::mock(ItemPayoutRepository::class);

        // Initialize PayoutService with mocked dependencies
        $this->payoutService = new PayoutServiceV2(
            $this->payoutRepository,
            $this->transactionRepository,
            $this->itemPayoutRepository
        );
    }

 /** @test */
public function it_should_process_payouts_successfully()
{
    // Arrange: Create sellers and items using factories
    $seller1 = Seller::factory()->create(['id' => 1, 'base_currency' => 'USD', 'name' => 'Price-Kunze']);
    
    // Create an item with a specific quantity
    $item1 = Item::factory()->create([
        'seller_id' => $seller1->id, 
        'price_amount' => 500, 
        'price_currency' => 'USD', 
        'channel_item_code' => 'Test_W739', 
        'quantity' => 3 // Create with quantity 3
    ]);

    // Prepare the request data (sold items)
    $data = collect([
        ['seller_reference' => $seller1->id, 'channel_item_code' => 'Test_W739']
    ]);

    // Mock repository methods to avoid direct DB interaction
    $this->payoutRepository->shouldReceive('createPayout')
        ->once()  // Ensures it is called once during the test
        ->andReturn(new \App\Models\Payout(['id' => 1]));  // Return a mock payout object

    $this->transactionRepository->shouldReceive('createBatchTransactions')
        ->once();  // Ensures it is called once

    $this->itemPayoutRepository->shouldReceive('saveMultipleItemPayouts')
        ->once();  // Ensures it is called once

    // Set a payout limit to simulate splitting logic
    $this->mockFunction('getPayoutLimit', 1000);  // Simulate payout limit

    // Act: Call the actual processPayouts method
    $result = $this->payoutService->processPayouts($data);

    // Assert: Check the structure of the result
    $this->assertIsArray($result);
    $this->assertNotEmpty($result);

    // Ensure the result contains correct payout information for the seller
    $this->assertArrayHasKey('Price-Kunze', $result);
    $this->assertArrayHasKey('U.S.A Payouts', $result['Price-Kunze']);
    

    // Assert that the number of items is based on the quantity (3 in this case)
    $this->assertCount(2, $result['Price-Kunze']['U.S.A Payouts']);

    // Assert specific values for the first item in the result
    $this->assertEquals('Test_W739', $result['Price-Kunze']['U.S.A Payouts'][0]['items'][0]['Item Channel Code']);
    $this->assertEquals(500.00, $result['Price-Kunze']['U.S.A Payouts'][0]['items'][0]['Item Unit Amount']);
    $this->assertEquals(1000.00, $result['Price-Kunze']['U.S.A Payouts'][0]['items'][0]['Item Amount']);
    $this->assertEquals(500.00, $result['Price-Kunze']['U.S.A Payouts'][1]['items'][0]['Item Amount']);
}



    /** @test */
    public function it_should_throw_exception_when_seller_not_found()
    {
        // Arrange: Invalid seller reference
        $data = collect([
            ['seller_reference' => 99, 'channel_item_code' => 'Test_W739']
        ]);

        // Assert that an exception is thrown
        $this->expectException(PayoutException::class);
        $this->expectExceptionMessage('Seller with reference 99 not found.');

        // Act
        $this->payoutService->processPayouts($data);
    }

    /** @test */
    public function it_should_throw_exception_when_item_not_found_for_seller()
    {
        // Arrange: Create a seller but no matching item
        $seller = Seller::factory()->create(['base_currency' => 'USD']);
        $data = collect([
            ['seller_reference' => $seller->id, 'channel_item_code' => 'Non_Existing_Item']
        ]);

        // Assert that an exception is thrown
        $this->expectException(PayoutException::class);
        $this->expectExceptionMessage("Item with channel_item_code Non_Existing_Item does not belong to Seller with reference {$seller->id}.");

        // Act
        $this->payoutService->processPayouts($data);
    }

    /** @test */
    public function it_should_throw_exception_when_item_exceeds_payout_limit()
    {
        // Arrange: Create a seller and an item that exceeds the payout limit
        $seller = Seller::factory()->create(['base_currency' => 'USD']);
        $item = Item::factory()->create([
            'seller_id' => $seller->id,
            'price_amount' => 10000, // Exceeds the limit
            'price_currency' => 'USD',
            'channel_item_code' => 'Test_W739',
            'quantity' => 1
        ]);

        $data = collect([
            ['seller_reference' => $seller->id, 'channel_item_code' => 'Test_W739']
        ]);

        // Set payout limit
        $this->mockFunction('getPayoutLimit', 1000);  // Adjusted to match the expected limit

        // Assert that an exception is thrown
        $this->expectException(PayoutException::class);

        try {
            // Act
            $this->payoutService->processPayouts($data);
        } catch (PayoutException $e) {
            // Partial message matching
            $this->assertStringContainsString("Item with ID {$item->id} has a price of 10000", $e->getMessage());
            $this->assertStringContainsString("exceeds the payout limit of 1000 USD", $e->getMessage());
            throw $e; // Rethrow to fulfill expectException
        }
    }

    /** @test */
    public function it_should_convert_currency_correctly()
    {
        // Arrange
        $amount = 100;
        $fromCurrency = 'USD';
        $toCurrency = 'GBP';
        $expectedConvertedAmount = 75; // Assuming 1 USD = 0.75 GBP

        // Act
        $convertedAmount = $this->payoutService->convertCurrency($amount, $fromCurrency, $toCurrency);

        // Assert
        $this->assertEquals($expectedConvertedAmount, $convertedAmount);
    }

    /** @test */
    public function it_should_throw_exception_on_invalid_currency_conversion()
    {
        // Arrange
        $amount = 100;
        $fromCurrency = 'USD';
        $toCurrency = 'INVALID';

        // Assert
        $this->expectException(PayoutException::class);
        $this->expectExceptionMessage('Currency conversion from USD to INVALID not available.');

        // Act
        $this->payoutService->convertCurrency($amount, $fromCurrency, $toCurrency);
    }

    /** @test */
    public function it_should_process_payouts_for_multiple_sellers_with_different_currencies()
    {
        // Arrange: Create sellers with different base currencies and items with different currencies
        $seller1 = Seller::factory()->create(['base_currency' => 'USD', 'name' => 'Seller1']);
        $seller2 = Seller::factory()->create(['base_currency' => 'GBP', 'name' => 'Seller2']);

        $item1 = Item::factory()->create([
            'seller_id' => $seller1->id,
            'price_amount' => 500,
            'price_currency' => 'USD',
            'channel_item_code' => 'Test_W739',
            'quantity' => 1
        ]);

        $item2 = Item::factory()->create([
            'seller_id' => $seller2->id,
            'price_amount' => 400,
            'price_currency' => 'GBP',
            'channel_item_code' => 'Test_G741',
            'quantity' => 1
        ]);

        $data = collect([
            ['seller_reference' => $seller1->id, 'channel_item_code' => 'Test_W739'],
            ['seller_reference' => $seller2->id, 'channel_item_code' => 'Test_G741']
        ]);

        // Mock repository methods
        $this->payoutRepository->shouldReceive('createPayout')->andReturn(new \App\Models\Payout(['id' => 1]));
        $this->transactionRepository->shouldReceive('createBatchTransactions')->twice();
        $this->itemPayoutRepository->shouldReceive('saveMultipleItemPayouts')->twice();

        // Set payout limit high enough to ensure no splitting
        $this->mockFunction('getPayoutLimit', 1000);

        // Act: Call the method
        $result = $this->payoutService->processPayouts($data);

        // Assert: Ensure the response has data for both sellers
        $this->assertArrayHasKey('Seller1', $result);
        $this->assertArrayHasKey('Seller2', $result);
    }

    /** @test */
    public function it_should_return_empty_response_when_no_items_are_provided()
    {
        // Arrange: Empty data
        $data = collect([]);

        // Mock repository methods (no interactions expected)
        $this->payoutRepository->shouldNotReceive('createPayout');
        $this->transactionRepository->shouldNotReceive('createBatchTransactions');
        $this->itemPayoutRepository->shouldNotReceive('saveMultipleItemPayouts');

        // Act: Call the method
        $result = $this->payoutService->processPayouts($data);

        // Assert: Ensure the result is empty
        $this->assertEmpty($result);
    }

    // Helper function to mock global functions like getPayoutLimit
    protected function mockFunction($functionName, $returnValue)
    {
        $mock = Mockery::mock('alias:' . $functionName);
        $mock->shouldReceive('__invoke')->andReturn($returnValue);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
