<?php

namespace App\Services;

use App\Models\Seller;
use App\Repositories\PayoutRepository;
use App\Repositories\TransactionRepository;
use App\Repositories\ItemPayoutRepository;
use Illuminate\Support\Collection;
use App\Exceptions\PayoutException;
use Illuminate\Support\Facades\DB;

class PayoutService
{
    protected $payoutRepository;
    protected $transactionRepository;
    protected $itemPayoutRepository;

    public function __construct(
        PayoutRepository $payoutRepository,
        TransactionRepository $transactionRepository,
        ItemPayoutRepository $itemPayoutRepository
    ) {
        $this->payoutRepository = $payoutRepository;
        $this->transactionRepository = $transactionRepository;
        $this->itemPayoutRepository = $itemPayoutRepository;
    }

    public function processPayouts(Collection $data)
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

        $responses = collect();

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
        $responses = $this->processPayoutItems($sellersWithItems);

        return $responses;
    }

    private function processPayoutItems(Collection $sellersWithItems): Array
    {
        $responses = [];
    
        foreach ($sellersWithItems as $seller) {
            $payoutCurrencyBatches = $this->calculatePayoutSplits($seller, $seller->items);
            dd($seller);
            
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
            $remainingAmountBaseCurrency = $this->convertCurrency($remainingAmount, $currency, $seller->base_currency);
        
            // Convert the payout limit to the seller's base currency
            $payoutLimit = getPayoutLimit();
        
            // Process each batch
            while ($remainingAmountBaseCurrency > 0 && $itemsByCurrency->isNotEmpty()) {
                $batchItems = collect();
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
                        $batchItems->push($item);
        
                        // Add the converted price amount to the batch amount
                        $batchAmountConvertedCurrency += $convertedPriceAmount;
                        $batchAmountInOriginalCurrency += $item['price_amount'];
        
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
                $remainingAmountBaseCurrency -= $batchAmountConvertedCurrency;
            }
            $payoutBatchesForCurrency[$currency]['batch'] = $payoutBatches;
            $payoutBatchesForCurrency[$currency]['total_amount_of_batch'] = $totalAmountInOriginalCurrency;
            $payoutBatchesForCurrency[$currency]['total_amount_in_base_currency'] = $totalAmountConvertedCurrency;
        }

        return $payoutBatchesForCurrency;
    }

    public function createPayoutEntry(int $sellerId, $payoutBatches, string $currency)
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
                $itemID = 0;
                $itemsArray = [];
                
                foreach ($batch['items'] as $index => $item){
                    $itemID = $item->id;
                    $itemsArray[$index]['Item Name'] = $item->name;
                    $itemsArray[$index]['Item Amount'] = $item->price_amount;
                    $itemsArray[$index]['Item Currency'] = $item->price_currency;
                    $itemsArray[$index]['Item Code'] = $item->channel_item_code;
                }
                $transactionCollection->push([
                    'batch_amount_in_original_currency' => $batch['batch_amount_in_original_currency'],
                    'batch_amount_in_base_currency' => $batch['batch_amount_in_base_currency'],
                    'item_id' => $itemID,
                ]);

                $response->push([
                    'payout_id' => $consolidatedPayout->id,
                    'seller_reference' => $sellerId,
                    'original_amount' => $batch['batch_amount_in_original_currency'],
                    'converted_amount' => $batch['batch_amount_in_base_currency'],
                    'original_currency' => $currency,
                    'converted_currency' => $payoutBatches['batch'][0]['currency'],
                    'items' => $itemsArray
                ]);
            }
            $this->transactionRepository->createBatchTransactions($consolidatedPayout->id, $transactionCollection);
            $this->itemPayoutRepository->saveMultipleItemPayouts($consolidatedPayout->id, $batch['items']);
            
            DB::commit();

            return $response;

        } catch (\Exception $e) {
            DB::rollBack();
            throw new PayoutException("Failed to create payout for Seller with ID {$sellerId}: " . $e->getMessage());
        }
    }

    private function buildResponse($payout, $transaction, $sellerReference, $amount, $currency, $items)
    {
        return [
            'payout_id' => $payout->id,
            'seller_reference' => $sellerReference,
            'transaction_id' => $transaction->id,
            'amount' => $amount,
            'currency' => $currency,
            'items' => $items->map(function ($item) {
                return [
                    'name' => $item->name,
                    'amount' => $item->price_amount,
                ];
            }),
        ];
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

    public function getCountryFromCurrency(string $currency): string{
        $exchangeCountries = [
            'USD' => "U.S.A",
            'GBP' => "UK",
            'EUR' => "Europe",
            'AED' => "Middle East"
        ];
        return isset($exchangeCountries[$currency]) ? $exchangeCountries[$currency] : $currency;
    }
}
