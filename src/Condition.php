<?php

declare(strict_types=1);

namespace Pam\Native\FeatureFlags;

final readonly class Condition
{
    /** @param bool|int|float|string|list<bool|int|float|string> $expected */
    public function __construct(
        public string $attribute,
        public ConditionOperator $operator,
        public bool|int|float|string|array $expected,
    ) {
    }

    public function matches(EvaluationContext $context): bool
    {
        $actual = $context->attribute($this->attribute);
        if ($actual === null) {
            return false;
        }

        return match ($this->operator) {
            ConditionOperator::Equals => $actual === $this->expected,
            ConditionOperator::NotEquals => $actual !== $this->expected,
            ConditionOperator::Contains => is_string($actual) && is_string($this->expected)
                && str_contains($actual, $this->expected),
            ConditionOperator::GreaterThan => is_numeric($actual) && is_numeric($this->expected)
                && (float) $actual > (float) $this->expected,
            ConditionOperator::LessThan => is_numeric($actual) && is_numeric($this->expected)
                && (float) $actual < (float) $this->expected,
            ConditionOperator::OneOf => is_array($this->expected) && in_array($actual, $this->expected, true),
        };
    }
}
