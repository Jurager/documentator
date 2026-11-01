<?php

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Jurager\Documentator\OpenApi\ExampleGenerator;
use Jurager\Documentator\Parsers\SourceParser;
use Jurager\Documentator\Tests\Fixtures\WidgetResource;
use Jurager\Documentator\Tests\Fixtures\WidgetsController;

it('reads literal resource keys and relationship declarations', function () {
    $reader = app(SourceParser::class);
    $array = new ReflectionMethod(WidgetResource::class, 'toArray');
    $relationships = new ReflectionMethod(WidgetResource::class, 'toRelationships');
    expect($reader->arrayKeys($array))->toBe(['id', 'name', 'price', 'is_active'])
        ->and($reader->relationLinks($relationships)['children']['collection'])->toBeTrue()
        ->and($reader->relationLinks($relationships)['owner']['collection'])->toBeFalse();
});

it('statically reads inline validation without executing controller actions', function () {
    $reader = app(SourceParser::class);
    expect($reader->validationRules(new ReflectionMethod(WidgetsController::class, 'inline'))['name'])->toBe(['required', 'string'])
        ->and($reader->validationRules(new ReflectionMethod(WidgetsController::class, 'index')))->toBe([]);
});

it('handles changed invalid source without executing it', function () {
    $file = tempnam(sys_get_temp_dir(), 'documentator-ast-');
    $name = 'DocumentatorSource_'.bin2hex(random_bytes(6));
    try {
        file_put_contents($file, "<?php class {$name} { public function fields() { return ['id' => 1]; } }");
        require $file;
        $method = new ReflectionMethod($name, 'fields');
        file_put_contents($file, '<?php class { broken');
        $reader = app(SourceParser::class);
        expect($reader->arrayKeys($method))->toBe([])
            ->and($reader->relationLinks($method))->toBe([])
            ->and($reader->validationRules($method))->toBe([])
            ->and($reader->warnings())->not->toBeEmpty();
    } finally {
        unlink($file);
    }
});

trait DocumentatorResourceFields
{
    public function toArray(Request $request): array
    {
        return ['display_id' => (string) $this->id];
    }
}

class DocumentatorTraitResource extends JsonResource
{
    use DocumentatorResourceFields;
}

it('reads resource fields declared in traits without executing them', function () {
    $properties = app(SourceParser::class)->properties(new ReflectionMethod(DocumentatorTraitResource::class, 'toArray'));
    expect($properties)->toBe(['display_id' => ['type' => 'string']]);
});

it('reads only request validation in the current method scope and merges validation blocks', function () {
    $rules = app(SourceParser::class)->validationRules(new ReflectionMethod(WidgetsController::class, 'scopedValidation'));

    expect($rules)->toBe(['name' => ['required', 'string'], 'count' => 'required|integer']);
});

it('reports inline rules that cannot be read statically', function () {
    $parser = app(SourceParser::class);
    $rules = $parser->validationRules(new ReflectionMethod(WidgetsController::class, 'dynamicValidation'));

    expect($rules)->toBe([])
        ->and(implode('\n', $parser->warnings()))->toContain('Cannot statically read rules for name');
});

class DocumentatorPretendResource
{
    public static function make(): void
    {
        throw new RuntimeException('This class is not a resource.');
    }
}

class DocumentatorPretendController
{
    public function index()
    {
        return DocumentatorPretendResource::make();
    }
}

it('does not infer resources from a class name suffix', function () {
    expect(app(SourceParser::class)->resourceClass(new ReflectionMethod(DocumentatorPretendController::class, 'index')))->toBeNull();
});

class DocumentatorConditionalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if ($request->boolean('numeric')) {
            return ['value' => 1, 'count' => 1];
        }

        return ['value' => 'one', 'name' => 'First'];
    }
}

it('retains resource fields and type alternatives from literal return branches', function () {
    $properties = app(SourceParser::class)->properties(new ReflectionMethod(DocumentatorConditionalResource::class, 'toArray'));

    expect($properties)->toHaveKeys(['value', 'count', 'name'])
        ->and($properties['value'])->toBe(['anyOf' => [['type' => 'integer'], ['type' => 'string']]]);

    $schema = ['type' => 'object', 'properties' => $properties];
    assertOpenApiExample($schema, app(ExampleGenerator::class)->generate($schema));
});
