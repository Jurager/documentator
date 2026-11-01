---
title: Generating Documentation
weight: 30
---

# Generating Documentation

## Generating a Specification

Run the `documentator:generate` Artisan command after updating your API:

```shell
php artisan documentator:generate
```

The specification is written to `docs/openapi.json`. Use the `--output` option to choose another path or `--format` to select a [response format](response-formats.md):

```shell
php artisan documentator:generate --output=public/openapi.json
php artisan documentator:generate --format=json-api
```

Use verbose output to inspect processed routes and fields that could not be documented:

```shell
php artisan documentator:generate -v
```

If a route is missing, check `php artisan route:list` and your [route configuration](configuration.md#routes). Controller methods provide PHPDoc, validation rules, and resource information; closure routes receive basic generated operations.

## Generating from Code

The `generate` method returns the specification as an array using the same configuration:

```php
use Jurager\Documentator\Documentator;

$specification = app(Documentator::class)->generate();
```

## Checking Parameters

The `documentator:lint` command checks shared query parameters and headers for inconsistent types or descriptions:

```shell
php artisan documentator:lint
```

Use [presets](phpdoc-annotations.md#presets) to share parameter definitions. The command returns a non-zero exit code when it finds differences. Generation returns a non-zero exit code when it cannot build or write the specification.
