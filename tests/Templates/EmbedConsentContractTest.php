<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Who decides that an iframe or code embed waits for consent, and how.
 *
 * The block says whether its content waits: only the editor knows whether the
 * URL is a tracker or a page of the site. How it then gets consent belongs to
 * the site, through `consent.mode`, since a cookie manager is wired once for
 * the whole project.
 *
 * The block field is an opt-out on purpose. A box defaulting to checked would
 * read unchecked on a block saved before it existed, and the next save would
 * drop the protection without the editor knowing. An absent opt-out means
 * protected, in the template and in the admin alike.
 */
final class EmbedConsentContractTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function consentForms(): iterable
    {
        yield 'iframe block' => ['config/templates/blocks/iframe.xml'];
        yield 'code block' => ['config/templates/fragments/code-block-common.xml'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function blockStyles(): iterable
    {
        foreach (['iframe', 'code'] as $block) {
            foreach (['default', 'fullwidth'] as $style) {
                yield $block . ' ' . $style => [\sprintf('templates/blocks/%s/_style_%s.html.twig', $block, $style)];
            }
        }
    }

    #[Test]
    #[DataProvider('consentForms')]
    public function theBlockOffersAnOptOutLeftUnchecked(string $relative): void
    {
        $xpath = self::xpath($relative);

        $default = $xpath->query(
            '//sulu:property[@name="loadWithoutConsent"][@type="checkbox"]/sulu:params/sulu:param[@name="default_value"]/@value',
        );
        self::assertNotFalse($default);
        self::assertSame(1, $default->count(), $relative . ' must offer the loadWithoutConsent checkbox.');
        self::assertSame('false', $default->item(0)?->nodeValue);

        $mechanism = $xpath->query('//sulu:property[@name="consentMode"]');
        self::assertNotFalse($mechanism);
        self::assertSame(0, $mechanism->count(), 'The consent mechanism is a site setting, not a block field.');
    }

    /**
     * The placeholder fields only make sense while the embed waits.
     */
    #[Test]
    #[DataProvider('consentForms')]
    public function thePlaceholderFieldsFollowTheOptOut(string $relative): void
    {
        $fields = self::xpath($relative)->query('//sulu:property[starts-with(@name, "consent")]');
        self::assertNotFalse($fields);
        self::assertGreaterThan(0, $fields->count());

        foreach ($fields as $field) {
            self::assertInstanceOf(\DOMElement::class, $field);
            self::assertSame(
                '__parent.loadWithoutConsent != true',
                $field->getAttribute('visibleCondition'),
                \sprintf('%s in %s', $field->getAttribute('name'), $relative),
            );
        }
    }

    #[Test]
    #[DataProvider('blockStyles')]
    public function anAbsentOptOutMeansTheEmbedWaits(string $relative): void
    {
        $source = (string) file_get_contents(self::root() . '/' . $relative);

        self::assertStringContainsString('consentRequired: not (loadWithoutConsent|default(false))', $source);
    }

    #[Test]
    public function anEmbedThatWaitsGetsTheSiteMechanism(): void
    {
        $html = self::frame(['consentRequired' => true], 'delegated');

        self::assertDoesNotMatchRegularExpression('/<iframe[^>]*\ssrc=/', $html);
        self::assertStringContainsString('data-consent-mode-value="delegated"', $html);
    }

    #[Test]
    public function anOptedOutEmbedLoadsStraightAway(): void
    {
        $html = self::frame(['consentRequired' => false], 'placeholder');

        self::assertMatchesRegularExpression('/<iframe[^>]*\ssrc="https:\/\/calendly\.com\/demo"/', $html);
        self::assertStringNotContainsString('data-controller="consent"', $html);
    }

    /**
     * A site with no consent requirement loads everything, even an embed set
     * to wait.
     */
    #[Test]
    public function aSiteWithoutConsentRequirementLoadsEverything(): void
    {
        $html = self::frame(['consentRequired' => true], 'none');

        self::assertMatchesRegularExpression('/<iframe[^>]*\ssrc="https:\/\/calendly\.com\/demo"/', $html);
    }

    /**
     * @param array<string, mixed> $vars
     */
    private static function frame(array $vars, string $siteMode): string
    {
        $loader = new FilesystemLoader();
        $loader->addPath(self::root() . '/templates', 'ItechWorldSuluTailwindTheme');
        $twig = new Environment($loader, ['strict_variables' => false, 'autoescape' => 'html']);
        $twig->addFilter(new TwigFilter('trans', static fn (string $key): string => $key));
        $twig->addFunction(new TwigFunction('iw_sulu_tailwind_theme_consent_mode', static fn (): string => $siteMode));
        $twig->addFunction(new TwigFunction('iw_sulu_tailwind_theme_unique_id', static fn (string $prefix): string => $prefix));
        $twig->addFunction(new TwigFunction('sulu_resolve_media', static fn (): null => null));

        return $twig->render(
            '@ItechWorldSuluTailwindTheme/blocks/common/_embed_frame.html.twig',
            $vars + ['src' => 'https://calendly.com/demo'],
        );
    }

    private static function xpath(string $relative): \DOMXPath
    {
        $document = new \DOMDocument();
        self::assertTrue($document->load(self::root() . '/' . $relative));
        $document->xinclude();

        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('sulu', 'http://schemas.sulu.io/template/template');

        return $xpath;
    }

    private static function root(): string
    {
        return \dirname(__DIR__, 2);
    }
}
