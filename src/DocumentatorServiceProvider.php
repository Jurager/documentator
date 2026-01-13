<?php

namespace Jurager\Documentator;

use Illuminate\Support\ServiceProvider;
use Jurager\Documentator\Commands\GenerateCommand;

class DocumentatorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/documentator.php', 'openapi');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/documentator.php' => config_path('documentator.php'),
            ], 'documentator-config');

            $this->commands([
                GenerateCommand::class,
            ]);
        }
    }
}
