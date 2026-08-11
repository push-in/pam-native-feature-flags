# PAM Native Feature Flags

Typed feature flags with deterministic targeting, percentage rollouts, local
overrides, exposure events and offline native snapshots.

```bash
pam add feature-flags
pam doctor
```

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


## What installation does

`pam add feature-flags` resolves the official compatible package, performs a non-mutating Composer preflight, updates the normal `composer.json` and `composer.lock`, refreshes generated native integration when required, and leaves the project ready for `pam doctor` validation.

Use `pam packages` to inspect availability and `pam remove feature-flags` to uninstall the capability safely. Direct Composer commands are an advanced interoperability path; PAM is the supported application workflow.

## API guide

| API | Responsibility |
| --- | --- |
| `FeatureFlags` | Evaluate typed flags and manage local overrides. |
| `FlagDefinition` / `TargetingRule` | Declare defaults, rules, and rollouts. |
| `EvaluationContext` | Provide a stable bucketing identifier and attributes. |
| `SnapshotStore` | Persist bounded offline-native snapshots. |
| `FlagProvider` | Supply definitions from memory, JSON, or a custom backend. |

All coded states, kinds, and variants are sequential integer-backed enums. Use enum cases in application code; do not depend on raw wire numbers.

## Production checklist

- Use a stable, non-secret bucketing identifier across sessions.
- Emit exposure events once per product decision, not every render.
- Always provide a safe default for offline and malformed configurations.
- Run `pam doctor`, `pam test`, and a signed release build on every supported platform.
- Exercise denial, cancellation, backgrounding, process restart, and offline behavior before release.

## Troubleshooting

- **Users change buckets:** verify the bucketing identifier is stable.
- **A rule never matches:** inspect attribute types as well as values.
- **Snapshot save fails:** keep the serialized payload below the documented one-MiB bound.
- **Native integration is stale:** run `pam doctor --fix`, rebuild the native host, and inspect the first reported diagnostic.

## Compatibility and support

This package targets PAM Native `0.6.x`, Android API 26+, and iOS 15+ unless a platform-specific section above states a stricter requirement. Platform SDKs, credentials, entitlements, physical hardware, and store configuration remain application responsibilities.

- [PAM documentation](https://push-in.github.io/pam-docs/introduction/)
- [PAM Native overview](https://push-in.github.io/pam-docs/native/overview/)
- [Plugin and native capability model](https://push-in.github.io/pam-docs/native/plugins/)
- [Report an issue](https://github.com/push-in/pam-native-feature-flags/issues)

Security vulnerabilities should be reported through the repository security policy or GitHub private vulnerability reporting, not a public issue.
