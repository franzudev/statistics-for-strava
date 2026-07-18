<?php

declare(strict_types=1);

namespace App\Domain\Garmin;

use App\Infrastructure\ValueObject\String\NonEmptyStringLiteral;

final readonly class GarminActivityFileEndpointTemplate extends NonEmptyStringLiteral
{
    public function forActivityId(string $activityId): string
    {
        return str_replace('{activityId}', rawurlencode($activityId), (string) $this);
    }
}
