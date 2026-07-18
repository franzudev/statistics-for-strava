<?php

declare(strict_types=1);

namespace App\Application\Import\GarminImport\ImportActivities;

use App\Application\Import\CalculateActivityMetrics\CalculateActivityMetrics;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\ImportSource;
use App\Domain\Activity\Lap\ActivityLapRepository;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Garmin\Garmin;
use App\Domain\Import\DuplicateActivityScanner;
use App\Domain\Import\FileImport;
use App\Domain\Import\FileImportId;
use App\Domain\Import\FileImportRepository;
use App\Domain\Import\FileImportStatus;
use App\Domain\Import\FileParser\ActivityFileParsers;
use App\Domain\Import\FileParser\RawActivityFile;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\DependencyInjection\Mutex\WithMutex;
use App\Infrastructure\Mutex\LockName;
use App\Infrastructure\Mutex\Mutex;
use App\Infrastructure\Time\Clock\Clock;
use App\Infrastructure\ValueObject\String\ExternalReferenceId;
use App\Infrastructure\ValueObject\String\KernelProjectDir;
use App\Infrastructure\ValueObject\String\Path;
use Symfony\Component\Filesystem\Filesystem;

#[WithMutex(lockName: LockName::IMPORT_DATA_OR_BUILD_APP)]
final readonly class ImportGarminActivitiesCommandHandler implements CommandHandler
{
    public function __construct(
        private Garmin $garmin,
        private ActivityFileParsers $activityFileParsers,
        private DuplicateActivityScanner $duplicateActivityScanner,
        private ActivityRepository $activityRepository,
        private ActivityStreamRepository $activityStreamRepository,
        private ActivityLapRepository $activityLapRepository,
        private FileImportRepository $fileImportRepository,
        private CommandBus $commandBus,
        private Mutex $mutex,
        private Clock $clock,
        private KernelProjectDir $kernelProjectDir,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof ImportGarminActivities);
        $output = $command->getOutput();
        $output->writeln('Importing Garmin activities...');

        if (!$this->garmin->isConfigured()) {
            $output->writeln('  => Garmin is not configured. Set GARMIN_CLIENT_ID and GARMIN_CLIENT_SECRET.');

            return;
        }

        if (!$this->garmin->isAuthorized()) {
            $output->writeln('  => Garmin is not connected yet. Open /garmin-oauth and connect your account.');

            return;
        }

        $countImported = 0;
        $countSkipped = 0;
        $countFailed = 0;
        $filesystem = new Filesystem();
        $temporaryDirectory = sprintf('%s/var/garmin-import', (string) $this->kernelProjectDir);
        $filesystem->mkdir($temporaryDirectory);

        $activities = $this->garmin->getActivities($this->garmin->getLastSyncTimestamp());
        foreach ($activities as $garminActivity) {
            $this->mutex->heartbeat();
            $activityId = $garminActivity->getActivityId();
            $filePath = sprintf('%s/%s.fit', $temporaryDirectory, preg_replace('/[^A-Za-z0-9_.-]/', '-', $activityId));

            try {
                $contents = $this->garmin->downloadFitFile($garminActivity);
                $filesystem->dumpFile($filePath, $contents);
                $rawFile = RawActivityFile::from(Path::fromString($filePath), $contents);
                $parsedFile = $this->activityFileParsers->parse($rawFile);
                $activity = $parsedFile->getActivity()->withImportSource(
                    ImportSource::GARMIN_CONNECT_API,
                    ExternalReferenceId::fromString($activityId),
                );

                if ($this->duplicateActivityScanner->isDuplicate(
                    file: $rawFile,
                    sportType: $activity->getSportType(),
                    startDateTime: $activity->getStartDate(),
                )) {
                    $this->fileImportRepository->add(FileImport::createFromRawFile(
                        fileImportId: FileImportId::random(),
                        file: $rawFile,
                        source: ImportSource::GARMIN_CONNECT_API,
                        status: FileImportStatus::SKIPPED,
                        errorMessage: 'Skipped, activity was already imported',
                        activityId: null,
                        importedOn: $this->clock->getCurrentDateTimeImmutable(),
                    ));
                    ++$countSkipped;
                    $output->writeln(sprintf('  => Skipped Garmin activity "%s", already imported', $activityId));
                    continue;
                }

                $this->activityRepository->add(ActivityWithRawData::fromState(
                    activity: $activity,
                    rawData: ['garminActivityId' => $activityId],
                ));
                foreach ($parsedFile->getStreams() as $stream) {
                    $this->activityStreamRepository->add($stream);
                }
                $this->activityRepository->markActivityStreamsAsImported($activity->getId());
                foreach ($parsedFile->getLaps() as $lap) {
                    $this->activityLapRepository->add($lap);
                }
                $this->fileImportRepository->add(FileImport::createFromRawFile(
                    fileImportId: FileImportId::random(),
                    file: $rawFile,
                    source: ImportSource::GARMIN_CONNECT_API,
                    status: FileImportStatus::SUCCESS,
                    errorMessage: null,
                    activityId: $activity->getId(),
                    importedOn: $this->clock->getCurrentDateTimeImmutable(),
                ));
                ++$countImported;
                $output->writeln(sprintf('  => Imported Garmin activity "%s" as "%s - %s"', $activityId, $activity->getName(), $activity->getStartDate()->format('d-m-Y')));
            } catch (\Throwable $e) {
                ++$countFailed;
                $output->writeln(sprintf('  => <error>Could not import Garmin activity "%s": %s</error>', $activityId, $e->getMessage()));
            } finally {
                $filesystem->remove($filePath);
            }
        }

        $this->garmin->markSyncedNow();
        if ($countImported > 0) {
            $this->commandBus->dispatch(new CalculateActivityMetrics($output));
        }

        $output->writeln(sprintf('  => Imported %d, skipped %d, failed %d Garmin activit(y/ies)', $countImported, $countSkipped, $countFailed));
    }
}
