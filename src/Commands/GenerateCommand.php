<?php

namespace Jurager\Documentator\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use JsonException;
use Jurager\Documentator\Builders\SpecificationBuilder;
use Jurager\Documentator\Collectors\RouteCollector;

class GenerateCommand extends Command implements Isolatable
{
    protected $signature = 'docs:generate
                            {--output= : Override output path}
                            {--format= : Override response format}';

    protected $description = 'Generate OpenAPI specification';

    private array $config;

    private SpecificationBuilder $specBuilder;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->config = config('documentator');

        if ($formatOverride = $this->option('format')) {
            $this->config['format'] = $formatOverride;
        }

        $this->initializeBuilder();

        $this->components->info('Generating OpenAPI specification');
        $this->newLine();

        $processedRoutes = 0;

        // Set progress callback to display routes being processed
        $this->specBuilder->setProgressCallback(function ($route, $path, $methods) use (&$processedRoutes) {
            $processedRoutes++;
            $methodsList = strtoupper(implode(', ', $methods));
            $this->line("  <fg=blue>•</> <fg=gray>[$methodsList]</> $path");
        });

        try {
            $spec = $this->specBuilder->build();
            $outputPath = $this->option('output') ?: ($this->config['output']['path'] ?? $this->config['output']);
            $path = $this->resolvePath($outputPath);

            $this->ensureDirectory($path);

            $prettyPrint = $this->config['output']['pretty_print'] ?? true;
            $flags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE;
            if ($prettyPrint) {
                $flags |= JSON_PRETTY_PRINT;
            }

            $json = json_encode($spec, $flags);

            if (file_put_contents($path, $json) === false) {
                $this->error("Failed to write: $path");

                return self::FAILURE;
            }

            $this->displaySuccess($spec, $json, $path, $processedRoutes);

            return self::SUCCESS;
        } catch (JsonException $e) {
            $this->error('JSON error: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Initialize specification builder with all dependencies.
     */
    private function initializeBuilder(): void
    {
        $this->specBuilder = new SpecificationBuilder(new RouteCollector(), $this->config);
    }

    /**
     * Display success message with statistics.
     */
    private function displaySuccess(array $spec, string $json, string $path, int $processedRoutes): void
    {
        $paths = $spec['paths'] ?? [];
        $schemas = $spec['components']['schemas'] ?? [];
        $responses = $spec['components']['responses'] ?? [];
        $securitySchemes = $spec['components']['securitySchemes'] ?? [];
        $tags = $spec['tags'] ?? [];

        $pathsCount = is_countable($paths) ? count($paths) : 0;
        $schemasCount = is_countable($schemas) ? count($schemas) : 0;
        $responsesCount = is_countable($responses) ? count($responses) : 0;
        $securityCount = is_countable($securitySchemes) ? count($securitySchemes) : 0;
        $tagsCount = is_countable($tags) ? count($tags) : 0;

        $fileSize = strlen($json);
        $fileSizeFormatted = $this->formatBytes($fileSize);

        $info = $spec['info'] ?? [];
        $apiTitle = $info['title'] ?? 'API';
        $apiVersion = $info['version'] ?? '1.0.0';
        $openapiVersion = $spec['openapi'] ?? '3.0.3';

        $this->newLine();
        $this->components->twoColumnDetail(
            '<fg=green;options=bold>✓ Documentation generated successfully</>',
            ''
        );
        $this->newLine();

        // API Information
        $this->components->bulletList([
            "<fg=cyan;options=bold>API:</>        $apiTitle <fg=gray>v$apiVersion</>",
            "<fg=cyan;options=bold>OpenAPI:</>    <fg=gray>v$openapiVersion</>",
            "<fg=cyan;options=bold>Format:</>     <fg=yellow>{$this->config['format']}</>",
        ]);

        $this->newLine();

        // Statistics
        $this->line('  <fg=white;options=bold>Statistics:</>');
        ;
        $this->components->bulletList([
            "Routes processed: <fg=white;options=bold>$processedRoutes</>",
            "Endpoints: <fg=white;options=bold>$pathsCount</>",
            "Schemas: <fg=white;options=bold>$schemasCount</>",
            "Responses: <fg=white;options=bold>$responsesCount</>",
            "Security schemes: <fg=white;options=bold>$securityCount</>",
            "Tags: <fg=white;options=bold>$tagsCount</>",
        ]);

        $this->newLine();

        // Output information
        $this->components->twoColumnDetail(
            '<fg=white;options=bold>Output file:</>',
            "<fg=green>$path</>"
        );
        $this->components->twoColumnDetail(
            '<fg=white;options=bold>File size:</>',
            "<fg=gray>$fileSizeFormatted</>"
        );

        // Warnings
        if ($processedRoutes === 0) {
            $this->newLine();
            $this->components->warn('No routes were found matching your configuration');
            $this->line('  <fg=gray>Check your routes.include and routes.exclude settings in config/documentator.php</>');
        } elseif ($pathsCount === 0) {
            $this->newLine();
            $this->components->warn('Routes were found but no endpoints were generated');
        }

        $this->newLine();
    }

    /**
     * Format bytes to human-readable size.
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1024 ** $pow);

        return round($bytes, 2).' '.$units[$pow];
    }

    /**
     * Resolve output path.
     */
    private function resolvePath(string $path): string
    {
        if (str_starts_with($path, '/') || preg_match('#^[A-Za-z]:[/\\\\]#', $path)) {
            return $path;
        }

        return base_path($path);
    }

    /**
     * Ensure directory exists.
     */
    private function ensureDirectory(string $path): void
    {
        $dir = dirname($path);

        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new \RuntimeException("Cannot create directory: $dir");
        }
    }
}
