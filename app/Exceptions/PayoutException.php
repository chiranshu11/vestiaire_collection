<?php

namespace App\Exceptions;

use Exception;

class PayoutException extends Exception {

    /**
     * Log the error message for debugging purposes.
     */
    public function report() {
        // Log the error message with additional context
        \Log::error("Payout error: " . $this->getMessage(), [
            'exception' => $this
        ]);
    }

    /**
     * Render a JSON response for the client with a graceful error message.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function render($request) {
        // Return a structured JSON response with an appropriate status code (400 for client-side issues)
        return response()->json([
            'success' => false,
            'message' => 'Payout processing error',
            'error' => $this->getMessage(),
        ], 400);
    }
}
