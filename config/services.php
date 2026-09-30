<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'ai' => [
        'api_key' => env('AI_API_KEY', ''),
        'provider' => env('AI_PROVIDER', 'gemini'),
        'timeout' => (int) env('AI_TIMEOUT', 45),
        'budget' => (int) env('AI_BUDGET', 90),
    ],

    /*
     | Google Gemini (primary provider - requires GEMINI_API_KEY).
     | Model names change over time; override with GEMINI_MODEL if needed.
     */
    'gemini' => [
        'key' => env('GEMINI_API_KEY', ''),
        'model' => env('GEMINI_MODEL', 'gemini-3.6-flash'),
        'fallback_models' => array_values(array_filter(array_map('trim', explode(
            ',',
            env('GEMINI_FALLBACK_MODELS', 'gemini-3.8-flash,gemini-3.5-flash,gemini-3.1-flash-lite')
        )))),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com'),
    ],

    /*
     | GitHub Models (OpenAI compatible - requires GITHUB_TOKEN with models:read).
     */
    'github' => [
        'token' => env('GITHUB_TOKEN', ''),
        'model' => env('GITHUB_MODEL', 'gpt-4o-mini'),
        'base_url' => env('GITHUB_MODELS_BASE_URL', 'https://models.github.ai/inference/chat/completions'),
    ],

    /*
     | Keyless best-effort provider (rate limited by the upstream service).
     */
    'pollinations' => [
        'base_url' => env('POLLINATIONS_BASE_URL', 'https://text.pollinations.ai/openai/v1/chat/completions'),
        'model' => env('POLLINATIONS_MODEL', 'openai'),
    ],

    'openrouter' => [
        'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1/chat/completions'),
        'model' => env('OPENROUTER_MODEL', 'meta-llama/llama-3.3-8b-instruct:free'),
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
