<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Service\TitleMarkupRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The cases below are the reference set for the title markup.
 *
 * The admin field type parses the same syntax in JavaScript to decide which
 * button is enabled. Keep the two in sync: any case added here should have a
 * counterpart on the JS side.
 */
#[CoversClass(TitleMarkupRenderer::class)]
final class TitleMarkupRendererTest extends TestCase
{
    private TitleMarkupRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new TitleMarkupRenderer();
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function provideRenderCases(): array
    {
        return [
            'plain text passes through' => [
                'Notre expertise',
                'Notre expertise',
            ],
            'a bare marker becomes a highlight' => [
                'Notre [[expertise]]. Vos besoins.',
                'Notre <span class="iw-highlight">expertise</span>. Vos besoins.',
            ],
            'a colored marker uses the palette class' => [
                'Notre [[accent:expertise]].',
                'Notre <span class="iw-text--accent">expertise</span>.',
            ],
            'a shade is part of the color name' => [
                '[[primary-700:Notre]] expertise',
                '<span class="iw-text--primary-700">Notre</span> expertise',
            ],
            'a slug with dashes is a valid color' => [
                '[[rose-employeur:Nos]] offres',
                '<span class="iw-text--rose-employeur">Nos</span> offres',
            ],
            'several markers in one title' => [
                '[[Notre]] expertise, [[accent:vos]] besoins',
                '<span class="iw-highlight">Notre</span> expertise, <span class="iw-text--accent">vos</span> besoins',
            ],
            'a marker can cover part of a word' => [
                "l'[[ADN]] du projet",
                'l&#039;<span class="iw-highlight">ADN</span> du projet',
            ],
            'newlines become line breaks' => [
                "Notre expertise.\nVos besoins.",
                "Notre expertise.<br>\nVos besoins.",
            ],
            'html in the stored text is escaped, never rendered' => [
                'Notre <b>expertise</b> & <script>alert(1)</script>',
                'Notre &lt;b&gt;expertise&lt;/b&gt; &amp; &lt;script&gt;alert(1)&lt;/script&gt;',
            ],
            'html inside a marker is escaped too' => [
                '[[<img src=x onerror=alert(1)>]]',
                '<span class="iw-highlight">&lt;img src=x onerror=alert(1)&gt;</span>',
            ],
            'an unclosed marker stays literal' => [
                'Notre [[expertise',
                'Notre [[expertise',
            ],
            'an uppercase color name is not a color prefix' => [
                '[[Accent:expertise]]',
                '<span class="iw-highlight">Accent:expertise</span>',
            ],
            'an empty marker is left alone' => [
                'Notre [[]] expertise',
                'Notre [[]] expertise',
            ],
        ];
    }

    #[Test]
    #[DataProvider('provideRenderCases')]
    public function itRendersTheStoredMarkup(string $stored, string $expected): void
    {
        self::assertSame($expected, $this->renderer->render($stored));
    }

    #[Test]
    public function itHonoursAColorWhereverTheTitleSits(): void
    {
        // A block title used to discard the colour and fall back to its
        // variant, which made `title_editor.blocks.color` offer a button whose
        // effect was thrown away: the marker was stored, and the page still
        // showed the accent. Which buttons the admin offers is a config
        // decision; the page shows what was written.
        self::assertSame(
            'Notre <span class="iw-text--accent">expertise</span>.',
            $this->renderer->render('Notre [[accent:expertise]].'),
        );

        // Without a colour the marker still follows the variant, or the theme
        // accent outside one, which is what `.iw-highlight` resolves to.
        self::assertSame(
            'Notre <span class="iw-highlight">expertise</span>.',
            $this->renderer->render('Notre [[expertise]].'),
        );
    }

    #[Test]
    public function noTemplateAsksForTheColourToBeDiscarded(): void
    {
        // The flag is gone from the renderer. A template still passing a second
        // argument would fail at render time, and only on the page carrying
        // that title.
        // Recursive: `templates/**/*.twig` only descends one level, and would
        // miss most of the tree.
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(\dirname(__DIR__, 2) . '/templates'),
        );

        $offenders = [];
        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || 'twig' !== $file->getExtension()) {
                continue;
            }

            if (1 === preg_match('/title_markup\([^()]*,\s*(?:true|false)\s*\)/', (string) file_get_contents($file->getPathname()))) {
                $offenders[] = $file->getBasename();
            }
        }

        self::assertSame([], $offenders, 'These templates still pass the removed flag.');
    }

    #[Test]
    public function itReturnsAnEmptyStringForAnEmptyTitle(): void
    {
        self::assertSame('', $this->renderer->render(null));
        self::assertSame('', $this->renderer->render(''));
        self::assertSame('', $this->renderer->render("  \n "));
    }

    #[Test]
    public function itStripsMarkupDownToPlainText(): void
    {
        self::assertSame(
            'Notre expertise. Vos besoins.',
            $this->renderer->toPlainText("Notre [[accent:expertise]].\nVos [[besoins]]."),
        );
    }

    #[Test]
    public function itLeavesPlainTextUntouchedWhenStripping(): void
    {
        self::assertSame('Notre expertise', $this->renderer->toPlainText('Notre expertise'));
        self::assertSame('', $this->renderer->toPlainText(null));
    }

    #[Test]
    public function itDoesNotEscapeWhenStripping(): void
    {
        // toPlainText() feeds attributes and meta tags, which Twig escapes on
        // its own. Escaping here would double-encode them.
        self::assertSame("l'ADN & co", $this->renderer->toPlainText("l'[[ADN]] & co"));
    }

    #[Test]
    public function itDetectsWhetherATitleCarriesMarkup(): void
    {
        self::assertFalse($this->renderer->hasMarkup(null));
        self::assertFalse($this->renderer->hasMarkup(''));
        self::assertFalse($this->renderer->hasMarkup('Notre expertise'));
        self::assertTrue($this->renderer->hasMarkup('Notre [[expertise]]'));
        self::assertTrue($this->renderer->hasMarkup('[[accent:Notre]] expertise'));
        self::assertTrue($this->renderer->hasMarkup("Notre\nexpertise"));
    }
}
