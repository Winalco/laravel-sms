<?php

use Winalco\Sms\Http\WinalcoSmsWebhookController;

function signWinalcoWebhook(string $body, int $t, string $secret): string
{
    return 't='.$t.',v1='.hash_hmac('sha256', $t.'.'.$body, $secret);
}

it('accepts a valid signature', function () {
    $body = '{"event":"message.sent","id":"abc","status":"sent"}';
    $t = 1785000000;

    $header = signWinalcoWebhook($body, $t, 'whs_secret');

    expect(WinalcoSmsWebhookController::signatureValid($header, $body, 'whs_secret', $t + 10))->toBeTrue();
});

it('rejects a tampered body', function () {
    $t = 1785000000;
    $header = signWinalcoWebhook('{"status":"sent"}', $t, 'whs_secret');

    expect(WinalcoSmsWebhookController::signatureValid($header, '{"status":"failed"}', 'whs_secret', $t + 10))->toBeFalse();
});

it('rejects a stale timestamp (replay protection)', function () {
    $body = '{"id":"abc"}';
    $t = 1785000000;
    $header = signWinalcoWebhook($body, $t, 'whs_secret');

    expect(WinalcoSmsWebhookController::signatureValid($header, $body, 'whs_secret', $t + 301))->toBeFalse();
});

it('rejects a wrong secret', function () {
    $body = '{"id":"abc"}';
    $t = 1785000000;
    $header = signWinalcoWebhook($body, $t, 'whs_autre');

    expect(WinalcoSmsWebhookController::signatureValid($header, $body, 'whs_secret', $t))->toBeFalse();
});

it('rejects a malformed header', function (string $header) {
    expect(WinalcoSmsWebhookController::signatureValid($header, '{}', 'whs_secret', 1785000000))->toBeFalse();
})->with(['', 'garbage', 't=abc,v1=def', 'v1=8f3c,t=1785000000']);

it('rejects everything when no secret is configured', function () {
    $body = '{"id":"abc"}';
    $t = 1785000000;
    $header = signWinalcoWebhook($body, $t, '');

    expect(WinalcoSmsWebhookController::signatureValid($header, $body, '', $t))->toBeFalse();
});
