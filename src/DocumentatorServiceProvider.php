<?php

namespace Jurager\Documentator;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Jurager\Documentator\Console\GenerateCommand;
use Jurager\Documentator\Console\LintCommand;
use Jurager\Documentator\Contracts\ResponseFormat;
use Jurager\Documentator\Formats\JsonApiFormat;
use Jurager\Documentator\Formats\SimpleFormat;
use Jurager\Documentator\Parsers\SourceParser;

class DocumentatorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/documentator.php', 'documentator');

        $this->app->scoped(SourceParser::class);
        $this->app->bind(ResponseFormat::class, $this->resolveFormat(...));
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'documentator');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([__DIR__.'/../config/documentator.php' => config_path('documentator.php')], 'documentator-config');
        $this->commands([GenerateCommand::class, LintCommand::class]);
    }

    protected function resolveFormat(Container $app, array $parameters): ResponseFormat
    {
        $format = $parameters['format'] ?? $app->make(Repository::class)->get('documentator.format', SimpleFormat::class);
        $class = match ($format) {
            'simple' => SimpleFormat::class,
            'json-api' => JsonApiFormat::class,
            default => $format,
        };

        if (! is_string($class) || ! class_exists($class)) {
            throw new InvalidArgumentException('Unknown format: '.(is_scalar($format) ? $format : get_debug_type($format)));
        }

        if (! is_subclass_of($class, ResponseFormat::class)) {
            throw new InvalidArgumentException("Format {$class} must implement ".ResponseFormat::class.'.');
        }

        try {
            return $app->make($class);
        } catch (BindingResolutionException $exception) {
            throw new InvalidArgumentException("Unable to resolve format {$class}: ".$exception->getMessage(), previous: $exception);
        }
    }
}
