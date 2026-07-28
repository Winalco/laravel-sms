<?php

use function Pest\Laravel\postJson;

beforeEach(function () {
    config(['winalco-sms.webhook_secret' => 'whs_secret']);
});

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
