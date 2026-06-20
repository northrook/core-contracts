<?php

declare(strict_types = 1);

namespace Northrook\Contracts\Attributes;

use Attribute;

/**
 * Indicates the object, entity, or value is secret.
 *
 * This will trigger obfuscation/omission from non-authoritative outputs.
 */
#[Attribute]
final class Secret {}
