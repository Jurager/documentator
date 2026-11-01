<?php

namespace Jurager\Documentator\Parsers;

use Illuminate\Contracts\Config\Repository;
use InvalidArgumentException;

class PhpDocParser
{
    public function __construct(protected Repository $config) {}

    public function parse(string $comment): array
    {
        $info = [];
        $description = [];
        $presets = [];
        $readingText = true;
        $readingDescription = false;
        $comment = preg_replace('/^\s*\/\*\*|\*\/\s*$/', '', trim($comment));

        foreach (preg_split('/\R/u', $comment) ?: [] as $line) {
            $line = trim(preg_replace('/^\s*\* ?/', '', $line));
            if ($line === '') {
                continue;
            }
            if (! str_starts_with($line, '@')) {
                if ($readingText && ! isset($info['summary'])) {
                    $info['summary'] = $line;
                } elseif ($readingText || $readingDescription) {
                    $description[] = $line;
                }

                continue;
            }

            [$tag, $value] = array_pad(preg_split('/\s+/u', substr($line, 1), 2), 2, '');
            $tag = strtolower($tag);
            $readingText = false;
            $readingDescription = $tag === 'description';

            switch ($tag) {
                case 'summary':
                case 'group':
                    if ($value !== '') {
                        $info[$tag] = $value;
                    }
                    break;
                case 'resource':
                    if ($value !== '') {
                        $info['resource'] = explode(' ', $value, 2)[0];
                    }
                    break;
                case 'description':
                    if ($value !== '') {
                        $description[] = $value;
                    }
                    break;
                case 'deprecated':
                    $info['deprecated'] = true;
                    break;
                case 'authenticated':
                case 'unauthenticated':
                    $info['authenticated'] = $tag === 'authenticated';
                    break;
                case 'preset':
                    $presets[] = explode(' ', $value, 2)[0];
                    break;
                case 'response':
                    if ($response = $this->response($value)) {
                        $info['responses'][] = $response;
                    }
                    break;
                default:
                    $key = match ($tag) {
                        'queryparam' => 'queryParams',
                        'bodyparam' => 'bodyParams',
                        'urlparam' => 'urlParams',
                        'header' => 'headers',
                        default => null,
                    };
                    if ($key !== null && $parameter = $this->parameter($value)) {
                        $info[$key][] = $parameter;
                    }
            }
        }

        $definitions = $this->config->get('documentator.presets') ?? [];
        foreach (array_unique($presets) as $name) {
            foreach (['query' => 'queryParams', 'header' => 'headers'] as $location => $key) {
                foreach ($definitions[$name][$location] ?? [] as $definition) {
                    $parameter = $this->parameter($definition);
                    if ($parameter === null || in_array($parameter['name'], array_column($info[$key] ?? [], 'name'), true)) {
                        continue;
                    }
                    $info[$key][] = $parameter;
                }
            }
        }

        if ($description !== []) {
            $info['description'] = trim(implode("\n", $description));
        }

        return $info;
    }

    protected function parameter(string $text): ?array
    {
        $parts = preg_split('/\s+/u', trim($text), 4);
        if ($parts[0] === '') {
            return null;
        }

        $flag = strtolower($parts[2] ?? '');
        $description = in_array($flag, ['required', 'optional'], true)
            ? trim($parts[3] ?? '')
            : trim(implode(' ', array_slice($parts, 2)));

        $parameter = [
            'name' => $parts[0],
            'type' => $parts[1] ?? 'string',
            'required' => $flag === 'required',
            'description' => $description,
        ];
        if (preg_match('/^(.*?)\bExample:\s*(.+)$/su', $description, $matches)) {
            $parameter['description'] = trim($matches[1]);
            $example = json_decode(trim($matches[2]));
            $parameter['example'] = json_last_error() === JSON_ERROR_NONE ? $example : trim($matches[2]);
        }

        return $parameter;
    }

    protected function response(string $text): ?array
    {
        if (! preg_match('/^(?:(\d+)\s+)?(.+)$/u', $text, $matches)) {
            return null;
        }
        $status = (int) ($matches[1] ?: 200);
        if ($status < 100 || $status > 599) {
            throw new InvalidArgumentException('Response status must be between 100 and 599.');
        }

        $content = json_decode($matches[2]);

        return ['status' => $status, 'content' => json_last_error() === JSON_ERROR_NONE ? $content : $matches[2]];
    }
}
