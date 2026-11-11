<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Tests\Support\TestDatabase;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        // Override even cached configuration before providers can open a connection.
        $app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\LoadConfiguration::class,
            fn (Application $app) => TestDatabase::isolate($app));
        $app->make(Kernel::class)->bootstrap();

        TestDatabase::prepare($app);

        return $app;
    }
}
