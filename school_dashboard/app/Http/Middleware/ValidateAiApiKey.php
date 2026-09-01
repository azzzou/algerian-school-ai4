<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Validate that the incoming request carries a valid API key.
 *
 * The key is expected in the `X-API-Key` header OR as a `api_key` query
 * parameter.  The expected value is stored in config('app.ai_api_key').
 */
class ValidateAiApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     *
     * @throws \Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException
     */
    public function handle(Request $request, Closure $next)
    {
        $expected = config('app.ai_api_key');

        if (!$expected) {
            return response()->json([
                'success' => false,
                'error'   => 'Server API key not configured.',
            ], 500);
        }

        $provided = $request->header('X-API-Key') ?: $request->query('api_key');

        if (!$provided || !hash_equals($expected, $provided)) {
            return response()->json([
                'success' => false,
                'error'   => 'Unauthorized. Provide a valid X-API-Key header.',
            ], 401);
        }

        return $next($request);
    }
}
