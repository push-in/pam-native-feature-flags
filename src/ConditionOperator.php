<?php

declare(strict_types=1);

namespace Pam\Native\FeatureFlags;

enum ConditionOperator: int
{
    case Equals = 1;
    case NotEquals = 2;
    case Contains = 3;
    case GreaterThan = 4;
    case LessThan = 5;
    case OneOf = 6;
}
