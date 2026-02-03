<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CorrelationIdMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if request already has a correlation ID (for microservice architecture)
        // Otherwise generate a new UUID
        $correlationId = $request->header('X-Correlation-ID') ?: (string) Str::uuid();

        // Attach to request (for access from controllers/services)
        $request->headers->set('X-Correlation-ID', $correlationId);

        // Add to log context (to correlate all logs by this ID in tools like Grafana/Loki)
        \Illuminate\Support\Facades\Log::shareContext([
            'correlation_id' => $correlationId
        ]);

        $response = $next($request);

        // Also set on response header (so client can track it)
        $response->headers->set('X-Correlation-ID', $correlationId);

        return $response;
    }
}
