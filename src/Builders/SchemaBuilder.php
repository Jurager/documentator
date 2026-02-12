<?php

namespace Jurager\Documentator\Builders;

use Faker\Factory;
use Faker\Generator;
use Illuminate\Support\Str;

/**
 * Builds OpenAPI schemas and generates example data.
 */
class SchemaBuilder
{
    private Generator $faker;

    /**
     * @param  array  $typeMap  Custom type mapping for validation rules
     * @param  Generator|null  $faker  Faker instance for generating examples
     */
    public function __construct(
        private array $typeMap = [],
        ?Generator $faker = null
    ) {
        $this->faker = $faker ?? Factory::create();
    }

    /**
     * Build schema from validation rules and doc params.
     *
     * @param  array  $rules  Validation rules array
     * @param  array  $docParams  Documentation parameters from PHPDoc
     * @return array OpenAPI schema definition
     */
    public function build(array $rules, array $docParams = []): array
    {
        $props = [];
        $required = [];

        foreach ($rules as $field => $r) {
            $ruleList = is_array($r) ? $r : explode('|', $r);
            $isRequired = in_array('required', $ruleList);

            // Handle array items: field.*.subfield
            if (str_contains($field, '.*.')) {
                [$arrayField, , $itemField] = explode('.', $field, 3);
                $props[$arrayField] ??= [
                    'type' => 'array',
                    'description' => Str::headline($arrayField),
                    'items' => ['type' => 'object', 'properties' => [], 'required' => []],
                ];
                $props[$arrayField]['items']['properties'][$itemField] = $this->buildFieldSchema($itemField, $ruleList);
                if ($isRequired) {
                    $props[$arrayField]['items']['required'][] = $itemField;
                }

                continue;
            }

            // Skip nested fields
            if (str_contains($field, '.')) {
                continue;
            }

            if ($isRequired) {
                $required[] = $field;
            }

            $props[$field] = $this->buildFieldSchema($field, $ruleList);
        }

        // Add body params from doc comments
        foreach ($docParams as $p) {
            if (str_contains($p['name'], '.')) {
                [$arrayField, $itemField] = explode('.', $p['name'], 2);
                $props[$arrayField] ??= [
                    'type' => 'array',
                    'description' => Str::headline($arrayField),
                    'items' => ['type' => 'object', 'properties' => []],
                ];
                $props[$arrayField]['items']['properties'][$itemField] = [
                    'type' => $this->normalizeType($p['type']),
                    'description' => $p['description'],
                ];
            } else {
                $props[$p['name']] ??= [
                    'type' => $this->normalizeType($p['type']),
                    'description' => $p['description'],
                ];
                if ($p['required'] && ! in_array($p['name'], $required)) {
                    $required[] = $p['name'];
                }
            }
        }

        return array_filter([
            'type' => 'object',
            'properties' => $props,
            'required' => $required ?: null,
        ]);
    }

    /**
     * Build schema for a single field from validation rules.
     */
    private function buildFieldSchema(string $field, array $rules): array
    {
        $type = $this->resolveType($rules);

        $schema = [
            'type' => $type,
            'description' => $this->buildDescription($field, $rules),
        ];

        foreach ($rules as $rule) {

            if (!is_string($rule)) {
                continue; // игнорируем Closure и объекты Rule
            }

            match (true) {
                str_starts_with($rule, 'max:') =>
                $schema[$type === 'string' ? 'maxLength' : 'maximum'] = (int) substr($rule, 4),

                str_starts_with($rule, 'min:') =>
                $schema[$type === 'string' ? 'minLength' : 'minimum'] = (int) substr($rule, 4),

                str_starts_with($rule, 'in:') =>
                $schema['enum'] = explode(',', substr($rule, 3)),

                $rule === 'email' =>
                $schema['format'] = 'email',

                $rule === 'url' =>
                $schema['format'] = 'uri',

                $rule === 'uuid' =>
                $schema['format'] = 'uuid',

                $rule === 'date' =>
                $schema['format'] = 'date',

                $rule === 'nullable' =>
                $schema['nullable'] = true,

                default => null,
            };
        }

        return $schema;
    }

    /**
     * Resolve OpenAPI type from validation rules.
     */
    private function resolveType(array $rules): string
    {
        foreach ($rules as $rule) {
            $name = explode(':', $rule)[0];
            if (isset($this->typeMap[$name])) {
                return $this->typeMap[$name];
            }
        }

        return 'string';
    }

    /**
     * Build field description from validation rules.
     */
    private function buildDescription(string $field, array $rules): string
    {
        $desc = [];

        foreach ($rules as $rule) {

            // Строковые правила
            if (is_string($rule)) {
                match (true) {
                    str_starts_with($rule, 'max:') =>
                    $desc[] = __('documentator::messages.max', ['value' => substr($rule, 4)]),

                    str_starts_with($rule, 'min:') =>
                    $desc[] = __('documentator::messages.min', ['value' => substr($rule, 4)]),

                    $rule === 'email' =>
                    $desc[] = 'email',

                    str_starts_with($rule, 'unique') =>
                    $desc[] = __('documentator::messages.unique'),

                    str_starts_with($rule, 'exists:') =>
                    $desc[] = __('documentator::messages.exists'),

                    default => null,
                };

                continue;
            }

            // Объектные правила
            if ($rule instanceof Unique) {
                $desc[] = __('documentator::messages.unique');
                continue;
            }

            if ($rule instanceof Exists) {
                $desc[] = __('documentator::messages.exists');
                continue;
            }
        }

        return $desc
            ? Str::headline($field).' ('.implode(', ', $desc).')'
            : Str::headline($field);
    }

    /**
     * Normalize type name.
     */
    private function normalizeType(string $type): string
    {
        return match (strtolower($type)) {
            'int', 'integer', 'numeric' => 'integer',
            'float', 'double', 'number' => 'number',
            'bool', 'boolean' => 'boolean',
            default => 'string',
        };
    }

    /**
     * Generate random integer between min and max.
     *
     * @param  int  $min  Minimum value
     * @param  int  $max  Maximum value
     * @return int Random integer
     */
    public function randomInt(int $min = 1, int $max = 100): int
    {
        return $this->faker->numberBetween($min, $max);
    }

    /**
     * Generate example value based on field name and type.
     *
     * @param  string  $field  Field name
     * @param  string  $type  Field type (string, integer, number, boolean, array, object)
     * @return mixed Generated example value
     */
    public function generateValue(string $field, string $type = 'string'): mixed
    {
        $f = strtolower($field);

        // Type-based generation
        return match ($type) {
            'integer' => $this->generateInteger($f),
            'number' => $this->generateNumber($f),
            'boolean' => $this->generateBoolean($f),
            'array' => [],
            'object' => new \stdClass(),
            default => $this->generateString($f),
        };
    }

    /**
     * Generate example object from attributes schema.
     *
     * @param  array  $attributes  Attributes schema with types
     * @param  int  $id  Object ID
     * @return array Generated example object
     */
    public function generateObject(array $attributes, int $id = 1): array
    {
        $example = ['id' => $id];

        foreach ($attributes as $name => $config) {
            if ($name === 'id') {
                continue;
            }

            $type = $config['type'] ?? 'string';
            $example[$name] = $this->generateValue($name, $type);
        }

        return $example;
    }

    /**
     * Generate JSON:API resource example.
     *
     * @param  string  $resourceType  Resource type name
     * @param  array  $attributes  Attributes schema with types
     * @param  int  $id  Resource ID
     * @return array Generated JSON:API resource object
     */
    public function generateJsonApiResource(string $resourceType, array $attributes, int $id = 1): array
    {
        $resourceAttributes = [];

        foreach ($attributes as $name => $config) {
            if ($name === 'id') {
                continue;
            }

            $type = $config['type'] ?? 'string';
            $resourceAttributes[$name] = $this->generateValue($name, $type);
        }

        return [
            'type' => $resourceType,
            'id' => (string) $id,
            'attributes' => $resourceAttributes,
            'links' => [
                'self' => "/$resourceType/$id",
            ],
        ];
    }

    /**
     * Generate integer value based on field name.
     */
    private function generateInteger(string $field): int
    {
        return match (true) {
            str_contains($field, 'age') => $this->faker->numberBetween(18, 80),
            str_contains($field, 'year') => $this->faker->year(),
            str_contains($field, 'month') => $this->faker->numberBetween(1, 12),
            str_contains($field, 'day') => $this->faker->numberBetween(1, 28),
            str_contains($field, 'hour') => $this->faker->numberBetween(0, 23),
            str_contains($field, 'minute') => $this->faker->numberBetween(0, 59),
            str_contains($field, 'count'), str_contains($field, 'quantity') => $this->faker->numberBetween(1, 100),
            str_contains($field, 'stock') => $this->faker->numberBetween(0, 1000),
            str_contains($field, 'total') => $this->faker->numberBetween(50, 500),
            str_contains($field, 'page') => 1,
            str_contains($field, 'per_page'), str_contains($field, 'limit') => 15,
            str_contains($field, 'offset'), str_contains($field, 'skip') => 0,
            str_contains($field, 'order'), str_contains($field, 'position'), str_contains($field, 'sort') => $this->faker->numberBetween(1, 10),
            str_contains($field, 'priority'), str_contains($field, 'level') => $this->faker->numberBetween(1, 5),
            str_contains($field, 'percent') => $this->faker->numberBetween(0, 100),
            str_contains($field, 'rating'), str_contains($field, 'score') => $this->faker->numberBetween(1, 5),
            str_contains($field, 'width'), str_contains($field, 'height') => $this->faker->numberBetween(100, 1920),
            str_contains($field, 'size') => $this->faker->numberBetween(100, 10000),
            str_contains($field, 'duration') => $this->faker->numberBetween(60, 7200),
            str_contains($field, 'attempts'), str_contains($field, 'retries') => $this->faker->numberBetween(1, 5),
            str_ends_with($field, '_id') => $this->faker->numberBetween(1, 1000),
            default => $this->faker->numberBetween(1, 100),
        };
    }

    /**
     * Generate number (float) value based on field name.
     */
    private function generateNumber(string $field): float
    {
        return match (true) {
            str_contains($field, 'price') => $this->faker->randomFloat(2, 10, 1000),
            str_contains($field, 'amount'), str_contains($field, 'sum') => $this->faker->randomFloat(2, 1, 10000),
            str_contains($field, 'cost') => $this->faker->randomFloat(2, 1, 500),
            str_contains($field, 'total') => $this->faker->randomFloat(2, 10, 5000),
            str_contains($field, 'balance') => $this->faker->randomFloat(2, 0, 10000),
            str_contains($field, 'tax') => $this->faker->randomFloat(2, 0, 100),
            str_contains($field, 'discount') => $this->faker->randomFloat(2, 0, 50),
            str_contains($field, 'rate') => $this->faker->randomFloat(2, 0, 1),
            str_contains($field, 'percent') => $this->faker->randomFloat(1, 0, 100),
            str_contains($field, 'latitude'), str_contains($field, 'lat') => $this->faker->latitude(),
            str_contains($field, 'longitude'), str_contains($field, 'lng'), str_contains($field, 'lon') => $this->faker->longitude(),
            str_contains($field, 'weight') => $this->faker->randomFloat(2, 0.1, 100),
            str_contains($field, 'rating'), str_contains($field, 'score') => $this->faker->randomFloat(1, 1, 5),
            default => $this->faker->randomFloat(2, 0, 1000),
        };
    }

    /**
     * Generate boolean value based on field name.
     */
    private function generateBoolean(string $field): bool
    {
        // Negative patterns - default to false
        if (str_contains($field, 'disabled') ||
            str_contains($field, 'hidden') ||
            str_contains($field, 'deleted') ||
            str_contains($field, 'blocked') ||
            str_contains($field, 'banned') ||
            str_contains($field, 'expired') ||
            str_contains($field, 'archived')) {
            return false;
        }

        return true;
    }

    /**
     * Generate string value based on field name.
     */
    private function generateString(string $field): string
    {
        return match (true) {
            // Identification
            str_contains($field, 'uuid') => $this->faker->uuid(),
            str_contains($field, 'slug') => $this->faker->slug(),
            str_contains($field, 'code') => strtoupper($this->faker->bothify('??###')),
            str_contains($field, 'sku') => strtoupper($this->faker->bothify('SKU-###')),
            str_contains($field, 'barcode') => $this->faker->ean13(),
            str_contains($field, 'serial') => strtoupper($this->faker->bothify('SN-####-????')),
            str_contains($field, 'token') => $this->faker->sha256(),
            str_contains($field, 'hash') => $this->faker->sha1(),
            str_contains($field, 'key') => 'key_'.$this->faker->bothify('??????????'),

            // Personal
            str_contains($field, 'first_name') => $this->faker->firstName(),
            str_contains($field, 'last_name') => $this->faker->lastName(),
            str_contains($field, 'full_name') => $this->faker->name(),
            $field === 'name' || str_ends_with($field, '_name') => $this->faker->words(2, true),
            str_contains($field, 'username') => $this->faker->userName(),
            str_contains($field, 'email') => $this->faker->safeEmail(),
            str_contains($field, 'phone'), str_contains($field, 'mobile'), str_contains($field, 'tel') => $this->faker->phoneNumber(),
            str_contains($field, 'avatar'), str_contains($field, 'photo'), str_contains($field, 'picture') => $this->faker->imageUrl(200, 200, 'people'),
            str_contains($field, 'gender') => $this->faker->randomElement(['male', 'female', 'other']),
            str_contains($field, 'locale') => $this->faker->locale(),
            str_contains($field, 'language'), str_contains($field, 'lang') => $this->faker->languageCode(),
            str_contains($field, 'timezone') => $this->faker->timezone(),

            // Address
            str_contains($field, 'address') => $this->faker->streetAddress(),
            str_contains($field, 'street') => $this->faker->streetName(),
            str_contains($field, 'city') => $this->faker->city(),
            str_contains($field, 'state'), str_contains($field, 'region'), str_contains($field, 'province') => $this->faker->stateAbbr(),
            str_contains($field, 'country') => $this->faker->countryCode(),
            str_contains($field, 'zip'), str_contains($field, 'postal') => $this->faker->postcode(),

            // Internet
            str_contains($field, 'url'), str_contains($field, 'link'), str_contains($field, 'website') => $this->faker->url(),
            str_contains($field, 'domain') => $this->faker->domainName(),
            str_contains($field, 'ip') => $this->faker->ipv4(),
            str_contains($field, 'mac') => $this->faker->macAddress(),
            str_contains($field, 'user_agent') => $this->faker->userAgent(),

            // Content
            str_contains($field, 'title'), str_contains($field, 'headline'), str_contains($field, 'subject') => $this->faker->sentence(),
            str_contains($field, 'description'), str_contains($field, 'summary') => $this->faker->paragraph(),
            str_contains($field, 'excerpt') => $this->faker->text(100),
            str_contains($field, 'content'), str_contains($field, 'body') => $this->faker->paragraphs(3, true),
            str_contains($field, 'text') => $this->faker->text(),
            str_contains($field, 'comment'), str_contains($field, 'note'), str_contains($field, 'message') => $this->faker->sentence(),
            str_contains($field, 'bio'), str_contains($field, 'about') => $this->faker->paragraph(),

            // Dates
            str_contains($field, 'datetime'), str_ends_with($field, '_at') => $this->faker->iso8601(),
            str_contains($field, 'date'), str_contains($field, 'birthday'), str_contains($field, 'birth') => $this->faker->date(),
            str_contains($field, 'time') => $this->faker->time(),
            str_contains($field, 'timestamp') => (string) $this->faker->unixTime(),
            str_contains($field, 'year') => (string) $this->faker->year(),
            str_contains($field, 'month') => $this->faker->monthName(),
            str_contains($field, 'day') => (string) $this->faker->dayOfMonth(),

            // Financial
            str_contains($field, 'currency') => $this->faker->currencyCode(),
            str_contains($field, 'iban') => $this->faker->iban('GB'),
            str_contains($field, 'swift'), str_contains($field, 'bic') => $this->faker->swiftBicNumber(),
            str_contains($field, 'card') => $this->faker->creditCardNumber(),

            // Status (checked before address 'state')
            $field === 'status' || str_ends_with($field, '_status') => $this->faker->randomElement(['active', 'inactive', 'pending']),
            $field === 'state' && ! str_contains($field, 'address') => $this->faker->randomElement(['draft', 'published', 'archived']),
            str_contains($field, 'type') => $this->faker->randomElement(['default', 'premium', 'basic']),
            str_contains($field, 'role') => $this->faker->randomElement(['user', 'admin', 'moderator']),
            str_contains($field, 'format') => $this->faker->randomElement(['json', 'xml', 'csv']),
            str_contains($field, 'method') => $this->faker->randomElement(['GET', 'POST', 'PUT', 'DELETE']),
            str_contains($field, 'action') => $this->faker->randomElement(['create', 'update', 'delete']),
            str_contains($field, 'event') => $this->faker->randomElement(['created', 'updated', 'deleted']),

            // Files
            str_contains($field, 'file'), str_contains($field, 'path') => '/uploads/'.$this->faker->word().'.'.$this->faker->fileExtension(),
            str_contains($field, 'filename') => $this->faker->word().'.'.$this->faker->fileExtension(),
            str_contains($field, 'extension'), str_contains($field, 'ext') => $this->faker->fileExtension(),
            str_contains($field, 'mime'), str_contains($field, 'mimetype') => $this->faker->mimeType(),
            str_contains($field, 'image'), str_contains($field, 'thumbnail'), str_contains($field, 'logo'), str_contains($field, 'icon') => $this->faker->imageUrl(),
            str_contains($field, 'video') => 'https://example.com/video/'.$this->faker->uuid().'.mp4',
            str_contains($field, 'audio') => 'https://example.com/audio/'.$this->faker->uuid().'.mp3',

            // Company
            str_contains($field, 'company'), str_contains($field, 'organization') => $this->faker->company(),
            str_contains($field, 'job'), str_contains($field, 'position') => $this->faker->jobTitle(),
            str_contains($field, 'department') => $this->faker->randomElement(['Engineering', 'Marketing', 'Sales', 'HR', 'Finance']),

            // Technical
            str_contains($field, 'version') => $this->faker->semver(),
            str_contains($field, 'color'), str_contains($field, 'hex') => $this->faker->hexColor(),
            str_contains($field, 'password') => '********',

            default => $this->faker->word(),
        };
    }
}
