<?php

declare(strict_types=1);

namespace Nebrix\Security;

use RuntimeException;

final class ValidationException extends RuntimeException
{
    /**
     * @param array<string, list<string>> $errors
     * @param array<string, mixed> $input input to re-populate the form with (never passwords)
     */
    public function __construct(
        private readonly array $errors,
        private readonly array $input = [],
    ) {
        parent::__construct('The given data was invalid.');
    }

    /**
     * @param array<string, string> $messages field => message
     */
    public static function withMessages(array $messages): self
    {
        return new self(array_map(static fn (string $message): array => [$message], $messages));
    }

    /**
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<string, mixed>
     */
    public function input(): array
    {
        return $this->input;
    }

    public function first(): string
    {
        foreach ($this->errors as $messages) {
            if ($messages !== []) {
                return $messages[0];
            }
        }

        return $this->getMessage();
    }
}
