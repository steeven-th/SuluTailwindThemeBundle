<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Command;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Moves published blocks off a layout style that no longer ships.
 *
 * A style key lives in two places at once, and dropping one without the other
 * breaks the site rather than the admin: the blocks already published carry the
 * key, and the theme stored in the database maps that key to the Twig file that
 * renders it. A theme still naming a file the bundle no longer holds throws on
 * render, and a block still naming a style the theme no longer lists falls back
 * to the first one in the list.
 *
 * So both are migrated together: the content moves to the replacement style,
 * and the theme forgets the entry.
 *
 * Nothing else is touched, and it can be run twice - a block already moved
 * names a style that is not in the table any more.
 */
#[AsCommand(
    name: 'iw-sulu:theme:migrate-block-styles',
    description: 'Move published blocks off the layout styles dropped in 3.0.0',
)]
class BlockStylesMigrateCommand extends Command
{
    /**
     * Block type => [dropped style key => the style taking over].
     *
     * `with_icons` was `inline` with the pictogram rendered, and `inline`
     * renders it now, so the two layouts differed by whether the editor had
     * filled a field. A figure with no pictogram draws no slot for one, which
     * is the whole of what the second style offered.
     */
    private const MOVES = [
        'key_figures' => ['with_icons' => 'inline'],
    ];

    /**
     * Every table holding block content, in the order they are reported.
     *
     * A block can be placed on a page, on a snippet and on an article alike,
     * and all three store their content in the same column. The article table
     * only exists when SuluArticleBundle is installed, hence the existence
     * check before reading any of them.
     */
    private const TABLES = [
        'pa_page_dimension_contents',
        'sn_snippet_dimension_contents',
        'ar_article_dimension_contents',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would change without writing anything')
            ->setHelp(<<<'HELP'
                Run it once when upgrading to 3.0.0, on every environment
                holding content:

                  <info>php bin/console iw-sulu:theme:migrate-block-styles --dry-run</info>
                  <info>php bin/console iw-sulu:theme:migrate-block-styles</info>

                It moves the key figures published on the dropped "with icons"
                style onto "inline", on pages, snippets and articles alike, and
                drops the entry from the themes stored in the database. It can
                be run twice.
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
        $moved = 0;

        foreach (self::TABLES as $table) {
            if (!$schemaManager->tablesExist([$table])) {
                $absent[] = $table;

                continue;
            }

            $result = $this->migrateTable($connection, $table, $dryRun);

            $moved += $result['moved'];
            $report[] = [$table, (string) $result['moved'], (string) $result['rows']];
        }

        $themes = $this->migrateThemes($connection, $schemaManager, $dryRun);

        if ([] !== $absent) {
            $io->note(\sprintf('Not installed here, skipped: %s.', implode(', ', $absent)));
        }

        if (0 === $moved && 0 === $themes) {
            $io->success('No block left on a dropped style.');

            return Command::SUCCESS;
        }

        $io->table(['Table', 'Blocks moved', 'Rows touched'], $report);

        if ($themes > 0) {
            $io->writeln(\sprintf('  Themes cleaned of the dropped styles: <info>%d</info>', $themes));
        }

        if ($dryRun) {
            $io->note('Dry run: nothing was written.');

            return Command::SUCCESS;
        }

        $io->success('Blocks moved onto the styles that ship.');
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
     * @return array{moved: int, rows: int} Blocks moved, and rows they sat in
     */
    private function migrateTable(Connection $connection, string $table, bool $dryRun): array
    {
        // The column is read under an explicit lower-case alias. An unquoted
        // identifier is folded to lower case by PostgreSQL, which returns the
        // row under `templatedata` and not under the name written here.
        $rows = $connection->fetchAllAssociative(
            \sprintf(
                'SELECT id, templateData AS template_data FROM %s WHERE templateData IS NOT NULL',
                $table,
            ),
        );

        $touched = 0;
        $moved = 0;

        foreach ($rows as $row) {
            /** @var array<string, mixed>|null $data */
            $data = json_decode((string) $row['template_data'], true);

            if (!\is_array($data) || !isset($data['blocks']) || !\is_array($data['blocks'])) {
                continue;
            }

            $count = 0;
            $data['blocks'] = $this->migrateBlocks($data['blocks'], $count);

            if (0 === $count) {
                continue;
            }

            ++$touched;
            $moved += $count;

            if (!$dryRun) {
                // Left unquoted on purpose, the mirror image of the SELECT:
                // DBAL writes `SET templateData = ?`, which PostgreSQL folds
                // onto the real column. Quoting it would miss it.
                $connection->update(
                    $table,
                    ['templateData' => json_encode($data, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES)],
                    ['id' => $row['id']],
                );
            }
        }

        return ['moved' => $moved, 'rows' => $touched];
    }

    /**
     * @param array<int|string, mixed> $blocks
     *
     * @return array<int|string, mixed>
     */
    private function migrateBlocks(array $blocks, int &$count): array
    {
        foreach ($blocks as $index => $block) {
            if (!\is_array($block)) {
                continue;
            }

            $type = $block['type'] ?? null;
            $style = $block['style'] ?? null;

            if (!\is_string($type) || !\is_string($style) || !isset(self::MOVES[$type][$style])) {
                continue;
            }

            $block['style'] = self::MOVES[$type][$style];
            $blocks[$index] = $block;

            ++$count;
        }

        return $blocks;
    }

    /**
     * Drop the dropped styles from the themes stored in the database.
     *
     * A theme lists, per block type, which styles it offers and which Twig file
     * each one renders through. Leaving an entry behind points the renderer at
     * a file the bundle no longer holds.
     *
     * @param Connection                                  $connection    The database connection
     * @param \Doctrine\DBAL\Schema\AbstractSchemaManager  $schemaManager Used to skip a table that is not installed
     * @param bool                                        $dryRun        Whether to leave the rows untouched
     *
     * @return int The number of themes that held one
     */
    private function migrateThemes(
        Connection $connection,
        \Doctrine\DBAL\Schema\AbstractSchemaManager $schemaManager,
        bool $dryRun,
    ): int {
        $table = 'iw_sulu_tailwind_theme_config';

        if (!$schemaManager->tablesExist([$table])) {
            return 0;
        }

        $rows = $connection->fetchAllAssociative(
            \sprintf('SELECT id, block_styles FROM %s WHERE block_styles IS NOT NULL', $table),
        );

        $touched = 0;

        foreach ($rows as $row) {
            /** @var array<string, mixed>|null $blockStyles */
            $blockStyles = json_decode((string) $row['block_styles'], true);

            if (!\is_array($blockStyles)) {
                continue;
            }

            $changed = false;

            foreach (self::MOVES as $blockType => $dropped) {
                if (!isset($blockStyles[$blockType]['styles']) || !\is_array($blockStyles[$blockType]['styles'])) {
                    continue;
                }

                $kept = [];
                foreach ($blockStyles[$blockType]['styles'] as $style) {
                    if (\is_array($style) && isset($dropped[$style['key'] ?? ''])) {
                        $changed = true;

                        continue;
                    }

                    $kept[] = $style;
                }

                // Reindexed, so the list stays a JSON array: a gap in the keys
                // would encode it as an object and the picker would read no
                // styles at all.
                $blockStyles[$blockType]['styles'] = array_values($kept);
            }

            if (!$changed) {
                continue;
            }

            ++$touched;

            if (!$dryRun) {
                $connection->update(
                    $table,
                    ['block_styles' => json_encode($blockStyles, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES)],
                    ['id' => $row['id']],
                );
            }
        }

        return $touched;
    }
}
