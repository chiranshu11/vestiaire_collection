<?php

namespace App\Services;

use App\Models\Seller;
use App\Repositories\PayoutRepository;
use App\Repositories\TransactionRepository;
use App\Repositories\ItemTransactionRepository;
use Illuminate\Support\Collection;
use App\Exceptions\PayoutException;
use Illuminate\Support\Facades\DB;

class PayoutService
{
    protected $payoutRepository;
    protected $transactionRepository;
    protected $itemTransactionRepository;

    public function __construct(
        PayoutRepository $payoutRepository,
        TransactionRepository $transactionRepository,
        ItemTransactionRepository $itemTransactionRepository
    ) {
        $this->payoutRepository = $payoutRepository;
        $this->transactionRepository = $transactionRepository;
        $this->itemTransactionRepository = $itemTransactionRepository;
    }

    public function processPayouts(Collection $data): array
    {
        $sellerReferences = $data->pluck('seller_reference')->unique();
        $channelItemCodes = $data->pluck('channel_item_code')->unique();

        // Eager load sellers and their associated items based on item codes
        $sellersWithItems = Seller::with(['items' => function ($query) use ($channelItemCodes) {
            $query->whereIn('channel_item_code', $channelItemCodes);
        }])
            ->whereIn('id', $sellerReferences)
            ->get()
            ->keyBy('id');

        foreach ($data as $entry) {
            $channelItemCode = $entry['channel_item_code'];
            $sellerReference = $entry['seller_reference'];

            // Fetch the seller
            $seller = $sellersWithItems->get($sellerReference);

            if (!$seller) {
                throw new PayoutException("Seller with reference {$sellerReference} not found.");
            }

            // Validate item existence in seller's items
            $item = $seller->items->firstWhere('channel_item_code', $channelItemCode);
            if (!$item) {
                throw new PayoutException("Item with channel_item_code {$channelItemCode} does not belong to Seller with reference {$sellerReference}.");
            }
        }

        // Process valid seller with item(s)
        return $this->processPayoutItems($sellersWithItems);

    }

    private function processPayoutItems(Collection $sellersWithItems): array
    {
        $responses = [];

        foreach ($sellersWithItems as $seller) {
            $duplicatedItems = collect(); // Temporary collection for duplicated items

            foreach ($seller->items as $item) {
                $quantity = $item->quantity;

                
                // Duplicate the item based on its quantity
                for ($i = 0; $i < $quantity; $i++) {
                    $item->quantity = 1; // Default to 1 if quantity is null or missing
                    $replicatedItem = $item->replicate();
                    $replicatedItem->id = $item->id;
                    
                    $duplicatedItems->push($replicatedItem);// Replicate the item for each quantity
                }
            }
            
            
            // Replace the seller's items collection with the duplicated items
            $seller->setRelation('items', $duplicatedItems);

            $payoutCurrencyBatches = $this->calculatePayoutSplits($seller, $seller->items);

            foreach ($payoutCurrencyBatches as $currency => $payoutBatches) {
                $countryPayoutLabel = $this->getCountryFromCurrency($currency) . " Payouts";
                $responses[$seller->name][$countryPayoutLabel] = $this->createPayoutEntry($seller->id, $payoutBatches, $currency);
            }
        }
        return $responses;
    }

    private function calculatePayoutSplits($seller, Collection $items): array
    {
    
        // Group items by currency
        $groupedItemsByCurrency = $items->groupBy('price_currency');
        $payoutBatchesForCurrency = [];
        
        foreach ($groupedItemsByCurrency as $currency => $itemsByCurrency) {
            $payoutBatches = [];
            $totalAmountInOriginalCurrency = 0;
            $totalAmountConvertedCurrency = 0;

            // Convert the remaining amount to the seller's base currency
            $remainingAmount = $itemsByCurrency->sum('price_amount');
            $remainingAmountConvertedCurrency = $this->convertCurrency($remainingAmount, $currency, $seller->base_currency);

            // Convert the payout limit to the seller's base currency
            $payoutLimit = getPayoutLimit();
        
            // Process each batch
            while ($remainingAmountConvertedCurrency > 0 && $itemsByCurrency->isNotEmpty()) {
                $batchItems = [];
                $batchAmountConvertedCurrency = 0;
                $batchAmountInOriginalCurrency = 0;
                
                foreach ($itemsByCurrency as $key => $item) {
                    $convertedPriceAmount = $this->convertCurrency($item['price_amount'], $currency, $seller->base_currency);

                     // Handle base case: If the converted item price is greater than or equal to payout limit
                    if ($convertedPriceAmount >= $payoutLimit && $batchAmountConvertedCurrency == 0) {
                        // Throw an error and stop further processing if the item price itself exceeds the limit
                        throw new PayoutException("Item with ID {$item->id} has a price of {$item['price_amount']} {$currency} (converted to {$convertedPriceAmount} {$seller->base_currency}), which exceeds the payout limit of {$payoutLimit} {$seller->base_currency}. Please contact support.");
                    }
            
                    // Check if adding the item would exceed the payout limit
                    if ($batchAmountConvertedCurrency + $convertedPriceAmount <= $payoutLimit) {
                        
                        $itemCode = $item->channel_item_code;

                        // Check if the item with the same channel_item_code already exists in $batchItems
                        if (isset($batchItems[$itemCode])) {
                            // If the item exists, increase its quantity
                            $batchItems[$itemCode]['Item Quantity'] += $item->quantity;
                            $batchItems[$itemCode]['Item Amount'] += (double)$item->price_amount;
                        } else {
                            
                            // If the item doesn't exist, add it to the batchItems
                            $batchItems[$itemCode] = [
                                'Item ID' => $item->id,
                                'Item Channel Code' => $itemCode,
                                'Item Name' => $item->name,
                                'Item Amount' => (double)$item->price_amount,
                                'Item Unit Amount' => (double)$item->price_amount,
                                'Item Currency' => $item->price_currency,
                                'Item Quantity' => $item->quantity, // Set initial quantity
                            ];
                        }
                        
                        // Add the converted price amount to the batch amount
                        $batchAmountConvertedCurrency += $convertedPriceAmount;
                        $batchAmountInOriginalCurrency += $item->price_amount;
        
                        // Remove processed item from the original collection
                        $itemsByCurrency->forget($key);
                    }
                }

                $totalAmountInOriginalCurrency += $batchAmountInOriginalCurrency;
                $totalAmountConvertedCurrency += $batchAmountConvertedCurrency;
        
                // Add the processed batch to the payoutBatches array
                $payoutBatches[] = [
                    'batch_amount_in_original_currency' => $batchAmountInOriginalCurrency,
                    'batch_amount_in_base_currency' => $batchAmountConvertedCurrency,
                    'items' => $batchItems,
                    'currency' => $seller->base_currency, // Use seller's base currency for payouts
                ];
        
                // Deduct the processed batch amount from the remaining amount
                $remainingAmountConvertedCurrency -= $batchAmountConvertedCurrency;
            }
            $payoutBatchesForCurrency[$currency]['batch'] = $payoutBatches;
            $payoutBatchesForCurrency[$currency]['total_amount_of_batch'] = $totalAmountInOriginalCurrency;
            $payoutBatchesForCurrency[$currency]['total_amount_in_base_currency'] = $totalAmountConvertedCurrency;
        }

        return $payoutBatchesForCurrency;
    }

    public function createPayoutEntry(int $sellerId, $payoutBatches, string $currency): Collection
    {
        try {
            DB::beginTransaction();

            // Step 1: Create consolidated payout
            $consolidatedPayout = $this->payoutRepository->createPayout([
                'seller_id' => $sellerId,
                'original_amount' => $payoutBatches['total_amount_of_batch'],
                'converted_amount' => $payoutBatches['total_amount_in_base_currency'],
                'original_currency' => $currency,
                'converted_currency' => $payoutBatches['batch'][0]['currency'],
            ]);
            $transactionCollection = collect();

            $response = collect();
            
            foreach ($payoutBatches['batch'] as $batch) {

                $itemsInTransaction = [];
                foreach (array_values($batch['items']) as $item) {
                    array_push($itemsInTransaction,$item);
                }
                $transactionCollection->push([
                    'batch_amount_in_original_currency' => $batch['batch_amount_in_original_currency'],
                    'batch_amount_in_base_currency' => $batch['batch_amount_in_base_currency'],
                    'items' => $itemsInTransaction
                ]);

                $response->push([
                    'payout_id' => $consolidatedPayout->id,
                    'seller_reference' => $sellerId,
                    'original_amount' => $batch['batch_amount_in_original_currency'],
                    'converted_amount' => $batch['batch_amount_in_base_currency'],
                    'original_currency' => $currency,
                    'converted_currency' => $payoutBatches['batch'][0]['currency'],
                    'items' => array_values($batch['items'])
                ]);
            }
            

            $transactions = $this->transactionRepository->createBatchTransactions($consolidatedPayout->id, $transactionCollection);
            $this->itemTransactionRepository->saveMultipleItemTransactions($transactions);

            DB::commit();

            return $response;

        } catch (\Exception $e) {
            DB::rollBack();
            throw new PayoutException("Failed to create payout for Seller with ID {$sellerId}: " . $e->getMessage());
        }
    }

    public function convertCurrency(float $amount, string $fromCurrency, string $toCurrency): float
    {
        $exchangeRates = [
            'USD' => ['GBP' => 0.75, 'EUR' => 0.85, 'USD' => 1.0, 'AED' => 3.67],
            'GBP' => ['USD' => 1.33, 'EUR' => 1.13, 'GBP' => 1.0, 'AED' => 4.89],
            'EUR' => ['USD' => 1.18, 'GBP' => 0.88, 'EUR' => 1.0, 'AED' => 4.28],
            'AED' => ['USD' => 0.27, 'GBP' => 0.20, 'EUR' => 0.23, 'AED' => 1.0]
        ];

        if (!isset($exchangeRates[$fromCurrency][$toCurrency])) {
            throw new PayoutException("Currency conversion from {$fromCurrency} to {$toCurrency} not available.");
        }

        return $amount * $exchangeRates[$fromCurrency][$toCurrency];
    }

    public function getCountryFromCurrency(string $currency): string
    {
        $exchangeCountries = [
            'USD' => "U.S.A",
            'GBP' => "UK",
            'EUR' => "Europe",
            'AED' => "Middle East"
        ];
        return isset($exchangeCountries[$currency]) ? $exchangeCountries[$currency] : $currency;
    }
}
