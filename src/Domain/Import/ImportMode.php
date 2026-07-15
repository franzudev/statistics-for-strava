<?php

declare(strict_types=1);

namespace App\Domain\Import;

enum ImportMode: string
{
    case STRAVA_API = 'stravaApi';
    case FILES = 'files';
    case HYBRID = 'hybrid';

    public function isStravaApi(): bool
    {
        return in_array($this, [self::STRAVA_API, self::HYBRID], true);
    }

    public function isFiles(): bool
    {
        return in_array($this, [self::FILES, self::HYBRID], true);
    }

    public function isHybrid(): bool
    {
        return self::HYBRID === $this;
    }

    public static function fromServerVar(): self
    {
        return self::from($_SERVER['IMPORT_MODE']);
    }
}
