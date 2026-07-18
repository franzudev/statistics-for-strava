<?php

declare(strict_types=1);

namespace App\Domain\Garmin;

use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Infrastructure\Serialization\Json;

final readonly class GarminAuthorizationStore
{
    public function __construct(
        private KeyValueStore $keyValueStore,
    ) {
    }

    public function saveOAuthState(GarminOAuthState $state): void
    {
        $this->keyValueStore->save(KeyValue::fromState(
            key: Key::GARMIN_OAUTH_STATE,
            value: Value::fromString(Json::encode($state->toArray()))
        ));
    }

    public function consumeOAuthState(): GarminOAuthState
    {
        try {
            $state = GarminOAuthState::fromArray(Json::decode((string) $this->keyValueStore->find(Key::GARMIN_OAUTH_STATE)));
        } finally {
            $this->keyValueStore->clear(Key::GARMIN_OAUTH_STATE);
        }

        return $state;
    }

    public function saveTokens(GarminTokens $tokens): void
    {
        $this->keyValueStore->save(KeyValue::fromState(
            key: Key::GARMIN_TOKENS,
            value: Value::fromString(Json::encode($tokens->toArray()))
        ));
    }

    public function getTokens(): ?GarminTokens
    {
        try {
            return GarminTokens::fromArray(Json::decode((string) $this->keyValueStore->find(Key::GARMIN_TOKENS)));
        } catch (EntityNotFound) {
            return null;
        }
    }

    public function isAuthorized(): bool
    {
        return $this->getTokens() instanceof GarminTokens;
    }

    public function saveLastSyncTimestamp(int $timestamp): void
    {
        $this->keyValueStore->save(KeyValue::fromState(
            key: Key::GARMIN_LAST_SYNC_AT,
            value: Value::fromString((string) $timestamp)
        ));
    }

    public function getLastSyncTimestamp(): ?int
    {
        try {
            return (int) (string) $this->keyValueStore->find(Key::GARMIN_LAST_SYNC_AT);
        } catch (EntityNotFound) {
            return null;
        }
    }
}
