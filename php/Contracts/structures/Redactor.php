<?php

declare(strict_types=1);

namespace Northrook\Contracts;

/**
 * Dump / non-authoritative secret placeholder.
 *
 * Extend and override {@see redact()} for app-specific branches;
 * call {@see parent::redact()} for the built-in labels.
 *
 * @disallows __clone(), __toString()
 *
 * @phpstan-import-type Type from Secret
 */
class Redactor
{
    /**
     * @param mixed        $value
     * @param Type         $type
     * @param null|string  $condition
     *
     * @return string
     */
    final public function __invoke(
        mixed       $value,
        string      $type,
        null|string $condition = null,
    ): string {
        return $this->redact($value, $type, $condition);
    }

    /**
     * Built-in dump placeholder.
     *
     * - {@see Secret::CONDITION_SENSITIVE_PARAMETER} → length-matched `*` for strings
     * - {@see Secret::CREDENTIAL} → `[Credential::{gettype}]`
     * - {@see Secret::SENSITIVE} → `[Secret::{gettype}]`
     *
     * @param mixed        $value
     * @param Type         $type
     * @param null|string  $condition
     *
     * @return string
     */
    protected function redact(
        mixed       $value,
        string      $type,
        null|string $condition = null,
    ): string {
        if ($condition === Secret::CONDITION_SENSITIVE_PARAMETER) {
            return \str_repeat('*', \is_string($value) ? \strlen($value) : 0);
        }

        $label = $type === Secret::CREDENTIAL ? 'Credential' : 'Secret';

        return "[{$label}::" . \gettype($value) . ']';
    }
}
