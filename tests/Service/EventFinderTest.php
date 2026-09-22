<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Article\EventDateScope;
use ItechWorld\SuluTailwindThemeBundle\Repository\WebspaceArticleRepository;
use ItechWorld\SuluTailwindThemeBundle\Service\ArticleFacetsService;
use ItechWorld\SuluTailwindThemeBundle\Service\ArticleItemResolver;
use ItechWorld\SuluTailwindThemeBundle\Service\EventFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sulu\Article\Domain\Repository\ArticleRepositoryInterface;
use Sulu\Bundle\CategoryBundle\Category\CategoryManagerInterface;
use Sulu\Bundle\CategoryBundle\Entity\CategoryRepositoryInterface;
use Sulu\Bundle\TagBundle\Tag\TagRepositoryInterface;
use Sulu\Component\Webspace\Analyzer\RequestAnalyzerInterface;
use Sulu\Component\Webspace\Webspace;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Application\ContentResolver\ContentResolverInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;

/**
 * The agenda a template asks for directly, without a field an editor fills.
 *
 * What matters here is the question that reaches the database: the right half
 * of the calendar, the site being visited, the published stage, and a limit -
 * since a home page asking for three events must not walk the whole archive.
 */
#[CoversClass(EventFinder::class)]
final class EventFinderTest extends TestCase
{
    /**
     * @var array{filters: array<string, mixed>, webspaceKey: string|null, scope: EventDateScope|null}|null
     */
    private ?array $captured = null;

    /**
     * The next events of the site being visited, capped, in the live stage.
     */
    #[Test]
    public function itAsksForTheNextEventsOfTheVisitedSite(): void
    {
        $this->finder()->upcoming(3);

        self::assertNotNull($this->captured);
        self::assertSame('fr', $this->captured['filters']['locale']);
        self::assertSame(DimensionContentInterface::STAGE_LIVE, $this->captured['filters']['stage']);
        self::assertSame(3, $this->captured['filters']['limit']);
        self::assertSame('website', $this->captured['webspaceKey']);
        self::assertTrue($this->captured['scope']?->isUpcoming());
    }

    /**
     * An archive asks for the other half.
     */
    #[Test]
    public function pastAsksForWhatIsOver(): void
    {
        $this->finder()->past(5);

        self::assertFalse($this->captured['scope']?->isUpcoming());
        self::assertSame(5, $this->captured['filters']['limit']);
    }

    /**
     * Categories, tags and templates narrow the agenda the way a listing does.
     */
    #[Test]
    public function itNarrowsOnWhatTheTemplateAsksFor(): void
    {
        $this->finder()->upcoming(3, [
            'categories' => ['12'],
            'tags' => 'festival',
            'templates' => ['iw_event'],
            'webspace' => 'othersite',
        ]);

        self::assertSame([12], $this->captured['filters']['categoryIds']);
        self::assertSame(['festival'], $this->captured['filters']['tagNames']);
        self::assertSame(['iw_event'], $this->captured['filters']['templateKeys']);
        self::assertSame('othersite', $this->captured['webspaceKey']);
    }

    /**
     * Passing `webspace: false` looks across every site, for a project that has
     * one and no request to read it from.
     */
    #[Test]
    public function itCanLookAcrossEverySite(): void
    {
        $this->finder()->upcoming(3, ['webspace' => false]);

        self::assertNull($this->captured['webspaceKey']);
    }

    /**
     * Off a request and with no locale given, an empty agenda beats an
     * exception in the middle of a page.
     */
    #[Test]
    public function itStaysQuietWhenThereIsNoLocaleToBeFound(): void
    {
        self::assertSame([], $this->finder(locale: null)->upcoming(3));
        self::assertNull($this->captured);
    }

    /**
     * A limit of zero still asks for something: a template asking for none
     * would not have called at all.
     */
    #[Test]
    public function itNeverAsksForLessThanOne(): void
    {
        $this->finder()->upcoming(0);

        self::assertSame(1, $this->captured['filters']['limit']);
    }

    private function finder(?string $locale = 'fr'): EventFinder
    {
        $repository = $this->createStub(WebspaceArticleRepository::class);
        $repository->method('findIdentifiersBy')->willReturnCallback(
            function (array $filters, array $sortBy, ?string $webspaceKey, ?EventDateScope $scope): array {
                $this->captured = ['filters' => $filters, 'webspaceKey' => $webspaceKey, 'scope' => $scope];

                return [];
            },
        );

        $webspace = new Webspace();
        $webspace->setKey('website');

        $requestAnalyzer = $this->createStub(RequestAnalyzerInterface::class);
        $requestAnalyzer->method('getWebspace')->willReturn($webspace);
        $requestAnalyzer->method('getCurrentLocalization')->willReturn(
            null === $locale ? null : new \Sulu\Component\Localization\Localization($locale),
        );

        return new EventFinder(
            $repository,
            new ArticleItemResolver(
                $this->createStub(ArticleRepositoryInterface::class),
                $this->createStub(ContentManagerInterface::class),
                $this->createStub(ContentResolverInterface::class),
            ),
            new ArticleFacetsService(
                $this->createStub(CategoryManagerInterface::class),
                $this->createStub(CategoryRepositoryInterface::class),
                $this->createStub(TagRepositoryInterface::class),
            ),
            $requestAnalyzer,
        );
    }
}
