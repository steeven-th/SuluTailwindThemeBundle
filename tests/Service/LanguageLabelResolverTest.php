<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Service\LanguageLabelResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Locales reach this resolver straight from the webspace XML, so it has to cope
 * with whatever an integrator wrote there: a bare language, a regional variant,
 * or a code ICU has never heard of. Returning an empty label would leave a blank
 * entry in the switcher, which is worse than showing a raw code.
 */
class LanguageLabelResolverTest extends TestCase
{
    private LanguageLabelResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new LanguageLabelResolver();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function shortCodeProvider(): iterable
    {
        yield 'language only' => ['fr', 'FR'];
        yield 'regional variant' => ['pt_BR', 'PT-BR'];
        yield 'already uppercase' => ['EN', 'EN'];
    }

    #[DataProvider('shortCodeProvider')]
    public function testShortCodeIsTheDefault(string $locale, string $expected): void
    {
        $this->assertSame($expected, $this->resolver->resolve($locale));
        $this->assertSame($expected, $this->resolver->resolve($locale, LanguageLabelResolver::FORMAT_CODE));
    }

    public function testNativeNamesAreCapitalised(): void
    {
        // ICU returns "français" lowercased, which reads as a typo in a menu.
        $this->assertSame('Français', $this->resolver->resolve('fr', LanguageLabelResolver::FORMAT_NATIVE));
        $this->assertSame('English', $this->resolver->resolve('en', LanguageLabelResolver::FORMAT_NATIVE));
        $this->assertSame('Deutsch', $this->resolver->resolve('de', LanguageLabelResolver::FORMAT_NATIVE));
    }

    public function testTranslatedNamesFollowTheDisplayLocale(): void
    {
        $this->assertSame('Anglais', $this->resolver->resolve('en', LanguageLabelResolver::FORMAT_TRANSLATED, 'fr'));
        $this->assertSame('French', $this->resolver->resolve('fr', LanguageLabelResolver::FORMAT_TRANSLATED, 'en'));
    }

    public function testTranslatedFallsBackToTheLocaleItself(): void
    {
        // No display locale given: the name is expressed in the locale itself,
        // which is the same as the native format.
        $this->assertSame(
            $this->resolver->resolve('de', LanguageLabelResolver::FORMAT_NATIVE),
            $this->resolver->resolve('de', LanguageLabelResolver::FORMAT_TRANSLATED),
        );
    }

    /**
     * A webspace may declare a locale ICU has no entry for. The switcher still
     * needs something to render.
     */
    public function testUnknownLocalesFallBackToTheCode(): void
    {
        $this->assertSame('ZZ', $this->resolver->resolve('zz', LanguageLabelResolver::FORMAT_NATIVE));
        $this->assertSame('ZZ', $this->resolver->resolve('zz', LanguageLabelResolver::FORMAT_TRANSLATED, 'fr'));
    }

    public function testUnknownFormatFallsBackToTheCode(): void
    {
        $this->assertSame('FR', $this->resolver->resolve('fr', 'something-else'));
    }

    public function testEmptyLocaleYieldsEmptyLabel(): void
    {
        $this->assertSame('', $this->resolver->resolve(''));
        $this->assertSame('', $this->resolver->resolve('   '));
    }

    public function testRegionalVariantsKeepTheirRegion(): void
    {
        // Both are declarable in a webspace and must stay distinguishable.
        $this->assertNotSame(
            $this->resolver->resolve('pt_BR', LanguageLabelResolver::FORMAT_NATIVE),
            $this->resolver->resolve('pt', LanguageLabelResolver::FORMAT_NATIVE),
        );
    }
}
