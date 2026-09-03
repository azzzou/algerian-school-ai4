<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Stripe, Mailgun, SparkPost and others. This file provides a sane
    | default location for this type of information, allowing packages
    | to have a conventional place to find your various credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'ses' => [
        'key' => env('SES_KEY'),
        'secret' => env('SES_SECRET'),
        'region' => env('SES_REGION', 'us-east-1'),
    ],

    'sparkpost' => [
        'secret' => env('SPARKPOST_SECRET'),
    ],

    'stripe' => [
        'model' => App\User::class,
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Facebook Messenger
    |--------------------------------------------------------------------------
    |
    | Configuration for Facebook Messenger webhook integration.
    | Get these values from the Facebook Developer Dashboard.
    |
    */
    'messenger' => [
        'verify_token'    => env('MESSENGER_VERIFY_TOKEN', 'algerian-school-verify-token-2026'),
        'page_access_token' => env('MESSENGER_PAGE_ACCESS_TOKEN'),
        'app_secret'      => env('MESSENGER_APP_SECRET'),
        'ai_service_url'  => env('MESSENGER_AI_SERVICE_URL', 'http://localhost:8000'),
        'python_path'     => env('MESSENGER_PYTHON_PATH', 'python'),
        'page_id'         => env('MESSENGER_PAGE_ID'),
        'contact_phone'   => env('MESSENGER_CONTACT_PHONE', '+213 XXX XX XX XX'),
        'reply_language'  => env('MESSENGER_REPLY_LANGUAGE', 'darija'),
        'school_name'     => env('MESSENGER_SCHOOL_NAME', 'مدرستنا'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Gemini (AI replies for the Messenger bot)
    |--------------------------------------------------------------------------
    |
    | Uses the Gemini REST API:
    |   POST https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent
    |
    | The model id and endpoint are hardcoded in GeminiService (gemini-1.5-flash)
    | so no env var can corrupt them. Only the API key is configurable here,
    | resolved from GEMINI_API_KEY first, then GOOGLE_API_KEY.
    |
    */
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY', env('GOOGLE_API_KEY')),
        'timeout' => (int) env('GEMINI_TIMEOUT', 30),
    ],

];
