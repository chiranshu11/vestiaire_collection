<?php
namespace App\Repositories;

use App\Models\Payout;

class PayoutRepository
{
    /**
     * Create a new payout.
     *
     * @param array $data
     * @return Payout
     */
    public function createPayout(array $data): Payout
    {
        return Payout::create($data);
    }

}