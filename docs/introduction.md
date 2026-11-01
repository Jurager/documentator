---
title: Introduction
weight: 10
---

# Introduction

Documentator generates an OpenAPI 3.0 specification from your Laravel routes. It reads controller documentation, validation rules, and API resources, so you can describe your API alongside the code that implements it.

Run `php artisan documentator:generate` to create `docs/openapi.json`. Import this file into your API documentation viewer or client.

Start with your existing FormRequests and resources. Add PHPDoc descriptions and examples where the generated documentation needs more context.

The package requires PHP 8.3+ and Laravel 10–13. Continue with [Installation](installation.md).
