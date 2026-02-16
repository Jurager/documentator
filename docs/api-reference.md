---
title: API Reference
weight: 90
---

# API Reference

## Main Entry Points

- `Jurager\Documentator\Commands\GenerateCommand`
- `Jurager\Documentator\DocumentatorServiceProvider`

## Core Builders

- `Builders\SpecificationBuilder` - orchestrates full OpenAPI document build.
- `Builders\OperationBuilder` - builds operation-level metadata, params, request body, and responses.
- `Builders\SchemaBuilder` - converts validation/doc fields into schema definitions and examples.

## Parsing and Collection

- `Collectors\RouteCollector` - route filtering and normalization.
- `Parsers\DocumentationParser` - PHPDoc parsing, validation extraction, resource parsing.
- `Resolvers\FieldTypeResolver` - field-name-based type hints for examples.

## Format Layer

- `Formats\AbstractFormatInterface`
- `Formats\AbstractFormat`
- `Formats\SimpleFormat`
- `Formats\JsonApiFormat`

## Service Provider Behavior

`DocumentatorServiceProvider`:

- merges package config,
- publishes config with tag `documentator-config`,
- registers `docs:generate` command,
- loads package translations in console context.
