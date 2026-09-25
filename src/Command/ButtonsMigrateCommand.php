<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Command;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use ItechWorld\SuluTailwindThemeBundle\Content\Migration\ButtonFieldMigrator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Moves stored buttons and pictograms onto the button and pictogram fields.
 *
 * The call-to-action buttons of every block, the cards, the key figures, the
 * timeline steps and the mega menu stored a button or a pictogram as a row of
 * flat properties. They now hold one `iw_theme_button` or
 * `iw_theme_icon_picker` value, and Sulu no longer resolves the flat ones: a
 * page not migrated shows its blocks without their buttons, its cards and
 * figures without their pictograms. See ButtonFieldMigrator for what moves.
 *
 * Draft and live rows alike, on pages, snippets and articles. It can be run
 * twice: a value already moved is left alone.
 */
#[AsCommand(
    name: 'iw-sulu:theme:migrate-buttons',
    description: 'Move stored buttons and pictograms onto the button and pictogram fields',
)]
class ButtonsMigrateCommand extends Command
{
    /**
     * Every table holding block content. The article table only exists when
     * SuluArticleBundle is installed, hence the existence check.
     */
    private const TABLES = [
        'pa_page_dimension_contents',
        'sn_snippet_dimension_contents',
        'ar_article_dimension_contents',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ButtonFieldMigrator $migrator = new ButtonFieldMigrator(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would change without writing anything')
            ->setHelp(<<<'HELP'
                Run it once when updating, on every environment holding content:

                  <info>php bin/console iw-sulu:theme:migrate-buttons --dry-run</info>
                  <info>php bin/console iw-sulu:theme:migrate-buttons</info>

                It covers the buttons of every block, the pictograms of cards,
                key figures and timeline steps, and the buttons of the mega
                menu, on pages, snippets and articles, draft and live. It can
                be run twice: a value already moved is left alone.
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
        $connection = $this->entityManager->getConnection();
        $schemaManager = $connection->createSchemaManager();

        $report = [];
        $absent = [];
        $total = 0;

        foreach (self::TABLES as $table) {
            if (!$schemaManager->tablesExist([$table])) {
                $absent[] = $table;

                continue;
            }

            $result = $this->migrateTable($connection, $table, $dryRun);
            $total += $result['buttons'] + $result['icons'] + $result['menu'];
            $report[] = [$table, (string) $result['buttons'], (string) $result['icons'], (string) $result['menu'], (string) $result['rows']];
        }

        if ([] !== $absent) {
            $io->note(\sprintf('Not installed here, skipped: %s.', implode(', ', $absent)));
        }

        if (0 === $total) {
            $io->success('No button or pictogram left to move.');

            return Command::SUCCESS;
        }

        $io->table(['Table', 'Buttons', 'Pictograms', 'Menu buttons', 'Rows touched'], $report);

        if ($dryRun) {
            $io->note('Dry run: nothing was written.');

            return Command::SUCCESS;
        }

        $io->success('Buttons and pictograms moved onto their fields.');
        $io->writeln('  Clear the content cache so the content is rendered again:');
        $io->writeln('  <info>php bin/console cache:pool:clear cache.app</info>');

        return Command::SUCCESS;
    }

    /**
     * Migrate one content table and report what it held.
     *
     * @param Connection $connection The database connection
     * @param string     $table      One of self::TABLES, never user input
     * @param bool       $dryRun     Whether to leave the rows untouched
     *
     * @return array{buttons: int, icons: int, menu: int, rows: int} Values moved, and rows they sat in
     */
    private function migrateTable(Connection $connection, string $table, bool $dryRun): array
    {
        // Lower-case aliases: PostgreSQL folds an unquoted identifier, and a
        // row read back under the camel case of the query is a missing key.
        $rows = $connection->fetchAllAssociative(
            \sprintf(
                'SELECT id, templateKey AS template_key, templateData AS template_data FROM %s WHERE templateData IS NOT NULL',
                $table,
            ),
        );

        $totals = ['buttons' => 0, 'icons' => 0, 'menu' => 0, 'rows' => 0];

        foreach ($rows as $row) {
            $data = json_decode((string) $row['template_data'], true);
            if (!\is_array($data)) {
                continue;
            }

            $counts = [];
            $templateKey = \is_string($row['template_key'] ?? null) ? $row['template_key'] : null;
            $migrated = $this->migrator->migrate($data, $templateKey, $counts);

            if (0 === array_sum($counts)) {
                continue;
            }

            foreach ($counts as $kind => $count) {
                $totals[$kind] += $count;
            }
            ++$totals['rows'];

            if (!$dryRun) {
                // Unquoted on purpose, the mirror image of the SELECT: PostgreSQL
                // folds `templateData` onto the real column.
                $connection->update(
                    $table,
                    ['templateData' => json_encode($migrated, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES)],
                    ['id' => $row['id']],
                );
            }
        }

        return $totals;
    }
}
