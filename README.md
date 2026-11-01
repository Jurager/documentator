# Jurager/Documentator

[![Latest Stable Version](https://poser.pugx.org/jurager/documentator/v/stable)](https://packagist.org/packages/jurager/documentator)
[![Total Downloads](https://poser.pugx.org/jurager/documentator/downloads)](https://packagist.org/packages/jurager/documentator)
[![PHP Version Require](https://poser.pugx.org/jurager/documentator/require/php)](https://packagist.org/packages/jurager/documentator)
[![License](https://poser.pugx.org/jurager/documentator/license)](https://packagist.org/packages/jurager/documentator)
[![Ko-fi](https://img.shields.io/badge/Ko--fi-support-FF5E5B?logo=ko-fi&logoColor=white)](https://ko-fi.com/jurager01)

Generate OpenAPI 3.0 documentation from your Laravel routes, validation rules, and API resources.

Requires PHP 8.3+ and Laravel 10–13.

## Installation

```bash
composer require jurager/documentator
php artisan documentator:generate
```

Your specification is written to `docs/openapi.json`. Routes matching `api/*` are included by default.

To customize the package, publish its configuration:

```bash
php artisan vendor:publish --tag=documentator-config
```

## Documenting Endpoints

Add descriptions and examples to your controller methods:

```php
/**
 * List users.
 *
 * @group Users
 * @preset pagination
 * @queryParam search string optional Filter users by name.
 */
public function index()
{
    return UserResource::collection(User::paginate());
}
```

Request fields are read from your FormRequest rules. Use `@bodyParam` and `@response` to describe fields or responses that need an explicit example.

Read the [documentation](https://docs.gerassimov.me/documentator/) or start with the [installation guide](docs/installation.md).

## Contributing

See [Contributing](CONTRIBUTING.md) for testing and formatting.

## License

The MIT License. See [LICENSE](LICENSE.md).
