<?php

declare(strict_types=1);

namespace Nebrix\Core;

use Closure;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

/**
 * Minimal service container with explicit factories and constructor autowiring.
 *
 * Every service is shared (one instance per container). Scalars and interfaces
 * must be registered explicitly; concrete classes are autowired by their
 * constructor's class-typed parameters.
 */
final class Container
{
    /** @var array<string, Closure(Container): mixed> */
    private array $factories = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /** @var array<string, true> */
    private array $resolving = [];

    /**
     * @param Closure(Container): mixed $factory
     */
    public function set(string $id, Closure $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    public function instance(string $id, mixed $value): void
    {
        $this->instances[$id] = $value;
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->factories[$id]) || class_exists($id);
    }

    /**
     * @template T of object
     * @param class-string<T>|string $id
     * @return ($id is class-string<T> ? T : mixed)
     */
    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (isset($this->resolving[$id])) {
            throw new RuntimeException(sprintf('Circular dependency while resolving "%s".', $id));
        }

        $this->resolving[$id] = true;
        try {
            $value = isset($this->factories[$id])
                ? ($this->factories[$id])($this)
                : $this->autowire($id);
        } finally {
            unset($this->resolving[$id]);
        }

        $this->instances[$id] = $value;

        return $value;
    }

    private function autowire(string $class): object
    {
        if (!class_exists($class)) {
            throw new RuntimeException(sprintf('Service "%s" is not registered and is not a class.', $class));
        }

        $reflection = new ReflectionClass($class);
        if (!$reflection->isInstantiable()) {
            throw new RuntimeException(sprintf('Service "%s" is not instantiable; register a factory.', $class));
        }

        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $arguments = [];
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $dependency = $type->getName();
                if ($this->has($dependency)) {
                    $arguments[] = $this->get($dependency);
                    continue;
                }
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            if ($type !== null && $type->allowsNull()) {
                $arguments[] = null;
                continue;
            }

            throw new RuntimeException(sprintf(
                'Cannot autowire parameter $%s of %s::__construct().',
                $parameter->getName(),
                $class,
            ));
        }

        return $reflection->newInstanceArgs($arguments);
    }
}
