<?php
namespace App\Repositories;

use Illuminate\Support\Collection;

class ItemTransactionRepository
{
    /**
     * Save item-payout relationships in bulk.
     *
     * @param int $payoutId
     * @param Collection $items
     */
    public function saveMultipleItemTransactions(Collection $transactions): void
    {
        $itemPayoutData = [];
        foreach ($transactions as $transaction) {
            
            $transactionID = $transaction['transaction_id'];
            foreach ($transaction['transaction_items'] as $item) {
                $itemPayoutData[] = [
                    'item_id' => $item['Item ID'],
                    'transaction_id' => $transactionID,
                    'quantity' => $item['Item Quantity'],
                    'created_at' => \Carbon::now()->toDateTimeString(),
                    'updated_at' => \Carbon::now()->toDateTimeString(),
                ];
            }
        }
        // Perform a bulk insert into the item_payout table
        \DB::table('item_transaction')->insert($itemPayoutData);
    }

}
