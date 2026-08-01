<?php

declare(strict_types=1);

namespace Pam\Native\FeatureFlags;

interface FlagProvider
{
    public function definition(string $key): ?FlagDefinition;
}
