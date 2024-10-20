<?php

use Illuminate\Support\Facades\Cache;

// Retrieves the payout limit at runtime, with caching rather than making redundant database requests
if (!function_exists('getPayoutLimit')) {
    
    function getPayoutLimit(): int
    {
        
        //In Future, Payout Limit would be powered from DB
        return Cache::remember('payout_limit', 30, function () { //For now cache is set for 30sec only
            return config("app.payout_limit", 1000);
        });
    }
}
