<?php

declare(strict_types=1);

namespace Northrook;

use Northrook\Context\AppDebug;
use Northrook\Context\AppEnv;

final readonly class RuntimeOptions
{
    /**
     * @param \Northrook\Context\AppEnv    $appEnv
     * @param \Northrook\Context\AppDebug  $appDebug
     * @param non-empty-string             $rootDirectory
     * @param null|non-empty-string        $sourceDirectory
     * @param null|non-empty-string        $varDirectory
     */
    public function __construct(
        public AppEnv      $appEnv,
        public AppDebug    $appDebug,
        public string      $rootDirectory,
        public null|string $sourceDirectory = null,
        public null|string $varDirectory = null,
    ) {}

    /**
     * Only place a mixed options bag is accepted as spread array.
     *
     * @param null|string  $app_env
     * @param null|string  $app_debug
     * @param null|string  $root_dir
     * @param null|string  $source_dir
     * @param null|string  $var_dir
     *
     * @return \Northrook\RuntimeOptions
     */
    public static function from(
        null|string $app_env = null,
        null|string $app_debug = null,
        null|string $root_dir = null,
        null|string $source_dir = null,
        null|string $var_dir = null,
    ): self {
        $appEnv        = AppEnv::resolve($app_env);
        $appDebug      = AppDebug::resolve($app_debug, $appEnv);
        $rootDirectory = \resolve_root_directory($root_dir);

        return new self(
            appEnv         : $appEnv,
            appDebug       : $appDebug,
            rootDirectory  : $rootDirectory,
            sourceDirectory: $source_dir
                ? Normalize::path(
                    $rootDirectory . \DIR_SEP . $source_dir,
                    throwOnEmpty: true,
                )
                : null,
            varDirectory   : $var_dir
                ? \resolve_var_directory(
                    $rootDirectory,
                    $var_dir,
                )
                : null,
        );
    }
}
