# PAM Native Feature Flags

Typed feature flags with deterministic targeting, percentage rollouts, local
overrides, exposure events and offline native snapshots.

```php
$provider = new InMemoryFlagProvider([
    new FlagDefinition(
        'checkout.new',
        FlagValue::boolean(false),
        rules: [
            new TargetingRule('brazil-pro', [
                new Condition('country', ConditionOperator::Equals, 'BR'),
                new Condition('plan', ConditionOperator::OneOf, ['pro', 'enterprise']),
            ], FlagValue::boolean(true)),
        ],
        rollout: [new PercentageRollout(1_000, FlagValue::boolean(true))],
    ),
]);

$flags = new FeatureFlags(
    $provider,
    new EvaluationContext($user->id, ['country' => 'BR', 'plan' => 'pro']),
);

if ($flags->boolean('checkout.new')) {
    // New checkout.
}
```

Rollouts use SHA-256 over `flag-key + NUL + bucketing-id`, then map the first
unsigned 32 bits into `0...9999`. Golden tests keep PHP, Kotlin and Swift
assignments identical. Snapshot payloads are bounded to one MiB.
