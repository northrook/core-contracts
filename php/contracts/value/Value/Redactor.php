<?php

declare(strict_types=1);

namespace Northrook\Contracts\Value;

use Northrook\AppEnv;

class Redactor
{
    protected private(set) Secret $secret;

    final public function __invoke(
        mixed  $value,
        Secret $secret,
    ): mixed {
        $this->secret = $secret;
        return $this->redact($value);
    }

    protected function redact(
        mixed $value,
    ): mixed {
        $mask = null;

        if (
            $this->secret->hasCondition(
                \SensitiveParameter::class,
            )
            && \is_string($value)
        ) {
            $mask = \SensitiveParameter::class;
        }

        $mask ??= AppEnv::isDebug()
            ? \debug_value_type($value)
            : \gettype($value);

        return "[{$this->secret->type}::{$mask}]";
    }
}
