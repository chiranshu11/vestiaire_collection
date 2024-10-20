<?php
namespace App\Repositories;

use Illuminate\Support\Collection;

class ItemPayoutRepository
{
    /**
     * Save item-payout relationships in bulk.
     *
     * @param int $payoutId
     * @param Collection $items
     */
    public function saveMultipleItemPayouts(int $payoutId, Array $items): void
    {
        $itemPayoutData = [];

        // Loop through each item and insert it based on its quantity
        foreach ($items as $item) {
            $itemPayoutData[] = [
                'item_id' => $item['Item ID'],
                'payout_id' => $payoutId,
                'quantity' => $item['Item Quantity']
            ];   
        }
        // Perform a bulk insert into the item_payout table
        \DB::table('item_payout')->insert($itemPayoutData);
    }

}
