<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use ItechWorld\SuluTailwindThemeBundle\Content\Migration\EventLocationMigrator;
use Sulu\Bundle\HttpCacheBundle\Cache\CacheManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Moves the free-text venue of events onto the venue field, and repairs the
 * locations an earlier version of this command left without coordinates.
 *
 * The event template now stores its address as a Sulu location, so that the
 * page can show it and the structured data can carry it, and its venue name
 * in a text field. Sulu does not read a former string as a location, and its
 * admin cannot open a location without coordinates. See EventLocationMigrator
 * for what moves.
 *
 * Draft, live and historical rows alike, in every locale. It can be run
 * twice: a row already migrated is left alone.
 *
 * The rows are written in SQL, which Sulu's HTTP cache never hears of: the
 * command clears it, or the pages keep serving the venue they had.
 */
#[AsCommand(
    name: 'iw-sulu:theme:migrate-event-location',
    description: 'Move the free-text venue of events onto the venue field and repair locations without coordinates',
)]
class EventLocationMigrateCommand extends Command
{
    /**
     * Where articles store their template data.
     */
    private const TABLE = 'ar_article_dimension_contents';

    /**
     * The template shipped by the bundle.
     */
    private const DEFAULT_TEMPLATE = 'iw_event';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventLocationMigrator $migrator = new EventLocationMigrator(),
        private readonly ?CacheManagerInterface $cacheManager = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would change without writing anything')
            ->addOption(
                'template',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Article template keys to migrate, for a project that copied the event template',
                [self::DEFAULT_TEMPLATE],
            )
            ->setHelp(<<<'HELP'
                Run it once when updating, on every environment holding content:

                  <info>php bin/console iw-sulu:theme:migrate-event-location --dry-run</info>
                  <info>php bin/console iw-sulu:theme:migrate-event-location</info>

                The former text becomes the venue name, and the location is left
                empty. Open each event afterwards and search its address in the
                Address field, so the page shows it and search engines can place
                the event. Put the venue name in the Title of the address: it
                names the place once the address is chosen.

                A project migrated by an earlier version of this command has
                locations without coordinates, which the admin cannot open. The
                same command repairs them: their title becomes the venue name.

                A project whose own event template kept the `location` field
                names it, the bundle one included if it is still used:

                  <info>php bin/console iw-sulu:theme:migrate-event-location --template=iw_event --template=app_event</info>

                It can be run twice: a row already migrated is left alone. It
                clears Sulu's HTTP cache after writing.
                HELP);
    }

    /**
     * @param InputInterface  $input  The console input
     * @param OutputInterface $output The console output
     *
     * @return int The command exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        /** @var list<string> $templates */
        $templates = \array_values(\array_filter((array) $input->getOption('template'), \is_string(...)));
        $connection = $this->entityManager->getConnection();

        if (!$connection->createSchemaManager()->tablesExist([self::TABLE])) {
            $io->note('SuluArticleBundle is not installed here: there are no events to migrate.');

            return Command::SUCCESS;
        }

        // Lower-case aliases: PostgreSQL folds an unquoted identifier, and a
        // row read back under the camel case of the query is a missing key.
        $rows = $connection->fetchAllAssociative(
            \sprintf(
                'SELECT id, locale, stage, templateKey AS template_key, templateData AS template_data FROM %s WHERE templateData IS NOT NULL',
                self::TABLE,
            ),
        );

        $moved = [];

        foreach ($rows as $row) {
            if (!\in_array($row['template_key'] ?? null, $templates, true)) {
                continue;
            }

            $data = \json_decode((string) $row['template_data'], true);
            if (!\is_array($data)) {
                continue;
            }

            $migrated = $this->migrator->migrate($data);
            if (null === $migrated) {
                continue;
            }

            $moved[] = [
                (string) $row['id'],
                (string) ($row['locale'] ?? ''),
                (string) ($row['stage'] ?? ''),
                \is_string($data[EventLocationMigrator::FIELD]) ? 'text' : 'location without coordinates',
                (string) ($migrated[EventLocationMigrator::VENUE_FIELD] ?? '-'),
            ];

            if (!$dryRun) {
                // Unquoted on purpose, the mirror image of the SELECT: PostgreSQL
                // folds `templateData` onto the real column.
                $connection->update(
                    self::TABLE,
                    ['templateData' => \json_encode($migrated, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES)],
                    ['id' => $row['id']],
                );
            }
        }

        if ([] === $moved) {
            $io->success('No event venue left to move.');

            return Command::SUCCESS;
        }

        $io->table(['Row', 'Locale', 'Stage', 'Was', 'Venue'], $moved);

        if ($dryRun) {
            $io->note('Dry run: nothing was written.');

            return Command::SUCCESS;
        }

        $io->success(\sprintf('%d event row(s) migrated.', \count($moved)));
        $io->writeln('  Search each address in the Address field, then put the venue name in its Title.');

        if (null !== $this->cacheManager && $this->cacheManager->supportsClear()) {
            $this->cacheManager->clear();
            $io->writeln('  The HTTP cache was cleared.');
        } else {
            $io->writeln('  Clear the HTTP cache so the events are rendered again (Varnish or another proxy: purge it).');
        }

        $io->writeln('  Then clear the content cache:');
        $io->writeln('  <info>php bin/console cache:pool:clear cache.app</info>');

        return Command::SUCCESS;
    }
}
