<?php

return [
    'store' => env('EMAIL_VERIFICATION_CACHE_STORE', 'redis'),
    'ttl' => (int) env('EMAIL_VERIFICATION_TTL', 60 * 15),
    'key_prefix' => env('EMAIL_VERIFICATION_KEY_PREFIX', 'email_verifications:'),
];
