<?php

declare(strict_types=1);

namespace Pam\Native\FeatureFlags;

enum EvaluationReason: int
{
    case DefaultValue = 1;
    case TargetingRule = 2;
    case PercentageRollout = 3;
    case OverrideValue = 4;
    case CachedSnapshot = 5;
}
