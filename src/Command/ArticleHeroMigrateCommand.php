<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\HttpCacheBundle\Cache\CacheManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Moves the tagline of articles from `subtitle` to `heroSubtitle`.
 *
 * Articles and pages now share their head fields (hero-content.xml), under
 * the page names. Sulu only resolves the properties a template declares, so
 * an article not migrated yet shows no tagline, and saving it in the admin
 * drops the former one for good: run it as soon as the bundle is updated.
 *
 * Draft, live and historical rows alike, in every locale. It can be run
 * twice: a row already migrated is left alone. When a row holds both, the
 * tagline typed in the new field wins.
 *
 * The rows are written in SQL, which Sulu's HTTP cache never hears of: the
 * command clears it, or the pages keep serving what they had.
 */
#[AsCommand(
    name: 'iw-sulu:theme:migrate-article-hero',
    description: 'Move the tagline of articles from subtitle to heroSubtitle, the field they now share with pages',
)]
class ArticleHeroMigrateCommand extends Command
{
    /**
     * Where articles store their template data.
     */
    private const TABLE = 'ar_article_dimension_contents';

    /**
     * The article templates shipped by the bundle.
     */
    private const DEFAULT_TEMPLATES = ['iw_news', 'iw_event', 'iw_blog_post'];

    public const FORMER_FIELD = 'subtitle';

    public const FIELD = 'heroSubtitle';

    /**
     * @param EntityManagerInterface     $entityManager Reaches the article rows
     * @param CacheManagerInterface|null $cacheManager  Sulu's HTTP cache, cleared after writing
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
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
                'Article template keys to migrate, for a project whose own templates include article-hero.xml',
                self::DEFAULT_TEMPLATES,
            )
            ->setHelp(<<<'HELP'
                Run it once when updating, on every environment holding content,
                before anyone saves an article in the admin:

                  <info>php bin/console iw-sulu:theme:migrate-article-hero --dry-run</info>
                  <info>php bin/console iw-sulu:theme:migrate-article-hero</info>

                A project whose own article templates include article-hero.xml
                names them, the bundle ones included if they are still used:

                  <info>php bin/console iw-sulu:theme:migrate-article-hero --template=iw_news --template=app_press_release</info>

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
        $templates = array_values(array_filter((array) $input->getOption('template'), \is_string(...)));
        $connection = $this->entityManager->getConnection();

        if (!$connection->createSchemaManager()->tablesExist([self::TABLE])) {
            $io->note('SuluArticleBundle is not installed here: there are no articles to migrate.');

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

            $data = json_decode((string) $row['template_data'], true);
            if (!\is_array($data)) {
                continue;
            }

            $migrated = self::migrate($data);
            if (null === $migrated) {
                continue;
            }

            $moved[] = [
                (string) $row['id'],
                (string) ($row['locale'] ?? ''),
                (string) ($row['stage'] ?? ''),
                self::isEmpty($data[self::FIELD] ?? null) ? 'moved' : 'kept heroSubtitle',
            ];

            if (!$dryRun) {
                // Unquoted on purpose, the mirror image of the SELECT: PostgreSQL
                // folds `templateData` onto the real column.
                $connection->update(
                    self::TABLE,
                    ['templateData' => json_encode($migrated, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES)],
                    ['id' => $row['id']],
                );
            }
        }

        if ([] === $moved) {
            $io->success('No article tagline left to move.');

            return Command::SUCCESS;
        }

        $io->table(['Row', 'Locale', 'Stage', 'Tagline'], $moved);

        if ($dryRun) {
            $io->note('Dry run: nothing was written.');

            return Command::SUCCESS;
        }

        $io->success(\sprintf('%d article row(s) migrated.', \count($moved)));

        if (null !== $this->cacheManager && $this->cacheManager->supportsClear()) {
            $this->cacheManager->clear();
            $io->writeln('  The HTTP cache was cleared.');
        } else {
            $io->writeln('  Clear the HTTP cache so the articles are rendered again (Varnish or another proxy: purge it).');
        }

        $io->writeln('  Then clear the content cache:');
        $io->writeln('  <info>php bin/console cache:pool:clear cache.app</info>');

        return Command::SUCCESS;
    }

    /**
     * The template data with its tagline under the new name, if it has to move.
     *
     * @param array<string, mixed> $data The template data of one row
     *
     * @return array<string, mixed>|null The migrated data, or null when there is nothing to do
     */
    public static function migrate(array $data): ?array
    {
        if (!\array_key_exists(self::FORMER_FIELD, $data)) {
            return null;
        }

        if (self::isEmpty($data[self::FIELD] ?? null)) {
            $data[self::FIELD] = $data[self::FORMER_FIELD];
        }
        unset($data[self::FORMER_FIELD]);

        return $data;
    }

    /**
     * @param mixed $value A stored tagline
     *
     * @return bool True when nothing is written in it
     */
    private static function isEmpty(mixed $value): bool
    {
        return null === $value || '' === trim(\is_string($value) ? $value : '');
    }
}
