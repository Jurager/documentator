---
title: Documenting Endpoints
weight: 50
---

# Documenting Endpoints

## Descriptions

Add a PHPDoc block to your controller method. The first line becomes the summary, followed by the description:

```php
/**
 * List users.
 *
 * Return users visible to the current account.
 *
 * @group Users
 */
```

Use `@summary` or `@description` to set either value explicitly. Add `@deprecated` to mark an endpoint as deprecated.

## Parameters

Use `@queryParam`, `@urlParam`, and `@header` to describe parameters:

```php
/**
 * @urlParam user integer required The user ID. Example: 42
 * @queryParam search string optional Filter by name. Example: Alex
 * @header Accept-Language string optional Preferred language. Example: en
 */
```

Each definition contains a name, type, `required` or `optional`, and a description. Add `Example:` to provide a value. Supported types include `string`, `integer`, `number`, `boolean`, `object`, and arrays such as `integer[]`.

URL parameter names must match the route placeholders. Path parameters are always required.

## Request Fields

Use `@bodyParam` to add descriptions and examples to request fields:

```php
/**
 * @bodyParam name string required The user's name. Example: Alex
 * @bodyParam items[].id integer required The product ID. Example: 42
 * @bodyParam attachments file[] optional Supporting documents.
 */
```

Nested fields accept both `items.*.id` and `items[].id` notation.

## Responses

Use `@response` to provide response examples and status codes:

```php
/**
 * @response 200 {"success":true,"data":{"id":42,"name":"Alex"}}
 * @response 404 {"message":"User not found."}
 */
```

These responses replace the generated success response. Write each example on one line. The status defaults to 200 when omitted.

## Grouping Endpoints

Use `@group Users` to group related endpoints. Group descriptions may be defined in your [configuration](configuration.md#tags).

Use `@resource user` to set the name used for resource discovery and JSON:API resource types.

## Authentication

Endpoints inherit authentication from your [configuration](configuration.md#authentication). Add `@unauthenticated` to document a public endpoint.

## Presets

Define shared query parameters and headers in `config/documentator.php`:

```php
'presets' => [
    'locale' => [
        'header' => [
            'Accept-Language string optional Preferred language. Example: en',
        ],
    ],
],
```

Apply the preset to a controller method:

```php
/**
 * @preset locale
 */
```

The package includes `pagination` and `accept-language` presets. Parameters declared on the method take precedence over presets. Use [`documentator:lint`](generate-specification.md#checking-parameters) to check for inconsistent definitions.
