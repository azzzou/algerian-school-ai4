<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * GeminiService
 *
 * Thin wrapper around the Google Gemini REST API used by the Messenger bot to
 * generate a real AI reply for incoming student messages.
 *
 * Endpoint:
 *   POST {base_url}/models/{model}:generateContent
 *   Auth via x-goog-api-key header (falls back to the ?key= query param).
 *
 * The system prompt gives the bot the persona of a front-desk assistant for
 * "BAC Boumerdes" who replies warmly, professionally, and in the language the
 * student prefers (Arabic / French / Algerian Darija).
 */
class GeminiService
{
    /** System prompt defining the bot persona. */
    public const SYSTEM_PROMPT = <<<'PROMPT'
        أنت مساعد ذكي وموظف استقبال لمركز "BAC Boumerdes"، تجيب الطلاب بأسلوب ودي واحترافي وباللغة التي يفضلونها (العربية أو الفرنسية أو الدارجة الجزائرية) لمساعدتهم في استفساراتهم حول امتحانات البكالوريا والتسجيلات.
        PROMPT;

    /**
     * Generate an AI reply for a user message.
     *
     * @param  string  $message  The student's incoming message text.
     * @param  string  $conversationId  Optional psid/conversation id for context.
     * @return string  The generated reply text.
     *
     * @throws \Exception  If the API key is missing or the request fails.
     */
    public function reply(string $message, string $conversationId = ''): string
    {
        $apiKey = config('services.gemini.api_key');
        $model  = config('services.gemini.model', 'gemini-3.6-flash');
        $base   = rtrim((string) config('services.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/');
        $timeout = (int) config('services.gemini.timeout', 30);

        if (!$apiKey) {
            throw new \RuntimeException('Gemini API key is not configured (GEMINI_API_KEY / GOOGLE_API_KEY).');
        }

        $url = "{$base}/models/{$model}:generateContent";

        $payload = [
            'system_instruction' => [
                'parts' => [['text' => self::SYSTEM_PROMPT]],
            ],
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => $message]],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.7,
            ],
        ];

        $response = Http::timeout($timeout)
            ->withHeaders(['x-goog-api-key' => $apiKey])
            ->post($url, $payload);

        if (!$response->successful()) {
            Log::error('Gemini API request failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \RuntimeException('Gemini API returned status ' . $response->status());
        }

        $data = $response->json();

        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (!$text) {
            throw new \RuntimeException('Gemini returned no reply text.');
        }

        return trim($text);
    }
}