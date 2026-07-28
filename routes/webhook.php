<?php

use Illuminate\Support\Facades\Route;
use Winalco\Sms\Http\WinalcoSmsWebhookController;

// Statuts SMS poussés par le relay Winalco (signés HMAC, pas d'auth).
// Préfixe /api explicite: les routes de package ne passent pas par le
// RouteServiceProvider de l'application.
Route::post('/api/webhooks/winalco-sms', WinalcoSmsWebhookController::class)
    ->middleware('api')
    ->name('webhooks.winalco-sms');
