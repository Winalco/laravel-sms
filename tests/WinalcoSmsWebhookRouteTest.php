<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Winalco\Sms\Models\SmsMessage;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['winalco-sms.webhook_secret' => 'whs_secret']);
});

/** Corps signé comme le fait le relay: HMAC sur "t.<corps brut>". */
function signedPost(array $payload, array $headers = [])
{
    $body = json_encode($payload);
    $t = time();

    return test()->call('POST', '/api/webhooks/winalco-sms', [], [], [], array_merge([
        'HTTP_X-Winalco-Signature' => 't='.$t.',v1='.hash_hmac('sha256', $t.'.'.$body, 'whs_secret'),
        'CONTENT_TYPE' => 'application/json',
    ], $headers), $body);
}

it('rejects an unsigned request with 403', function () {
    postJson('/api/webhooks/winalco-sms', ['id' => 'abc'])->assertStatus(403);
});

it('accepts a validly signed body without an id and touches nothing', function () {
    $body = '{"event":"message.sent"}';
    $t = time();
    $header = 't='.$t.',v1='.hash_hmac('sha256', $t.'.'.$body, 'whs_secret');

    $response = $this->call('POST', '/api/webhooks/winalco-sms', [], [], [], [
        'HTTP_X-Winalco-Signature' => $header,
        'CONTENT_TYPE' => 'application/json',
    ], $body);

    $response->assertStatus(200);
});

// Garde de régression: Laravel 11 a retiré throttle:api du groupe "api", ce qui
// avait laissé cette route publique sans limite sur 11/12/13.
it('keeps the route rate-limited on every supported laravel version', function () {
    postJson('/api/webhooks/winalco-sms'); // boot du kernel HTTP -> middlewares résolus

    $route = collect(Route::getRoutes()->getRoutes())
        ->first(fn ($r) => $r->getName() === 'webhooks.winalco-sms');

    $resolved = app('router')->gatherRouteMiddleware($route);

    expect(collect($resolved)->contains(fn ($m) => str_contains($m, 'ThrottleRequests')))->toBeTrue();
});

it('ignores a status outside the final set instead of writing it', function (mixed $status) {
    $sms = SmsMessage::create([
        'provider_id' => 'pid-1', 'to' => '0661702451',
        'message' => 'x', 'status' => SmsMessage::STATUS_QUEUED,
    ]);

    signedPost(['id' => 'pid-1', 'status' => $status])->assertStatus(200);

    expect($sms->fresh()->status)->toBe(SmsMessage::STATUS_QUEUED);
})->with([
    'chaîne inconnue' => 'delivered',
    'tableau (ferait échouer la requête)' => [['sent']],
    'entier' => 1,
    'absent' => null,
]);

it('bounds the unsigned delivery header to the column width', function () {
    $sms = SmsMessage::create([
        'provider_id' => 'pid-2', 'to' => '0661702451',
        'message' => 'x', 'status' => SmsMessage::STATUS_QUEUED,
    ]);

    signedPost(
        ['id' => 'pid-2', 'status' => 'sent', 'errorCode' => str_repeat('e', 200)],
        ['HTTP_X-Winalco-Delivery' => str_repeat('d', 500)],
    )->assertStatus(200);

    $sms = $sms->fresh();

    expect($sms->status)->toBe(SmsMessage::STATUS_SENT)
        ->and(strlen($sms->webhook_delivery_id))->toBe(64)
        ->and(strlen($sms->error_code))->toBe(50);
});
