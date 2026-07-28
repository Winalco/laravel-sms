<?php

return [
    'key' => env('WINALCO_SMS_KEY'),
    'base_url' => env('WINALCO_SMS_URL', 'https://sms-relay.winalco.dz'),
    'webhook_secret' => env('WINALCO_SMS_WEBHOOK_SECRET'),
];
