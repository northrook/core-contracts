<?php

declare(strict_types=1);

namespace Northrook\Container\Service;

use Northrook\Container\AutodiscoverInterface;
use Northrook\Contracts\Exportable;
use Northrook\Export;
use Northrook\Runtime\Assert;
use Northrook\Serializer;

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
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final readonly class Tag implements AutodiscoverInterface, Exportable
{
    use Serializer;

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
     * @var array<array-key, mixed>
     */
    public array $arguments;

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
        $this->arguments = $arguments;
    }

    public function _export(): string
    {
        $this->guardExport();

        return Export::class(
            Tag::class,
            $this->reference,
            ...$this->arguments,
        );
    }
}
