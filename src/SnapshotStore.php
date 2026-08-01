<?php

declare(strict_types=1);

namespace Pam\Native\FeatureFlags;

use Closure;
use Pam\Native\Modules\NativeModuleResult;
use Pam\Native\Modules\NativeModules;

final class SnapshotStore
{
    private const string MODULE = 'feature-flags.snapshot';

    /** @param Closure(bool): void $complete */
    public function save(string $namespace, string $snapshot, Closure $complete): int
    {
        return NativeModules::call(self::MODULE, 'save', [
            'namespace' => $namespace,
            'snapshot' => $snapshot,
        ], static fn (NativeModuleResult $result): mixed => $complete($result->succeeded()));
    }

    /** @param Closure(?string): void $complete */
    public function load(string $namespace, Closure $complete): int
    {
        return NativeModules::call(self::MODULE, 'load', [
            'namespace' => $namespace,
        ], static function (NativeModuleResult $result) use ($complete): void {
            if (!$result->succeeded()) {
                $complete(null);
                return;
            }
            $values = $result->values();
            $complete(isset($values['snapshot']) && is_string($values['snapshot']) ? $values['snapshot'] : null);
        });
    }

    /** @param Closure(bool): void $complete */
    public function delete(string $namespace, Closure $complete): int
    {
        return NativeModules::call(self::MODULE, 'delete', [
            'namespace' => $namespace,
        ], static fn (NativeModuleResult $result): mixed => $complete($result->succeeded()));
    }
}
