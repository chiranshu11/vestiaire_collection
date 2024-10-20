<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;

class LogRequest
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Log request details before processing
        $startTime = microtime(true);
        Log::info('Request received', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'params' => $request->all(),
            'timestamp' => now()
        ]);

        // Process the request
        $response = $next($request);

        // Log response details after processing
        $endTime = microtime(true);
        $duration = (float)($endTime - $startTime);  // Cast to float to avoid type mismatch

        Log::info('Response sent', [
            'status' => $response->getStatusCode(),
            'duration' => $duration,
            'timestamp' => now()
        ]);

        return $response;
    }
}
