<?php
namespace App\Listeners;

use App\Events\PayoutCreated;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessPayout implements ShouldQueue
{
    // Handles async processing when a payout is created
    public function handle(PayoutCreated $event)
    {
        // Payout processing logic (e.g., notification, logging)
    }
}
