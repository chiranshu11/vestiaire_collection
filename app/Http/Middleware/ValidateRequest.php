<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ValidateRequest
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Example validation logic
        if (!$request->isJson()) {
            return response()->json(['error' => 'Invalid request format. JSON expected.'], 400);
        }

        return $next($request);
    }
}
