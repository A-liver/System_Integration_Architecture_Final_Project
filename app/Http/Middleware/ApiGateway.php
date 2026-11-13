<?php

namespace App\Http\Middleware;

use App\Models\IntegrationLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Single entry point for REST clients: authenticates the caller, tags the
 * request with an id, and records the route, status and time taken.
 */
class ApiGateway
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::uuid();
        $expected = (string) config('registration.gateway_key');
        $started = hrtime(true);

        if ($expected === '' || ! hash_equals($expected, (string) $request->header('X-Gateway-Key'))) {
            IntegrationLog::record('api_gateway', $request->method().' '.$request->path(), 'denied', 'Missing or wrong gateway key', $requestId);

            return response()->json(['error' => 'unauthorized', 'message' => 'A valid X-Gateway-Key header is required.'], 401);
        }

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        IntegrationLog::record(
            'api_gateway',
            $request->method().' '.$request->path(),
            (string) $response->getStatusCode(),
            null,
            $requestId,
            (int) ((hrtime(true) - $started) / 1_000_000),
        );

        return $response;
    }
}
