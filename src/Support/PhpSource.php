<?php

namespace Jurager\Documentator\Support;

use Generator;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use ReflectionMethod;
use RuntimeException;

class PhpSource
{
    protected array $files = [];

    protected Parser $parser;

    protected NodeFinder $finder;

    public function __construct()
    {
        $this->parser = (new ParserFactory)->createForHostVersion();
        $this->finder = new NodeFinder;
    }

    public function reset(): void
    {
        $this->files = [];
    }

    public function method(ReflectionMethod $reflection): ?ClassMethod
    {
        $nodes = $this->parse((string) $reflection->getFileName());

        foreach ($this->finder->findInstanceOf($nodes, Class_::class) as $class) {
            if (isset($class->namespacedName) && $class->namespacedName->toString() === $reflection->getDeclaringClass()->getName()) {
                if ($method = $class->getMethod($reflection->getName())) {
                    return $method;
                }
            }
        }

        // Trait aliases retain the source line of the original method.
        foreach ($this->finder->findInstanceOf($nodes, ClassMethod::class) as $method) {
            if ($method->getStartLine() === $reflection->getStartLine()) {
                return $method;
            }
        }

        return null;
    }

    /** @return Generator<int, Node> */
    public function nodes(Node $scope, string $type): Generator
    {
        foreach ($scope->getSubNodeNames() as $name) {
            yield from $this->matchingNodes($scope->$name, $type);
        }
    }

    protected function matchingNodes(mixed $node, string $type): Generator
    {
        if (is_array($node)) {
            foreach ($node as $child) {
                yield from $this->matchingNodes($child, $type);
            }

            return;
        }

        if (! $node instanceof Node || $node instanceof Node\FunctionLike || $node instanceof Node\Stmt\ClassLike) {
            return;
        }

        if ($node instanceof $type) {
            yield $node;
        }

        foreach ($node->getSubNodeNames() as $name) {
            yield from $this->matchingNodes($node->$name, $type);
        }
    }

    protected function parse(string $file): array
    {
        if (array_key_exists($file, $this->files)) {
            return $this->files[$file];
        }

        // Cache failed reads too, so one source failure produces one diagnostic per run.
        $this->files[$file] = [];
        if (! is_readable($file)) {
            throw new RuntimeException("Source file is not readable: {$file}");
        }

        $nodes = $this->parser->parse(file_get_contents($file)) ?? [];

        return $this->files[$file] = (new NodeTraverser(new NameResolver))->traverse($nodes);
    }
}
