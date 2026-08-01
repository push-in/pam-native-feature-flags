<?php

declare(strict_types=1);

namespace Pam\Native\FeatureFlags;

use InvalidArgumentException;

final readonly class FlagValue
{
    private function __construct(
        public FlagValueKind $kind,
        public bool|int|float|string $value,
    ) {
    }

    public static function boolean(bool $value): self { return new self(FlagValueKind::Boolean, $value); }
    public static function integer(int $value): self { return new self(FlagValueKind::Integer, $value); }
    public static function decimal(float $value): self { return new self(FlagValueKind::Decimal, $value); }
    public static function text(string $value): self { return new self(FlagValueKind::Text, $value); }

    public static function fromScalar(bool|int|float|string $value): self
    {
        return match (get_debug_type($value)) {
            'bool' => self::boolean($value),
            'int' => self::integer($value),
            'float' => self::decimal($value),
            'string' => self::text($value),
            default => throw new InvalidArgumentException('Unsupported feature flag scalar.'),
        };
    }
}
