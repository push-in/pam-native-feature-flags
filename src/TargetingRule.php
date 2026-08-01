<?php

declare(strict_types=1);

namespace Pam\Native\FeatureFlags;

use InvalidArgumentException;

final readonly class TargetingRule
{
    /** @param non-empty-list<Condition> $conditions */
    public function __construct(
        public string $identifier,
        public array $conditions,
        public FlagValue $value,
    ) {
        if ($identifier === '' || $conditions === []) {
            throw new InvalidArgumentException('Targeting rules require an identifier and conditions.');
        }
    }

    public function matches(EvaluationContext $context): bool
    {
        foreach ($this->conditions as $condition) {
            if (!$condition->matches($context)) {
                return false;
            }
        }
        return true;
    }
}
