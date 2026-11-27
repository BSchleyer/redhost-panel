<?php

declare(strict_types=1);

namespace Nebrix\Http\Middleware;

interface ParameterizedMiddleware extends Middleware
{
    /**
     * @param list<string> $parameters values after the colon in "alias:a,b"
     */
    public function withParameters(array $parameters): static;
}
