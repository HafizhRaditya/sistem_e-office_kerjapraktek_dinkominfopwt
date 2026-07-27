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

    // Cloudflare Turnstile (FR-A02). Leave the keys empty in .env to skip
    // verification during local development; set them to enable it in staging/prod.
    'turnstile' => [
        'sitekey' => env('TURNSTILE_SITEKEY'),
        'secret' => env('TURNSTILE_SECRET'),
    ],

    // Keycloak SSO (OIDC) — a SECOND login path beside NIP/NIK; the local
    // password login is unchanged. Read by socialiteproviders/keycloak, which
    // builds its endpoints from base_url + realms. Leaving client_id/secret
    // empty disables the "Masuk dengan Keycloak" button (the AuthController gate
    // and the login view both check config('services.keycloak.client_id')), so a
    // fresh checkout without Keycloak keys still runs on password login alone.
    'keycloak' => [
        'client_id' => env('KEYCLOAK_CLIENT_ID'),
        'client_secret' => env('KEYCLOAK_CLIENT_SECRET'),
        'redirect' => env('KEYCLOAK_REDIRECT_URI'),
        'base_url' => env('KEYCLOAK_BASE_URL'),
        'realms' => env('KEYCLOAK_REALM'),
    ],

];
