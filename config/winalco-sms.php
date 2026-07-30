<?php

return [
    'key' => env('WINALCO_SMS_KEY'),
    'base_url' => env('WINALCO_SMS_URL', 'https://sms-relay.winalco.dz'),
    'webhook_secret' => env('WINALCO_SMS_WEBHOOK_SECRET'),

    // Rétention des lignes sms_messages (numéro + corps du message) pour
    // `php artisan model:prune`. null = ne rien supprimer.
    'prune_after_days' => env('WINALCO_SMS_PRUNE_AFTER_DAYS'),
];
