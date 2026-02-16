---
title: Response Formats
weight: 70
---

# Response Formats

Documentator supports pluggable response formats.

## Built-in Formats

- `simple` - REST-style envelope with `success`, `data`, `meta`.
- `json-api` - JSON:API media type and document structure.

Set in config:

```php
'format' => env('OPENAPI_FORMAT', 'simple'),
```

Override at runtime:

```bash
php artisan docs:generate --format=json-api
```

## Register Custom Format

1. Create a class implementing `Jurager\Documentator\Formats\AbstractFormatInterface`
   (usually extend `AbstractFormat`).
2. Register it in `custom_formats`.
3. Select the key in `format`.

```php
'custom_formats' => [
    'telegram' => App\Documentator\TelegramFormat::class,
],
'format' => 'telegram',
```

Example skeleton:

```php
namespace App\Documentator;

use Jurager\Documentator\Builders\SchemaBuilder;
use Jurager\Documentator\Formats\AbstractFormat;

class TelegramFormat extends AbstractFormat
{
    public function __construct(SchemaBuilder $schemaBuilder)
    {
        parent::__construct($schemaBuilder);
    }

    public function name(): string
    {
        return 'telegram';
    }

    public function description(): string
    {
        return 'Telegram Bot API style responses';
    }

    protected function successSchema(): string
    {
        return 'TelegramSuccess';
    }

    protected function errorSchema(): string
    {
        return 'TelegramError';
    }
}
```

## Class-Based Format Selection

You can also set `format` to a fully-qualified class name.

> [!WARNING]
> `SpecificationBuilder` resolves custom formats as `new $format($schemaBuilder)`, so constructor signature must be compatible.
