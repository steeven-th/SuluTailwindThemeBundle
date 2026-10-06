<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Command;

use Doctrine\ORM\EntityManagerInterface;
use ItechWorld\SuluTailwindThemeBundle\Command\FooterColorsMigrateCommand;
use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;
use ItechWorld\SuluTailwindThemeBundle\Repository\ThemeConfigRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The footer leaves its block variant for colors of its own, and must look
 * the same afterwards - or say why it cannot.
 */
final class FooterColorsMigrateCommandTest extends TestCase
{
    private const VARIANTS = [
        [
            'slug' => 'light',
            'blockBg' => '#ffffff',
            'paragraph' => '#334155',
            'title' => '#0f172a',
            'link' => 'ref:primary',
        ],
        [
            'slug' => 'dark',
            'blockBg' => 'ref:secondary-950',
            'paragraph' => '#cbd5e1',
            'title' => '#ffffff',
            'link' => '#ffffff',
            'linkHover' => '#f97316',
            'hr' => 'rgba(255,255,255,0.2)',
            'highlight' => 'ref:accent',
        ],
    ];

    #[Test]
    public function theColorsOfTheVariantReplaceIt(): void
    {
        $theme = $this->theme(['type' => 'columns', 'variant' => 'dark']);

        $tester = $this->tester([$theme], flushes: 1);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame([
            'type' => 'columns',
            'colors' => [
                'bg' => 'ref:secondary-950',
                'text' => '#cbd5e1',
                'title' => '#ffffff',
                'link' => '#ffffff',
                'linkHover' => '#f97316',
                'divider' => 'rgba(255,255,255,0.2)',
                'accent' => 'ref:accent',
            ],
        ], $theme->getFooterConfig());
    }

    /**
     * An empty variant meant the first one, at render time.
     */
    #[Test]
    public function anEmptyVariantTakesTheFirstOne(): void
    {
        $theme = $this->theme(['type' => 'columns', 'variant' => '']);

        $this->tester([$theme], flushes: 1)->execute([]);

        self::assertSame('#ffffff', $theme->getFooterConfig()['colors']['bg']);
        self::assertArrayNotHasKey('variant', $theme->getFooterConfig());
    }

    /**
     * A theme saved after the upgrade may hold colors already: what its
     * editor picked wins, the gaps are filled.
     */
    #[Test]
    public function aColorAlreadySetIsKept(): void
    {
        $theme = $this->theme(['variant' => 'dark', 'colors' => ['linkHover' => '#22c55e', 'bg' => '']]);

        $this->tester([$theme], flushes: 1)->execute([]);

        self::assertSame('#22c55e', $theme->getFooterConfig()['colors']['linkHover']);
        self::assertSame('ref:secondary-950', $theme->getFooterConfig()['colors']['bg']);
    }

    #[Test]
    public function aDryRunWritesNothing(): void
    {
        $footer = ['type' => 'columns', 'variant' => 'dark'];
        $theme = $this->theme($footer);

        $tester = $this->tester([$theme], flushes: 0);
        $tester->execute(['--dry-run' => true]);

        self::assertSame($footer, $theme->getFooterConfig());
        self::assertStringContainsString('1 theme(s) would be updated', $tester->getDisplay());
    }

    #[Test]
    public function runningItTwiceChangesNothing(): void
    {
        $theme = $this->theme(['variant' => 'dark']);
        $this->tester([$theme], flushes: 1)->execute([]);
        $migrated = $theme->getFooterConfig();

        $tester = $this->tester([$theme], flushes: 0);
        $tester->execute([]);

        self::assertSame($migrated, $theme->getFooterConfig());
        self::assertStringContainsString('Nothing to migrate', $tester->getDisplay());
    }

    /**
     * The separator settings and the block border of the variant have no
     * footer setting. Silence would leave a footer changed with nobody able to
     * say why.
     */
    #[Test]
    public function whatCannotBeCarriedIsReported(): void
    {
        $variants = [
            ['slug' => 'hidden', 'separatorMode' => 'none'],
            ['slug' => 'dashed', 'separatorMode' => 'style', 'separatorStyle' => 'dashed', 'blockBorder' => '#e2e8f0', 'blockBorderWidth' => '2'],
            ['slug' => 'plain', 'separatorStyle' => 'solid'],
        ];
        $hidden = $this->theme(['variant' => 'hidden'], $variants, 'hidden');
        $dashed = $this->theme(['variant' => 'dashed'], $variants, 'dashed');
        $plain = $this->theme(['variant' => 'plain'], $variants, 'plain');

        $tester = $this->tester([$hidden, $dashed, $plain], flushes: 0);
        $tester->execute(['--dry-run' => true]);
        $display = preg_replace('/\s+/', ' ', $tester->getDisplay());

        self::assertStringContainsString('hidden: the divider was hidden (separator mode "none") and now shows.', $display);
        self::assertStringContainsString('dashed: the divider was drawn "dashed" and is now solid.', $display);
        self::assertStringContainsString('.iw-footer { border: 2px solid #e2e8f0; }', $display);
        self::assertStringNotContainsString('plain: the', $display);
    }

    /**
     * @param array<string, mixed>       $footer
     * @param list<array<string, mixed>> $variants
     */
    private function theme(array $footer, array $variants = self::VARIANTS, string $name = 'corporate'): ThemeConfig
    {
        $theme = new ThemeConfig();
        $theme->setName($name);
        $theme->setTokens(['blockVariants' => $variants]);
        $theme->setFooterConfig($footer);

        return $theme;
    }

    /**
     * @param list<ThemeConfig> $themes
     */
    private function tester(array $themes, int $flushes): CommandTester
    {
        $repository = $this->createStub(ThemeConfigRepository::class);
        $repository->method('findAll')->willReturn($themes);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly($flushes))->method('flush');

        return new CommandTester(new FooterColorsMigrateCommand($entityManager, $repository));
    }
}
