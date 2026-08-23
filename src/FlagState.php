<?php

declare(strict_types=1);

namespace Pam\Native\FeatureFlags;

enum FlagState: int
{
    case Enabled = 1;
    case Disabled = 2;
}
