<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use ItechWorld\SuluTailwindThemeBundle\Controller\Website\ArticleListingController;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards the switch that says what a listing page lists.
 *
 * The page carries one selection per source, each bound to the provider that
 * answers it, because a smart content is bound to its provider in the template:
 * that binding is what makes the preview in the admin list what the site will
 * list. A single selection kept on the plain article provider would show the
 * editor every event, past ones included, while the page showed the agenda.
 *
 * Two things can drift apart here, both silently. The controller reads the
 * selection by name, so a property renamed in the XML leaves it reading nothing
 * and the page lists nothing. And a selection declared on the wrong provider
 * still renders, simply showing the wrong list in the admin.
 */
final class ListingSourceContractTest extends TestCase
{
    /**
     * Which provider each source is expected to be built on.
     *
     * @var array<string, array{provider: string, direction: string|null}>
     */
    private const EXPECTED = [
        'articles' => ['provider' => 'articles', 'direction' => null],
        'upcoming' => ['provider' => 'iw_events', 'direction' => 'upcoming'],
        'past' => ['provider' => 'iw_events', 'direction' => 'past'],
    ];

    /**
     * Every source the controller reads has its selection in the template.
     */
    #[Test]
    public function everySourceTheControllerReadsHasItsSelection(): void
    {
        $template = $this->template();

        foreach ($this->sourceProperties() as $source => $property) {
            self::assertStringContainsString(
                '<property name="' . $property . '" type="smart_content"',
                $template,
                \sprintf(
                    'The controller reads the "%s" source from a property named "%s", which the '
                    . 'listing template does not declare. The page would list nothing at all.',
                    $source,
                    $property,
                ),
            );
        }
    }

    /**
     * Each selection shows under its own source, and no other.
     */
    #[Test]
    public function eachSelectionShowsUnderItsOwnSource(): void
    {
        $template = $this->template();

        foreach ($this->sourceProperties() as $source => $property) {
            self::assertMatchesRegularExpression(
                '/<property name="' . \preg_quote($property, '/') . '"[^>]*visibleCondition="source == \'' . \preg_quote($source, '/') . '\'"/',
                $template,
                \sprintf('The "%s" selection is not the one shown when the page lists "%s".', $property, $source),
            );
        }
    }

    /**
     * Each selection is built on the provider that answers its source.
     */
    #[Test]
    public function eachSelectionIsBuiltOnTheProviderThatAnswersIt(): void
    {
        foreach ($this->sourceProperties() as $source => $property) {
            $params = $this->propertyParams($property);
            $expected = self::EXPECTED[$source];

            self::assertStringContainsString(
                '<param name="provider" value="' . $expected['provider'] . '"/>',
                $params,
                \sprintf(
                    'The "%s" selection is not built on the "%s" provider, so the admin would '
                    . 'preview a different list from the one the site renders.',
                    $property,
                    $expected['provider'],
                ),
            );

            if (null !== $expected['direction']) {
                self::assertStringContainsString(
                    '<param name="direction" value="' . $expected['direction'] . '"/>',
                    $params,
                    \sprintf('The "%s" selection does not name the half of the agenda it shows.', $property),
                );
            }
        }
    }

    /**
     * The sources the controller knows, read from the controller itself.
     *
     * @return array<string, string> source => property name
     */
    private function sourceProperties(): array
    {
        $constants = (new \ReflectionClass(ArticleListingController::class))->getConstants();

        /** @var array<string, string> $properties */
        $properties = $constants['SOURCE_PROPERTIES'];

        self::assertSame(\array_keys(self::EXPECTED), \array_keys($properties));

        return $properties;
    }

    /**
     * The params block of one property of the listing template.
     */
    private function propertyParams(string $property): string
    {
        $template = $this->template();

        $start = \strpos($template, '<property name="' . $property . '"');
        self::assertIsInt($start);

        $end = \strpos($template, '</property>', $start);
        self::assertIsInt($end);

        return \substr($template, $start, $end - $start);
    }

    private function template(): string
    {
        $path = \dirname(__DIR__, 2) . '/config/templates/pages/iw_article_listing.xml';

        self::assertFileExists($path);

        return (string) \file_get_contents($path);
    }
}
