<?php

namespace Jurager\Documentator\Support;

use Faker\Factory;
use Faker\Generator;

class ExampleGenerator
{
    private Generator $faker;

    /**
     * Field name patterns mapped to faker methods.
     *
     * @var array<string, callable(Generator): mixed>
     */
    private array $patterns;

    public function __construct(?string $locale = null)
    {
        $this->faker = Factory::create($locale ?? config('app.faker_locale', 'en_US'));
        $this->patterns = $this->defaultPatterns();
    }

    /**
     * Generate example value based on field name and optional type.
     */
    public function value(string $field, ?string $type = null): mixed
    {
        $fieldLower = strtolower($field);

        // Check patterns first
        foreach ($this->patterns as $pattern => $generator) {
            if (str_contains($fieldLower, $pattern)) {
                return $generator($this->faker);
            }
        }

        // Fallback to type-based generation
        return match ($type) {
            'integer' => $this->faker->numberBetween(1, 100),
            'number' => $this->faker->randomFloat(2, 0, 1000),
            'boolean' => $this->faker->boolean(),
            'array' => [],
            default => $this->faker->word(),
        };
    }

    /**
     * Generate example object from attributes schema.
     *
     * @param  array<string, mixed>|null  $attributes
     */
    public function object(?array $attributes, string $id = '1'): array
    {
        $example = ['id' => (int) $id];

        if ($attributes) {
            foreach ($attributes as $name => $config) {
                $type = $config['type'] ?? 'string';
                $example[$name] = $this->value($name, $type);
            }
        } else {
            $example['created_at'] = $this->faker->iso8601();
            $example['updated_at'] = $this->faker->iso8601();
        }

        return $example;
    }

    /**
     * Generate JSON:API resource example.
     *
     * @param  array<string, mixed>|null  $attributes
     */
    public function jsonApiResource(string $resource, ?array $attributes, string $id = '1'): array
    {
        $example = [
            'type' => $resource,
            'id' => $id,
            'attributes' => [],
            'links' => [
                'self' => "/{$resource}/{$id}",
            ],
        ];

        if ($attributes) {
            foreach ($attributes as $name => $config) {
                $type = $config['type'] ?? 'string';
                $example['attributes'][$name] = $this->value($name, $type);
            }
        } else {
            $example['attributes'] = [
                'created_at' => $this->faker->iso8601(),
                'updated_at' => $this->faker->iso8601(),
            ];
        }

        return $example;
    }

    /**
     * Add custom pattern.
     *
     * @param  callable(Generator): mixed  $generator
     */
    public function addPattern(string $pattern, callable $generator): self
    {
        $this->patterns[$pattern] = $generator;

        return $this;
    }

    /**
     * Get faker instance for advanced usage.
     */
    public function faker(): Generator
    {
        return $this->faker;
    }

    /**
     * Default field patterns.
     *
     * @return array<string, callable(Generator): mixed>
     */
    private function defaultPatterns(): array
    {
        return [
            // Identification
            'uuid' => fn (Generator $f) => $f->uuid(),
            'slug' => fn (Generator $f) => $f->slug(3),

            // Personal
            'first_name' => fn (Generator $f) => $f->firstName(),
            'last_name' => fn (Generator $f) => $f->lastName(),
            'full_name' => fn (Generator $f) => $f->name(),
            'name' => fn (Generator $f) => $f->name(),
            'username' => fn (Generator $f) => $f->userName(),
            'email' => fn (Generator $f) => $f->safeEmail(),
            'phone' => fn (Generator $f) => $f->e164PhoneNumber(),
            'avatar' => fn (Generator $f) => $f->imageUrl(200, 200, 'people'),
            'bio' => fn (Generator $f) => $f->sentence(10),
            'gender' => fn (Generator $f) => $f->randomElement(['male', 'female', 'other']),
            'birthday' => fn (Generator $f) => $f->date('Y-m-d'),
            'birth' => fn (Generator $f) => $f->date('Y-m-d'),
            'age' => fn (Generator $f) => $f->numberBetween(18, 80),

            // Contact
            'address' => fn (Generator $f) => $f->streetAddress(),
            'street' => fn (Generator $f) => $f->streetName(),
            'city' => fn (Generator $f) => $f->city(),
            'state' => fn (Generator $f) => $f->state(),
            'country' => fn (Generator $f) => $f->country(),
            'zip' => fn (Generator $f) => $f->postcode(),
            'postcode' => fn (Generator $f) => $f->postcode(),
            'postal' => fn (Generator $f) => $f->postcode(),
            'latitude' => fn (Generator $f) => $f->latitude(),
            'longitude' => fn (Generator $f) => $f->longitude(),
            'lat' => fn (Generator $f) => $f->latitude(),
            'lng' => fn (Generator $f) => $f->longitude(),
            'lon' => fn (Generator $f) => $f->longitude(),
            'timezone' => fn (Generator $f) => $f->timezone(),

            // Internet
            'url' => fn (Generator $f) => $f->url(),
            'link' => fn (Generator $f) => $f->url(),
            'website' => fn (Generator $f) => $f->url(),
            'domain' => fn (Generator $f) => $f->domainName(),
            'ip' => fn (Generator $f) => $f->ipv4(),
            'ipv4' => fn (Generator $f) => $f->ipv4(),
            'ipv6' => fn (Generator $f) => $f->ipv6(),
            'mac' => fn (Generator $f) => $f->macAddress(),
            'user_agent' => fn (Generator $f) => $f->userAgent(),

            // Content
            'title' => fn (Generator $f) => $f->sentence(4),
            'headline' => fn (Generator $f) => $f->sentence(6),
            'subject' => fn (Generator $f) => $f->sentence(5),
            'description' => fn (Generator $f) => $f->paragraph(2),
            'summary' => fn (Generator $f) => $f->paragraph(1),
            'content' => fn (Generator $f) => $f->paragraphs(3, true),
            'body' => fn (Generator $f) => $f->paragraphs(3, true),
            'text' => fn (Generator $f) => $f->text(200),
            'excerpt' => fn (Generator $f) => $f->sentence(15),
            'comment' => fn (Generator $f) => $f->sentence(10),
            'note' => fn (Generator $f) => $f->sentence(8),
            'message' => fn (Generator $f) => $f->sentence(12),

            // Dates & Times
            'date' => fn (Generator $f) => $f->date('Y-m-d'),
            'time' => fn (Generator $f) => $f->time('H:i:s'),
            'datetime' => fn (Generator $f) => $f->iso8601(),
            'timestamp' => fn (Generator $f) => $f->unixTime(),
            '_at' => fn (Generator $f) => $f->iso8601(),
            'created' => fn (Generator $f) => $f->iso8601(),
            'updated' => fn (Generator $f) => $f->iso8601(),
            'deleted' => fn (Generator $f) => $f->iso8601(),
            'published' => fn (Generator $f) => $f->iso8601(),
            'expired' => fn (Generator $f) => $f->iso8601(),
            'expires' => fn (Generator $f) => $f->iso8601(),

            // Financial
            'price' => fn (Generator $f) => $f->randomFloat(2, 10, 1000),
            'amount' => fn (Generator $f) => $f->randomFloat(2, 1, 10000),
            'cost' => fn (Generator $f) => $f->randomFloat(2, 1, 500),
            'total' => fn (Generator $f) => $f->randomFloat(2, 10, 5000),
            'balance' => fn (Generator $f) => $f->randomFloat(2, 0, 10000),
            'salary' => fn (Generator $f) => $f->numberBetween(30000, 150000),
            'currency' => fn (Generator $f) => $f->currencyCode(),
            'credit_card' => fn (Generator $f) => $f->creditCardNumber(),
            'card_number' => fn (Generator $f) => $f->creditCardNumber(),
            'iban' => fn (Generator $f) => $f->iban(),
            'swift' => fn (Generator $f) => $f->swiftBicNumber(),

            // Quantities
            'count' => fn (Generator $f) => $f->numberBetween(1, 100),
            'quantity' => fn (Generator $f) => $f->numberBetween(1, 50),
            'stock' => fn (Generator $f) => $f->numberBetween(0, 1000),
            'number' => fn (Generator $f) => $f->numberBetween(1, 1000),
            'percent' => fn (Generator $f) => $f->numberBetween(0, 100),
            'percentage' => fn (Generator $f) => $f->numberBetween(0, 100),
            'rating' => fn (Generator $f) => $f->randomFloat(1, 1, 5),
            'score' => fn (Generator $f) => $f->numberBetween(0, 100),
            'weight' => fn (Generator $f) => $f->randomFloat(2, 0.1, 100),
            'height' => fn (Generator $f) => $f->numberBetween(100, 220),
            'width' => fn (Generator $f) => $f->numberBetween(1, 1000),
            'length' => fn (Generator $f) => $f->numberBetween(1, 1000),
            'size' => fn (Generator $f) => $f->randomElement(['S', 'M', 'L', 'XL']),
            'order' => fn (Generator $f) => $f->numberBetween(0, 100),
            'position' => fn (Generator $f) => $f->numberBetween(1, 50),
            'priority' => fn (Generator $f) => $f->numberBetween(1, 10),
            'level' => fn (Generator $f) => $f->numberBetween(1, 10),

            // Status & Flags
            'status' => fn (Generator $f) => $f->randomElement(['active', 'inactive', 'pending']),
            'state' => fn (Generator $f) => $f->randomElement(['draft', 'published', 'archived']),
            'type' => fn (Generator $f) => $f->randomElement(['default', 'premium', 'basic']),
            'role' => fn (Generator $f) => $f->randomElement(['user', 'admin', 'moderator']),
            'active' => fn (Generator $f) => $f->boolean(80),
            'enabled' => fn (Generator $f) => $f->boolean(80),
            'disabled' => fn (Generator $f) => $f->boolean(20),
            'visible' => fn (Generator $f) => $f->boolean(80),
            'hidden' => fn (Generator $f) => $f->boolean(20),
            'verified' => fn (Generator $f) => $f->boolean(70),
            'confirmed' => fn (Generator $f) => $f->boolean(70),
            'approved' => fn (Generator $f) => $f->boolean(60),
            'published' => fn (Generator $f) => $f->boolean(50),
            'featured' => fn (Generator $f) => $f->boolean(20),
            'is_' => fn (Generator $f) => $f->boolean(),
            'has_' => fn (Generator $f) => $f->boolean(),
            'can_' => fn (Generator $f) => $f->boolean(),

            // Files & Media
            'file' => fn (Generator $f) => $f->filePath(),
            'path' => fn (Generator $f) => $f->filePath(),
            'filename' => fn (Generator $f) => $f->word().'.'.$f->fileExtension(),
            'extension' => fn (Generator $f) => $f->fileExtension(),
            'mime' => fn (Generator $f) => $f->mimeType(),
            'image' => fn (Generator $f) => $f->imageUrl(640, 480),
            'photo' => fn (Generator $f) => $f->imageUrl(640, 480),
            'picture' => fn (Generator $f) => $f->imageUrl(640, 480),
            'thumbnail' => fn (Generator $f) => $f->imageUrl(150, 150),
            'logo' => fn (Generator $f) => $f->imageUrl(200, 200),
            'icon' => fn (Generator $f) => $f->imageUrl(64, 64),
            'video' => fn (Generator $f) => 'https://example.com/video/'.$f->uuid().'.mp4',
            'audio' => fn (Generator $f) => 'https://example.com/audio/'.$f->uuid().'.mp3',

            // Company & Business
            'company' => fn (Generator $f) => $f->company(),
            'organization' => fn (Generator $f) => $f->company(),
            'job' => fn (Generator $f) => $f->jobTitle(),
            'job_title' => fn (Generator $f) => $f->jobTitle(),
            'department' => fn (Generator $f) => $f->randomElement(['Engineering', 'Marketing', 'Sales', 'HR']),
            'industry' => fn (Generator $f) => $f->randomElement(['Technology', 'Finance', 'Healthcare', 'Retail']),

            // Technical
            'token' => fn (Generator $f) => $f->sha256(),
            'hash' => fn (Generator $f) => $f->sha256(),
            'key' => fn (Generator $f) => $f->md5(),
            'secret' => fn (Generator $f) => $f->sha1(),
            'code' => fn (Generator $f) => strtoupper($f->bothify('??###')),
            'sku' => fn (Generator $f) => strtoupper($f->bothify('???-####')),
            'barcode' => fn (Generator $f) => $f->ean13(),
            'serial' => fn (Generator $f) => strtoupper($f->bothify('??##??##??')),
            'version' => fn (Generator $f) => $f->semver(),
            'locale' => fn (Generator $f) => $f->locale(),
            'language' => fn (Generator $f) => $f->languageCode(),
            'color' => fn (Generator $f) => $f->hexColor(),
            'hex' => fn (Generator $f) => $f->hexColor(),
            'rgb' => fn (Generator $f) => $f->rgbColorAsArray(),
        ];
    }
}