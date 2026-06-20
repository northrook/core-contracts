<?php

declare(strict_types = 1);

namespace Northrook\Contracts\Interfaces;

use Northrook\Contracts\Container\ParameterMapInterface;

/**
 * Read-only access to application settings as typed container parameters.
 */
interface SettingsInterface extends ParameterMapInterface {}
