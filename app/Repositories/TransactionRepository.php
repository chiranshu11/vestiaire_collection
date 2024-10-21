<?php
namespace App\Repositories;

use App\Models\Transaction;
use Illuminate\Support\Collection;


class TransactionRepository
{
    
    const STATUS_FAILED = 1;
    const STATUS_PROCESSED = 2;

    public function createBatchTransactions(int $payoutId, Collection $transactionCollection): Collection
    {
        // Map over the transaction collection to prepare data for the transactions table
        $transact = collect();
        $transactionData = $transactionCollection->map(function ($transactionData) use ($payoutId) {
            return [
                'payout_id' => $payoutId,
                'batch_amount_in_original_currency' => $transactionData['batch_amount_in_original_currency'], // Save the base currency amount
                'batch_amount_in_base_currency' => $transactionData['batch_amount_in_base_currency'], // Save the base currency amount
                'created_at' => \Carbon::now()->toDateTimeString(),
                'updated_at' => \Carbon::now()->toDateTimeString(),
                'items' => $transactionData['items']
            ];
        })->toArray();

        foreach ($transactionData as $singleTransaction) {
            $items = $singleTransaction['items'];
            unset($singleTransaction['items']);
            $transactionId = \DB::table('transactions')->insertGetId($singleTransaction);
            $transact->push([
                'transaction_id' => $transactionId,
                'transaction_items' => $items 
            ]);
        }
        return $transact;
    }
}
