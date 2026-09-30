<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use ItechWorld\SuluTailwindThemeBundle\Service\ButtonStyleAudit;
use ItechWorld\SuluTailwindThemeBundle\Service\SnippetWebspaceLocator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Which buttons the style check reports, and which it must leave alone.
 *
 * A missed button keeps the generic look of app.css on a live site, with
 * nothing to point at it. A false positive sends an editor to change a style
 * that renders exactly as the theme intends, and teaches them to ignore the
 * check.
 */
final class ButtonStyleAuditTest extends TestCase
{
    private const SLUGS = [
        'site-a' => ['primary-full', 'primary-stroke'],
        'site-b' => ['brand', 'outline'],
    ];

    #[Test]
    public function itReportsAStyleTheThemeOfTheSiteLacks(): void
    {
        $data = ['blocks' => [['type' => 'cta', 'button' => self::button('secondary')]]];

        self::assertSame(
            [['site' => 'site-a', 'style' => 'secondary', 'path' => 'blocks.0.button']],
            $this->audit()->unknownStylesIn($data, ['site-a'], self::SLUGS),
        );
    }

    #[Test]
    public function itSaysNothingAboutAStyleTheThemeDefines(): void
    {
        $data = ['blocks' => [['type' => 'cta', 'button' => self::button('primary-full')]]];

        self::assertSame([], $this->audit()->unknownStylesIn($data, ['site-a'], self::SLUGS));
    }

    /**
     * No style is the default style of the variant, not a missing one.
     */
    #[Test]
    public function aButtonWithoutStyleIsNotReported(): void
    {
        $data = ['blocks' => [['button' => self::button('')], ['button' => self::button(null)]]];

        self::assertSame([], $this->audit()->unknownStylesIn($data, ['site-a'], self::SLUGS));
    }

    /**
     * A content shown on two sites is checked against each theme: a slug that
     * is right on one can be wrong on the other.
     */
    #[Test]
    public function eachSiteIsCheckedAgainstItsOwnTheme(): void
    {
        $data = ['button' => self::button('brand')];

        self::assertSame(
            [['site' => 'site-a', 'style' => 'brand', 'path' => 'button']],
            $this->audit()->unknownStylesIn($data, ['site-a', 'site-b'], self::SLUGS),
        );
    }

    /**
     * A per-site choice is read the way the page reads it: the override on its
     * site, the shared value everywhere else.
     */
    #[Test]
    public function aPerSiteStyleIsCheckedOnTheSiteItApplies(): void
    {
        $data = ['button' => self::button(['_default' => 'primary-full', 'site-b' => 'brand'])];
        self::assertSame([], $this->audit()->unknownStylesIn($data, ['site-a', 'site-b'], self::SLUGS));

        $data = ['button' => self::button(['_default' => 'primary-full', 'site-b' => 'gone'])];
        self::assertSame(
            [['site' => 'site-b', 'style' => 'gone', 'path' => 'button']],
            $this->audit()->unknownStylesIn($data, ['site-a', 'site-b'], self::SLUGS),
        );
    }

    #[Test]
    public function nestedButtonsAreFoundWithTheirPath(): void
    {
        $data = [
            'blocks' => [
                ['type' => 'timeline', 'steps' => [
                    ['ctaButtons' => [['type' => 'button', 'button' => self::button('primary-full')]]],
                    ['ctaButtons' => [['type' => 'button', 'button' => self::button('accent')]]],
                ]],
            ],
            // The mega menu keeps its call-to-action at the top level.
            'cta' => self::button('secondary'),
        ];

        self::assertSame(
            ['blocks.0.steps.1.ctaButtons.0.button', 'cta'],
            array_column($this->audit()->unknownStylesIn($data, ['site-a'], self::SLUGS), 'path'),
        );
    }

    /**
     * A snippet picked from a page names no site. Its style is only wrong
     * when no theme at all defines it.
     */
    #[Test]
    public function aContentWithoutSiteIsComparedWithEveryTheme(): void
    {
        $known = ['button' => self::button('brand')];
        self::assertSame([], $this->audit()->unknownStylesIn($known, [], self::SLUGS));

        $unknown = ['button' => self::button('secondary')];
        self::assertSame(
            [['site' => ButtonStyleAudit::ANY_SITE, 'style' => 'secondary', 'path' => 'button']],
            $this->audit()->unknownStylesIn($unknown, [], self::SLUGS),
        );

        // Its per-site choices still name a site, checked against that theme.
        $scoped = ['button' => self::button(['_default' => 'brand', 'site-a' => 'brand'])];
        self::assertSame(
            [['site' => 'site-a', 'style' => 'brand', 'path' => 'button']],
            $this->audit()->unknownStylesIn($scoped, [], self::SLUGS),
        );
    }

    /**
     * A site without a theme is the check's own warning, not a button's.
     */
    #[Test]
    public function aSiteWithoutThemeIsSkipped(): void
    {
        $data = ['button' => self::button('secondary')];

        self::assertSame([], $this->audit()->unknownStylesIn($data, ['site-without-theme'], self::SLUGS));
    }

    /**
     * Only a `link` beside a `style` is a button: a Sulu link value, a list,
     * or a block with a `style` of its own is not.
     */
    #[Test]
    public function onlyTheShapeOfAButtonIsRead(): void
    {
        $data = [
            'blocks' => [
                ['type' => 'hero', 'style' => 'overlay'],
                ['type' => 'text', 'link' => ['provider' => 'page', 'href' => 'uuid']],
            ],
            'list' => ['secondary', 'accent'],
        ];

        self::assertSame([], $this->audit()->unknownStylesIn($data, ['site-a'], self::SLUGS));
    }

    #[Test]
    public function findingsAreFoldedPerContentSiteAndStyle(): void
    {
        $finding = static fn (string $locale, string $stage, string $path, string $style = 'secondary', string $title = 'Timeline'): array => [
            'kind' => 'page',
            'id' => 'uuid-1',
            'title' => $title,
            'locale' => $locale,
            'stage' => $stage,
            'site' => 'site-a',
            'style' => $style,
            'path' => $path,
        ];

        $groups = ButtonStyleAudit::byContent([
            $finding('en', 'draft', 'blocks.0.button', title: ''),
            $finding('en', 'draft', 'blocks.1.button'),
            $finding('en', 'live', 'blocks.0.button'),
            $finding('en', 'draft', 'blocks.0.button'),
            $finding('fr', 'live', 'blocks.2.button', 'accent'),
        ]);

        self::assertSame([
            [
                'kind' => 'page',
                'id' => 'uuid-1',
                'title' => 'Timeline',
                'site' => 'site-a',
                'style' => 'secondary',
                'buttons' => 2,
                'versions' => ['en draft', 'en live'],
            ],
            [
                'kind' => 'page',
                'id' => 'uuid-1',
                'title' => 'Timeline',
                'site' => 'site-a',
                'style' => 'accent',
                'buttons' => 1,
                'versions' => ['fr live'],
            ],
        ], $groups);
    }

    #[Test]
    public function nothingIsReadWithoutAnyTheme(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('getConnection');

        self::assertSame([], (new ButtonStyleAudit($entityManager, new SnippetWebspaceLocator()))->findUnknown([]));
    }

    /**
     * @param mixed $style The stored style, plain or per site
     *
     * @return array{link: array<string, string>, style: mixed, display: string}
     */
    private static function button(mixed $style): array
    {
        return ['link' => ['provider' => 'page', 'href' => 'uuid'], 'style' => $style, 'display' => 'button'];
    }

    private function audit(): ButtonStyleAudit
    {
        return new ButtonStyleAudit($this->createStub(EntityManagerInterface::class), new SnippetWebspaceLocator());
    }
}
