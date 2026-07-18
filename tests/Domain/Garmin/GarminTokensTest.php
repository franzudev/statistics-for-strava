<?php

declare(strict_types=1);

namespace App\Tests\Domain\Garmin;

use App\Domain\Garmin\GarminTokens;
use App\Tests\Infrastructure\Time\Clock\PausedClock;
use PHPUnit\Framework\TestCase;

final class GarminTokensTest extends TestCase
{
    public function testItCreatesTokensFromOAuthResponseAndRefreshTokensAreRotated(): void
    {
        $clock = PausedClock::fromString('2026-07-17 12:00:00');

        $tokens = GarminTokens::fromTokenResponse([
            'access_token' => 'access-token',
            'refresh_token' => 'rotated-refresh-token',
            'expires_in' => 3600,
        ], $clock);

        $this->assertSame('access-token', $tokens->getAccessToken());
        $this->assertSame('rotated-refresh-token', $tokens->getRefreshToken());
        $this->assertFalse($tokens->isExpired($clock));
        $this->assertTrue($tokens->isExpired(PausedClock::fromString('2026-07-17 13:00:00')));
        $this->assertSame([
            'accessToken' => 'access-token',
            'refreshToken' => 'rotated-refresh-token',
            'expiresAt' => 1784293140,
        ], $tokens->toArray());
    }
}
