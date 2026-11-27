<?php

declare(strict_types=1);

namespace Nebrix\Provisioning;

use Nebrix\Security\ValidationException;
use Nebrix\Security\Validator;

/**
 * A remote action a provisioner offers for a service (power actions,
 * password reset, reinstall, …). Fields describe user input; values are
 * validated server-side against the rules and, for select fields, against
 * the offered options (whitelist).
 */
final class ServiceAction
{
    /** Executed immediately within the request. */
    public const SYNC = 'sync';

    /** Re-creates the remote resource through the provisioning queue. */
    public const REINSTALL = 'reinstall';

    /**
     * Single sign-on: perform() returns an absolute https URL (e.g. a
     * one-time control panel login) the user is redirected to. The runner
     * passes the client IP as input "_client_ip".
     */
    public const REDIRECT = 'redirect';

    /**
     * @param array<string, array{label: string, rules: string, type?: string, options?: array<string, string>, help?: string}> $fields
     */
    public function __construct(
        public readonly string $label,
        public readonly array $fields = [],
        public readonly ?string $confirm = null,
        public readonly string $mode = self::SYNC,
        public readonly bool $danger = false,
        /** Confirmation for queued actions (mode REINSTALL); a generic reinstall message otherwise. */
        public readonly ?string $queuedMessage = null,
    ) {
    }

    public static function simple(string $label, ?string $confirm = null, bool $danger = false): self
    {
        return new self($label, [], $confirm, self::SYNC, $danger);
    }

    /**
     * @param array<string, mixed> $input raw request data
     * @return array<string, mixed> validated values of the declared fields only
     *
     * @throws ValidationException
     */
    public function validate(array $input): array
    {
        $rules = [];
        foreach ($this->fields as $name => $field) {
            $rules[$name] = $field['rules'];
        }
        $values = $rules === [] ? [] : Validator::validate($input, $rules);

        foreach ($this->fields as $name => $field) {
            $value = $values[$name] ?? null;
            if (isset($field['options']) && $value !== null && $value !== '' && !array_key_exists((string) $value, $field['options'])) {
                throw ValidationException::withMessages([$name => 'Choose one of the offered options.']);
            }
        }

        return $values;
    }
}
