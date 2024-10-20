<?php
namespace App\Http\Controllers;

use App\Http\Requests\PayoutRequest;
use App\Services\PayoutService;
use App\Services\PayoutServiceV2;
use Illuminate\Http\Request;

class PayoutController extends Controller
{
    protected $payoutService;

    public function __construct(PayoutServiceV2 $payoutService)
    {
        $this->payoutService = $payoutService;
    }

    public function store(PayoutRequest $request)
    {
        // Collect request data (seller_reference and channel_item_code)
        $payouts = $this->payoutService->processPayouts(collect($request->sold_items));

        return response()->json(['payouts' => $payouts], 201);
    }
}
