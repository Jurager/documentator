---
title: Troubleshooting
weight: 100
---

# Troubleshooting

## No Routes Found

If generation finishes with no routes:

1. Check `routes.include` patterns.
2. Verify routes are registered with `php artisan route:list`.
3. Ensure routes are not filtered by `routes.exclude`.
4. Ensure methods are allowed by `routes.methods`.

## Endpoints Missing in `paths`

1. Ensure route actions point to controller methods (not closures).
2. Confirm controller class/method exists and is autoloadable.
3. Verify route HTTP methods are in `routes.methods`.

## Request Body Schema Is Empty

1. Ensure validation is in `FormRequest::rules()` or `$request->validate([...])`.
2. For nested objects/arrays, verify rule keys and PHPDoc body params are aligned.
3. Check that path params are not expected in request body.

## Authentication Incorrectly Marked

- Use `@unauthenticated` for public endpoints.
- Verify `security.default` and `security.schemes` in config.

## Unknown Format Error

If generator throws unknown format:

1. Check `format` key in config.
2. Register custom class in `custom_formats`.
3. Confirm class exists and implements `AbstractFormatInterface`.
