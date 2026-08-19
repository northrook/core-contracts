<?php

declare(strict_types=1);

namespace Northrook\Container;

use Northrook\ParameterReference;

use const Northrook\Secret\SENSITIVE;

/**
 * Marks a property or parameter as secret-aware.
 *
 * Holds a {@see \Northrook\Parameter\Secret} tier plus optional tag seeds
 * in `$conditions` (merged into {@see Parameter} / {@see ParameterReference} tags,
 * or passed as redaction context). Does not wrap a payload.
 *
 * - Default {@see \Northrook\Parameter\Secret::SENSITIVE} — dump / debug hygiene (≈ {@see \SensitiveParameter});
 *   may still serialize (including on the public wire).
 * - {@see \Northrook\Parameter\Secret::CREDENTIAL} — trusted persist OK outside
 *   {@see \Northrook\Kernel\KernelContext::Request}; refused on the outbound wire (Serializer throws by default).
 *
 * @see \Northrook\Parameter\Secret
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final readonly class Secret
{
    public \Northrook\Parameter\Secret $secret;

    /** @var list<non-empty-string> */
    public array $conditions;

    /**
     * @param 'sensitive'|'credential'|\Northrook\Parameter\Secret  $type
     * @param non-empty-string                                      ...$conditions  tag seeds
     */
    public function __construct(
        string|\Northrook\Parameter\Secret $type = SENSITIVE,
        string ...                         $conditions,
    ) {
        $this->secret     = \Northrook\Parameter\Secret::from($type);
        $this->conditions = \array_values($conditions);
    }

    /**
     * Debug-out / non-authoritative redaction via the tier enum + tag seeds.
     *
     * @return non-empty-string
     */
    public function __invoke(
        mixed $value,
    ): string {
        return ( $this->secret )($value, $this->conditions);
    }
}
