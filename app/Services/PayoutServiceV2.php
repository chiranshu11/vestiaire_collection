<?php

namespace App\Services;


use App\Exceptions\PayoutException;
use App\Models\Item;
use App\Models\Payout;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;


class PayoutServiceV2
{

    /**
     * @throws PayoutException
     */
    public function process(Collection $items)
    {
        $payouts = [];

        DB::transaction(function() use (&$payouts, $items){
            $payouts = $this->splitPayouts($this->createPayouts($this->validateItems($items)));
        });

        return $payouts;
    }


    protected function createPayouts($db_items): Collection
    {
        $db_items = $db_items->map(function(Item $item){

            $original_amount = $item->quantity * $item->price_amount;
            $converted_amount = $this->convertCurrency($item->quantity * $item->price_amount, $item->price_currency, $item->seller->base_currency);

            return [
                'item_id' => $item->id,
                'seller_id' => $item->seller_id,
                'channel_item_code' => $item->channel_item_code,
                'seller_reference' => $item->seller_id,
                'base_currency' => $item->seller->base_currency,
                'item_currency' => $item->price_currency,
                'qty' => $item->quantity,
                'original_amount' => $original_amount,
                'converted_amount' => $converted_amount
            ];
        })->groupBy(['seller_id', 'item_currency', 'base_currency']);

//        return $db_items;


        $payouts = [];

        foreach ($db_items as $seller_id => $from_currencies){

            foreach ($from_currencies as $from_currency => $to_currencies){

                foreach ($to_currencies as $to_currency => $items){

                    $payout = new Payout();
                    $payout->seller_id = $seller_id;
                    $payout->original_amount = collect($items)->sum('original_amount');
                    $payout->converted_amount = collect($items)->sum('converted_amount');
                    $payout->original_currency = $from_currency;
                    $payout->converted_currency = $to_currency;
                    $payout->save();

                    $payout->items()->sync($items->pluck('item_id'));

                    $payouts[] = $payout;

                }

            }

        }

        return collect($payouts)->pluck('id');
    }

    protected function splitPayouts(Collection $payout_ids)
    {
        $payout_limit = config('app.payout_limit');

        $payouts = Payout::query()->find($payout_ids);

        $payouts->each(function(Payout $payout) use ($payout_limit){

            $amount = $payout->converted_amount;

            while ($amount >= 0) {

                if ($amount >= $payout_limit){
                    $to_save = $payout_limit;
                }else{
                    $to_save = $amount;
                }

                $transaction = new Transaction();
                $transaction->payout_id = $payout->id;
                $transaction->batch_amount_in_original_currency = $to_save;
                $transaction->batch_amount_in_base_currency = $to_save;
                $transaction->save();

                $amount = $amount - $payout_limit;

            }

        });

        $payouts->loadMissing('transactions', 'items');

        return $payouts;
    }

    protected function validateItems(Collection $items): \Illuminate\Database\Eloquent\Collection
    {
        $db_items = Item::query()
            ->with('seller')
            ->where(function(Builder $query) use ($items) {
                $items->each(function($item) use ($query){
                    $query->orWhere(function(Builder $q)use ($item){
                        $q->where('channel_item_code', $item['channel_item_code'])->where('seller_id', $item['seller_reference']);

                    });
                });
            })->get();

       if ($db_items->count() != $items->count()){
           throw new PayoutException("Item or Seller not found in database.");
       }

        return $db_items;
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

}