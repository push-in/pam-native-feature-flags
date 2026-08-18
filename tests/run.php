<?php

declare(strict_types=1);

$packageAutoload = dirname(__DIR__).'/vendor/autoload.php';
if (is_file($packageAutoload)) {
    require $packageAutoload;
}

$roots = [
    'Pam\\Native\\FeatureFlags\\' => dirname(__DIR__).'/src/',
    'Pam\\Native\\Testing\\' => dirname(__DIR__, 2).'/pam-native-testing/src/',
    'Pam\\Native\\' => dirname(__DIR__, 2).'/../pam-native/packages/native/src/',
];
spl_autoload_register(static function (string $class) use ($roots): void {
    foreach ($roots as $prefix => $root) {
        if (str_starts_with($class, $prefix)) {
            $path = $root.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
            if (is_file($path)) { require $path; }
            return;
        }
    }
});

use Pam\Native\FeatureFlags\Condition;
use Pam\Native\FeatureFlags\ConditionOperator;
use Pam\Native\FeatureFlags\EvaluationContext;
use Pam\Native\FeatureFlags\EvaluationReason;
use Pam\Native\FeatureFlags\Evaluator;
use Pam\Native\FeatureFlags\FeatureFlags;
use Pam\Native\FeatureFlags\FlagDefinition;
use Pam\Native\FeatureFlags\FlagValue;
use Pam\Native\FeatureFlags\InMemoryFlagProvider;
use Pam\Native\FeatureFlags\JsonFlagProvider;
use Pam\Native\FeatureFlags\PercentageRollout;
use Pam\Native\FeatureFlags\SnapshotStore;
use Pam\Native\FeatureFlags\TargetingRule;
use Pam\Native\Testing\NativeTestHarness;

$tests = [];
$test = static function (string $name, Closure $callback) use (&$tests): void { $tests[$name] = $callback; };
$expect = static function (bool $condition, string $message = 'Expectation failed'): void {
    if (!$condition) { throw new RuntimeException($message); }
};

$test('matches targeting rules before rollouts', static function () use ($expect): void {
    $flag = new FlagDefinition(
        'checkout.new',
        FlagValue::boolean(false),
        [new TargetingRule('brazil-pro', [
            new Condition('country', ConditionOperator::Equals, 'BR'),
            new Condition('plan', ConditionOperator::OneOf, ['pro', 'enterprise']),
        ], FlagValue::boolean(true))],
        [new PercentageRollout(10_000, FlagValue::boolean(false))],
    );
    $evaluation = (new Evaluator())->evaluate($flag, new EvaluationContext('user-1', [
        'country' => 'BR', 'plan' => 'pro',
    ]));
    $expect($evaluation->value->value === true);
    $expect($evaluation->reason === EvaluationReason::TargetingRule);
    $expect($evaluation->ruleIdentifier === 'brazil-pro');
});

$test('keeps rollout buckets stable across languages', static function () use ($expect): void {
    $expect(Evaluator::bucket('checkout.new', 'user-1') === 656);
    $expect(Evaluator::bucket('checkout.new', 'user-2') === 509);
    $expect(Evaluator::bucket('search.v2', 'device-abc') === 493);
});

$test('enforces typed values, overrides and exposure callbacks', static function () use ($expect): void {
    $exposures = [];
    $provider = new InMemoryFlagProvider([
        new FlagDefinition('checkout.new', FlagValue::boolean(false)),
    ]);
    $flags = new FeatureFlags(
        $provider,
        new EvaluationContext('user-1'),
        onExposure: static function ($exposure) use (&$exposures): void { $exposures[] = $exposure; },
    );
    $expect($flags->boolean('checkout.new') === false);
    $flags->override('checkout.new', true);
    $evaluation = $flags->evaluate('checkout.new');
    $expect($evaluation->value->value === true);
    $expect($evaluation->reason === EvaluationReason::OverrideValue);
    $expect(count($exposures) === 2);
});

$test('persists snapshots through the official deterministic bridge fake', static function () use ($expect): void {
    $fake = NativeTestHarness::install();
    $fake->succeed('feature-flags.snapshot', 'save', ['saved' => true]);
    $fake->succeed('feature-flags.snapshot', 'load', ['snapshot' => '{"revision":7}']);
    $store = new SnapshotStore();
    $saved = false;
    $loaded = null;
    $store->save('production', '{"revision":7}', static function (bool $value) use (&$saved): void { $saved = $value; });
    $store->load('production', static function (?string $value) use (&$loaded): void { $loaded = $value; });
    $expect($saved && $loaded === '{"revision":7}');
    $fake->assertCalled('feature-flags.snapshot', 'save');
    $fake->assertCalled('feature-flags.snapshot', 'load');
    $fake->assertSatisfied();
    NativeTestHarness::uninstall();
});

$test('parses strict provider-neutral JSON contracts', static function () use ($expect): void {
    $provider = JsonFlagProvider::fromJson(json_encode([
        'version' => 1,
        'flags' => [[
            'key' => 'search.v2',
            'default' => ['kind' => 1, 'value' => false],
            'rules' => [[
                'identifier' => 'staff',
                'conditions' => [[
                    'attribute' => 'role',
                    'operator' => 1,
                    'expected' => 'staff',
                ]],
                'value' => ['kind' => 1, 'value' => true],
            ]],
            'rollout' => [[
                'basisPoints' => 500,
                'value' => ['kind' => 1, 'value' => true],
            ]],
        ]],
    ], JSON_THROW_ON_ERROR));
    $flags = new FeatureFlags($provider, new EvaluationContext('user-4', ['role' => 'staff']));
    $expect($flags->boolean('search.v2'));

    try {
        JsonFlagProvider::fromJson('{"version":1,"flags":[{"key":"bad","default":{"kind":"boolean","value":true}}]}');
        $expect(false, 'String-coded value kind unexpectedly passed.');
    } catch (InvalidArgumentException $exception) {
        $expect(str_contains($exception->getMessage(), 'Invalid flag bad default'));
    }
});

$failures = 0;
foreach ($tests as $name => $callback) {
    try {
        $callback();
        fwrite(STDOUT, "PASS {$name}\n");
    } catch (Throwable $throwable) {
        ++$failures;
        NativeTestHarness::uninstall();
        fwrite(STDERR, "FAIL {$name}: {$throwable->getMessage()}\n");
    }
}
fwrite(STDOUT, sprintf("%d tests, %d failures\n", count($tests), $failures));
exit($failures === 0 ? 0 : 1);
