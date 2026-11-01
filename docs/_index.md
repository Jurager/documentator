---
title: Documentator
weight: 1
---

# Documentator

## Introduction

Documentator generates OpenAPI 3.0 specifications from your Laravel routes, validation rules, and API resources. Add descriptions and examples to your controller methods using PHPDoc.

## Installation

Documentator requires PHP 8.3 or greater and Laravel 10–13. Install the package using Composer:

```shell
composer require jurager/documentator
```

Generate your specification using the `documentator:generate` Artisan command:

```shell
php artisan documentator:generate
```

The command includes routes matching `api/*` and writes the specification to `docs/openapi.json`.

## Configuration

To customize the package, publish its configuration file:

```shell
php artisan vendor:publish --tag=documentator-config
```

The configuration file is published to `config/documentator.php`. See [Configuration](configuration.md) for available options.

Continue with [Generating Documentation](generate-specification.md), [Request and Response Schemas](schema-detection.md), or [Documenting Endpoints](phpdoc-annotations.md).
