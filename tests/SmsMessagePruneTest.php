<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Winalco\Sms\Models\SmsMessage;

uses(RefreshDatabase::class);

function makeSms(string $providerId, ?int $daysAgo = null): SmsMessage
{
    $sms = SmsMessage::create([
        'provider_id' => $providerId, 'to' => '0661702451',
        'message' => 'x', 'status' => SmsMessage::STATUS_SENT,
    ]);

    if ($daysAgo !== null) {
        $sms->forceFill(['created_at' => now()->subDays($daysAgo)])->save();
    }

    return $sms;
}

it('deletes only the rows past the retention window', function () {
    config(['winalco-sms.prune_after_days' => 90]);

    makeSms('vieux', 120);
    makeSms('recent', 10);

    expect((new SmsMessage)->pruneAll())->toBe(1)
        ->and(SmsMessage::pluck('provider_id')->all())->toBe(['recent']);
});

it('deletes nothing when retention is not configured', function (mixed $setting) {
    config(['winalco-sms.prune_after_days' => $setting]);

    makeSms('tres-vieux', 3650);

    expect((new SmsMessage)->pruneAll())->toBe(0)
        ->and(SmsMessage::count())->toBe(1);
})->with([
    'null (défaut)' => null,
    'chaîne vide (.env vide)' => '',
    'zéro' => 0,
]);
