<?php

use Illuminate\Support\Facades\Cache;

// Retrieves the payout limit at runtime, with caching rather than making redundant database requests
if (!function_exists('getPayoutLimit')) {
    
    function getPayoutLimit(): int
    {
        return 1000;
        //it would be from db in future
        return Cache::remember('payout_limit', 300, function () {
            return env('PAYOUT_LIMIT', 1000);
        });
    }
}
