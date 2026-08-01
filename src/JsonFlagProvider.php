<?php

declare(strict_types=1);

namespace Pam\Native\FeatureFlags;

use InvalidArgumentException;
use JsonException;

final class JsonFlagProvider implements FlagProvider
{
    private function __construct(private readonly InMemoryFlagProvider $provider)
    {
    }

    public static function fromJson(string $json): self
    {
        if (strlen($json) > 1_048_576) {
            throw new InvalidArgumentException('Feature flag document exceeds one MiB.');
        }
        try {
            $document = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Feature flag document is invalid JSON.', previous: $exception);
        }
        if (!is_array($document) || array_is_list($document) || ($document['version'] ?? null) !== 1) {
            throw new InvalidArgumentException('Feature flag document version must be integer 1.');
        }
        $flags = $document['flags'] ?? null;
        if (!is_array($flags) || !array_is_list($flags)) {
            throw new InvalidArgumentException('Feature flag document requires a flag list.');
        }
        $definitions = [];
        $keys = [];
        foreach ($flags as $index => $flag) {
            if (!is_array($flag) || array_is_list($flag)) {
                throw new InvalidArgumentException("Flag at index {$index} must be an object.");
            }
            $key = $flag['key'] ?? null;
            if (!is_string($key) || isset($keys[$key])) {
                throw new InvalidArgumentException("Flag at index {$index} has an invalid or duplicate key.");
            }
            $keys[$key] = true;
            $default = self::value($flag['default'] ?? null, "flag {$key} default");
            $rules = [];
            foreach (self::list($flag['rules'] ?? [], "flag {$key} rules") as $ruleIndex => $rule) {
                if (!is_array($rule) || array_is_list($rule)) {
                    throw new InvalidArgumentException("Flag {$key} rule {$ruleIndex} must be an object.");
                }
                $identifier = $rule['identifier'] ?? null;
                if (!is_string($identifier)) {
                    throw new InvalidArgumentException("Flag {$key} rule {$ruleIndex} requires an identifier.");
                }
                $conditions = [];
                foreach (self::list($rule['conditions'] ?? null, "flag {$key} rule conditions") as $condition) {
                    if (!is_array($condition) || array_is_list($condition)) {
                        throw new InvalidArgumentException("Flag {$key} contains an invalid condition.");
                    }
                    $attribute = $condition['attribute'] ?? null;
                    $operator = $condition['operator'] ?? null;
                    $expected = $condition['expected'] ?? null;
                    if (!is_string($attribute) || !is_int($operator) || ConditionOperator::tryFrom($operator) === null
                        || (!is_scalar($expected) && !is_array($expected))) {
                        throw new InvalidArgumentException("Flag {$key} contains an invalid condition contract.");
                    }
                    /** @var bool|int|float|string|list<bool|int|float|string> $expected */
                    $conditions[] = new Condition($attribute, ConditionOperator::from($operator), $expected);
                }
                $rules[] = new TargetingRule(
                    $identifier,
                    $conditions,
                    self::value($rule['value'] ?? null, "flag {$key} rule value"),
                );
            }
            $rollout = [];
            foreach (self::list($flag['rollout'] ?? [], "flag {$key} rollout") as $allocation) {
                if (!is_array($allocation) || array_is_list($allocation) || !is_int($allocation['basisPoints'] ?? null)) {
                    throw new InvalidArgumentException("Flag {$key} contains an invalid rollout allocation.");
                }
                $rollout[] = new PercentageRollout(
                    $allocation['basisPoints'],
                    self::value($allocation['value'] ?? null, "flag {$key} rollout value"),
                );
            }
            $definitions[] = new FlagDefinition($key, $default, $rules, $rollout);
        }
        return new self(new InMemoryFlagProvider($definitions));
    }

    public function definition(string $key): ?FlagDefinition
    {
        return $this->provider->definition($key);
    }

    private static function value(mixed $input, string $path): FlagValue
    {
        if (!is_array($input) || array_is_list($input) || !is_int($input['kind'] ?? null)) {
            throw new InvalidArgumentException("Invalid {$path}.");
        }
        $kind = FlagValueKind::tryFrom($input['kind']);
        $value = $input['value'] ?? null;
        return match ($kind) {
            FlagValueKind::Boolean => is_bool($value) ? FlagValue::boolean($value) : throw new InvalidArgumentException("Invalid {$path}."),
            FlagValueKind::Integer => is_int($value) ? FlagValue::integer($value) : throw new InvalidArgumentException("Invalid {$path}."),
            FlagValueKind::Decimal => is_float($value) || is_int($value) ? FlagValue::decimal((float) $value) : throw new InvalidArgumentException("Invalid {$path}."),
            FlagValueKind::Text => is_string($value) ? FlagValue::text($value) : throw new InvalidArgumentException("Invalid {$path}."),
            null => throw new InvalidArgumentException("Invalid {$path} kind."),
        };
    }

    /** @return list<mixed> */
    private static function list(mixed $value, string $path): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new InvalidArgumentException("{$path} must be a list.");
        }
        return $value;
    }
}
