---
title: Installation
weight: 20
---

# Installation

Documentator requires PHP 8.3+ and Laravel 10–13. Install it using Composer:

```bash
composer require jurager/documentator
```

Generate your first specification:

```bash
php artisan documentator:generate
```

The command includes routes matching `api/*` and writes `docs/openapi.json`.

## Configuration

To change the included routes, API title, or output path, publish the configuration:

```bash
php artisan vendor:publish --tag=documentator-config
```

You may now update `config/documentator.php`. See [Configuration](configuration.md) for common settings.
