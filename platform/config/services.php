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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
     * Social sign-in. Each block stays empty until you add credentials, and
     * SocialAuthController returns 503 for an unconfigured provider rather
     * than exposing a broken redirect.
     */
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI', '/auth/facebook/callback'),
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT_URI', '/auth/github/callback'),
    ],

    /*
     * Comment moderation.
     *
     * `driver` selects the Moderator implementation. The default lexicon is
     * local, free, auditable, and narrow by design — see LexiconModerator for
     * what it deliberately does not catch.
     */
    'moderation' => [
        'driver' => env('MODERATION_DRIVER', 'lexicon'),
    ],

    /*
     * While `secret` is null the app runs its demo upgrade path: premium can be
     * toggled from /upgrade with no payment taken. Setting a real key disables
     * that route (see UpgradeController) — wire Cashier before you set it.
     */
    'billing' => [
        'driver' => env('BILLING_DRIVER', 'stripe'),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'price_id' => env('STRIPE_PRICE_ID'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

];
