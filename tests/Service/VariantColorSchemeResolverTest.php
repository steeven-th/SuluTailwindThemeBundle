<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Service\OklchPaletteGenerator;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeProvider;
use ItechWorld\SuluTailwindThemeBundle\Service\VariantColorSchemeResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(VariantColorSchemeResolver::class)]
final class VariantColorSchemeResolverTest extends TestCase
{
    /**
     * @param array<string, mixed> $tokens
     */
    private function resolver(array $tokens): VariantColorSchemeResolver
    {
        $provider = $this->createStub(ThemeProvider::class);
        $provider->method('getTokens')->willReturn($tokens);
        $provider->method('getCurrentWebspaceKey')->willReturn(null);

        return new VariantColorSchemeResolver($provider, new OklchPaletteGenerator());
    }

    /**
     * @return array<string, mixed>
     */
    private function tokensWithGradientVariant(string $from, string $to): array
    {
        return [
            'colors' => [['role' => 'background', 'slug' => 'background', 'value' => '#ffffff']],
            'gradients' => [['slug' => 'band', 'stops' => [['color' => $from], ['color' => $to, 'position' => 100]]]],
            'blockVariants' => [['slug' => 'band', 'label' => 'Band', 'blockBg' => 'gradient:band']],
        ];
    }

    #[Test]
    public function aGradientBackgroundIsJudgedOnItsFallback(): void
    {
        self::assertSame(
            VariantColorSchemeResolver::SCHEME_DARK,
            $this->resolver($this->tokensWithGradientVariant('#3a4b8f', '#172f57'))->resolve('band'),
        );
        self::assertSame(
            VariantColorSchemeResolver::SCHEME_LIGHT,
            $this->resolver($this->tokensWithGradientVariant('#fff8e1', '#ffe0b2'))->resolve('band'),
        );
    }

    #[Test]
    public function anOrphanGradientLeavesThePageBackgroundToDecide(): void
    {
        $tokens = $this->tokensWithGradientVariant('#000000', '#000000');
        $tokens['gradients'] = [];

        self::assertSame(VariantColorSchemeResolver::SCHEME_LIGHT, $this->resolver($tokens)->resolve('band'));
    }
}
