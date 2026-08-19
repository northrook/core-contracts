<?php

declare(strict_types=1);

namespace Northrook;

use Northrook\Parameter\Secret;

class Redactor
{
    /**
     * @var \Northrook\Parameter\Secret
     */
    final protected private(set) Secret $secret;

    /**
     * @var array<non-empty-string, non-empty-string>
     */
    final protected private(set) array $context;

    /**
     * @param mixed                                      $value
     * @param \Northrook\Parameter\Secret                $secret
     * @param array<non-empty-string, non-empty-string>  $context
     *
     * @return mixed
     */
    final public function __invoke(
        mixed  $value,
        Secret $secret,
        array  $context,
    ): mixed {
        $this->secret  = $secret;
        $this->context = $context;
        return $this->redact($value);
    }

    final protected function hasContext(
        string $value,
    ): bool {
        return \array_key_exists(
            \strtolower($value),
            $this->context,
        );
    }

    protected function redact(
        mixed $value,
    ): mixed {
        return ( $this->secret )(
            $value,
            $this->context,
        );
    }
}
