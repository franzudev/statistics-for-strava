<?php

declare(strict_types=1);

namespace App\Domain\Garmin;

use App\Infrastructure\ValueObject\String\NonEmptyStringLiteral;

final readonly class GarminClientId extends NonEmptyStringLiteral
{
    public function isConfigured(): bool
    {
        return 'replace-me' !== (string) $this;
    }
}
