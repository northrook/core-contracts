<?php

declare(strict_types=1);

namespace Northrook\Context;

// TODO : [l] Move to `northrook/php-dev`

/**
 * Context-aware attribute to mark a class or method as debug-only.
 *
 * The {@see \Northrook\Container\CompilerInterface} should throw if this attribute is present {@see \Northrook\Context\AppEnv::Production}.
 */
#[\Attribute]
final class DebugOnly {}
