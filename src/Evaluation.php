<?php

declare(strict_types=1);

namespace Pam\Native\FeatureFlags;

final readonly class Evaluation
{
    public function __construct(
        public string $key,
        public FlagValue $value,
        public EvaluationReason $reason,
        public ?string $ruleIdentifier = null,
        public ?int $bucket = null,
    ) {
    }
}
