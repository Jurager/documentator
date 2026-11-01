<?php

namespace Jurager\Documentator\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Jurager\Documentator\Linting\ParameterLinter;

class LintCommand extends Command
{
    protected $signature = 'documentator:lint';

    protected $description = 'Check query/header parameters for a description or type that differs across endpoints';

    public function handle(ParameterLinter $linter): int
    {
        $this->components->info('Checking parameter consistency');
        $this->newLine();

        $issues = $linter->lint();

        if ($this->output->isVerbose()) {
            foreach ($linter->warnings() as $warning) {
                $this->components->warn($warning);
            }
        }

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

                $this->line('    <fg=gray>['.($index + 1)."]</> <fg=cyan>$type</> — $description");

                foreach ($variant['locations'] as $location) {
                    $this->line("        <fg=gray>$location</>");
                }
            }

            $this->newLine();
        }

        $this->components->error(count($issues).' '.Str::plural('parameter', count($issues)).' documented inconsistently. Extract a shared preset (config(\'documentator.presets\')) or align the descriptions.');

        return self::FAILURE;
    }
}
