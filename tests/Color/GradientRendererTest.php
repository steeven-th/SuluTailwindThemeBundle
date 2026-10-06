<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Color;

use ItechWorld\SuluTailwindThemeBundle\Color\Gradient;
use ItechWorld\SuluTailwindThemeBundle\Color\GradientRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GradientRenderer::class)]
final class GradientRendererTest extends TestCase
{
    /**
     * A renderer whose palette knows `ref:secondary` only.
     */
    private function renderer(): GradientRenderer
    {
        return new GradientRenderer(static fn (string $value): ?string => match (true) {
            'ref:secondary' === $value => '#172F57',
            str_starts_with($value, 'ref:') => null,
            default => $value,
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    private function gradient(array $data): Gradient
    {
        $gradient = Gradient::fromArray(['slug' => 'test'] + $data);
        self::assertNotNull($gradient);

        return $gradient;
    }

    #[Test]
    public function itWritesALinearGradientWithItsOverlayOnTop(): void
    {
        $gradient = $this->gradient([
            'angle' => 180,
            'stops' => [
                ['color' => '#3A4B8F', 'opacity' => 100, 'position' => 0],
                ['color' => 'ref:secondary', 'opacity' => 100, 'position' => 100],
            ],
            'overlay' => ['color' => '#000000', 'opacity' => 20],
        ]);

        self::assertSame(
            'linear-gradient(rgb(0 0 0 / 0.2), rgb(0 0 0 / 0.2)), linear-gradient(180deg, #3a4b8f 0%, #172f57 100%)',
            $this->renderer()->image($gradient),
        );
    }

    #[Test]
    public function itAppliesTheOpacityOfEachStop(): void
    {
        $gradient = $this->gradient([
            'stops' => [
                ['color' => 'ref:secondary', 'opacity' => 76, 'position' => 0],
                ['color' => 'ref:secondary', 'opacity' => 0, 'position' => 100],
            ],
        ]);

        self::assertSame(
            'linear-gradient(180deg, rgb(23 47 87 / 0.76) 0%, transparent 100%)',
            $this->renderer()->image($gradient),
        );
    }

    #[Test]
    public function itWritesARadialGradientAtItsCenter(): void
    {
        $gradient = $this->gradient([
            'type' => 'radial',
            'position' => 'top-left',
            'stops' => [['color' => '#ffffff', 'position' => 0], ['color' => '#000000', 'position' => 100]],
        ]);

        self::assertSame(
            'radial-gradient(at top left, #ffffff 0%, #000000 100%)',
            $this->renderer()->image($gradient),
        );
    }

    #[Test]
    public function anUnresolvableOrUnsafeStopRendersTransparent(): void
    {
        $gradient = $this->gradient([
            'stops' => [
                ['color' => 'ref:deleted', 'position' => 0],
                ['color' => 'red;}body{display:none', 'position' => 50],
                ['color' => 'rgb(10 20 30)', 'opacity' => 50, 'position' => 100],
            ],
        ]);

        self::assertSame(
            'linear-gradient(180deg, transparent 0%, transparent 50%, color-mix(in srgb, rgb(10 20 30) 50%, transparent) 100%)',
            $this->renderer()->image($gradient),
        );
    }

    #[Test]
    public function theFallbackAveragesTheStopsInOklab(): void
    {
        // Black and white meet at OKLab L = 0.5, a darker gray than the sRGB
        // midpoint (#808080).
        $gradient = $this->gradient(['stops' => [['color' => '#000000'], ['color' => '#ffffff', 'position' => 100]]]);

        self::assertSame('#636363', $this->renderer()->fallback($gradient));
    }

    #[Test]
    public function theFallbackCompositesTheOverlayOnTop(): void
    {
        $gradient = $this->gradient([
            'stops' => [['color' => '#ffffff'], ['color' => '#ffffff', 'position' => 100]],
            'overlay' => ['color' => '#000000', 'opacity' => 50],
        ]);

        self::assertSame('#808080', $this->renderer()->fallback($gradient));
    }

    #[Test]
    public function theFallbackOfAFadeIsTranslucent(): void
    {
        $fade = $this->gradient(['stops' => [
            ['color' => '#ff0000', 'opacity' => 100],
            ['color' => '#ff0000', 'opacity' => 0, 'position' => 100],
        ]]);
        $invisible = $this->gradient(['stops' => [['color' => 'transparent'], ['color' => 'ref:deleted']]]);

        self::assertSame('#ff000080', $this->renderer()->fallback($fade));
        self::assertSame('transparent', $this->renderer()->fallback($invisible));
    }

    #[Test]
    public function anExplicitFallbackWins(): void
    {
        $gradient = $this->gradient([
            'stops' => [['color' => '#000000'], ['color' => '#ffffff']],
            'fallback' => 'ref:secondary',
        ]);
        $broken = $this->gradient([
            'stops' => [['color' => '#000000'], ['color' => '#000000']],
            'fallback' => 'ref:deleted',
        ]);

        self::assertSame('#172f57', $this->renderer()->fallback($gradient));
        self::assertSame('#000000', $this->renderer()->fallback($broken));
    }
}
