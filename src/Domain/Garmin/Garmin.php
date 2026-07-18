<?php

declare(strict_types=1);

namespace App\Domain\Garmin;

use App\Infrastructure\Serialization\Json;
use App\Infrastructure\Time\Clock\Clock;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

final readonly class Garmin
{
    public function __construct(
        private Client $client,
        private GarminClientId $clientId,
        private GarminClientSecret $clientSecret,
        private GarminOAuthAuthorizeUrl $authorizeUrl,
        private GarminOAuthTokenUrl $tokenUrl,
        private GarminActivityApiBaseUri $activityApiBaseUri,
        private GarminActivityListEndpoint $activityListEndpoint,
        private GarminActivityFileEndpointTemplate $activityFileEndpointTemplate,
        private GarminAuthorizationStore $authorizationStore,
        private Clock $clock,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->clientId->isConfigured() && $this->clientSecret->isConfigured();
    }

    public function isAuthorized(): bool
    {
        return $this->authorizationStore->isAuthorized();
    }

    public function createAuthorizationUrl(string $redirectUri): string
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Garmin OAuth is not configured. Set GARMIN_CLIENT_ID and GARMIN_CLIENT_SECRET.');
        }

        $state = GarminOAuthState::create();
        $this->authorizationStore->saveOAuthState($state);

        return (string) $this->authorizeUrl.'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => (string) $this->clientId,
            'redirect_uri' => $redirectUri,
            'code_challenge' => $state->getCodeChallenge(),
            'code_challenge_method' => 'S256',
            'state' => $state->getState(),
        ]);
    }

    public function handleAuthorizationCallback(string $code, string $state, string $redirectUri): void
    {
        $storedState = $this->authorizationStore->consumeOAuthState();
        if (!hash_equals($storedState->getState(), $state)) {
            throw new \RuntimeException('Invalid Garmin OAuth state');
        }

        $response = $this->client->post((string) $this->tokenUrl, [
            RequestOptions::FORM_PARAMS => [
                'grant_type' => 'authorization_code',
                'client_id' => (string) $this->clientId,
                'client_secret' => (string) $this->clientSecret,
                'code' => $code,
                'redirect_uri' => $redirectUri,
                'code_verifier' => $storedState->getCodeVerifier(),
            ],
        ]);

        $this->authorizationStore->saveTokens(GarminTokens::fromTokenResponse(
            Json::decode($response->getBody()->getContents()),
            $this->clock,
        ));
    }

    public function getAccessToken(): string
    {
        $tokens = $this->authorizationStore->getTokens() ?? throw new \RuntimeException('Garmin is not connected yet');
        if (!$tokens->isExpired($this->clock)) {
            return $tokens->getAccessToken();
        }

        $response = $this->client->post((string) $this->tokenUrl, [
            RequestOptions::FORM_PARAMS => [
                'grant_type' => 'refresh_token',
                'client_id' => (string) $this->clientId,
                'client_secret' => (string) $this->clientSecret,
                'refresh_token' => $tokens->getRefreshToken(),
            ],
        ]);

        $tokens = GarminTokens::fromTokenResponse(
            Json::decode($response->getBody()->getContents()),
            $this->clock,
        );
        $this->authorizationStore->saveTokens($tokens);

        return $tokens->getAccessToken();
    }

    /** @return list<GarminActivity> */
    public function getActivities(?int $sinceTimestamp = null): array
    {
        $query = [];
        if (null !== $sinceTimestamp) {
            $query['uploadStartTimeInSeconds'] = $sinceTimestamp;
        }
        $query['uploadEndTimeInSeconds'] = $this->clock->getCurrentDateTimeImmutable()->getTimestamp();

        $response = $this->client->get((string) $this->activityListEndpoint, [
            'base_uri' => (string) $this->activityApiBaseUri,
            RequestOptions::HEADERS => [
                'Authorization' => 'Bearer '.$this->getAccessToken(),
                'Accept' => 'application/json',
            ],
            RequestOptions::QUERY => $query,
        ]);

        $payload = Json::decode($response->getBody()->getContents());
        $items = $payload['activities'] ?? $payload['activityDetails'] ?? $payload['summaries'] ?? $payload;
        if (!is_array($items)) {
            throw new \RuntimeException('Unexpected Garmin activities response');
        }

        $activities = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $activities[] = GarminActivity::fromArray($item);
        }

        return $activities;
    }

    public function downloadFitFile(GarminActivity $activity): string
    {
        $url = $activity->getDownloadUrl() ?? $this->activityFileEndpointTemplate->forActivityId($activity->getActivityId());
        $options = [
            RequestOptions::HEADERS => [
                'Authorization' => 'Bearer '.$this->getAccessToken(),
                'Accept' => 'application/octet-stream, application/vnd.garmin.fit, */*',
            ],
        ];

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            $response = $this->client->get($url, $options);
        } else {
            $response = $this->client->get($url, array_merge($options, [
                'base_uri' => (string) $this->activityApiBaseUri,
            ]));
        }

        return $response->getBody()->getContents();
    }

    public function markSyncedNow(): void
    {
        $this->authorizationStore->saveLastSyncTimestamp($this->clock->getCurrentDateTimeImmutable()->getTimestamp());
    }

    public function getLastSyncTimestamp(): ?int
    {
        return $this->authorizationStore->getLastSyncTimestamp();
    }
}
