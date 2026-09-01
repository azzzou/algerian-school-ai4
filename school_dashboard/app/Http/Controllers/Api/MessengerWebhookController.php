<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Facebook Messenger Webhook Controller
 *
 * Handles:
 * - GET  /webhook/messenger — Verification handshake (hub.verify_token)
 * - POST /webhook/messenger — Incoming messages from Messenger
 *
 * Process:
 * 1. Receive message from Facebook
 * 2. Send to AI engine for processing
 * 3. Store lead in database
 * 4. Auto-reply via Facebook Graph API
 */
class MessengerWebhookController extends Controller
{
    /**
     * GET /webhook/messenger
     *
     * Facebook sends this request to verify your webhook URL.
     * It includes hub.mode, hub.verify_token, and hub.challenge.
     */
    public function verify(Request $request): string
    {
        // Laravel converts dots in query params to underscores
        // Facebook sends: hub.mode, hub.verify_token, hub.challenge
        $mode = $request->query('hub.mode') ?? $request->query('hub_mode');
        $token = $request->query('hub.verify_token') ?? $request->query('hub_verify_token');
        $challenge = $request->query('hub.challenge') ?? $request->query('hub_challenge');

        $verifyToken = config('services.messenger.verify_token');

        if ($mode === 'subscribe' && $token === $verifyToken) {
            Log::info('Messenger webhook verified successfully');
            return $challenge;
        }

        Log::warning('Messenger webhook verification failed', [
            'mode' => $mode,
            'token' => $token,
        ]);

        return response('Forbidden', 403);
    }

    /**
     * POST /webhook/messenger
     *
     * Receives incoming messages from Facebook Messenger.
     * Processes each message through the AI engine and auto-replies.
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        $body = $request->all();

        // Validate the webhook request
        if (!isset($body['object']) || $body['object'] !== 'page') {
            return response()->json(['status' => 'error', 'message' => 'Not a page event'], 404);
        }

        // Process each entry
        if (isset($body['entry'])) {
            foreach ($body['entry'] as $entry) {
                $pageId = $entry['id'] ?? null;
                $events = $entry['messaging'] ?? [];

                foreach ($events as $event) {
                    $this->processMessagingEvent($event, $pageId);
                }
            }
        }

        // Always return 200 to Facebook
        return response()->json(['status' => 'ok']);
    }

    /**
     * Process a single messaging event.
     */
    protected function processMessagingEvent(array $event, ?string $pageId): void
    {
        $senderId = $event['sender']['id'] ?? null;
        $recipientId = $event['recipient']['id'] ?? null;
        $timestamp = $event['timestamp'] ?? null;
        $message = $event['message'] ?? null;
        $postback = $event['postback'] ?? null;

        // Only process text messages
        if (!$message || !isset($message['text'])) {
            Log::debug('Skipping non-text messaging event', ['event' => $event]);
            return;
        }

        $messageText = $message['text'];
        $messageId = $message['mid'] ?? null;

        Log::info('Messenger message received', [
            'sender' => $senderId,
            'text' => $messageText,
            'message_id' => $messageId,
        ]);

        // Process through AI engine
        $aiResult = $this->processWithAI($messageText, $senderId);

        // Store lead in database
        $lead = $this->storeLead($aiResult, $messageText, $senderId, $pageId);

        // Auto-reply to user
        $this->sendAutoReply($senderId, $aiResult['reply_text'] ?? null);
    }

    /**
     * Process message through the AI engine (Gemini/CrewAI).
     *
     * Calls the FastAPI service or directly uses the Python engine.
     */
    protected function processWithAI(string $message, string $conversationId): array
    {
        // Try calling the FastAPI service first
        $aiServiceUrl = config('services.messenger.ai_service_url');

        if ($aiServiceUrl) {
            try {
                $response = Http::timeout(30)
                    ->post("{$aiServiceUrl}/v1/reply", [
                        'message' => $message,
                        'conversation_id' => $conversationId,
                    ]);

                if ($response->successful()) {
                    return $response->json();
                }
            } catch (\Exception $e) {
                Log::warning('AI service call failed, falling back to local', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Fallback: Call Python script directly
        return $this->processWithPython($message, $conversationId);
    }

    /**
     * Process message using Python AI engine directly.
     */
    protected function processWithPython(string $message, string $conversationId): array
    {
        $pythonPath = config('services.messenger.python_path', 'python');
        $scriptPath = base_path('../ai_engine/crews/algerian_support_crew/main.py');

        $escapedMessage = escapeshellarg($message);
        $escapedConversationId = escapeshellarg($conversationId);

        $command = "{$pythonPath} {$scriptPath} --message {$escapedMessage} --conversation-id {$escapedConversationId} --json 2>&1";

        try {
            $output = shell_exec($command);
            $result = json_decode($output, true);

            if (json_last_error() === JSON_ERROR_NONE && isset($result['reply_text'])) {
                return $result;
            }

            // If JSON parsing fails, return a fallback
            return [
                'reply_text' => 'Saha lik! 3aytelna baad chwiya, l\'agent ghadi yruj3 lak. (Processing...)',
                'extracted_info' => (object)[],
            ];
        } catch (\Exception $e) {
            Log::error('Python AI engine failed', ['error' => $e->getMessage()]);

            return [
                'reply_text' => 'Saha lik! 3aytelna baad chwiya wala t3awed b messajek. (Technical issue)',
                'extracted_info' => (object)[],
            ];
        }
    }

    /**
     * Store the lead in the database.
     */
    protected function storeLead(
        array $aiResult,
        string $rawMessage,
        string $senderId,
        ?string $pageId
    ): Lead {
        $extractedInfo = $aiResult['extracted_info'] ?? [];

        // Handle both array and object
        if (is_object($extractedInfo)) {
            $extractedInfo = (array) $extractedInfo;
        }

        $lead = Lead::create([
            'id'               => Str::uuid()->toString(),
            'created_at'       => now('UTC')->toIso8601String(),
            'source'           => 'messenger',
            'conversation_id'  => $senderId,
            'raw_message'      => $rawMessage,
            'student_name'     => $extractedInfo['student_name'] ?? null,
            'phone_number'     => $extractedInfo['phone_number'] ?? null,
            'branch_or_level'  => $extractedInfo['branch_or_level'] ?? null,
            'lead_score'       => strtoupper($extractedInfo['lead_score'] ?? 'COLD'),
            'level'            => $extractedInfo['level'] ?? null,
            'filiere'          => $extractedInfo['filiere'] ?? null,
            'subject'          => $extractedInfo['subject'] ?? null,
            'ai_reply'         => $aiResult['reply_text'] ?? null,
        ]);

        Log::info('Lead stored from Messenger', [
            'lead_id' => $lead->id,
            'sender' => $senderId,
            'score' => $lead->lead_score,
        ]);

        return $lead;
    }

    /**
     * Send auto-reply to user via Facebook Graph API.
     */
    protected function sendAutoReply(string $recipientId, ?string $messageText): bool
    {
        if (!$messageText) {
            return false;
        }

        $pageAccessToken = config('services.messenger.page_access_token');

        if (!$pageAccessToken) {
            Log::warning('Facebook page access token not configured');
            return false;
        }

        try {
            $response = Http::timeout(10)
                ->post("https://graph.facebook.com/v18.0/me/messages", [
                    'access_token' => $pageAccessToken,
                    'recipient' => ['id' => $recipientId],
                    'message' => ['text' => $messageText],
                ]);

            if ($response->successful()) {
                Log::info('Auto-reply sent', ['recipient' => $recipientId]);
                return true;
            }

            Log::warning('Failed to send auto-reply', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Exception sending auto-reply', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Send a typed-on indicator before replying.
     */
    protected function sendTypingIndicator(string $recipientId): void
    {
        $pageAccessToken = config('services.messenger.page_access_token');

        if (!$pageAccessToken) {
            return;
        }

        try {
            Http::timeout(5)
                ->post("https://graph.facebook.com/v18.0/me/messages", [
                    'access_token' => $pageAccessToken,
                    'recipient' => ['id' => $recipientId],
                    'sender_action' => 'typing_on',
                ]);
        } catch (\Exception $e) {
            // Silently ignore typing indicator failures
        }
    }
}
