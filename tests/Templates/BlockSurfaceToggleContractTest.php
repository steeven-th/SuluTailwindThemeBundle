<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards the switches that let a block drop part of its colour variant.
 *
 * A variant paints two surfaces on every block, the block itself and the
 * content wrapper, and each has a background and a border. All four are
 * independent answers, so all four are switchable from the block settings.
 *
 * The test exists because the coverage had already drifted once: `text` was
 * the only block with a block radius and no background switch, so its surface
 * was painted whatever the editor wanted, and nothing said so. A block added
 * later would drift the same way, silently, since a missing checkbox looks
 * exactly like a checkbox nobody has touched.
 */
final class BlockSurfaceToggleContractTest extends TestCase
{
    /**
     * The fragment holding the four switches.
     */
    private const SURFACES_FRAGMENT = 'block-surfaces.xml';

    /**
     * Blocks that compose their own switches instead of including the group.
     *
     * They still paint surfaces, so they still have to offer a way out of
     * them: the exception is about where the field is declared, never about
     * whether it exists.
     *
     * @var array<string, string>
     */
    private const COMPOSES_ITS_OWN = [
        'blocks/separator.xml' => 'The separator is a rule or a gap, so its background is a '
            . 'yes/no the editor flips often: it declares a toggler of its own rather than the '
            . 'shared checkbox.',
    ];

    /**
     * Every block template of the bundle, keyed for readable failures.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function blockTemplates(): array
    {
        $found = [];
        foreach (['blocks', 'blocks-code', 'blocks-code-open', 'blocks-form', 'blocks-form-bundle'] as $directory) {
            foreach (glob(self::root() . '/config/templates/' . $directory . '/*.xml') ?: [] as $path) {
                $name = $directory . '/' . basename($path);
                $found[$name] = [$path, $name];
            }
        }

        self::assertNotEmpty($found);

        return $found;
    }

    /**
     * Every block that paints a surface offers the switches for it.
     *
     * Reached through the fragments a block includes, not by the include line
     * alone: the two code blocks pull the group through `code-block-common`,
     * and a block composing its settings that way is no less covered.
     */
    #[Test]
    #[DataProvider('blockTemplates')]
    public function everyBlockWithASurfaceCanSwitchItOff(string $path, string $name): void
    {
        if (isset(self::COMPOSES_ITS_OWN[$name])) {
            self::assertMatchesRegularExpression(
                '/<property name="showBackground"/',
                (string) file_get_contents($path),
                \sprintf(
                    '%s is listed as composing its own switches, so it must declare them. %s',
                    $name,
                    self::COMPOSES_ITS_OWN[$name],
                ),
            );

            return;
        }

        self::assertTrue(
            self::includesFragment($path, self::SURFACES_FRAGMENT),
            \sprintf(
                "%s paints the block and content surfaces of its variant but offers no way to "
                . "switch them off. Include ../fragments/%s in its settings section, or list it "
                . "in COMPOSES_ITS_OWN with the reason.",
                $name,
                self::SURFACES_FRAGMENT,
            ),
        );
    }

    /**
     * The four switches exist, and every one of them defaults to on.
     *
     * A default of off would strip the variant from every block of every site
     * on upgrade, since published content carries none of these keys.
     */
    #[Test]
    public function theFourSwitchesDefaultToOn(): void
    {
        $fragments = self::fragmentSources(self::SURFACES_FRAGMENT);
        $source = implode("\n", $fragments);

        foreach (['showBackground', 'showBlockBorder', 'showContentBackground', 'showContentBorder'] as $property) {
            self::assertMatchesRegularExpression(
                '/<property name="' . $property . '" type="checkbox".*?default_value" value="true"/s',
                $source,
                \sprintf(
                    '%s must exist as a checkbox defaulting to true, or upgrading a site would '
                    . 'change what every published block looks like.',
                    $property,
                ),
            );
        }
    }

    /**
     * A style that emits the background attribute emits the border one too.
     *
     * Six styles of `text_images` build their own `<section>` instead of
     * extending the wrapper, so they carry these attributes by hand. One of
     * them left behind, or a seventh copied from it, would paint a border no
     * editor can remove, which is the very bug this feature answers.
     */
    #[Test]
    public function everyStyleEmittingTheBackgroundAttributeEmitsTheBorderOne(): void
    {
        $missing = [];
        foreach (self::twigTemplates() as $path) {
            $source = (string) file_get_contents($path);

            if (!str_contains($source, 'data-has-bg')) {
                continue;
            }

            if (!str_contains($source, 'data-has-border')) {
                $missing[] = self::relative($path);
            }
        }

        self::assertSame(
            [],
            $missing,
            "These emit the background attribute but not the border one, so the block border "
            . "cannot be switched off on them:\n  " . implode("\n  ", $missing),
        );
    }

    /**
     * Every content container carries the attributes of its surface.
     *
     * The compiler hangs the content background and border off them, so a
     * container without them is painted whatever the block settings say.
     */
    #[Test]
    public function everyContentContainerCarriesItsSurfaceAttributes(): void
    {
        $missing = [];
        foreach (self::twigTemplates() as $path) {
            $source = (string) file_get_contents($path);

            if (!str_contains($source, 'iw-block__content')) {
                continue;
            }

            if (!str_contains($source, 'contentSurfaceAttrs|raw')) {
                $missing[] = self::relative($path);
            }
        }

        self::assertSame(
            [],
            $missing,
            "These open a content container that carries none of its surface attributes, so its "
            . "background and border ignore the block settings:\n  " . implode("\n  ", $missing),
        );
    }

    /**
     * The switches are labelled, in every language the bundle ships.
     *
     * A checkbox whose label falls back to its translation key is unusable,
     * and these four sit side by side where the difference between block and
     * content is the whole point.
     */
    #[Test]
    public function everySwitchIsLabelledInEveryLanguage(): void
    {
        $keys = [
            'iw_sulu_tailwind_theme.settings_group_surfaces',
            'iw_sulu_tailwind_theme.show_background',
            'iw_sulu_tailwind_theme.show_block_border',
            'iw_sulu_tailwind_theme.show_content_background',
            'iw_sulu_tailwind_theme.show_content_border',
        ];

        foreach (['fr', 'en', 'de'] as $locale) {
            $path = self::root() . '/translations/admin.' . $locale . '.json';
            /** @var array<string, string> $messages */
            $messages = json_decode((string) file_get_contents($path), true, 512, \JSON_THROW_ON_ERROR);

            foreach ($keys as $key) {
                self::assertArrayHasKey(
                    $key,
                    $messages,
                    \sprintf('%s is missing from admin.%s.json.', $key, $locale),
                );
                self::assertNotSame('', trim($messages[$key]));
            }
        }
    }

    /**
     * Whether a template includes a fragment, directly or through another one.
     */
    private static function includesFragment(string $path, string $fragment, int $depth = 0): bool
    {
        if ($depth > 3 || !is_file($path)) {
            return false;
        }

        $source = (string) file_get_contents($path);
        if (str_contains($source, $fragment)) {
            return true;
        }

        preg_match_all('/href="([^"]*fragments\/[\w-]+\.xml|[\w-]+\.xml)"/', $source, $matches);
        foreach ($matches[1] as $href) {
            $target = self::root() . '/config/templates/fragments/' . basename($href);
            if (self::includesFragment($target, $fragment, $depth + 1)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The fragment and everything it includes, as raw sources.
     *
     * @return list<string>
     */
    private static function fragmentSources(string $fragment): array
    {
        $path = self::root() . '/config/templates/fragments/' . $fragment;
        $sources = [(string) file_get_contents($path)];

        preg_match_all('/href="([\w-]+\.xml)"/', $sources[0], $matches);
        foreach ($matches[1] as $href) {
            $included = self::root() . '/config/templates/fragments/' . $href;
            if (is_file($included)) {
                $sources[] = (string) file_get_contents($included);
            }
        }

        return $sources;
    }

    /**
     * Every Twig template of the bundle.
     *
     * @return list<string>
     */
    private static function twigTemplates(): array
    {
        $found = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::root() . '/templates'),
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.html.twig')) {
                $found[] = $file->getPathname();
            }
        }

        sort($found);

        return $found;
    }

    /**
     * Path relative to the bundle root, for readable failures.
     */
    private static function relative(string $path): string
    {
        return str_replace(self::root() . '/', '', $path);
    }

    /**
     * Absolute path of the bundle root.
     */
    private static function root(): string
    {
        return \dirname(__DIR__, 2);
    }
}
