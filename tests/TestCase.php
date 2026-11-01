<?php

namespace Jurager\Documentator\Tests;

use Jurager\Documentator\Documentator;
use Jurager\Documentator\DocumentatorServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [DocumentatorServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('documentator.routes.include', ['api/*']);
        $app['config']->set('documentator.resources.namespaces', ['Jurager\\Documentator\\Tests\\Fixtures']);
    }

    protected function specification(array $config = []): array
    {
        $original = config('documentator');
        config(['documentator' => array_replace_recursive($original, $config)]);

        try {
            return app(Documentator::class)->generate();
        } finally {
            config(['documentator' => $original]);
        }
    }
}
