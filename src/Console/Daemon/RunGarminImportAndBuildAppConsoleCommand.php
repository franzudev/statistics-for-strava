<?php

declare(strict_types=1);

namespace App\Console\Daemon;

use App\Application\AppIsNotReady;
use App\Application\AppStatusChecker;
use App\Application\AppUrl;
use App\Application\Build\RunBuild\RunBuild;
use App\Application\Import\GarminImport\ImportActivities\ImportGarminActivities;
use App\Application\RebuildStatus;
use App\Domain\Integration\Notification\SendNotification\SendNotification;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\DependencyInjection\Mutex\WithMutex;
use App\Infrastructure\Doctrine\Migrations\RequiresUpToDateDatabaseSchema;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Infrastructure\Logging\LoggableConsoleOutput;
use App\Infrastructure\Mutex\LockIsAlreadyAcquired;
use App\Infrastructure\Mutex\LockName;
use App\Infrastructure\Mutex\Mutex;
use App\Infrastructure\Time\Clock\Clock;
use App\Infrastructure\Time\ResourceUsage\ResourceUsage;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[WithMonologChannel('daemon')]
#[WithMutex(lockName: LockName::IMPORT_DATA_OR_BUILD_APP)]
#[RequiresUpToDateDatabaseSchema]
#[AsCommand(name: RunGarminImportAndBuildAppConsoleCommand::NAME, description: 'Run Garmin import')]
final class RunGarminImportAndBuildAppConsoleCommand extends Command
{
    use HandlesImportAndBuild;

    public const string NAME = 'app:cron:run-garmin-import';

    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly AppStatusChecker $appStatusChecker,
        private readonly ResourceUsage $resourceUsage,
        private readonly Mutex $mutex,
        private readonly AppUrl $appUrl,
        private readonly Clock $clock,
        private readonly KeyValueStore $keyValueStore,
        private readonly LoggerInterface $logger,
        private readonly RebuildStatus $rebuildStatus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addImportAndBuildOptions();
        $this->addIfRequiredOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, new LoggableConsoleOutput($output, $this->logger));
        $shouldImport = $this->resolvePhases($input)[self::IMPORT_OPTION];
        $today = $this->clock->getCurrentDateTimeImmutable()->format('Y-m-d');
        $appNeedsToBeBuilt = $this->appNeedsToBeBuilt(
            input: $input,
            keyValueStore: $this->keyValueStore,
            rebuildStatus: $this->rebuildStatus,
            today: $today
        );

        if (!$shouldImport && !$appNeedsToBeBuilt) {
            $output->writeln('Nothing to build...');

            return Command::SUCCESS;
        }

        $this->resourceUsage->startTimer();

        try {
            $this->mutex->acquireLock('runGarminImportAndBuildApp');
        } catch (LockIsAlreadyAcquired) {
            $output->writeln('<comment>Postponing Garmin import, another process is importing data.</comment>');

            return Command::SUCCESS;
        }

        try {
            if ($shouldImport) {
                $this->appStatusChecker->ensureIsReadyForBuild();
                $this->commandBus->dispatch(new ImportGarminActivities($output));
            }

            if ($appNeedsToBeBuilt) {
                $this->appStatusChecker->ensureIsReadyForBuild();
                $this->commandBus->dispatch(new RunBuild($output));
                $this->keyValueStore->save(KeyValue::fromState(
                    key: Key::APP_LAST_BUILT_ON,
                    value: Value::fromString($today),
                ));
                $this->keyValueStore->clear(Key::FORCE_REBUILD);
            }
        } catch (AppIsNotReady $e) {
            $this->mutex->releaseLock();
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
            $this->mutex->releaseLock();
            throw $e;
        }

        $this->mutex->releaseLock();
        $this->resourceUsage->stopTimer();
        $this->commandBus->dispatch(new SendNotification(
            title: 'Garmin import successful',
            message: sprintf('New Garmin import and build was successful in %ss', $this->resourceUsage->getRunTimeInSeconds()),
            tags: ['+1'],
            actionUrl: $this->appUrl,
        ));
        $output->writeln(sprintf('<info>%s</info>', $this->resourceUsage->format()));

        return Command::SUCCESS;
    }
}
