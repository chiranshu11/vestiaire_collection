<?php
namespace App\Repositories;

use App\Models\Transaction;
use Illuminate\Support\Collection;


class TransactionRepository
{
    
    const STATUS_FAILED = 1;
    const STATUS_PROCESSED = 2;

    public function createBatchTransactions(int $payoutId, Collection $transactionCollection)
    {
        // Map over the transaction collection to prepare data for the transactions table
        $transactionData = $transactionCollection->map(function ($transactionData) use ($payoutId) {
            return [
                'payout_id' => $payoutId,
                'batch_amount_in_original_currency' => $transactionData['batch_amount_in_original_currency'], // Save the base currency amount
                'batch_amount_in_base_currency' => $transactionData['batch_amount_in_base_currency'], // Save the base currency amount
                'created_at' => now(), // Add timestamps for bulk insert
                'updated_at' => now(),
            ];
        })->toArray();

        // Insert all transactions in bulk into the transactions table
        \DB::table('transactions')->insert($transactionData);
    }
}
