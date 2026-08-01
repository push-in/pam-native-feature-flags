<?php

declare(strict_types=1);

namespace Pam\Native\FeatureFlags;

use Closure;
use LogicException;

final class FeatureFlags
{
    /** @var array<string, FlagValue> */
    private array $overrides = [];

    /** @param null|Closure(Exposure): void $onExposure */
    public function __construct(
        private readonly FlagProvider $provider,
        private readonly EvaluationContext $context,
        private readonly Evaluator $evaluator = new Evaluator(),
        private readonly ?Closure $onExposure = null,
    ) {
    }

    public function evaluate(string $key): Evaluation
    {
        $definition = $this->provider->definition($key)
            ?? throw new LogicException("Unknown feature flag {$key}.");
        $override = $this->overrides[$key] ?? null;
        if ($override !== null) {
            if ($override->kind !== $definition->defaultValue->kind) {
                throw new LogicException("Override for {$key} has a different value kind.");
            }
            $evaluation = new Evaluation($key, $override, EvaluationReason::OverrideValue);
        } else {
            $evaluation = $this->evaluator->evaluate($definition, $this->context);
        }
        ($this->onExposure)?->__invoke(new Exposure(
            $evaluation,
            $this->context,
            (int) floor(microtime(true) * 1000),
        ));
        return $evaluation;
    }

    public function boolean(string $key): bool
    {
        $evaluation = $this->evaluate($key);
        if ($evaluation->value->kind !== FlagValueKind::Boolean) {
            throw new LogicException("Feature flag {$key} is not boolean.");
        }
        return (bool) $evaluation->value->value;
    }

    public function override(string $key, bool|int|float|string $value): void
    {
        $this->overrides[$key] = FlagValue::fromScalar($value);
    }

    public function clearOverride(string $key): void
    {
        unset($this->overrides[$key]);
    }
}
