<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Facebook Comment Webhook Controller
 *
 * Handles:
 * - POST /webhook/facebook/comments — Incoming comments on page posts
 *
 * Process:
 * 1. Receive comment from Facebook
 * 2. Skip if it's from the page itself (avoid loops)
 * 3. Extract user PSID (Page-Scoped ID) from comment
 * 4. Send to AI engine for reply generation
 * 5. Post reply as a comment response (public)
 * 6. Send personalized welcome message via Messenger (private)
 */
class FacebookCommentController extends Controller
{
    /**
     * POST /webhook/facebook/comments
     *
     * Receives comments on page posts from Facebook.
     * Generates AI-powered replies and sends both public and private responses.
     */
    public function handleComment(Request $request): JsonResponse
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
                $events = $entry['changes'] ?? [];

                foreach ($events as $change) {
                    if (($change['field'] ?? '') === 'feed') {
                        $this->processFeedChange($change['value'] ?? [], $pageId);
                    }
                }
            }
        }

        // Always return 200 to Facebook
        return response()->json(['status' => 'ok']);
    }

    /**
     * Process a feed change (comment on a post).
     */
    protected function processFeedChange(array $value, ?string $pageId): void
    {
        // Only process new comments
        $item = $value['item'] ?? null;
        if ($item !== 'comment') {
            Log::debug('Skipping non-comment feed change', ['value' => $value]);
            return;
        }

        $commentId = $value['comment_id'] ?? null;
        $postId = $value['post_id'] ?? null;
        $commentText = $value['message'] ?? null;
        $fromId = $value['from']['id'] ?? null;  // PSID of the user
        $fromName = $value['from']['name'] ?? null;

        if (!$commentId || !$commentText) {
            Log::warning('Comment missing required fields', ['value' => $value]);
            return;
        }

        // Skip comments from the page itself (avoid infinite loops)
        $pageIdConfig = config('services.messenger.page_id');
        if ($fromId && $pageIdConfig && $fromId === $pageIdConfig) {
            Log::debug('Skipping comment from page itself', ['from' => $fromId]);
            return;
        }

        Log::info('Facebook comment received', [
            'comment_id' => $commentId,
            'post_id' => $postId,
            'from_id' => $fromId,
            'from' => $fromName,
            'text' => $commentText,
        ]);

        // Process through AI engine with comment-specific context
        $aiResult = $this->processCommentWithAI($commentText, $fromName, $commentId);

        // 1. Post public reply under the comment
        $this->replyToComment($commentId, $aiResult['reply_text'] ?? null);

        // 2. Send private message to user via Messenger
        if ($fromId) {
            $this->sendPrivateMessage($fromId, $commentText, $fromName, $aiResult);
        }

        // 3. Like the comment for engagement
        $this->likeComment($commentId);
    }

    /**
     * Process comment through AI engine with context.
     *
     * Adds context about Facebook comments and encourages Messenger contact.
     */
    protected function processCommentWithAI(
        string $commentText,
        ?string $commenterName,
        string $commentId
    ): array {
        // Add context to help AI understand this is a Facebook comment
        $contextualMessage = $this->buildCommentContext($commentText, $commenterName);

        // Try calling the FastAPI service first
        $aiServiceUrl = config('services.messenger.ai_service_url');

        if ($aiServiceUrl) {
            try {
                $response = Http::timeout(30)
                    ->post("{$aiServiceUrl}/v1/reply", [
                        'message' => $contextualMessage,
                        'conversation_id' => "comment_{$commentId}",
                        'source' => 'facebook_comment',
                    ]);

                if ($response->successful()) {
                    $result = $response->json();
                    return $this->enhanceCommentReply($result, $commenterName);
                }
            } catch (\Exception $e) {
                Log::warning('AI service call failed for comment, falling back to local', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Fallback: Call Python script directly
        return $this->processCommentWithPython($contextualMessage, $commentId);
    }

    /**
     * Build contextual message for AI processing.
     */
    protected function buildCommentContext(string $commentText, ?string $commenterName): string
    {
        $name = $commenterName ? " (from {$commenterName})" : '';

        return <<<EOT
[FACEBOOK COMMENT{$name}]

The following is a comment on a Facebook post from our Algerian School Support page:

"{commentText}"

Generate a friendly, professional reply in Algerian Darija/French that:
1. Thanks them for their interest
2. Answers their question if any
3. Encourages them to send us a private message on Messenger for personalized assistance
4. Mentions they can provide their phone number for faster registration
5. Keep the reply concise (2-3 sentences max)
6. Use appropriate emojis sparingly
EOT;
    }

    /**
     * Build context for private Messenger message.
     *
     * This message is more detailed and personalized.
     */
    protected function buildPrivateMessageContext(
        string $commentText,
        ?string $commenterName
    ): string {
        $nameGreeting = $commenterName ? " {$commenterName}" : '';

        return <<<EOT
[PRIVATE MESSENGER WELCOME - After Facebook Comment]

User "{$commenterName}" just commented on our Facebook post: "{$commentText}"

Generate a warm, personalized welcome message in Algerian Darija/French for Messenger:
1. Address them by name if available
2. Thank them for their comment on the post
3. Mention that you noticed their interest in the school/support courses
4. Offer to provide detailed information about:
   - Pricing (BEM: 2000 DA, BAC: 2500 DA per subject/month)
   - Schedule (Fridays, Saturdays, evenings)
   - Subjects available
5. Ask for their phone number or preferred contact method
6. Keep it friendly, professional, and encouraging
7. Use emojis appropriately
8. The message should feel personal and not like a template
EOT;
    }

    /**
     * Enhance AI reply for comment context.
     */
    protected function enhanceCommentReply(array $result, ?string $commenterName): array
    {
        $reply = $result['reply_text'] ?? '';

        // Ensure the reply encourages Messenger contact
        $messengerPrompt = $this->getMessengerPrompt();

        // Only add if not already mentioned
        if (!str_contains(strtolower($reply), 'message') &&
            !str_contains(strtolower($reply), 'messenger') &&
            !str_contains(strtolower($reply), 'رسالة')) {
            $reply .= "\n\n" . $messengerPrompt;
        }

        $result['reply_text'] = $reply;
        return $result;
    }

    /**
     * Get the Messenger prompt to add to replies.
     */
    protected function getMessengerPrompt(): string
    {
        $language = config('services.messenger.reply_language', 'darija');

        $prompts = [
            'darija' => "📩 Ib3atlna message privé 3la Messenger wala btelfon 3la: " . config('services.messenger.contact_phone', '+213 XXX XX XX XX'),
            'french' => "📩 Envoyez-nous un message privé sur Messenger ou appelez-nous au: " . config('services.messenger.contact_phone', '+213 XXX XX XX XX'),
            'arabic' => "📩 أرسل لنا رسالة خاصة على الماسنجر أو اتصل بنا على: " . config('services.messenger.contact_phone', '+213 XXX XX XX XX'),
        ];

        return $prompts[$language] ?? $prompts['darija'];
    }

    /**
     * Process comment using Python AI engine directly.
     */
    protected function processCommentWithPython(string $message, string $commentId): array
    {
        $pythonPath = config('services.messenger.python_path', 'python');
        $scriptPath = base_path('../ai_engine/process_message.py');

        $escapedMessage = escapeshellarg($message);
        $escapedConversationId = escapeshellarg("comment_{$commentId}");

        $command = "{$pythonPath} {$scriptPath} --message {$escapedMessage} --conversation-id {$escapedConversationId} --json 2>&1";

        try {
            $output = shell_exec($command);
            $result = json_decode($output, true);

            if (json_last_error() === JSON_ERROR_NONE && isset($result['reply_text'])) {
                return $this->enhanceCommentReply($result, null);
            }

            // If JSON parsing fails, return a fallback
            return [
                'reply_text' => $this->getFallbackReply(),
                'extracted_info' => (object)[],
            ];
        } catch (\Exception $e) {
            Log::error('Python AI engine failed for comment', ['error' => $e->getMessage()]);

            return [
                'reply_text' => $this->getFallbackReply(),
                'extracted_info' => (object)[],
            ];
        }
    }

    /**
     * Get a fallback reply when AI fails.
     */
    protected function getFallbackReply(): string
    {
        return "Merci pour votre intérêt! 🎓\n\n" .
               "Envoyez-nous un message privé sur Messenger pour plus de détails " .
               "et pour vous inscrire rapidement.\n\n" .
               "📩 Message privé disponible sur notre page!";
    }

    /**
     * Send private message to user via Messenger.
     *
     * This is the main feature: after someone comments, we send them
     * a personalized welcome message directly in their Messenger inbox.
     */
    protected function sendPrivateMessage(
        string $userPsid,
        string $commentText,
        ?string $userName,
        array $aiResult
    ): bool {
        $pageAccessToken = config('services.messenger.page_access_token');

        if (!$pageAccessToken) {
            Log::warning('Facebook page access token not configured for private message');
            return false;
        }

        // Generate personalized private message using AI
        $privateMessage = $this->generatePrivateMessage($commentText, $userName);

        try {
            $response = Http::timeout(10)
                ->post("https://graph.facebook.com/v18.0/me/messages", [
                    'access_token' => $pageAccessToken,
                    'recipient' => ['id' => $userPsid],
                    'message' => ['text' => $privateMessage],
                ]);

            if ($response->successful()) {
                Log::info('Private message sent to commenter', [
                    'user_psid' => $userPsid,
                    'user_name' => $userName,
                ]);
                return true;
            }

            Log::warning('Failed to send private message', [
                'user_psid' => $userPsid,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Exception sending private message', [
                'user_psid' => $userPsid,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Generate personalized private message for Messenger.
     */
    protected function generatePrivateMessage(string $commentText, ?string $userName): string
    {
        $language = config('services.messenger.reply_language', 'darija');
        $contactPhone = config('services.messenger.contact_phone', '+213 XXX XX XX XX');
        $schoolName = config('services.messenger.school_name', 'مدرستنا');

        // Generate message based on language preference
        $messages = [
            'darija' => $this->getDarijaPrivateMessage($userName, $commentText, $contactPhone, $schoolName),
            'french' => $this->getFrenchPrivateMessage($userName, $commentText, $contactPhone, $schoolName),
            'arabic' => $this->getArabicPrivateMessage($userName, $commentText, $contactPhone, $schoolName),
        ];

        return $messages[$language] ?? $messages['darija'];
    }

    /**
     * Get Darija private message.
     */
    protected function getDarijaPrivateMessage(
        ?string $userName,
        string $commentText,
        string $contactPhone,
        string $schoolName
    ): string {
        $name = $userName ? " {$userName}" : '';

        return "سلام{$name}! 👋\n\n" .
               "Sherfna b commentaire dyalk 3la l-post dyalna! 🎓\n\n" .
               "Daba ghadi n3tiwak kolchi 3la {$schoolName}:\n\n" .
               "📚 Tarifatna:\n" .
               "- BEM: 2000 DA / matiere / chhar\n" .
               "- BAC: 2500 DA / matiere / chhar\n" .
               "- Intensif: 5000-6000 DA\n\n" .
               "⏰ Awqatna:\n" .
               "- Jemaa / Sabt\n" .
               "- Ftrak masaiya\n\n" .
               "📍 L'mo9ata3: El Oued\n\n" .
               "3aytelna b ntifown wala 3mel message PRIVÉ hna! 📩\n\n" .
               "Rqamna: {$contactPhone}\n\n" .
               "Yallah nab9aw f contact! 💪";
    }

    /**
     * Get French private message.
     */
    protected function getFrenchPrivateMessage(
        ?string $userName,
        string $commentText,
        string $contactPhone,
        string $schoolName
    ): string {
        $name = $userName ? " {$userName}" : '';

        return "Bonjour{$name}! 👋\n\n" .
               "Merci pour votre commentaire sur notre publication! 🎓\n\n" .
               "Voici les détails de {$schoolName}:\n\n" .
               "📚 Nos tarifs:\n" .
               "- BEM: 2000 DA / matière / mois\n" .
               "- BAC: 2500 DA / matière / mois\n" .
               "- Intensif: 5000-6000 DA\n\n" .
               "⏰ Horaires:\n" .
               "- Vendredi & Samedi\n" .
               "- Créneaux du soir\n\n" .
               "📍 Localisation: El Oued\n\n" .
               "N'hésitez pas à nous appeler ou envoyer un message PRIVÉ! 📩\n\n" .
               "Téléphone: {$contactPhone}\n\n" .
               "À très bientôt! 💪";
    }

    /**
     * Get Arabic private message.
     */
    protected function getArabicPrivateMessage(
        ?string $userName,
        string $commentText,
        string $contactPhone,
        string $schoolName
    ): string {
        $name = $userName ? " {$userName}" : '';

        return "مرحبا{$name}! 👋\n\n" .
               "شكرا على تعليقك على منشورنا! 🎓\n\n" .
               "إليك تفاصيل {$schoolName}:\n\n" .
               "📚 الأسعار:\n" .
               "- BEM: 2000 دج / مادة / شهر\n" .
               "- BAC: 2500 دج / مادة / شهر\n" .
               "- المكثف: 5000-6000 دج\n\n" .
               "⏰ الأوقات:\n" .
               "- الجمعة والسبت\n" .
               "- فترات مسائية\n\n" .
               "📍 المقر: الوادي\n\n" .
               "لا تتردد في الاتصال بنا أو إرسال رسالة خاصة! 📩\n\n" .
               "الهاتف: {$contactPhone}\n\n" .
               "نتواصل معك قريبا! 💪";
    }

    /**
     * Reply to a comment using Facebook Graph API.
     *
     * Posts a reply under the original comment (public).
     */
    protected function replyToComment(string $commentId, ?string $replyText): bool
    {
        if (!$replyText) {
            return false;
        }

        $pageAccessToken = config('services.messenger.page_access_token');

        if (!$pageAccessToken) {
            Log::warning('Facebook page access token not configured for comment reply');
            return false;
        }

        try {
            // Use the Comments API to reply to the comment
            $response = Http::timeout(10)
                ->post("https://graph.facebook.com/v18.0/{$commentId}/comments", [
                    'access_token' => $pageAccessToken,
                    'message' => $replyText,
                ]);

            if ($response->successful()) {
                $replyId = $response->json('id');
                Log::info('Comment reply posted', [
                    'original_comment' => $commentId,
                    'reply_id' => $replyId,
                ]);
                return true;
            }

            Log::warning('Failed to post comment reply', [
                'comment_id' => $commentId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Exception posting comment reply', [
                'comment_id' => $commentId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Like a comment for engagement.
     */
    protected function likeComment(string $commentId): bool
    {
        $pageAccessToken = config('services.messenger.page_access_token');

        if (!$pageAccessToken) {
            return false;
        }

        try {
            $response = Http::timeout(5)
                ->post("https://graph.facebook.com/v18.0/{$commentId}/likes", [
                    'access_token' => $pageAccessToken,
                ]);

            if ($response->successful()) {
                Log::debug('Comment liked', ['comment_id' => $commentId]);
            }

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}
