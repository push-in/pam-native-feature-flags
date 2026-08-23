<?php

declare(strict_types=1);

namespace Pam\Native\FeatureFlags;

use InvalidArgumentException;
use JsonException;

final readonly class SignedFlagSnapshot
{
    public function __construct(
        public int $revision,
        public int $expiresAtUnix,
        public string $payload,
        public string $keyId,
        public string $signature,
    ) {
        if ($revision < 1 || $expiresAtUnix < 1 || strlen($payload) > 1_048_576
            || preg_match('/^[A-Za-z0-9._-]{1,128}$/D', $keyId) !== 1
            || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES
        ) {
            throw new InvalidArgumentException('Invalid signed feature flag snapshot.');
        }
    }

    /** @param array<string, string> $trustedKeys Ed25519 public keys indexed by key id. */
    public static function fromJson(
        string $json,
        array $trustedKeys,
        ?int $now = null,
        int $minimumRevision = 1,
    ): self
    {
        try {
            $document = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Signed snapshot is invalid JSON.', previous: $exception);
        }
        if (!is_array($document) || array_is_list($document)
            || !is_int($document['revision'] ?? null)
            || !is_int($document['expiresAtUnix'] ?? null)
            || !is_string($document['payload'] ?? null)
            || !is_string($document['keyId'] ?? null)
            || !is_string($document['signature'] ?? null)
        ) {
            throw new InvalidArgumentException('Signed snapshot envelope is invalid.');
        }
        $payload = base64_decode($document['payload'], true);
        $signature = base64_decode($document['signature'], true);
        $publicKey = $trustedKeys[$document['keyId']] ?? null;
        if ($payload === false || $signature === false || !is_string($publicKey)
            || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
        ) {
            throw new InvalidArgumentException('Signed snapshot encoding or key is invalid.');
        }
        $signed = self::message($document['revision'], $document['expiresAtUnix'], $document['keyId'], $payload);
        if (!sodium_crypto_sign_verify_detached($signature, $signed, $publicKey)) {
            throw new InvalidArgumentException('Signed snapshot signature is invalid.');
        }
        if ($document['expiresAtUnix'] < ($now ?? time())) {
            throw new InvalidArgumentException('Signed snapshot has expired.');
        }
        if ($document['revision'] < $minimumRevision) {
            throw new InvalidArgumentException('Signed snapshot revision would roll back configuration.');
        }

        return new self($document['revision'], $document['expiresAtUnix'], $payload, $document['keyId'], $signature);
    }

    public function provider(): JsonFlagProvider
    {
        return JsonFlagProvider::fromJson($this->payload);
    }

    private static function message(int $revision, int $expiresAtUnix, string $keyId, string $payload): string
    {
        return "pam-native-feature-flags\0{$revision}\0{$expiresAtUnix}\0{$keyId}\0{$payload}";
    }
}
