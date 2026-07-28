<?php

namespace Winalco\Sms\Tests;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Orchestra\Testbench\TestCase as Orchestra;
use Winalco\Sms\SmsServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [SmsServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Le squelette testbench ne définit pas le rate limiter "api"
        // qu'exige le middleware throttle:api de la route webhook. Enregistré
        // après le boot; le middleware ne le résout qu'au moment de la requête.
        RateLimiter::for('api', fn () => Limit::none());
    }
}
