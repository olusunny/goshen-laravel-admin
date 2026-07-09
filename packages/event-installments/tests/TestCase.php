<?php

namespace Personal\EventInstallments\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Personal\EventInstallments\EventInstallmentsServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [EventInstallmentsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('event-installments.ticket.qr_secret', 'testing-secret');
        $app['config']->set('event-installments.ticket.email.attach_pdf', false);
        $app['config']->set('event-installments.ticket.email.attach_ics', false);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
