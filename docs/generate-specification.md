---
title: Generating Documentation
weight: 40
---

# Generating Documentation

Generate a specification from your application's registered routes:

```bash
php artisan documentator:generate
```

The command writes `docs/openapi.json` and displays the number of routes it processed. Run it again after changing your API.

## Command Options

Use `--output` to choose another destination and `--format` to override the configured response format:

```bash
php artisan documentator:generate --output=public/openapi.json
php artisan documentator:generate --format=json-api
```

Relative output paths are resolved from your application's root. Use verbose output to see processed routes and extraction diagnostics:

```bash
php artisan documentator:generate -v
```

## Checking Shared Parameters

Check that query parameters and headers use consistent types and descriptions across endpoints:

```bash
php artisan documentator:lint
```

The command returns a non-zero exit code when it finds differences. Use [presets](phpdoc-annotations.md#presets) to share repeated definitions.

Both commands can run in CI. Generation returns a non-zero exit code when it cannot build or write the specification.
