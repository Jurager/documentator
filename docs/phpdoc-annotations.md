---
title: Documenting Endpoints
weight: 60
---

# Documenting Endpoints

Add PHPDoc to controller methods to describe your endpoints. The first line becomes the summary; the following text becomes the description:

```php
/**
 * List users.
 *
 * Returns users visible to the current account.
 *
 * @group Users
 * @queryParam search string optional Filter by name. Example: Alice
 */
public function index()
{
    return UserResource::collection(User::paginate());
}
```

## Parameters

Use `@queryParam`, `@urlParam`, or `@header` to describe a parameter:

```php
/**
 * @urlParam user integer required The user ID. Example: 42
 * @queryParam include string optional Related resources to include.
 * @header Accept-Language string optional Preferred language. Example: en
 */
```

Each definition contains a name, type, `required` or `optional`, and a description. Add `Example:` to provide a value. Parameter types include `string`, `integer`, `number`, `boolean`, `object`, and arrays such as `integer[]`.

URL parameter names must match the route placeholders. OpenAPI path parameters are always required.

## Request Fields

Use `@bodyParam` to add descriptions or examples to fields discovered from validation rules:

```php
/**
 * @bodyParam name string required The user's display name. Example: Alice
 * @bodyParam items[].id integer required The product ID. Example: 42
 * @bodyParam attachments file[] optional Supporting documents.
 */
```

Both Laravel dot notation (`items.*.id`) and bracket notation (`items[].id`) are supported.

## Response Examples

Add `@response` to document an endpoint's response:

```php
/**
 * @response 200 {"success":true,"data":{"id":42,"name":"Alice"}}
 * @response 404 {"message":"User not found."}
 */
```

Explicit responses replace the automatically generated success response. Omit the status code to use 200. Write each example on one line.

## Presets

Define repeated parameters once in `config/documentator.php`:

```php
'presets' => [
    'locale' => [
        'header' => [
            'Accept-Language string optional Preferred language. Example: en',
        ],
    ],
],
```

Apply a preset to any endpoint:

```php
/**
 * @preset pagination
 * @preset locale
 */
```

The package includes `pagination` and `accept-language` presets. A parameter declared on the method overrides the same name from a preset.

Run `php artisan documentator:lint` to check shared query parameters and headers for inconsistent descriptions or types.

## Endpoint Options

| Tag | Purpose |
| --- | --- |
| `@group Users` | Group the endpoint under a tag. |
| `@resource users` | Set the resource name used for discovery and schema naming. |
| `@unauthenticated` | Document a public endpoint without default authentication. |
| `@deprecated` | Mark the endpoint as deprecated. |
| `@summary text` | Override the summary. |
| `@description text` | Add a description, including subsequent lines. |

Authentication is inherited from your [configuration](configuration.md#authentication). These annotations describe your API; they do not change route access.
