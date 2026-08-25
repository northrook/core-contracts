<?php

declare(strict_types=1);

namespace Northrook\Container\Service;

use Northrook\Container\AutodiscoverInterface;
use Northrook\InvalidArgumentException;
use Northrook\Runtime\Assert;

/**
 * Unique label or named-binding key on a service, with optional constructor args.
 *
 * Must be unique to the `service`. Freeform tags (`role.logger`, …) are discovery
 * labels today; that role does not exempt {@see $arguments}. The reserved
 * {@see \Northrook\ContainerInterface::DEFAULT_REFERENCE} key carries
 * primary constructor/factory overrides (prefer
 * {@see \Northrook\Container\ServiceDefinition::setArguments()}).
 *
 * Every tag’s {@see $arguments} (freeform and reserved) must be compatible with
 * the service constructor or static factory. Checked at
 * {@see \Northrook\Container\CompilerPass::VALIDATE}: named keys may
 * omit parameters; positional keys must align with the signature.
 *
 * Intended end state: `Tag.reference` aligns with
 * {@see \Northrook\ContainerInterface::get()} `$reference` for named bindings.
 *
 * @phpstan-type TagFrom string|array{ 0: string, ...}
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final readonly class Tag implements AutodiscoverInterface
{
    /**
     * Unique tag / binding key for this service.
     *
     * @var non-empty-string
     */
    public string $reference;

    /**
     * Optional constructor / factory arguments for this tag.
     *
     * Must be compatible with the service constructor or static factory
     * signature (validated at {@see \Northrook\Container\CompilerPass::VALIDATE}).
     * Named keys may omit parameters; positional keys must align.
     *
     * @var null|array<array-key, mixed>
     */
    public null|array $arguments;

    /**
     * @param non-empty-string $reference must be unique to the `service`
     * @param mixed ...$arguments must match the service constructor / factory signature
     */
    public function __construct(
        string   $reference,
        mixed ...$arguments,
    ) {
        Assert::validKey(
            value : $reference,
            source: Tag::class,
        );

        $this->reference = $reference;
        $this->arguments = empty($arguments) ? null : $arguments;
    }

    /**
     * @param TagFrom $value
     * @param mixed ...$arguments
     *
     *
     * @return \Northrook\Container\Service\Tag
     */
    public static function from(
        string|array $value,
        mixed ...    $arguments,
    ): Tag {
        if (empty($value)) {
            throw new InvalidArgumentException(
                message: __METHOD__ . ' requires a non-empty string or array',
                context: [
                    'expected'  => 'non-empty string or array',
                    'received'  => $value,
                    'arguments' => $arguments,
                ],
            );
        }

        if (\is_array($value)) {
            $reference = \array_shift($value);
            $arguments = \array_merge($arguments, $value);
        }
        else {
            $reference = $value;
        }
        return new Tag($reference, ...$arguments);
    }
}
