# Jurager/Documentator

[![Latest Stable Version](https://poser.pugx.org/jurager/documentator/v/stable)](https://packagist.org/packages/jurager/documentator)
[![Total Downloads](https://poser.pugx.org/jurager/documentator/downloads)](https://packagist.org/packages/jurager/documentator)
[![PHP Version Require](https://poser.pugx.org/jurager/documentator/require/php)](https://packagist.org/packages/jurager/documentator)
[![License](https://poser.pugx.org/jurager/documentator/license)](https://packagist.org/packages/jurager/documentator)
[![Ko-fi](https://img.shields.io/badge/Ko--fi-support-FF5E5B?logo=ko-fi&logoColor=white)](https://ko-fi.com/jurager01)

Generate OpenAPI 3.0 specifications from Laravel routes, validation rules, and API resources.

## Installation

Documentator requires PHP 8.3 or greater and Laravel 10–13:

```shell
composer require jurager/documentator
php artisan documentator:generate
```

Your specification is written to `docs/openapi.json`. Routes matching `api/*` are included by default.

## Documenting Endpoints

Add descriptions and examples to your controller methods:

```php
/**
 * List users.
 *
 * @group Users
 * @preset pagination
 * @queryParam search string optional Filter by name.
 */
```

Request fields are read from validation rules and response fields from API resources. See the [documentation](https://docs.gerassimov.me/documentator/) or the [installation guide](docs/_index.md#installation) to get started.

## License

The MIT License. See [LICENSE](LICENSE.md).
