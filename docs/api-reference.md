---
title: Using PHP
weight: 90
---

# Using PHP

Generate a specification from your application's registered routes:

```php
use Jurager\Documentator\Documentator;

$specification = app(Documentator::class)->generate();
```

The returned array uses the same configuration as `documentator:generate`. Laravel must be bootstrapped and your routes registered before generating it.

For file output, use the [Artisan command](generate-specification.md). For another response envelope, see [Custom Formats](response-formats.md#custom-formats).
