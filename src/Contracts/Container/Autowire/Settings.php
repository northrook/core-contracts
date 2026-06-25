<?php

declare(strict_types=1);

namespace Northrook\Contracts\Container\Autowire;

use Northrook\Contracts\Container\Autowire;
use Northrook\Contracts\Interfaces\SettingsInterface;

/**
 * Autowires the container {@see SettingsInterface} into {@see static::$settings}.
 */
trait Settings
{
    protected SettingsInterface $settings;

    /**
     * @internal autowired by the {@see ContainerInterface}
     *
     * @param SettingsInterface $settings
     *
     * @return void
     *
     * @final
     */
    #[Autowire]
    final public function assignSettings(SettingsInterface $settings): void
    {
        $this->settings = $settings;
    }
}
