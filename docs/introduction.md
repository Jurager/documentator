---
title: Introduction
weight: 10
---

# Introduction

Jurager/Documentator generates OpenAPI 3 specifications from Laravel routes.

It scans route actions, PHPDoc tags, validation rules, and API Resources to produce endpoint documentation with request/response schemas.

## Key Capabilities

- Build OpenAPI from discovered Laravel routes.
- Extract request schemas from `FormRequest` and `$request->validate([...])` rules.
- Build response examples from Resource classes.
- Use PHPDoc tags like `@summary`, `@group`, `@queryParam`, and `@response`.
- Support response envelope formats: `simple` and `json-api`.

## When To Use

- You want API documentation generated from existing controller code.
- You need a repeatable command for OpenAPI generation in CI/CD.
- You want docs and schema output to stay aligned with validation rules.

## Requirements

- PHP >= 8.2
- Laravel 10+
- Composer
