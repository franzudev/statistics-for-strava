<?php

declare(strict_types=1);

namespace App\Domain\Garmin;

final readonly class GarminActivity
{
    private function __construct(
        private string $activityId,
        private ?string $downloadUrl,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $activityId = $data['activityId']
            ?? $data['summaryId']
            ?? $data['activitySummaryId']
            ?? $data['id']
            ?? null;

        if (!is_scalar($activityId) || '' === trim((string) $activityId)) {
            throw new \RuntimeException('Garmin activity payload does not contain an activity id');
        }

        $downloadUrl = $data['fitFileUrl']
            ?? $data['activityDetailUrl']
            ?? $data['fileUrl']
            ?? $data['downloadUrl']
            ?? null;

        return new self(
            activityId: (string) $activityId,
            downloadUrl: is_scalar($downloadUrl) && '' !== trim((string) $downloadUrl) ? (string) $downloadUrl : null,
        );
    }

    public function getActivityId(): string
    {
        return $this->activityId;
    }

    public function getDownloadUrl(): ?string
    {
        return $this->downloadUrl;
    }
}
