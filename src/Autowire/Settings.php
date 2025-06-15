<?php

namespace Core\Contracts\Autowire;

use Core\Contracts\Container\{Autowire};
use Core\Contracts\SettingsInterface;

trait Settings
{
    protected readonly SettingsInterface $settings;

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
    final public function assignSettings( SettingsInterface $settings ) : void
    {
        $this->settings = $settings;
    }
}
