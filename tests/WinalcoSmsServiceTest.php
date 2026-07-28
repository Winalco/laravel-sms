<?php

use Winalco\Sms\WinalcoSms;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'winalco-sms.key' => 'wak_test',
        'winalco-sms.base_url' => 'https://sms-relay.winalco.dz',
    ]);
});

it('sends an sms with api key and idempotency headers', function () {
    Http::fake([
        'sms-relay.winalco.dz/api/v1/sms/send' => Http::response([
            'id' => '0f4c9b2e-0000-0000-0000-000000000000',
            'to' => '0661702451',
            'status' => 'pending',
        ], 201),
    ]);

    $result = (new WinalcoSms)->send('0661702451', 'Bonjour', 'sms-42');

    expect($result['id'])->toBe('0f4c9b2e-0000-0000-0000-000000000000')
        ->and($result['status'])->toBe('pending');

    Http::assertSent(function ($request) {
        return $request->hasHeader('X-Api-Key', 'wak_test')
            && $request->hasHeader('Idempotency-Key', 'sms-42')
            && $request->url() === 'https://sms-relay.winalco.dz/api/v1/sms/send'
            && $request['to'] === '0661702451'
            && $request['message'] === 'Bonjour';
    });
});

it('omits the idempotency header when not provided', function () {
    Http::fake(['*' => Http::response(['id' => 'x', 'status' => 'pending'], 201)]);

    (new WinalcoSms)->send('0661702451', 'Bonjour');

    Http::assertSent(fn ($request) => ! $request->hasHeader('Idempotency-Key'));
});

it('throws on quota exceeded', function () {
    Http::fake(['*' => Http::response(['message' => 'Quota mensuel atteint.'], 429)]);

    (new WinalcoSms)->send('0661702451', 'Bonjour');
})->throws(RequestException::class);

it('fetches usage', function () {
    Http::fake([
        'sms-relay.winalco.dz/api/v1/sms/usage' => Http::response(['dailyUsed' => 3, 'dailyQuota' => 100], 200),
    ]);

    expect((new WinalcoSms)->usage()['dailyUsed'])->toBe(3);
});

it('reports configured only when a key is present', function () {
    expect(WinalcoSms::configured())->toBeTrue();

    config(['winalco-sms.key' => null]);

    expect(WinalcoSms::configured())->toBeFalse();
});
