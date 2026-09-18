<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use ItechWorld\SuluTailwindThemeBundle\Service\AppearanceOverrideAudit;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * What the audit must see, and what it must not report.
 *
 * Both mistakes are expensive. A missed orphan stays invisible forever, since
 * the page renders fine and the admin never offers the site again. A false
 * positive sends an editor hunting for a problem that is not there, in content
 * that is working exactly as intended.
 */
final class AppearanceOverrideAuditTest extends TestCase
{
    #[Test]
    public function itReportsAnOverrideNamingASiteTheArticleLeft(): void
    {
        $data = [
            'blocks' => [
                ['type' => 'text', 'variant' => ['_default' => 'sombre', 'site-gone' => 'nuit-noire']],
            ],
        ];

        $orphans = $this->audit()->orphansIn($data, ['site-a', 'site-b']);

        self::assertSame(
            [['webspace' => 'site-gone', 'property' => 'blocks.0.variant']],
            $orphans,
        );
    }

    #[Test]
    public function itSaysNothingAboutASiteTheArticleIsStillPublishedOn(): void
    {
        $data = [
            'blocks' => [
                ['variant' => ['_default' => 'sombre', 'site-b' => 'nuit-noire']],
            ],
        ];

        self::assertSame([], $this->audit()->orphansIn($data, ['site-a', 'site-b']));
    }

    /**
     * Blocks nest, and a project may put an appearance field anywhere, so the
     * search goes by shape rather than by a list of property names that would
     * go stale the day someone adds a block.
     */
    #[Test]
    public function itLooksInsideNestedBlocks(): void
    {
        $data = [
            'blocks' => [
                [
                    'type' => 'cards',
                    'cards' => [
                        ['linkStyle' => ['_default' => 'primary', 'site-gone' => 'outline']],
                    ],
                ],
            ],
        ];

        $orphans = $this->audit()->orphansIn($data, ['site-a']);

        self::assertSame('blocks.0.cards.0.linkStyle', $orphans[0]['property']);
    }

    /**
     * Ordinary content is the overwhelming majority of what the audit walks
     * through, and none of it may be reported.
     */
    #[Test]
    public function itIgnoresEverythingThatIsNotAnOverrideMap(): void
    {
        $data = [
            'title' => 'A plain string',
            'blocks' => [
                ['variant' => 'sombre', 'tags' => ['a', 'b'], 'settings' => ['radius' => 'lg']],
            ],
        ];

        self::assertSame([], $this->audit()->orphansIn($data, ['site-a']));
    }

    #[Test]
    public function itReportsEveryOrphanedSiteOfTheSameValue(): void
    {
        $data = [
            'variant' => ['_default' => 'clair', 'gone-one' => 'a', 'gone-two' => 'b'],
        ];

        $orphans = $this->audit()->orphansIn($data, ['site-a']);

        self::assertSame(['gone-one', 'gone-two'], array_column($orphans, 'webspace'));
    }

    private function audit(): AppearanceOverrideAudit
    {
        return new AppearanceOverrideAudit($this->createStub(EntityManagerInterface::class));
    }
}
