<?php

declare(strict_types=1);

namespace Northrook\Contracts;

/**
 * Secret-aware payload base.
 *
 * @property-read mixed             $value
 * @property-read null|Value\Secret $secret
 */
class Value implements Serializable
{
    use Serializer {
        resolvePropertySecret as private resolveAttributedSecret;
    }

    protected(set) mixed $value;

    protected(set) null|Value\Secret $secret;

    /**
     * @param mixed                             $value
     * @param null|string|Value\Secret          $secret
     */
    public function __construct(
        mixed                        $value = null,
        null|string|Value\Secret $secret = null,
    ) {
        $this->value  = $value;
        $this->secret = Value\Secret::from($secret);
    }

    /**
     * @param null|'sensitive'|'credential'  $type
     *
     * @return bool
     */
    final public function isSecret(
        null|string $type = null,
    ): bool {
        return $type === null
            ? $this->secret !== null
            : $this->secret?->type === $type;
    }

    /**
     * Guards the {@see $value} payload with this instance's own policy.
     */
    protected function resolvePropertySecret(
        string              $name,
        mixed               $value,
        \ReflectionProperty $property,
    ): null|Value\Secret {
        if ( $name === 'value' ) {
            return $this->secret;
        }

        return $this->resolveAttributedSecret( $name, $value, $property );
    }
}
