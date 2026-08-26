<?php

declare(strict_types=1);

namespace Northrook\Container;

use Northrook\DependencyException;
use Northrook\Parameter;
use Northrook\ParameterMapInterface;
use Northrook\PathfinderInterface;

/**
 * Marks a parameter or property for container injection.
 *
 * Exactly one handler must be provided:
 * - {@see $reference} — container lookup key:
 *   - `class-string` — service id / alias (traits use the softest contract
 *     interface, e.g. {@see \Psr\Log\LoggerInterface}, {@see PathfinderInterface})
 *   - dotted parameter key — {@see Parameter} from {@see ParameterMapInterface} (`app.token`, …)
 * - {@see $resolve} — callable invoked on injection
 *
 * Attribute arguments must be constant expressions: use a string callable or
 * `[ClassName::class, 'method']` for {@see $resolve}. Closures are fine when
 * constructing this attribute programmatically.
 *
 * Return-type compatibility against the annotated slot is validated during
 * container compilation, not at construction.
 *
 * @phpstan-type HandlerType 'reference'|'resolve'
 * @phpstan-type ReferenceKey non-empty-string
 * @phpstan-type ResolveHandler callable|array{class-string, non-empty-string}|non-empty-string
 */
#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::TARGET_PROPERTY)]
final readonly class Autowire
{
    public const string METHOD_PREFIX = '_autowire_';

    /** @var HandlerType */
    public string $type;

    /** @var ReferenceKey|ResolveHandler */
    public mixed $handler;

    /**
     * @param null|ReferenceKey     $reference  Service/alias FQCN or parameter map key
     * @param null|ResolveHandler   $resolve    Invoked on injection; callability
     *                                          checked at compile time
     */
    public function __construct(
        null|string $reference = null,
        mixed       $resolve = null,
    ) {
        [$this->type, $this->handler] = $this->pickHandler(\get_defined_vars());
    }

    /**
     * @param array{reference: null|ReferenceKey, resolve: null|ResolveHandler}  $arguments
     *
     * @return array{HandlerType, ReferenceKey|ResolveHandler}
     */
    private function pickHandler(
        array $arguments,
    ): array {
        $handlers = \array_filter(
            $arguments,
            static fn($value): bool => $value !== null,
        );

        if (\count($handlers) !== 1) {
            throw new DependencyException(
                message: empty($handlers)
                    ? 'No autowire handler provided; expected reference or resolve.'
                    : 'Invalid autowire handler; provide exactly one of reference or resolve.',
            );
        }

        $type    = \array_key_first($handlers);
        $handler = $handlers[$type];

        return [$type, $handler];
    }
}
