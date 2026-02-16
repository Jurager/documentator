---
title: Generate Specification
weight: 40
---

# Generate Specification

Use the Artisan command to build OpenAPI:

```bash
php artisan docs:generate
```

## Options

```bash
php artisan docs:generate --output=public/openapi.json
php artisan docs:generate --format=json-api
```

- `--output` overrides `output.path`.
- `--format` overrides `format` (`simple`, `json-api`, or a configured custom format).

> [!NOTE]
> If `--format` is invalid, generation fails with an `Unknown format` exception.

## What the Command Does

1. Collects routes using `routes.include`, `routes.exclude`, and `routes.exclude_middleware`.
2. Builds operations for allowed HTTP methods.
3. Adds schemas, responses, tags, and security components.
4. Writes the final spec file.

## Command Output

The command prints:

- processed routes and endpoints,
- schema/response/tag counters,
- output file path and file size,
- warnings when no matching routes were found.

> [!WARNING]
> A generated file with empty `paths` usually means routes were filtered out or use unsupported action patterns.
