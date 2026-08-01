<?php

declare(strict_types=1);

namespace Pam\Native\FeatureFlags;

final class Evaluator
{
    public function evaluate(FlagDefinition $flag, EvaluationContext $context): Evaluation
    {
        foreach ($flag->rules as $rule) {
            if ($rule->matches($context)) {
                return new Evaluation(
                    $flag->key,
                    $rule->value,
                    EvaluationReason::TargetingRule,
                    $rule->identifier,
                );
            }
        }

        if ($flag->rollout !== []) {
            $bucket = self::bucket($flag->key, $context->bucketingId);
            $boundary = 0;
            foreach ($flag->rollout as $allocation) {
                $boundary += $allocation->basisPoints;
                if ($bucket < $boundary) {
                    return new Evaluation(
                        $flag->key,
                        $allocation->value,
                        EvaluationReason::PercentageRollout,
                        bucket: $bucket,
                    );
                }
            }
        }

        return new Evaluation($flag->key, $flag->defaultValue, EvaluationReason::DefaultValue);
    }

    public static function bucket(string $key, string $bucketingId): int
    {
        $hex = substr(hash('sha256', $key."\0".$bucketingId), 0, 8);

        return (int) (hexdec($hex) % 10_000);
    }
}
