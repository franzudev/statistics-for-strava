<?php

declare(strict_types=1);

namespace App\Domain\Garmin;

use App\Infrastructure\Time\Clock\Clock;

final readonly class GarminTokens
{
    private function __construct(
        private string $accessToken,
        private string $refreshToken,
        private int $expiresAt,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromTokenResponse(array $data, Clock $clock): self
    {
        $expiresIn = is_numeric($data['expires_in'] ?? null) ? (int) $data['expires_in'] : 3600;

        return new self(
            accessToken: (string) ($data['access_token'] ?? throw new \RuntimeException('Garmin access token missing from response')),
            refreshToken: (string) ($data['refresh_token'] ?? throw new \RuntimeException('Garmin refresh token missing from response')),
            expiresAt: $clock->getCurrentDateTimeImmutable()->getTimestamp() + $expiresIn - 60,
        );
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            accessToken: (string) $data['accessToken'],
            refreshToken: (string) $data['refreshToken'],
            expiresAt: (int) $data['expiresAt'],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'accessToken' => $this->accessToken,
            'refreshToken' => $this->refreshToken,
            'expiresAt' => $this->expiresAt,
        ];
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function getRefreshToken(): string
    {
        return $this->refreshToken;
    }

    public function isExpired(Clock $clock): bool
    {
        return $clock->getCurrentDateTimeImmutable()->getTimestamp() >= $this->expiresAt;
    }
}
