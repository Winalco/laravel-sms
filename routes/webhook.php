<?php

use Illuminate\Support\Facades\Route;
use Winalco\Sms\Http\WinalcoSmsWebhookController;

// Statuts SMS poussés par le relay Winalco (signés HMAC, pas d'auth).
// Préfixe /api explicite: les routes de package ne passent pas par le
// RouteServiceProvider de l'application.
//
// Limite explicite plutôt que le groupe "api": Laravel 11 a retiré throttle:api
// de ce groupe par défaut (Foundation\Configuration\Middleware n'ajoute throttle
// que si l'app appelle throttleApi()), donc "api" ne bornait plus rien sur
// 11/12/13. 600/min et non 60: un sendBulk de 500 produit jusqu'à 500 webhooks
// finaux depuis la seule IP du relay, tous dans le même seau.
Route::post('/api/webhooks/winalco-sms', WinalcoSmsWebhookController::class)
    ->middleware('throttle:600,1')
    ->name('webhooks.winalco-sms');
