<?php

namespace Winalco\Sms;

use Illuminate\Support\ServiceProvider;

class SmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/winalco-sms.php', 'winalco-sms');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/webhook.php');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/winalco-sms.php' => config_path('winalco-sms.php'),
        ], 'winalco-sms-config');
    }
}
