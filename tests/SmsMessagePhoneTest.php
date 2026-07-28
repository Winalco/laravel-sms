<?php

use Winalco\Sms\Models\SmsMessage;

it('normalizes valid algerian mobile numbers', function (string $input, string $expected) {
    expect(SmsMessage::normalizePhone($input))->toBe($expected);
})->with([
    ['0661702451', '0661702451'],
    ['06 61 70 24 51', '0661702451'],
    ['05.55.12.34.56', '0555123456'],
    ['+213661702451', '+213661702451'],
    ['00213770000001', '00213770000001'],
]);

it('rejects invalid numbers', function (?string $input) {
    expect(SmsMessage::normalizePhone($input))->toBeNull();
})->with([
    '041234567',       // landline prefix
    '066170245',       // too short
    '06617024512',     // too long
    '+21661702451',    // wrong country code
    'abc',
    '',
    null,
]);
