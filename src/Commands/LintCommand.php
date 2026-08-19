<?php

namespace Jurager\Documentator\Commands;

use Illuminate\Console\Command;
use Jurager\Documentator\Linters\ParameterConsistencyLinter;

class LintCommand extends Command
{
    protected $signature = 'documentator:lint';

    protected $description = 'Check query/header parameters for a description or type that differs across endpoints';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->components->info('Checking parameter consistency');
        $this->newLine();

        $linter = new ParameterConsistencyLinter(config('documentator'));
        $issues = $linter->lint();

        if (empty($issues)) {
            $this->components->info('No inconsistencies found.');

            return self::SUCCESS;
        }

        foreach ($issues as $issue) {
            $variantCount = count($issue['variants']);
            $this->line("  <fg=yellow;options=bold>{$issue['in']}</> <fg=white;options=bold>{$issue['name']}</> <fg=gray>— $variantCount different descriptions</>");

            foreach ($issue['variants'] as $index => $variant) {
                $type = $variant['signature']['type'] ?? 'string';
                $description = $variant['signature']['description'] ?: '(no description)';

                $this->line("    <fg=gray>[".($index + 1)."]</> <fg=cyan>$type</> — $description");

                foreach ($variant['locations'] as $location) {
                    $this->line("        <fg=gray>$location</>");
                }
            }

            $this->newLine();
        }

        $this->components->error(count($issues).' '.($this->plural(count($issues), 'parameter', 'parameters')).' documented inconsistently. Extract a shared preset (config(\'documentator.presets\')) or align the descriptions.');

        return self::FAILURE;
    }

    /**
     * Minimal English pluralization for the summary line.
     */
    private function plural(int $count, string $singular, string $plural): string
    {
        return $count === 1 ? $singular : $plural;
    }
}
