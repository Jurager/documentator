<?php

namespace Jurager\Documentator\Parsers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Jurager\Documentator\OpenApi\TypeResolver;
use Jurager\Documentator\Support\PhpSource;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;
use Throwable;

class SourceParser
{
    protected array $warnings = [];

    public function __construct(protected PhpSource $source, protected TypeResolver $types) {}

    public function reset(): void
    {
        $this->source->reset();
        $this->warnings = [];
    }

    public function report(string $source, string $reason): void
    {
        $message = "{$source}: {$reason}";
        $this->warnings[$message] = $message;
    }

    public function warnings(): array
    {
        return array_values($this->warnings);
    }

    public function properties(ReflectionMethod $reflection, ?Model $model = null): array
    {
        $method = $this->method($reflection);
        if ($method === null) {
            return [];
        }

        $properties = [];
        foreach ($this->returnedArrays($method) as $array) {
            foreach ($this->arraySchema($array, $model)['properties'] ?? [] as $name => $schema) {
                if (isset($properties[$name]) && $properties[$name] !== $schema) {
                    $variants = $properties[$name]['anyOf'] ?? [$properties[$name]];
                    $properties[$name] = ['anyOf' => array_values(array_unique([...$variants, $schema], SORT_REGULAR))];
                } else {
                    $properties[$name] = $schema;
                }
            }
        }

        return $properties;
    }

    protected function returnedArrays(ClassMethod $method): array
    {
        $arrays = [];

        foreach ($this->source->nodes($method, Return_::class) as $return) {
            if ($return->expr instanceof Array_) {
                $arrays[] = $return->expr;
            }
        }

        return $arrays;
    }

    protected function arraySchema(Array_ $array, ?Model $model = null): array
    {
        $properties = [];
        $items = [];
        foreach ($array->items as $item) {
            if ($item?->key instanceof String_) {
                $properties[$item->key->value] = $this->expressionSchema($item->value, $item->key->value, $model);
            } elseif ($item !== null) {
                $items[] = $this->expressionSchema($item->value, '', $model);
            }
        }

        if ($properties !== []) {
            return ['type' => 'object', 'properties' => $properties];
        }

        $items = array_values(array_unique($items, SORT_REGULAR));

        return ['type' => 'array', 'items' => count($items) > 1 ? ['oneOf' => $items] : ($items[0] ?? (object) [])];
    }

    protected function expressionSchema(Node $value, string $name, ?Model $model): array
    {
        if ($value instanceof Array_) {
            return $this->arraySchema($value, $model);
        }

        $type = match (true) {
            $value instanceof Node\Scalar\Int_, $value instanceof Node\Expr\Cast\Int_ => 'integer',
            $value instanceof Node\Scalar\Float_, $value instanceof Node\Expr\Cast\Double => 'number',
            $value instanceof String_, $value instanceof Node\Expr\Cast\String_ => 'string',
            $value instanceof Node\Expr\Cast\Bool_ => 'boolean',
            $value instanceof Node\Expr\Cast\Array_ => 'array',
            $value instanceof Node\Expr\Cast\Object_ => 'object',
            default => null,
        };

        if ($type !== null) {
            return $this->types->schema($type);
        }

        if ($value instanceof Node\Expr\ConstFetch) {
            return match (strtolower($value->name->toString())) {
                'true', 'false' => ['type' => 'boolean'],
                'null' => ['type' => $this->types->fromFieldName($name), 'nullable' => true],
                default => $this->types->schema($this->types->fromFieldName($name)),
            };
        }

        if ($value instanceof Node\Expr\PropertyFetch && $value->name instanceof Node\Identifier && $value->var instanceof Node\Expr\Variable && $value->var->name === 'this' && $model !== null) {
            $field = $value->name->toString();
            if ($cast = $model->getCasts()[$field] ?? null) {
                return $this->types->fromCast($cast);
            }
        }

        return $this->types->schema($this->types->fromFieldName($name));
    }

    /** @return list<string> */
    public function arrayKeys(ReflectionMethod $reflection): array
    {
        $method = $this->method($reflection);

        if ($method === null) {
            return [];
        }

        $keys = [];

        foreach ($this->returnedArrays($method) as $array) {
            foreach ($array->items as $item) {
                if ($item?->key instanceof String_) {
                    $keys[] = $item->key->value;
                }
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * Relationship names mapped to resolved resource classes and collection flags.
     *
     * @return array<string, array{resource: string, collection: bool}>
     */
    public function relationLinks(ReflectionMethod $reflection): array
    {
        $method = $this->method($reflection);

        if ($method === null) {
            return [];
        }

        $links = [];

        foreach ($this->returnedArrays($method) as $array) {
            foreach ($array->items as $item) {
                if (! $item?->key instanceof String_) {
                    continue;
                }

                if ($call = $this->resourceCall($item->value, $reflection)) {
                    $links[$item->key->value] = $call;
                }
            }
        }

        return $links;
    }

    /**
     * @return array{resource: string, collection: bool}|null
     */
    protected function resourceCall(Node $value, ReflectionMethod $reflection): ?array
    {
        if ($value instanceof MethodCall) {
            foreach ($value->getArgs() as $argument) {
                if ($call = $this->resourceCall($argument->value, $reflection)) {
                    return $call;
                }
            }

            return null;
        }

        if ($value instanceof New_ && $value->class instanceof Node\Name) {
            return $this->link($this->className($value->class, $reflection), 'make');
        }
        // fn () => XxxResource::collection(...)
        if ($value instanceof ArrowFunction) {
            return $this->resourceCall($value->expr, $reflection);
        }

        // function () { return XxxResource::collection(...); }
        if ($value instanceof Closure) {
            foreach ($this->source->nodes($value, Return_::class) as $return) {
                if ($return->expr && $call = $this->resourceCall($return->expr, $reflection)) {
                    return $call;
                }
            }

            return null;
        }

        // XxxResource::make(...) / ::collection(...)
        if ($value instanceof StaticCall && $value->class instanceof Node\Name && $value->name instanceof Node\Identifier) {
            return $this->link($this->className($value->class, $reflection), $value->name->toString());
        }

        // XxxResource::class
        if ($value instanceof ClassConstFetch && $value->class instanceof Node\Name && $value->name instanceof Node\Identifier) {
            return $this->link($this->className($value->class, $reflection), $value->name->toString());
        }

        return null;
    }

    /**
     * @return array{resource: string, collection: bool}|null
     */
    protected function link(string $class, string $method): ?array
    {
        if (! is_subclass_of($class, JsonResource::class) || is_a($class, ResourceCollection::class, true) || ! in_array(strtolower($method), ['make', 'collection', 'class'], true)) {
            return null;
        }

        return ['resource' => $class, 'collection' => strtolower($method) === 'collection'];
    }

    public function resourceClass(ReflectionMethod $reflection): ?string
    {
        $method = $this->method($reflection);
        if ($method === null) {
            return null;
        }

        foreach ($this->source->nodes($method, Return_::class) as $return) {
            if ($return->expr !== null && $resource = $this->resourceCall($return->expr, $reflection)) {
                return $resource['resource'];
            }
        }

        return null;
    }

    public function validationRules(ReflectionMethod $reflection): array
    {
        $method = $this->method($reflection);
        if ($method === null) {
            return [];
        }

        $rules = [];
        $requests = $this->requestParameters($reflection);
        $source = $reflection->getDeclaringClass()->getName().'::'.$reflection->getName();

        foreach ($this->source->nodes($method, MethodCall::class) as $call) {
            if (! $call->name instanceof Node\Identifier || strtolower($call->name->toString()) !== 'validate' || ! $call->var instanceof Node\Expr\Variable) {
                continue;
            }

            $arguments = $call->getArgs();
            if (in_array($call->var->name, $requests, true)) {
                $array = $arguments[0]->value ?? null;
            } elseif ($call->var->name === 'this' && ($arguments[0]->value ?? null) instanceof Node\Expr\Variable && in_array($arguments[0]->value->name, $requests, true)) {
                $array = $arguments[1]->value ?? null;
            } else {
                continue;
            }

            if (! $array instanceof Array_) {
                $this->report($source, 'Inline validation is dynamic; add @bodyParam for missing fields.');

                continue;
            }

            $rules = array_replace($rules, $this->literalRules($array, $source));
        }

        return $rules;
    }

    protected function literalRules(Array_ $array, string $source): array
    {
        $rules = [];

        foreach ($array->items as $item) {
            if (! $item?->key instanceof String_) {
                continue;
            }

            $field = $item->key->value;
            if ($item->value instanceof String_) {
                $rules[$field] = $item->value->value;

                continue;
            }

            if (! $item->value instanceof Array_) {
                $this->report($source, "Cannot statically read rules for {$field}.");

                continue;
            }

            $rules[$field] = [];
            foreach ($item->value->items as $token) {
                if ($token?->value instanceof String_) {
                    $rules[$field][] = $token->value->value;
                } else {
                    $this->report($source, "Cannot statically read a rule for {$field}.");
                }
            }
        }

        return $rules;
    }

    protected function className(Node\Name $name, ReflectionMethod $method): string
    {
        return match (strtolower($name->toString())) {
            'self', 'static' => $method->getDeclaringClass()->getName(),
            'parent' => $method->getDeclaringClass()->getParentClass()?->getName() ?? '',
            default => $name->toString(),
        };
    }

    protected function requestParameters(ReflectionMethod $method): array
    {
        $parameters = [];

        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();
            $types = $type instanceof ReflectionUnionType ? $type->getTypes() : [$type];
            foreach ($types as $type) {
                if ($type instanceof ReflectionNamedType && is_a($type->getName(), Request::class, true)) {
                    $parameters[] = $parameter->getName();
                    break;
                }
            }
        }

        return $parameters;
    }

    protected function method(ReflectionMethod $reflection): ?ClassMethod
    {
        try {
            return $this->source->method($reflection);
        } catch (Throwable $exception) {
            $this->report($reflection->getDeclaringClass()->getName().'::'.$reflection->getName(), $exception->getMessage());

            return null;
        }
    }
}
