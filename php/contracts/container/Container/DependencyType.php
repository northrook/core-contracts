<?php

declare(strict_types=1);

namespace Northrook\Container;

enum DependencyType
{
    case Value;
    case Service;
    case Parameter;
    case Resolve;
    case Default;
    case Unresolved;
}
