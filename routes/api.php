<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PayoutController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('log')->group(function () {
    // Endpoint for creating a payout
    // Route::post('/payouts', [PayoutController::class 'store']);
    Route::post('/payouts', [PayoutController::class, 'store']);

    // Route::post('/payouts', [PayoutController::class, 'store']);

});
