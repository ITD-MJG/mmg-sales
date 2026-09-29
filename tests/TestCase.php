<?php

namespace Tests;

use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Creates the application.
     *
     * @return Application
     */
    public function createApplication()
    {
        // RefreshDatabase runs migrate:fresh, so aiming the suite anywhere but the test
        // database destroys real data. That wiped the dev database on 2026-09-25.
        // Laravel's Dotenv loads `.env` and overwrites PHPUnit's env vars (even with
        // force="true"), so the check must read the RESOLVED connection after boot.
        // RefreshDatabase runs migrate:fresh: aiming it anywhere but the test database
        // destroys real data. That wiped the dev database twice on 2026-09-25.

        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        // Laravel's Dotenv loads `.env` and overwrites PHPUnit's <env> entries (even with
        // force="true"), so the guard must read the RESOLVED connection after boot.
        $database = $app->make('config')->get('database.connections.mariadb.database');

        if ($database !== 'mmg_sales_testing') {
            throw new RuntimeException(
                'Refusing to run tests against database "'.$database.'": the suite runs '
                .'migrate:fresh and would wipe it. Set DB_DATABASE=mmg_sales_testing before running tests.'
            );
        }
        // Register Filament service providers for testing
        $app->register(FilamentServiceProvider::class);
        $app->register(ActionsServiceProvider::class);
        $app->register(FormsServiceProvider::class);
        $app->register(InfolistsServiceProvider::class);
        $app->register(NotificationsServiceProvider::class);
        $app->register(TablesServiceProvider::class);

        return $app;
    }
}
