<?php

declare(strict_types=1);

namespace Pam\Native\FeatureFlags;

use InvalidArgumentException;

final readonly class FlagDefinition
{
    /** @param list<TargetingRule> $rules @param list<PercentageRollout> $rollout */
    public function __construct(
        public string $key,
        public FlagValue $defaultValue,
        public array $rules = [],
        public array $rollout = [],
    ) {
        if (preg_match('/^[a-z][a-z0-9._-]{0,127}$/D', $key) !== 1) {
            throw new InvalidArgumentException('Feature flag keys must be safe lowercase identifiers.');
        }
        $total = array_sum(array_map(static fn (PercentageRollout $item): int => $item->basisPoints, $rollout));
        if ($total > 10_000) {
            throw new InvalidArgumentException('Rollout allocations cannot exceed 10000 basis points.');
        }
        foreach ($rules as $rule) {
            if ($rule->value->kind !== $defaultValue->kind) {
                throw new InvalidArgumentException("Rule {$rule->identifier} has a different value kind.");
            }
        }
        foreach ($rollout as $item) {
            if ($item->value->kind !== $defaultValue->kind) {
                throw new InvalidArgumentException('Rollout value has a different value kind.');
            }
        }
    }
}
