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
 * Moves the pictograms of published content onto the shared icon picker.
 *
 * Several blocks carried a pictogram long before the theme had an icon library,
 * each through a media field of its own: `icon` on a card, `icon` on a timeline
 * step, `image` on a key figure. Those names are now the shared ones, where
 * `icon` holds a library name and `iconMedia` holds a media - so a stored media
 * sitting under `icon` would be read as an icon name and render nothing.
 *
 * This command moves it: the media goes to `iconMedia`, and `iconCustom` is
 * turned on so the block keeps showing the editor's own file. Nothing else
 * changes, and content already migrated is left alone, so it can be run twice.
 */
#[AsCommand(
    name: 'iw-sulu:theme:migrate-icons',
    description: 'Move block pictograms onto the shared icon picker',
)]
class IconsMigrateCommand extends Command
{
    /**
     * Block type => the media field it used to carry its pictogram in.
     *
     * The key is the `type` stored on each block, so a block of another kind
     * holding a field of the same name is never touched.
     */
    private const MOVES = [
        'cards' => ['items' => 'items', 'field' => 'icon'],
        'timeline' => ['items' => 'steps', 'field' => 'icon'],
        'key_figures' => ['items' => 'figures', 'field' => 'image'],
    ];

    /**
     * Every table holding block content, in the order they are reported.
     *
     * The three blocks that carry a pictogram can be placed on a page, on a
     * snippet and on an article alike, and all three tables store their content
     * in the same column. The article table only exists when SuluArticleBundle
     * is installed, hence the existence check before reading any of them.
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
                Run it once after upgrading, on every environment holding content:

                  <info>php bin/console iw-sulu:theme:migrate-icons --dry-run</info>
                  <info>php bin/console iw-sulu:theme:migrate-icons</info>

                Pages, snippets and articles are all covered. It can be run
                twice: a pictogram already moved is left alone.
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

        if ([] !== $absent) {
            $io->note(\sprintf('Not installed here, skipped: %s.', implode(', ', $absent)));
        }

        if (0 === $moved) {
            $io->success('No pictogram left to move.');

            return Command::SUCCESS;
        }

        $io->table(['Table', 'Pictograms moved', 'Rows touched'], $report);

        if ($dryRun) {
            $io->note('Dry run: nothing was written.');

            return Command::SUCCESS;
        }

        $io->success('Pictograms moved onto the shared picker.');
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
     * @return array{moved: int, rows: int} Pictograms moved, and rows they sat in
     */
    private function migrateTable(Connection $connection, string $table, bool $dryRun): array
    {
        // The column is read under an explicit lower-case alias. An unquoted
        // identifier is folded to lower case by PostgreSQL, which returns the
        // row under `templatedata` and not under the name written here, so
        // reading `templateData` back worked on MySQL and on nothing else.
        // The alias settles the key on both engines.
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

            if (!\is_string($type) || !isset(self::MOVES[$type])) {
                continue;
            }

            $move = self::MOVES[$type];
            $listKey = $move['items'];

            if (!isset($block[$listKey]) || !\is_array($block[$listKey])) {
                continue;
            }

            foreach ($block[$listKey] as $itemIndex => $item) {
                if (!\is_array($item)) {
                    continue;
                }

                $block[$listKey][$itemIndex] = $this->migrateItem($item, $move['field'], $count);
            }

            $blocks[$index] = $block;
        }

        return $blocks;
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array<string, mixed>
     */
    private function migrateItem(array $item, string $field, int &$count): array
    {
        // Already moved, or nothing to move. A media picker opened and left
        // empty stores `{id: null}`, which is not a pictogram.
        if (isset($item['iconMedia']) || !isset($item[$field]) || !\is_array($item[$field])) {
            return $item;
        }

        if (!isset($item[$field]['id']) || null === $item[$field]['id']) {
            return $item;
        }

        $item['iconMedia'] = $item[$field];
        $item['iconCustom'] = true;
        unset($item[$field]);

        ++$count;

        return $item;
    }
}
