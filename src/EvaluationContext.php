<?php

declare(strict_types=1);

namespace Pam\Native\FeatureFlags;

final readonly class EvaluationContext
{
    /** @param array<string, bool|int|float|string> $attributes */
    public function __construct(
        public string $bucketingId,
        public array $attributes = [],
    ) {
    }

    public function attribute(string $name): bool|int|float|string|null
    {
        return $name === 'bucketingId' ? $this->bucketingId : ($this->attributes[$name] ?? null);
    }
}
