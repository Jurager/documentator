---
title: Troubleshooting
weight: 100
---

# Troubleshooting

## Missing Routes

Run `php artisan route:list` and check the route URI against `routes.include`, `routes.exclude`, `routes.exclude_middleware`, and `routes.methods`.

Closure routes receive generated operations. Use controller methods to supply PHPDoc, FormRequest rules, and resource information.

## Missing Request Fields

Run `php artisan documentator:generate -v` to see extraction diagnostics. Add rules to your FormRequest or an inline `$request->validate([...])` call. For rules that depend on request data or database state, add `@bodyParam` annotations.

Request bodies are generated for POST, PUT, PATCH, and DELETE. Route placeholders are documented as path parameters.

## Unexpected Responses

Select the format matching your API: `simple` or `json-api`. Add `@response` when an endpoint returns another shape or status.

## Public Endpoints Require Authentication

Add `@unauthenticated` to the controller method. To remove default authentication from every endpoint, set `security.default` to `[]`.

## Unknown Format

Check that the format is `simple`, `json-api`, or a custom format class. Custom classes must implement `Contracts\ResponseFormat`. Select a custom format by its class name.

## Inconsistent Parameters

Run `php artisan documentator:lint` to locate differences. Share repeated definitions through [presets](phpdoc-annotations.md#presets).
