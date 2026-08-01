<?php

declare(strict_types=1);

namespace Pam\Native\FeatureFlags;

use InvalidArgumentException;

final readonly class PercentageRollout
{
    public function __construct(
        public int $basisPoints,
        public FlagValue $value,
    ) {
        if ($basisPoints < 0 || $basisPoints > 10_000) {
            throw new InvalidArgumentException('Rollout basis points must be between 0 and 10000.');
        }
    }
}
