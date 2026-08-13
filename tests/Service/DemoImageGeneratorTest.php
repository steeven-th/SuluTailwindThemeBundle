<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Service\DemoImageGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Covers the bare-installation path - no theme created yet, or none assigned to
 * the webspace - which a project with a theme in place never exercises.
 */
class DemoImageGeneratorTest extends TestCase
{
    private DemoImageGenerator $generator;

    protected function setUp(): void
    {
        if (!\extension_loaded('gd')) {
            $this->markTestSkipped('The gd extension is required to generate demo images.');
        }

        $this->generator = new DemoImageGenerator();
    }

    /**
     * No theme at all: the caller passes an empty palette and still gets a real
     * image rather than an exception or a blank file.
     */
    public function testGeneratesWithoutAnyTheme(): void
    {
        $png = $this->generator->generate(1, []);

        $this->assertNotSame('', $png);
        $this->assertSame("\x89PNG\r\n\x1a\n", substr($png, 0, 8), 'Expected a PNG signature.');

        $size = getimagesizefromstring($png);
        $this->assertIsArray($size);
        $this->assertGreaterThan(0, $size[0]);
        $this->assertGreaterThan(0, $size[1]);
    }

    /**
     * A theme with usable colors drives the gradient, so the same index must
     * produce different pixels than the neutral fallback.
     */
    public function testThemeColorsChangeTheOutput(): void
    {
        $neutral = $this->generator->generate(1, []);
        $themed = $this->generator->generate(1, [['#ff0000', '#00ff00']]);

        $this->assertNotSame($neutral, $themed);
    }

    /**
     * Every index has to be drawable: the command walks the whole pool, and a
     * modulo that fell off the end of either table would only show up there.
     */
    public function testEveryIndexOfThePoolIsDrawable(): void
    {
        for ($i = 1; $i <= $this->generator->variantCount() * 2 + 1; ++$i) {
            $png = $this->generator->generate($i, []);
            $this->assertSame("\x89PNG\r\n\x1a\n", substr($png, 0, 8), "Index {$i} produced no PNG.");
        }
    }

    /**
     * The pool is meant to look varied; identical shapes across the whole run
     * would defeat the point of generating several placeholders.
     */
    public function testConsecutiveIndexesDifferInShape(): void
    {
        $dimensions = [];
        for ($i = 1; $i <= $this->generator->variantCount(); ++$i) {
            $size = getimagesizefromstring($this->generator->generate($i, []));
            $this->assertIsArray($size);
            $dimensions[] = $size[0] . 'x' . $size[1];
        }

        $this->assertGreaterThan(1, \count(array_unique($dimensions)), 'All placeholders share one size.');
    }

    /**
     * A malformed color must not abort the run: demo content is a convenience,
     * and a theme holding a broken value still deserves its placeholders.
     */
    public function testMalformedColorFallsBackInsteadOfFailing(): void
    {
        $png = $this->generator->generate(1, [['not-a-color', '#zzzzzz']]);

        $this->assertSame("\x89PNG\r\n\x1a\n", substr($png, 0, 8));
    }
}
