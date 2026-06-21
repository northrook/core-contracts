<?php

declare(strict_types = 1);

namespace Northrook {
    use Northrook\Contracts\ContractSingleton;

    /**
     * May in time hold some configuration
     */
    final class Contracts extends ContractSingleton
    {
        public const string VERSION = '0.1.0';

        private function __construct()
        {
            parent::__construct();
        }

        public static function register(): Contracts
        {
            return new self();
        }
    }
}

