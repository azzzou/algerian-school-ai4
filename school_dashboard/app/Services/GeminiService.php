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
 *   Content-Type: application/json
 *   Auth:        x-goog-api-key: {apiKey}
 *
 * The system prompt gives the bot the persona of a front-desk assistant for
 * "BAC Boumerdes" who replies warmly, professionally, and in the language the
 * student prefers (Arabic / French / Algerian Darija).
 */
class GeminiService
{
    /** System prompt defining the bot persona. */
    public const SYSTEM_PROMPT = <<<'PROMPT'
        أنت مساعد ذكي وموظف استقبال لمركز "BAC Boumerdes"، تجيب الطلاب بأسلوب ودي واحترافي وبالغة التي يفضلونها (العربية أو الفرنسية أو الدارجة الجزائرية) لمساعدتهم في استفساراتهم حول امتحانات البكالوريا والتسجيلات.
        PROMPT;

    /**
     * Generate an AI reply for a user message.
     *
     * @param  string  $message  The student's incoming message text.
     * @param  string  $conversationId  Optional psid/conversation id for context.
     * @return string  The generated reply text.
     *
     * @throws \RuntimeException  If the API key/model is missing or the request
     *                            fails. The message carries the Gemini diagnostic.
     */
    public function reply(string $message, string $conversationId = ''): string
    {
        $apiKey = $this->resolveApiKey();
        $model  = $this->resolveModel();
        $timeout = (int) config('services.gemini.timeout', 30);

        // Build the standard Google AI Studio endpoint explicitly and decisively.
        // The model is cleaned below so "models/" or "gemini/" prefixes can never
        // leak into the URL and cause a duplicate "models/models/... : generateContent".
        $cleanModel = preg_replace('#^(models/|gemini/)#', '', trim($model));
        if (empty($cleanModel)) {
            $cleanModel = 'gemini-1.5-flash';
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$cleanModel}:generateContent?key=" . $apiKey;

        Log::info('Gemini request prepared', [
            'url'     => $url,
            'model'   => $cleanModel,
            'sender'  => $conversationId,
            'message' => mb_substr($message, 0, 500),
        ]);

        // Payload matches the Gemini generateContent REST contract:
        //   - system_instruction.{ parts[].text }
        //   - contents[] with role + parts[].text
        //   - generationConfig (temperature)
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

        try {
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'x-goog-api-key' => $apiKey,
                    'Content-Type'   => 'application/json',
                ])
                ->post($url, $payload);
        } catch (\Throwable $e) {
            Log::error('Gemini request threw an exception', [
                'url'      => $url,
                'error'    => $e->getMessage(),
                'exception'=> get_class($e),
            ]);
            throw new \RuntimeException('Gemini connection failed: ' . $e->getMessage(), 0, $e);
        }

        $body = $response->body();

        if (!$response->successful()) {
            // Surface the real Gemini error payload so it can be diagnosed.
            Log::error('Gemini API returned an error', [
                'status' => $response->status(),
                'url'    => $url,
                'body'   => $body,
            ]);
            throw new \RuntimeException(
                sprintf('Gemini API error (HTTP %d): %s', $response->status(), $body)
            );
        }

        $data = $response->json();

        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (!$text) {
            Log::error('Gemini returned an empty/unexpected response', [
                'status' => $response->status(),
                'body'   => $body,
            ]);
            throw new \RuntimeException('Gemini returned no reply text.');
        }

        return trim($text);
    }

    /**
     * Resolve the Gemini API key from GEMINI_API_KEY or GOOGLE_API_KEY.
     *
     * @return string
     */
    protected function resolveApiKey(): string
    {
        $key = config('services.gemini.api_key');
        if (!$key) {
            $key = env('GEMINI_API_KEY') ?: env('GOOGLE_API_KEY');
        }

        if (!is_string($key) || trim($key) === '') {
            throw new \RuntimeException('Gemini API key is not configured (set GEMINI_API_KEY or GOOGLE_API_KEY).');
        }

        return trim($key);
    }

    /**
     * Resolve and validate the Gemini model name.
     *
     * Returns a clean, explicit model id (e.g. "gemini-1.5-flash") suitable for
     * the REST endpoint ".../v1beta/models/{model}:generateContent". It strips
     * any SDK/environment noise: a "gemini/" prefix, an accidental "models/",
     * or surrounding slashes — so "models/" is never duplicated in the URL.
     *
     * @return string
     */
    protected function resolveModel(): string
    {
        $model = trim((string) config('services.gemini.model', 'gemini-1.5-flash')) ?: 'gemini-1.5-flash';

        // Strip an accidental "models/" path prefix, then any surrounding slashes.
        $model = preg_replace('#^(models/)#i', '', $model);
        $model = trim($model, " \t\n\r\0\x0B/");

        // Map an SDK-style "gemini/<name>" (Google GenAI SDK convention) to the
        // REST model id "gemini-<name>" (e.g. gemini/1.5-flash -> gemini-1.5-flash).
        if (str_starts_with($model, 'gemini/')) {
            $model = 'gemini-' . substr($model, strlen('gemini/'));
        }

        if ($model === '' || !str_starts_with($model, 'gemini-')) {
            throw new \RuntimeException("Unsupported Gemini model '{$model}'. Expected a model like gemini-1.5-flash or gemini-1.5-pro.");
        }

        return $model;
    }
}