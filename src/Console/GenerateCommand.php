<?php

namespace Jurager\Documentator\Console;

use ErrorException;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\Route;
use InvalidArgumentException;
use JsonException;
use Jurager\Documentator\Contracts\ResponseFormat;
use Jurager\Documentator\Documentator;
use RuntimeException;

class GenerateCommand extends Command implements Isolatable
{
    protected $signature = 'documentator:generate
                            {--output= : Override output path}
                            {--format= : Response format class, simple, or json-api}';

    protected $description = 'Generate the OpenAPI specification';

    public function handle(Filesystem $files): int
    {
        try {
            $format = $this->laravel->make(ResponseFormat::class, ['format' => $this->option('format')]);
            $documentator = $this->laravel->make(Documentator::class, ['format' => $format]);
            $specification = $this->generate($documentator);
            $path = $this->outputPath();

            $this->writeSpecification($files, $path, $specification);
            $this->displayResult($documentator, $path);

            return self::SUCCESS;
        } catch (JsonException $exception) {
            $this->components->error('JSON error: '.$exception->getMessage());
        } catch (InvalidArgumentException|RuntimeException|ErrorException $exception) {
            $this->components->error($exception->getMessage());
        }

        return self::FAILURE;
    }

    protected function generate(Documentator $documentator): array
    {
        return $documentator->generate(progress: function (Route $route, string $path, array $methods): void {
            if (! $this->output->isVerbose()) {
                return;
            }

            $this->line(strtoupper(implode(', ', $methods))." {$path}");
        });
    }

    protected function outputPath(): string
    {
        $output = config('documentator.output');

        if (! is_array($output) || ! is_string($output['path'] ?? null)) {
            throw new InvalidArgumentException('Configure output.path and output.pretty_print in documentator.php.');
        }

        $path = $this->option('output') ?: $output['path'];

        if (str_starts_with($path, '/') || preg_match('#^(?:[A-Za-z]:[/\\\\]|\\\\\\\\)#', $path)) {
            return $path;
        }

        return base_path($path);
    }

    protected function writeSpecification(Filesystem $files, string $path, array $specification): void
    {
        $flags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

        if (config('documentator.output.pretty_print') ?? true) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $json = json_encode($specification, $flags);

        $files->ensureDirectoryExists(dirname($path));

        $files->replace($path, $json);
    }

    protected function displayResult(Documentator $documentator, string $path): void
    {
        $count = $documentator->getProcessedCount();

        $this->components->info("OpenAPI specification written to {$path}");
        $this->line("Routes processed: {$count}");

        if ($count === 0) {
            $this->components->warn('No routes were found matching your configuration.');
        }

        if (! $this->output->isVerbose()) {
            return;
        }

        foreach ($documentator->warnings() as $warning) {
            $this->components->warn($warning);
        }
    }
}
