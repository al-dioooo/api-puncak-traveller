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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
        'mobile_redirect' => env('GOOGLE_MOBILE_REDIRECT_URI'),
    ],

    'midtrans' => [
        'environment' => env('MIDTRANS_ENVIRONMENT', 'sandbox'),
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'notification_url' => env('MIDTRANS_NOTIFICATION_URL'),
        'snap_api_url' => env('MIDTRANS_SNAP_API_URL', 'https://app.sandbox.midtrans.com/snap/v1/transactions'),
        'snap_js_url' => env('MIDTRANS_SNAP_JS_URL', 'https://app.sandbox.midtrans.com/snap/snap.js'),
        'status_api_base_url' => env('MIDTRANS_STATUS_API_BASE_URL', 'https://api.sandbox.midtrans.com/v2'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
