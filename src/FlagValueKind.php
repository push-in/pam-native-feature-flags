<?php

declare(strict_types=1);

namespace Pam\Native\FeatureFlags;

enum FlagValueKind: int
{
    case Boolean = 1;
    case Integer = 2;
    case Decimal = 3;
    case Text = 4;
}
