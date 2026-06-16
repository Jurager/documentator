<?php

namespace Jurager\Documentator\Support;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use Throwable;

/**
 * Разбор тел методов ресурса через AST (php-parser) вместо регэкспов по исходнику.
 */
class ResourceAstReader
{
    /** @var array<string, list<Node>|null> file → AST (кэш на процесс) */
    private array $cache = [];

    private NodeFinder $finder;

    public function __construct()
    {
        $this->finder = new NodeFinder();
    }

    /**
     * Строковые ключи всех массивов-литералов в методе (атрибуты ресурса).
     *
     * @return string[]
     */
    public function arrayKeys(string $file, string $methodName): array
    {
        $method = $this->method($file, $methodName);

        if ($method === null) {
            return [];
        }

        $keys = [];

        foreach ($this->finder->findInstanceOf($method, Node\Expr\Array_::class) as $array) {
            foreach ($array->items as $item) {
                if ($item?->key instanceof String_) {
                    $keys[] = $item->key->value;
                }
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * name → { resource: короткое имя, collection: bool } для значений вида XxxResource::method|::class.
     *
     * @return array<string, array{resource: string, collection: bool}>
     */
    public function relationLinks(string $file, string $methodName): array
    {
        $method = $this->method($file, $methodName);

        if ($method === null) {
            return [];
        }

        $links = [];

        foreach ($this->finder->findInstanceOf($method, Node\Expr\Array_::class) as $array) {
            foreach ($array->items as $item) {
                if (! $item?->key instanceof String_) {
                    continue;
                }

                if ($call = $this->resourceCall($item->value)) {
                    $links[$item->key->value] = $call;
                }
            }
        }

        return $links;
    }

    /**
     * @return array{resource: string, collection: bool}|null
     */
    private function resourceCall(Node $value): ?array
    {
        // fn () => XxxResource::collection(...)
        if ($value instanceof ArrowFunction) {
            return $this->resourceCall($value->expr);
        }

        // function () { return XxxResource::collection(...); }
        if ($value instanceof Closure) {
            foreach ($this->finder->findInstanceOf($value, Return_::class) as $return) {
                if ($return->expr && $call = $this->resourceCall($return->expr)) {
                    return $call;
                }
            }

            return null;
        }

        // XxxResource::make(...) / ::collection(...)
        if ($value instanceof StaticCall && $value->class instanceof Node\Name && $value->name instanceof Node\Identifier) {
            return $this->link($value->class->getLast(), $value->name->toString());
        }

        // XxxResource::class
        if ($value instanceof ClassConstFetch && $value->class instanceof Node\Name && $value->name instanceof Node\Identifier) {
            return $this->link($value->class->getLast(), $value->name->toString());
        }

        return null;
    }

    /**
     * @return array{resource: string, collection: bool}|null
     */
    private function link(string $class, string $method): ?array
    {
        if (! str_ends_with($class, 'Resource')) {
            return null;
        }

        return ['resource' => $class, 'collection' => $method === 'collection'];
    }

    private function method(string $file, string $methodName): ?ClassMethod
    {
        $ast = $this->ast($file);

        if ($ast === null) {
            return null;
        }

        foreach ($this->finder->findInstanceOf($ast, ClassMethod::class) as $method) {
            if ($method->name->toString() === $methodName) {
                return $method;
            }
        }

        return null;
    }

    /**
     * @return list<Node>|null
     */
    private function ast(string $file): ?array
    {
        if (array_key_exists($file, $this->cache)) {
            return $this->cache[$file];
        }

        try {
            $parser = (new ParserFactory())->createForHostVersion();

            return $this->cache[$file] = $parser->parse((string) file_get_contents($file));
        } catch (Throwable) {
            return $this->cache[$file] = null;
        }
    }
}
