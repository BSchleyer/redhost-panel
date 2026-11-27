<?php

declare(strict_types=1);

namespace Nebrix\Security;

/**
 * Rule-based input validation.
 *
 * Only fields that have rules are returned, so controllers never pass raw
 * request arrays to persistence (mass-assignment protection). Values are
 * normalized to the declared type (int, bool, string, null).
 *
 * Supported rules: required, nullable, sometimes, string, int, numeric,
 * decimal, bool, email, url, https_url, hostname, ip, color, in:a,b,
 * min:n, max:n, between:a,b, regex:/…/, alpha_dash, slug, confirmed,
 * same:field, different:field, date, array, json, country, currency, timezone.
 */
final class Validator
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, string|list<string>> $rules
     * @param array<string, string> $labels human-readable field names
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function validate(array $data, array $rules, array $labels = []): array
    {
        $errors = [];
        $validated = [];

        foreach ($rules as $field => $ruleSet) {
            $ruleList = is_array($ruleSet) ? $ruleSet : self::splitRules($ruleSet);
            $label = $labels[$field] ?? str_replace('_', ' ', $field);
            $present = array_key_exists($field, $data);
            $value = $data[$field] ?? null;

            if (is_string($value)) {
                $value = trim($value);
            }

            $isEmpty = $value === null || $value === '' || $value === [];
            $isBool = in_array('bool', $ruleList, true);

            if (in_array('sometimes', $ruleList, true) && !$present) {
                continue;
            }

            if ($isBool && !$present) {
                // Unchecked checkboxes are not submitted.
                $validated[$field] = false;
                continue;
            }

            if ($isEmpty) {
                if (in_array('required', $ruleList, true)) {
                    $errors[$field][] = sprintf('The %s field is required.', $label);
                } else {
                    $validated[$field] = $isBool ? false : null;
                }
                continue;
            }

            [$normalized, $fieldErrors] = self::applyRules($field, $label, $value, $ruleList, $data);
            if ($fieldErrors !== []) {
                $errors[$field] = $fieldErrors;
                continue;
            }

            $validated[$field] = $normalized;
        }

        if ($errors !== []) {
            throw new ValidationException($errors, self::safeInput($data));
        }

        return $validated;
    }

    /**
     * Strips secret fields before input is flashed back into forms.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function safeInput(array $data): array
    {
        $safe = [];
        foreach ($data as $key => $value) {
            if (preg_match('/pass|secret|token|key|code/i', (string) $key) === 1) {
                continue;
            }
            if (is_scalar($value)) {
                $safe[$key] = mb_substr((string) $value, 0, 5000);
            }
        }

        return $safe;
    }

    /**
     * @return list<string>
     */
    private static function splitRules(string $rules): array
    {
        $result = [];
        // "regex:" may contain pipes, so it must be the last rule.
        $regexPosition = strpos($rules, 'regex:');
        if ($regexPosition !== false) {
            $regex = substr($rules, $regexPosition);
            $rules = rtrim(substr($rules, 0, $regexPosition), '|');
        }
        if ($rules !== '') {
            $result = explode('|', $rules);
        }
        if (isset($regex)) {
            $result[] = $regex;
        }

        return $result;
    }

    /**
     * @param list<string> $rules
     * @param array<string, mixed> $data
     * @return array{0: mixed, 1: list<string>}
     */
    private static function applyRules(string $field, string $label, mixed $value, array $rules, array $data): array
    {
        // Lists/objects (e.g. "a[]=1" or JSON arrays) are only valid for "array" fields;
        // other rules would otherwise run string functions on them.
        if (is_array($value) && !in_array('array', $rules, true)) {
            return [$value, [sprintf('The %s is invalid.', $label)]];
        }

        $errors = [];
        $isNumericType = (bool) array_intersect($rules, ['int', 'numeric', 'decimal']);

        foreach ($rules as $rule) {
            [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);

            switch ($name) {
                case 'required':
                case 'nullable':
                case 'sometimes':
                    break;

                case 'string':
                    if (!is_string($value)) {
                        $errors[] = sprintf('The %s must be text.', $label);
                    }
                    break;

                case 'int':
                    if (is_int($value) || (is_string($value) && preg_match('/^-?\d{1,18}$/', $value) === 1)) {
                        $value = (int) $value;
                    } else {
                        $errors[] = sprintf('The %s must be a whole number.', $label);
                    }
                    break;

                case 'numeric':
                    if (is_numeric($value)) {
                        $value = $value + 0;
                    } else {
                        $errors[] = sprintf('The %s must be a number.', $label);
                    }
                    break;

                case 'decimal':
                    // Monetary input such as "12.50" or "12,50"; kept as normalized string.
                    $candidate = is_string($value) ? str_replace(',', '.', $value) : (string) $value;
                    if (preg_match('/^-?\d{1,12}(\.\d{1,2})?$/', $candidate) === 1) {
                        $value = $candidate;
                    } else {
                        $errors[] = sprintf('The %s must be an amount with up to two decimals.', $label);
                    }
                    break;

                case 'bool':
                    $bool = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
                    if ($bool === null) {
                        $errors[] = sprintf('The %s must be yes or no.', $label);
                    } else {
                        $value = $bool;
                    }
                    break;

                case 'email':
                    if (!is_string($value) || filter_var($value, FILTER_VALIDATE_EMAIL) === false || mb_strlen($value) > 254) {
                        $errors[] = sprintf('The %s must be a valid e-mail address.', $label);
                    } else {
                        $value = mb_strtolower($value);
                    }
                    break;

                case 'url':
                case 'https_url':
                    $scheme = is_string($value) ? strtolower((string) parse_url($value, PHP_URL_SCHEME)) : '';
                    $allowed = $name === 'https_url' ? ['https'] : ['http', 'https'];
                    if (!is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false || !in_array($scheme, $allowed, true)) {
                        $errors[] = sprintf('The %s must be a valid %s URL.', $label, $name === 'https_url' ? 'https://' : 'http(s)://');
                    }
                    break;

                case 'hostname':
                    if (!is_string($value) || (filter_var($value, FILTER_VALIDATE_IP) === false
                        && filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false)) {
                        $errors[] = sprintf('The %s must be a valid host name or IP address.', $label);
                    }
                    break;

                case 'ip':
                    if (!is_string($value) || filter_var($value, FILTER_VALIDATE_IP) === false) {
                        $errors[] = sprintf('The %s must be a valid IP address.', $label);
                    }
                    break;

                case 'color':
                    if (!is_string($value) || preg_match('/^#[0-9a-fA-F]{6}$/', $value) !== 1) {
                        $errors[] = sprintf('The %s must be a hex color such as #1a2b3c.', $label);
                    }
                    break;

                case 'in':
                    $options = explode(',', (string) $parameter);
                    if (!is_scalar($value) || !in_array((string) (is_bool($value) ? (int) $value : $value), $options, true)) {
                        $errors[] = sprintf('The selected %s is invalid.', $label);
                    }
                    break;

                case 'min':
                case 'max':
                    $limit = (float) $parameter;
                    $measured = self::measure($value, $isNumericType);
                    if ($measured === null) {
                        break;
                    }
                    if ($name === 'min' && $measured < $limit) {
                        $errors[] = $isNumericType
                            ? sprintf('The %s must be at least %s.', $label, $parameter)
                            : sprintf('The %s must be at least %s characters.', $label, $parameter);
                    }
                    if ($name === 'max' && $measured > $limit) {
                        $errors[] = $isNumericType
                            ? sprintf('The %s must not be greater than %s.', $label, $parameter)
                            : sprintf('The %s must not be longer than %s characters.', $label, $parameter);
                    }
                    break;

                case 'between':
                    [$low, $high] = array_map('floatval', explode(',', (string) $parameter, 2) + [1 => 0]);
                    $measured = self::measure($value, $isNumericType);
                    if ($measured !== null && ($measured < $low || $measured > $high)) {
                        $errors[] = sprintf('The %s must be between %s and %s.', $label, $low, $high);
                    }
                    break;

                case 'regex':
                    if (!is_string($value) || preg_match((string) $parameter, $value) !== 1) {
                        $errors[] = sprintf('The %s format is invalid.', $label);
                    }
                    break;

                case 'alpha_dash':
                    if (!is_string($value) || preg_match('/^[A-Za-z0-9_-]+$/', $value) !== 1) {
                        $errors[] = sprintf('The %s may only contain letters, numbers, dashes and underscores.', $label);
                    }
                    break;

                case 'slug':
                    if (!is_string($value) || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) !== 1) {
                        $errors[] = sprintf('The %s may only contain lowercase letters, numbers and dashes.', $label);
                    }
                    break;

                case 'confirmed':
                    $confirmation = $data[$field . '_confirmation'] ?? null;
                    if (!is_string($confirmation) || !is_string($value) || !hash_equals($value, $confirmation)) {
                        $errors[] = sprintf('The %s confirmation does not match.', $label);
                    }
                    break;

                case 'same':
                    if (($data[(string) $parameter] ?? null) !== $value) {
                        $errors[] = sprintf('The %s must match %s.', $label, $parameter);
                    }
                    break;

                case 'different':
                    if (($data[(string) $parameter] ?? null) === $value) {
                        $errors[] = sprintf('The %s must be different from %s.', $label, $parameter);
                    }
                    break;

                case 'date':
                    if (!is_string($value) || \DateTimeImmutable::createFromFormat('!Y-m-d', $value) === false) {
                        $errors[] = sprintf('The %s must be a date (YYYY-MM-DD).', $label);
                    }
                    break;

                case 'array':
                    if (!is_array($value)) {
                        $errors[] = sprintf('The %s must be a list.', $label);
                    }
                    break;

                case 'json':
                    if (!is_string($value) || !json_validate($value)) {
                        $errors[] = sprintf('The %s must be valid JSON.', $label);
                    }
                    break;

                case 'country':
                    if (!is_string($value) || preg_match('/^[A-Za-z]{2}$/', $value) !== 1) {
                        $errors[] = sprintf('The %s must be a two-letter country code.', $label);
                    } else {
                        $value = strtoupper($value);
                    }
                    break;

                case 'currency':
                    if (!is_string($value) || preg_match('/^[A-Za-z]{3}$/', $value) !== 1) {
                        $errors[] = sprintf('The %s must be a three-letter currency code.', $label);
                    } else {
                        $value = strtoupper($value);
                    }
                    break;

                case 'timezone':
                    if (!is_string($value) || !in_array($value, \DateTimeZone::listIdentifiers(), true)) {
                        $errors[] = sprintf('The %s must be a valid time zone.', $label);
                    }
                    break;

                default:
                    throw new \InvalidArgumentException(sprintf('Unknown validation rule "%s".', $name));
            }

            if ($errors !== []) {
                break;
            }
        }

        return [$value, $errors];
    }

    private static function measure(mixed $value, bool $numeric): ?float
    {
        if ($numeric) {
            return is_numeric($value) ? (float) $value : null;
        }
        if (is_string($value)) {
            return (float) mb_strlen($value);
        }
        if (is_array($value)) {
            return (float) count($value);
        }

        return null;
    }
}
