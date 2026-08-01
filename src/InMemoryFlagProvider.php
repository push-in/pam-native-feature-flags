<?php

declare(strict_types=1);

namespace Pam\Native\FeatureFlags;

final class InMemoryFlagProvider implements FlagProvider
{
    /** @var array<string, FlagDefinition> */
    private array $definitions = [];

    /** @param iterable<FlagDefinition> $definitions */
    public function __construct(iterable $definitions = [])
    {
        foreach ($definitions as $definition) {
            $this->definitions[$definition->key] = $definition;
        }
    }

    public function definition(string $key): ?FlagDefinition
    {
        return $this->definitions[$key] ?? null;
    }

    public function replace(FlagDefinition $definition): void
    {
        $this->definitions[$definition->key] = $definition;
    }
}
