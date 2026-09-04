<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use ItechWorld\SuluTailwindThemeBundle\Service\DemoImageGenerator;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeProvider;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Page\Application\Message\ApplyWorkflowTransitionPageMessage;
use Sulu\Page\Application\Message\CreatePageMessage;
use Sulu\Page\Domain\Model\PageInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Creates a set of demo pages showing every content block of the bundle.
 *
 * One index page holds a linked-pages block pointing at one child page per
 * block type, each child presenting that block in all of its styles. Useful
 * both as a showcase right after installing the bundle, and as a fixture to
 * check for visual regressions against.
 *
 * The set is named, and the pages hang under the index rather than at the root,
 * so several sets can live side by side: run it again with another name to get
 * a fresh, independent copy.
 *
 * Placeholder images are drawn at run time by DemoImageGenerator in the theme's
 * colors - nothing is shipped with the bundle.
 */
#[AsCommand(
    name: 'iw-sulu:theme:demo-content',
    description: 'Create demo pages showcasing every content block',
)]
class DemoContentCommand extends Command
{
    use HandleTrait;

    /**
     * Default name of the index page, used when no name is given.
     */
    private const DEFAULT_NAME = 'Test Blocks';

    /**
     * Blocks kept by --minimal: enough to get the idea without creating
     * seventeen pages someone has to click through.
     *
     * @var list<string>
     */
    private const MINIMAL_PAGES = ['Text - Media', 'Gallery', 'Call to action', 'Accordion'];

    public function __construct(
        MessageBusInterface $messageBus,
        private readonly WebspaceManagerInterface $webspaceManager,
        private readonly MediaManagerInterface $mediaManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly DemoImageGenerator $imageGenerator,
        private readonly ThemeProvider $themeProvider,
    ) {
        $this->messageBus = $messageBus;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'name',
                InputArgument::OPTIONAL,
                'Name of the index page. Run again with another name for a second, independent set.',
                self::DEFAULT_NAME,
            )
            ->addOption('webspace', 'w', InputOption::VALUE_REQUIRED, 'Webspace key (defaults to the first one)')
            ->addOption('locale', 'l', InputOption::VALUE_REQUIRED, 'Locale to create the pages in')
            ->addOption('minimal', null, InputOption::VALUE_NONE, 'Only create a handful of representative pages');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = trim((string) $input->getArgument('name')) ?: self::DEFAULT_NAME;

        $webspace = $this->resolveWebspace((string) $input->getOption('webspace'));
        if (null === $webspace) {
            $io->error('No webspace found. Configure at least one webspace first.');

            return Command::FAILURE;
        }

        $webspaceKey = $webspace->getKey();
        $locale = (string) ($input->getOption('locale') ?: ($webspace->getDefaultLocalization()?->getLocale() ?? 'en'));

        $io->title(\sprintf('Demo content "%s" in %s (%s)', $name, $webspaceKey, $locale));

        if ($this->pageExists($name, $webspaceKey, $locale)) {
            $io->warning(\sprintf(
                'A page named "%s" already exists in %s. Nothing was created - run again with another name to add a second set.',
                $name,
                $webspaceKey,
            ));

            return Command::SUCCESS;
        }

        $fixture = $this->loadFixture();
        if ([] === $fixture) {
            $io->error('The demo fixture is missing or unreadable.');

            return Command::FAILURE;
        }

        $pages = $this->selectPages($fixture['pages'], $name, (bool) $input->getOption('minimal'));

        // Media first: the pages reference them, and the index references the
        // pages, so everything is resolved bottom-up.
        $io->section('Media');
        $mediaIds = $this->createMedia($name, $webspaceKey, $locale, (int) ($fixture['media_pool'] ?? 12), $io);

        $io->section('Pages');

        $homepageUuid = $this->homepageUuid($webspaceKey);
        if (null === $homepageUuid) {
            $io->error(\sprintf('No homepage found in "%s". Run sulu:page:initialize-homepage first.', $webspaceKey));

            return Command::FAILURE;
        }

        $index = $this->createPage($name, $webspaceKey, $locale, $this->slugify($name), [
            'title' => $name,
            'blocks' => [],
        ], $homepageUuid);
        $io->text(\sprintf('  %s (index)', $name));

        // Slugs differ from theme to theme, so the fixture names variants and
        // button styles by position and they are resolved against whichever
        // theme this webspace runs.
        $themeSlugs = $this->themeSlugs($webspaceKey);

        // Children carry the actual blocks, and some of them link to a sibling,
        // so they are created first and linked in a second pass: a page cannot
        // point at one the run has not created yet.
        $pageUuids = [];
        $children = [];
        foreach ($pages['children'] as $page) {
            $data = $this->resolveReferences($page['data'], $mediaIds, [], $locale, $themeSlugs);
            $data['title'] = $page['title'];
            $data['url'] = '/' . $this->slugify($name) . '/' . $this->slugify($page['title']);

            $child = $this->createPage(
                $page['title'],
                $webspaceKey,
                $locale,
                $data['url'],
                $data,
                $index->getUuid(),
            );

            $pageUuids[$page['title']] = $child->getUuid();
            $children[$page['title']] = $child->getUuid();
            $io->text(\sprintf('  %s', $page['title']));
        }

        // Second pass: now every sibling exists, the links between them
        // resolve. Publishing happens here, or the published version would be
        // the one whose links are still unresolved.
        foreach ($pages['children'] as $page) {
            $uuid = $children[$page['title']];

            if (self::linksAPage($page['data'])) {
                $data = $this->pruneUnresolvedLinks($page['data'], $pageUuids);
                $data = $this->resolveReferences($data, $mediaIds, $pageUuids, $locale, $themeSlugs);
                $data['title'] = $page['title'];
                $data['url'] = '/' . $this->slugify($name) . '/' . $this->slugify($page['title']);
                $this->modifyPage($uuid, $locale, $data);
            }

            $this->publish($uuid, $locale);
        }

        // The index links to the children, so it is filled once they exist.
        if (null !== $pages['index']) {
            // Prune before resolving, while the markers still name their target.
            $data = $this->pruneUnresolvedLinks($pages['index']['data'], $pageUuids);
            $data = $this->resolveReferences($data, $mediaIds, $pageUuids, $locale, $themeSlugs);
            $data['title'] = $name;
            $data['url'] = '/' . $this->slugify($name);
            $this->modifyPage($index->getUuid(), $locale, $data);
        }

        $this->publish($index->getUuid(), $locale);
        $this->entityManager->flush();

        $io->success(\sprintf(
            '%d pages and %d media created. Open "%s" in the admin.',
            \count($pages['children']) + 1,
            \count($mediaIds),
            $name,
        ));

        return Command::SUCCESS;
    }

    /**
     * Resolve the target webspace, defaulting to the first configured one.
     */
    private function resolveWebspace(string $key): ?\Sulu\Component\Webspace\Webspace
    {
        $webspaces = $this->webspaceManager->getWebspaceCollection();

        if ('' !== $key) {
            return $webspaces->getWebspace($key);
        }

        foreach ($webspaces as $webspace) {
            return $webspace;
        }

        return null;
    }

    /**
     * Uuid of the webspace's homepage, which the demo set hangs under.
     *
     * CreatePageMessageHandler::HOMEPAGE_PARENT_ID is not it: that sentinel
     * tells the handler to create *the* homepage, so passing it here produced
     * a page sitting next to the homepage instead of below it.
     */
    private function homepageUuid(string $webspaceKey): ?string
    {
        $uuid = $this->entityManager->getConnection()->fetchOne(
            'SELECT uuid FROM pa_pages WHERE webspaceKey = :webspace AND parent_id IS NULL LIMIT 1',
            ['webspace' => $webspaceKey],
        );

        return \is_string($uuid) && '' !== $uuid ? $uuid : null;
    }

    /**
     * Whether a page with this title already exists at the root of the webspace.
     *
     * Checked on the title rather than the URL: the point is to protect a set an
     * editor may have started working in, and the title is what identifies it.
     */
    private function pageExists(string $title, string $webspaceKey, string $locale): bool
    {
        $count = $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM pa_page_dimension_contents dc
             JOIN pa_pages p ON p.uuid = dc.pageUuid
             WHERE dc.title = :title AND dc.locale = :locale AND p.webspaceKey = :webspace',
            ['title' => $title, 'locale' => $locale, 'webspace' => $webspaceKey],
        );

        return (int) $count > 0;
    }

    /**
     * @return array<string, mixed> The decoded fixture, or an empty array
     */
    private function loadFixture(): array
    {
        $path = \dirname(__DIR__) . '/DataFixtures/demo-pages.json';

        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return \is_array($decoded) ? $decoded : [];
    }

    /**
     * Split the fixture into its index page and the children to create.
     *
     * @param list<array<string, mixed>> $pages
     *
     * @return array{index: array<string, mixed>|null, children: list<array<string, mixed>>}
     */
    private function selectPages(array $pages, string $name, bool $minimal): array
    {
        $index = null;
        $children = [];

        foreach ($pages as $page) {
            $title = (string) ($page['title'] ?? '');

            // The exported index is recognised by its linked-pages-only content.
            if ('Test Blocks' === $title) {
                $index = $page;
                continue;
            }

            if ($minimal && !\in_array($title, self::MINIMAL_PAGES, true)) {
                continue;
            }

            $children[] = $page;
        }

        return ['index' => $index, 'children' => $children];
    }

    /**
     * Create the placeholder media inside a collection named after the set.
     *
     * @return array<int, int> Fixture media index to created media id
     */
    private function createMedia(string $name, string $webspaceKey, string $locale, int $pool, SymfonyStyle $io): array
    {
        $collection = $this->createCollection($name, $locale);
        $colors = $this->themeColors($webspaceKey);
        $ids = [];

        for ($i = 1; $i <= $pool; ++$i) {
            $png = $this->imageGenerator->generate($i, $colors);

            $tmp = tempnam(sys_get_temp_dir(), 'iw-demo-') . '.png';
            file_put_contents($tmp, $png);

            $uploaded = new UploadedFile($tmp, \sprintf('demo-%02d.png', $i), 'image/png', null, true);

            $media = $this->mediaManager->save($uploaded, [
                'title' => \sprintf('%s %02d', $name, $i),
                'collection' => $collection->getId(),
                'locale' => $locale,
            ], null);

            $ids[$i] = $media->getId();
            @unlink($tmp);
        }

        $io->text(\sprintf('  %d placeholders in collection "%s"', \count($ids), $name));

        return $ids;
    }

    /**
     * Create the media collection carrying the set's name.
     */
    private function createCollection(string $name, string $locale): \Sulu\Bundle\MediaBundle\Entity\Collection
    {
        $collection = new \Sulu\Bundle\MediaBundle\Entity\Collection();

        $type = $this->entityManager->getRepository(\Sulu\Bundle\MediaBundle\Entity\CollectionType::class)->find(1);
        if (null !== $type) {
            $collection->setType($type);
        }

        $meta = new \Sulu\Bundle\MediaBundle\Entity\CollectionMeta();
        $meta->setTitle($name);
        $meta->setLocale($locale);
        $meta->setCollection($collection);

        $collection->addMeta($meta);
        $collection->setDefaultMeta($meta);

        $this->entityManager->persist($collection);
        $this->entityManager->persist($meta);
        $this->entityManager->flush();

        return $collection;
    }

    /**
     * Gradient pairs taken from the active theme, empty when there is none.
     *
     * A bare installation has no theme, or none assigned to the webspace, which
     * is exactly when demo content is wanted - the generator then falls back to
     * its own neutral palette.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function themeColors(string $webspaceKey): array
    {
        $theme = $this->themeProvider->getThemeForWebspace($webspaceKey);
        if (null === $theme) {
            return [];
        }

        $colors = [];
        foreach ($theme->getTokens()['colors'] ?? [] as $color) {
            $value = $color['value'] ?? null;
            if (\is_string($value) && 1 === preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
                $colors[] = $value;
            }
        }

        if (\count($colors) < 2) {
            return [];
        }

        // Pair each color with the next one, so gradients stay within the theme.
        $pairs = [];
        for ($i = 0; $i < \count($colors) - 1 && $i < 4; ++$i) {
            $pairs[] = [$colors[$i], $colors[$i + 1]];
        }

        return $pairs;
    }

    /**
     * Drop link items whose target page was not created in this run.
     *
     * `--minimal` creates a handful of pages while the index still links to all
     * of them, and an unresolvable link renders as a dead entry.
     *
     * @param mixed                 $value
     * @param array<string, string> $pageUuids Titles that actually got created
     */
    private function pruneUnresolvedLinks(mixed $value, array $pageUuids): mixed
    {
        if (!\is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->pruneUnresolvedLinks($item, $pageUuids);
        }

        // Only lists carry link collections; re-index after filtering so the
        // result stays a JSON array rather than becoming an object.
        if (array_is_list($value)) {
            $kept = array_filter($value, function (mixed $item) use ($pageUuids): bool {
                $href = \is_array($item) ? ($item['link']['href'] ?? null) : null;

                if (!\is_string($href) || !str_starts_with($href, '@page:')) {
                    return true;
                }

                return isset($pageUuids[substr($href, 6)]);
            });

            return array_values($kept);
        }

        return $value;
    }

    /**
     * The variant and button slugs of the theme this webspace runs, in order.
     *
     * Empty lists when the webspace has no theme, which leaves every positional
     * marker resolving to an empty value: the blocks then take the theme
     * defaults, which is right when there is no theme to name.
     *
     * @param string $webspaceKey The webspace being filled
     *
     * @return array{variant: list<string>, button: list<string>}
     */
    private function themeSlugs(string $webspaceKey): array
    {
        $tokens = $this->themeProvider->getThemeForWebspace($webspaceKey)?->getTokens() ?? [];

        $slugsOf = static function (mixed $entries): array {
            if (!\is_array($entries)) {
                return [];
            }

            $slugs = [];
            foreach ($entries as $entry) {
                $slug = \is_array($entry) ? ($entry['slug'] ?? null) : null;
                if (\is_string($slug) && '' !== $slug) {
                    $slugs[] = $slug;
                }
            }

            return $slugs;
        };

        return [
            'variant' => $slugsOf($tokens['blockVariants'] ?? []),
            'button' => $slugsOf($tokens['buttons'] ?? []),
        ];
    }

    /**
     * Whether these page data link to another demo page.
     *
     * Only such a page needs the second pass, so the others are not rewritten
     * and republished for nothing.
     *
     * @param mixed $value Any node of the fixture data
     */
    private static function linksAPage(mixed $value): bool
    {
        if (\is_string($value)) {
            return str_starts_with($value, '@page:');
        }

        if (!\is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (self::linksAPage($item)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Replace the fixture's symbolic markers by the ids created in this run.
     *
     * `@media:<n>` points into the generated pool, `@page:<title>` into the
     * children created just before, and `@variant:<n>` / `@button:<n>` into the
     * theme this webspace runs.
     *
     * The last two are positional because a slug is not portable: every theme
     * names its own variants and buttons, so a fixture holding "clair-nature"
     * or "accent" matches the one theme that happens to use those names and
     * leaves every other site with an unselected picker. Positions fall back to
     * the first entry, so a theme with fewer of them still gets a valid value.
     *
     * @param mixed                              $value
     * @param array<int, int>                    $mediaIds
     * @param array<string, string>              $pageUuids
     * @param string                             $locale     Overrides the locale frozen in the fixture
     * @param array{variant: list<string>, button: list<string>} $themeSlugs
     */
    private function resolveReferences(
        mixed $value,
        array $mediaIds,
        array $pageUuids,
        string $locale,
        array $themeSlugs = ['variant' => [], 'button' => []],
    ): mixed {
        if (\is_string($value)) {
            if (str_starts_with($value, '@media:')) {
                $index = (int) substr($value, 7);

                return $mediaIds[$index] ?? ($mediaIds[1] ?? null);
            }

            if (str_starts_with($value, '@page:')) {
                return $pageUuids[substr($value, 6)] ?? null;
            }

            foreach (['variant', 'button'] as $kind) {
                $marker = '@' . $kind . ':';
                if (str_starts_with($value, $marker)) {
                    $slugs = $themeSlugs[$kind];
                    $index = (int) substr($value, \strlen($marker)) - 1;

                    // Empty rather than a stale slug: the block then falls back
                    // to the theme default instead of naming something absent.
                    return $slugs[$index] ?? ($slugs[0] ?? '');
                }
            }

            return $value;
        }

        if (\is_array($value)) {
            // The fixture was exported from one locale and carries it inside
            // every internal link; a run in another locale must not keep it.
            if (isset($value['provider'], $value['locale']) && 'page' === $value['provider']) {
                $value['locale'] = $locale;
            }

            foreach ($value as $key => $item) {
                $value[$key] = $this->resolveReferences($item, $mediaIds, $pageUuids, $locale, $themeSlugs);
            }
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createPage(
        string $title,
        string $webspaceKey,
        string $locale,
        string $url,
        array $data,
        string $parentUuid,
    ): PageInterface {
        $data = array_merge($data, [
            'title' => $title,
            'template' => 'iw_theme_default',
            'locale' => $locale,
            'url' => str_starts_with($url, '/') ? $url : '/' . $url,
        ]);

        $message = new CreatePageMessage($webspaceKey, $parentUuid, $data);

        /** @var PageInterface $page */
        $page = $this->handle(new Envelope($message, [new EnableFlushStamp()]));

        return $page;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function modifyPage(string $uuid, string $locale, array $data): void
    {
        $data = array_merge($data, ['template' => 'iw_theme_default', 'locale' => $locale]);

        $this->handle(new Envelope(
            new \Sulu\Page\Application\Message\ModifyPageMessage(['uuid' => $uuid], $data),
            [new EnableFlushStamp()],
        ));
    }

    private function publish(string $uuid, string $locale): void
    {
        $this->handle(new Envelope(
            new ApplyWorkflowTransitionPageMessage(
                identifier: ['uuid' => $uuid],
                locale: $locale,
                transitionName: WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH,
            ),
            [new EnableFlushStamp()],
        ));
    }

    /**
     * Build a URL-safe slug from a page title.
     */
    private function slugify(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = (string) preg_replace('/[^a-z0-9]+/', '-', $slug);

        return trim($slug, '-') ?: 'demo';
    }
}
