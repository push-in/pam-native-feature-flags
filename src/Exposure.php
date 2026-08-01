<?php

declare(strict_types=1);

namespace Pam\Native\FeatureFlags;

final readonly class Exposure
{
    public function __construct(
        public Evaluation $evaluation,
        public EvaluationContext $context,
        public int $evaluatedAtUnixMillis,
    ) {
    }
}
