<?php

declare(strict_types=1);

namespace Nebrix\Http;

final class Route
{
    /** Matches {name} or {name:pattern}; the pattern may contain one level of braces, e.g. [a-f0-9]{64}. */
    private const PARAMETER_PATTERN = '/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::((?:[^{}]+|\{[^{}]*\})+))?\}/';

    private readonly string $regex;

    /** @var list<string> */
    private array $parameterNames = [];

    /**
     * @param list<string> $methods
     * @param array{0: class-string, 1: string} $handler
     * @param list<string> $middleware
     */
    public function __construct(
        public readonly array $methods,
        public readonly string $path,
        public readonly array $handler,
        public readonly array $middleware,
        public readonly bool $csrf,
        public readonly bool $stateless = false,
    ) {
        $this->regex = $this->compile($path);
    }

    private function compile(string $path): string
    {
        $regex = preg_replace_callback(
            self::PARAMETER_PATTERN,
            function (array $match): string {
                $this->parameterNames[] = $match[1];
                $pattern = $match[2] ?? '[^/]+';

                return '(?P<' . $match[1] . '>' . $pattern . ')';
            },
            $path,
        );

        return '#^' . $regex . '$#';
    }

    /**
     * @return array<string, string>|null
     */
    public function match(string $path): ?array
    {
        if (preg_match($this->regex, $path, $matches) !== 1) {
            return null;
        }

        $params = [];
        foreach ($this->parameterNames as $name) {
            $params[$name] = rawurldecode($matches[$name]);
        }

        return $params;
    }

    /**
     * @param array<string, string|int> $params
     */
    public function build(array $params): string
    {
        return (string) preg_replace_callback(
            self::PARAMETER_PATTERN,
            static function (array $match) use ($params): string {
                if (!array_key_exists($match[1], $params)) {
                    throw new \InvalidArgumentException(sprintf('Missing route parameter "%s".', $match[1]));
                }

                return rawurlencode((string) $params[$match[1]]);
            },
            $this->path,
        );
    }
}
