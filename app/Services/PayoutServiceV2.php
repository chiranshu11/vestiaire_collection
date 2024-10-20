<?php

namespace App\Services;

use App\Exceptions\PayoutException;
use App\Models\Item;
use App\Models\Payout;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

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

    protected function createPayouts($dbItems): Collection
    {
        $dbItems = $dbItems->map(function(Item $item){

            $originalAmount = $item->quantity * $item->price_amount;
            $convertedAmount = $this->convertCurrency($item->quantity * $item->price_amount, $item->price_currency, $item->seller->base_currency);

            return [
                'item_id' => $item->id,
                'seller_id' => $item->seller_id,
                'channel_item_code' => $item->channel_item_code,
                'seller_reference' => $item->seller_id,
                'base_currency' => $item->seller->base_currency,
                'item_currency' => $item->price_currency,
                'qty' => $item->quantity,
                'original_amount' => $originalAmount,
                'converted_amount' => $convertedAmount
            ];
        })->groupBy(['seller_id', 'item_currency', 'base_currency']);

        $payouts = [];

        foreach ($dbItems as $sellerId => $fromCurrencies){

            foreach ($fromCurrencies as $fromCurrency => $toCurrencies){

                foreach ($toCurrencies as $toCurrency => $items){

                    $payout = new Payout();
                    $payout->seller_id = $sellerId;
                    $payout->original_amount = collect($items)->sum('original_amount');
                    $payout->converted_amount = collect($items)->sum('converted_amount');
                    $payout->original_currency = $fromCurrency;
                    $payout->converted_currency = $toCurrency;
                    $payout->save();

                    $payout->items()->sync($items->pluck('item_id'));

                    $payouts[] = $payout;
                }

            }

        }

        return collect($payouts)->pluck('id');
    }

    protected function splitPayouts(Collection $payoutIds)
    {
        $payoutLimit = config('app.payout_limit');

        $payouts = Payout::query()->find($payoutIds);

        $payouts->each(function(Payout $payout) use ($payoutLimit){

            $amount = $payout->converted_amount;

            while ($amount >= 0) {

                if ($amount >= $payoutLimit){
                    $toSave = $payoutLimit;
                }else{
                    $toSave = $amount;
                }

                $transaction = new Transaction();
                $transaction->payout_id = $payout->id;
                $transaction->batch_amount_in_original_currency = $toSave;
                $transaction->batch_amount_in_base_currency = $toSave;
                $transaction->save();

                $amount = $amount - $payoutLimit;
            }

        });

        $payouts->loadMissing('transactions', 'items');

        return $payouts;
    }

    protected function validateItems(Collection $items): EloquentCollection
    {
        $dbItems = Item::query()
            ->with('seller')
            ->where(function(Builder $query) use ($items) {
                $items->each(function($item) use ($query){
                    $query->orWhere(function(Builder $q) use ($item){
                        $q->where('channel_item_code', $item['channel_item_code'])
                            ->where('seller_id', $item['seller_reference']);
                    });
                });
            })->get();

        if ($dbItems->count() != $items->count()){
            throw new PayoutException("Item or Seller not found in database.");
        }

        return $dbItems;
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

