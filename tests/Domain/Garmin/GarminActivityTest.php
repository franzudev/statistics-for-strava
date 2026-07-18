<?php

declare(strict_types=1);

namespace App\Tests\Domain\Garmin;

use App\Domain\Garmin\GarminActivity;
use PHPUnit\Framework\TestCase;

final class GarminActivityTest extends TestCase
{
    public function testItCreatesActivityFromGarminPayloadVariants(): void
    {
        $activity = GarminActivity::fromArray([
            'summaryId' => 'garmin-activity-id',
            'fitFileUrl' => 'https://example.com/activity.fit',
        ]);

        $this->assertSame('garmin-activity-id', $activity->getActivityId());
        $this->assertSame('https://example.com/activity.fit', $activity->getDownloadUrl());
    }
}
