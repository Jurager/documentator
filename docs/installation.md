---
title: Installation
weight: 20
---

# Installation

## Install Package

```bash
composer require jurager/documentator
```

## Publish Configuration

```bash
php artisan vendor:publish --tag=documentator-config
```

This creates `config/documentator.php`.

## Verify Command

```bash
php artisan list | grep docs:generate
```

## First Generation

```bash
php artisan docs:generate
```

Default output path:

`docs/openapi.json`

> [!NOTE]
> Before first generation, verify `routes.include` in `config/documentator.php` matches your API route prefixes.
