<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * GET /webhook
     *
     * Facebook verification handshake.
     * Receives hub.mode, hub.verify_token, hub.challenge
     * and must echo back the challenge on success.
     */
    public function verify(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $mode      = $request->input('hub_mode') ?? $request->input('hub.mode');
        $token     = $request->input('hub_verify_token') ?? $request->input('hub.verify_token');
        $challenge = $request->input('hub_challenge') ?? $request->input('hub.challenge');

        if ($mode === 'subscribe' && $token === 'Azzou123') {
            Log::info('Webhook verified successfully');
            return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::warning('Webhook verification failed', compact('mode', 'token'));
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    /**
     * POST /webhook
     *
     * Receives all Messenger events (messages, postbacks, etc.)
     * and responds with 200 immediately.
     */
    public function handle(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        Log::info('Webhook Message Received:', $request->all());

        return response('EVENT_RECEIVED', 200);
    }
}
