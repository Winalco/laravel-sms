<?php

namespace Winalco\Sms\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Winalco\Sms\SmsServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [SmsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        // Testbench pointe par défaut sur mysql/forge; sqlite en mémoire pour
        // que la suite tourne sans service externe (CI comprise).
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
    }
}
