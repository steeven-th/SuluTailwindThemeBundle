<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards that the controls of a block all name the same colour.
 *
 * The arrows, dots and chevrons that move a visitor through a block drifted
 * apart one block at a time: the dots of one carousel read the variant rule
 * colour falling back to primary, the dots of another read plain
 * `currentColor` with no hook at all, and the accordion chevron read a third
 * thing. Three references for one kind of object, none of them settable - the
 * same drift the card gaps had before a shared token pulled them back.
 *
 * What holds them together is a cascade of three: the block's own hook, then
 * the shared `--iw-controls-on-content-color`, then `currentColor`. The last
 * step is what keeps an existing site still, since the text around a control
 * is already painted by the variant.
 *
 * Controls sitting on a media are deliberately absent: a variant can say
 * nothing about a photograph, so `.iw-gallery-nav` carries its own colour and
 * its own veil.
 */
final class NavigationControlsContractTest extends TestCase
{
    /**
     * The declarations that paint a control sitting on the content.
     *
     * Listed rather than detected: what counts as a control is a matter of
     * meaning, not of naming, and a rule painting something else would be
     * caught by no pattern worth writing.
     *
     * @return array<string, array{0: string}>
     */
    public static function controls(): array
    {
        return [
            'accordion chevron' => ['--iw-accordion-icon-color'],
            'linked pages dots' => ['--iw-block-linked-pages-nav-color'],
            'testimonial dots' => ['--iw-block-testimonial-dot-color'],
        ];
    }

    /**
     * Each control falls back to the shared token, then to currentColor.
     */
    #[Test]
    #[DataProvider('controls')]
    public function everyControlOnContentCascadesThroughTheSharedToken(string $hook): void
    {
        $css = self::stylesheet();

        self::assertStringContainsString(
            \sprintf('var(%s, var(--iw-controls-on-content-color, currentColor))', $hook),
            $css,
            \sprintf(
                '%s must read the shared control colour between its own hook and currentColor, '
                . 'so one setting reaches every control and a block can still be dressed on its own.',
                $hook,
            ),
        );
    }

    /**
     * The controls over a media keep a colour of their own.
     */
    #[Test]
    public function theControlsOverAMediaDoNotFollowTheContentColour(): void
    {
        $css = self::stylesheet();

        self::assertStringContainsString(
            '--_color: var(--iw-gallery-nav-color, white);',
            $css,
            'The gallery arrows sit over a photograph, which no variant can speak for: '
            . 'they keep their own colour rather than following the content one.',
        );
        self::assertStringNotContainsString(
            '--iw-gallery-nav-color, var(--iw-controls-on-content-color',
            $css,
        );
    }

    private static function stylesheet(): string
    {
        $path = \dirname(__DIR__, 2) . '/assets/styles/app.css';
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /**
     * Every button holding an arrow over a media wears the shared class.
     *
     * The colour and the veil of those buttons are read by `.iw-gallery-nav`
     * and by nothing else, so a slider that dresses its own buttons is a
     * slider the theme cannot reach. The image slider did exactly that, with
     * `bg-black/40 text-white rounded-full` written into the markup: neither
     * the admin nor an integrator could touch it, and it was invisible until
     * someone set a colour and watched that one slider ignore it.
     *
     * @return array<string, array{0: string}>
     */
    public static function mediaSliders(): array
    {
        return [
            'gallery slider' => ['templates/blocks/gallery/_style_slider.html.twig'],
            'gallery carousel' => ['templates/blocks/gallery/_style_carousel.html.twig'],
            'gallery wide carousel' => ['templates/blocks/gallery/_style_wide_carousel.html.twig'],
            'gallery filmstrip' => ['templates/blocks/gallery/_style_filmstrip.html.twig'],
            'testimonial slider' => ['templates/blocks/testimonial/_style_slider.html.twig'],
            'image slider' => ['templates/blocks/common/_image_slider.html.twig'],
        ];
    }

    #[Test]
    #[DataProvider('mediaSliders')]
    public function everyArrowOverAMediaWearsTheSharedClass(string $relative): void
    {
        $path = \dirname(__DIR__, 2) . '/' . $relative;
        self::assertFileExists($path);

        $template = (string) file_get_contents($path);

        self::assertStringContainsString(
            'iw-gallery-nav',
            $template,
            \sprintf(
                '%s draws arrows over a media without the shared class, so the theme colours never reach them.',
                basename($relative),
            ),
        );
    }
}
