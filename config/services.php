<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'businesscentral' => [
        'tenant_id' => env('BC_TENANT_ID'),
        'client_id' => env('BC_CLIENT_ID'),
        'client_secret' => env('BC_CLIENT_SECRET'),
        'company' => env('BC_COMPANY'),
        'environment' => env('BC_ENVIRONMENT'),
        'token_url' => env('BC_TOKEN_URL', 'https://login.microsoftonline.com/{tenant_id}/oauth2/v2.0/token'),
        'odata_base_url' => env('BC_ODATA_BASE_URL', 'https://api.businesscentral.dynamics.com/v2.0/{tenant_id}/{environment}/ODataV4/Company({company})'),
    ],
];
