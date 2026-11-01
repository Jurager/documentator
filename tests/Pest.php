<?php

use cebe\openapi\json\JsonPointer;
use cebe\openapi\Reader;
use cebe\openapi\ReferenceContext;
use JsonSchema\Validator;
use Jurager\Documentator\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

function assertValidOpenApi(array|object $spec): void
{
    $json = json_encode($spec, JSON_THROW_ON_ERROR);
    // cebe 1.8 cannot read the valid anonymous security requirement {}.
    // The JSON Schema validator below checks security on the original document.
    $readerSpec = json_decode($json);
    unset($readerSpec->security);
    $document = Reader::readFromJson(json_encode($readerSpec, JSON_THROW_ON_ERROR));
    expect($document->validate())->toBeTrue(json_encode($document->getErrors(), JSON_PRETTY_PRINT));
    $document->setDocumentContext($document, new JsonPointer(''));
    $document->setReferenceContext(new ReferenceContext($document, 'file:///documentator.json'));
    $document->resolveReferences();

    $validator = new Validator;
    $schema = json_decode(file_get_contents(__DIR__.'/../vendor/cebe/php-openapi/schemas/openapi-v3.0.json'));
    $data = json_decode($json);
    $validator->validate($data, $schema);
    expect($validator->isValid())->toBeTrue(json_encode($validator->getErrors(), JSON_PRETTY_PRINT));

    // Structural validators do not enforce uniqueness of operation IDs or path bindings.
    $spec = json_decode($json, true);
    $ids = [];
    foreach ((array) $spec['paths'] as $path => $operations) {
        preg_match_all('/\{([^}]+)\}/', $path, $matches);
        foreach ($operations as $operation) {
            expect($ids)->not->toContain($operation['operationId']);
            $ids[] = $operation['operationId'];
            $parameters = collect($operation['parameters'] ?? []);
            expect($parameters->map(fn ($p) => $p['in'].':'.$p['name'])->duplicates())->toBeEmpty();
            foreach ($matches[1] as $name) {
                expect($parameters->first(fn ($p) => $p['in'] === 'path' && $p['name'] === $name))
                    ->toMatchArray(['required' => true]);
            }
        }
    }
}

function assertOpenApiExample(array $schema, mixed $example, array $components = []): void
{
    $normalize = function ($value, array $references = []) use (&$normalize, $components) {
        if (is_array($value)) {
            return array_map(fn ($item) => $normalize($item, $references), $value);
        }
        if (! $value instanceof stdClass) {
            return $value;
        }
        if (isset($value->{'$ref'})) {
            $reference = $value->{'$ref'};
            $key = str_replace('#/components/schemas/', '', $reference);
            if (in_array($reference, $references, true)) {
                return (object) [];
            }

            return $normalize(json_decode(json_encode($components[$key], JSON_THROW_ON_ERROR)), [...$references, $reference]);
        }
        $normalized = new stdClass;
        foreach (get_object_vars($value) as $key => $item) {
            $normalized->$key = $normalize($item, $references);
        }
        if (($normalized->nullable ?? false) && isset($normalized->type)) {
            $normalized->type = [$normalized->type, 'null'];
        }
        unset($normalized->nullable);

        return $normalized;
    };

    $jsonSchema = $normalize(json_decode(json_encode($schema, JSON_THROW_ON_ERROR)));
    $data = json_decode(json_encode($example, JSON_THROW_ON_ERROR));
    $validator = new Validator;
    $validator->validate($data, $jsonSchema);
    expect($validator->isValid())->toBeTrue(json_encode($validator->getErrors(), JSON_PRETTY_PRINT));
}
