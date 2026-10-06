<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;
use ItechWorld\SuluTailwindThemeBundle\Service\OklchPaletteGenerator;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeConfigResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The gradients the admin pickers receive, painted for fields outside the theme form.
 */
#[CoversClass(ThemeConfigResolver::class)]
final class ThemeConfigResolverGradientsTest extends TestCase
{
    #[Test]
    public function eachGradientTravelsPaintedWithThePalette(): void
    {
        $theme = new ThemeConfig();
        $theme->setTokens([
            'colors' => [['role' => 'secondary', 'slug' => 'secondary', 'value' => '#172f57']],
            'gradients' => [[
                'slug' => 'night',
                'label' => 'Night',
                'stops' => [
                    ['color' => '#3a4b8f', 'position' => 0],
                    ['color' => 'ref:secondary', 'position' => 100],
                ],
            ]],
        ]);

        $gradients = (new ThemeConfigResolver(new OklchPaletteGenerator()))->resolve($theme)['gradients'];

        self::assertSame([[
            'slug' => 'night',
            'label' => 'Night',
            'image' => 'linear-gradient(180deg, #3a4b8f 0%, #172f57 100%)',
            'fallback' => $gradients[0]['fallback'],
        ]], $gradients);
        self::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $gradients[0]['fallback']);
    }

    #[Test]
    public function anUnknownRefPaintsBlackAsTheCompilerDoes(): void
    {
        $theme = new ThemeConfig();
        $theme->setTokens(['gradients' => [[
            'slug' => 'broken',
            'stops' => [['color' => 'ref:deleted'], ['color' => '#ffffff', 'position' => 100]],
        ]]]);

        $gradients = (new ThemeConfigResolver(new OklchPaletteGenerator()))->resolve($theme)['gradients'];

        self::assertSame('linear-gradient(180deg, #000000 0%, #ffffff 100%)', $gradients[0]['image']);
        self::assertSame([], (new ThemeConfigResolver(new OklchPaletteGenerator()))->resolve(null)['gradients']);
    }
}
