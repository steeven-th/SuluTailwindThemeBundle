<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

use ItechWorld\SuluTailwindThemeBundle\Article\EventDateScope;
use ItechWorld\SuluTailwindThemeBundle\Repository\WebspaceArticleRepository;
use Sulu\Component\Webspace\Analyzer\RequestAnalyzerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;

/**
 * Answers "what is coming up" to a template that asks for it directly.
 *
 * The smart content provider covers the case where an editor picks the events
 * and how many - a block, or a property on a page. This covers the other one: a
 * page built by hand that shows the next three events and offers no setting for
 * it, which is how a home page usually carries its agenda.
 *
 * Both go through the same clause
 * ({@see \ItechWorld\SuluTailwindThemeBundle\Article\EventDateScope}), so the
 * two never disagree on what "upcoming" means, and both return the item shape
 * the card templates already read.
 *
 * Anything without a start date is left out by the clause itself, so a project
 * whose events live in a template of its own needs no configuration here.
 */
final class EventFinder
{
    /**
     * How many events a caller gets when it does not say.
     */
    public const DEFAULT_LIMIT = 3;

    public function __construct(
        private readonly WebspaceArticleRepository $webspaceArticleRepository,
        private readonly ArticleItemResolver $itemResolver,
        private readonly ArticleFacetsService $facetsService,
        private readonly ?RequestAnalyzerInterface $requestAnalyzer = null,
    ) {
    }

    /**
     * The next events, soonest first.
     *
     * @param int                  $limit   How many at most
     * @param array<string, mixed> $options See {@see find()}
     *
     * @return array<int, array<string, mixed>> Card items, in display order
     */
    public function upcoming(int $limit = self::DEFAULT_LIMIT, array $options = []): array
    {
        return $this->find(EventDateScope::upcoming(), $limit, $options);
    }

    /**
     * The events that are over, most recent first.
     *
     * @param int                  $limit   How many at most
     * @param array<string, mixed> $options See {@see find()}
     *
     * @return array<int, array<string, mixed>> Card items, in display order
     */
    public function past(int $limit = self::DEFAULT_LIMIT, array $options = []): array
    {
        return $this->find(EventDateScope::past(), $limit, $options);
    }

    /**
     * Run one half of the agenda.
     *
     * Options:
     *  - `categories`: category keys or ids, kept if the event carries any of them;
     *  - `tags`: tag names, same;
     *  - `templates`: template keys, to narrow to one kind of event;
     *  - `webspace`: a site key, or false to look across every site;
     *  - `locale`: a locale, when there is no request to read one from.
     *
     * @param EventDateScope       $scope   Which half, and in which order
     * @param int                  $limit   How many at most
     * @param array<string, mixed> $options The options above
     *
     * @return array<int, array<string, mixed>> Card items, in display order
     */
    private function find(EventDateScope $scope, int $limit, array $options): array
    {
        $locale = $options['locale'] ?? $this->requestAnalyzer?->getCurrentLocalization()?->getLocale();
        if (!\is_string($locale) || '' === $locale) {
            // Off a request and with no locale given there is no content to
            // resolve: an empty agenda beats an exception in a template.
            return [];
        }

        $limit = \max(1, $limit);

        $filters = [
            'locale' => $locale,
            'stage' => DimensionContentInterface::STAGE_LIVE,
            'page' => 1,
            'limit' => $limit,
        ];

        $categoryIds = $this->facetsService->resolveCategoryIds($this->stringList($options['categories'] ?? []));
        if ([] !== $categoryIds) {
            $filters['categoryIds'] = $categoryIds;
            $filters['categoryOperator'] = 'OR';
        }

        $tagNames = $this->stringList($options['tags'] ?? []);
        if ([] !== $tagNames) {
            $filters['tagNames'] = $tagNames;
            $filters['tagOperator'] = 'OR';
        }

        $templateKeys = $this->stringList($options['templates'] ?? []);
        if ([] !== $templateKeys) {
            $filters['templateKeys'] = $templateKeys;
        }

        $uuids = $this->webspaceArticleRepository->findIdentifiersBy(
            $filters,
            [],
            $this->webspaceKey($options),
            $scope,
        );

        return $this->itemResolver->resolve($uuids, $locale);
    }

    /**
     * The site to look in: the one being visited, unless the caller says otherwise.
     *
     * Passing `false` looks across every site, which is what a project with one
     * site and no request - a console command rendering a page - needs.
     *
     * @param array<string, mixed> $options
     */
    private function webspaceKey(array $options): ?string
    {
        if (\array_key_exists('webspace', $options)) {
            $webspace = $options['webspace'];

            return \is_string($webspace) && '' !== $webspace ? $webspace : null;
        }

        return $this->requestAnalyzer?->getWebspace()?->getKey();
    }

    /**
     * Normalise an option that may arrive as a list, a single value or nothing.
     *
     * @return list<string>
     */
    private function stringList(mixed $values): array
    {
        if (\is_string($values)) {
            $values = [$values];
        }

        if (!\is_array($values)) {
            return [];
        }

        $list = [];
        foreach ($values as $value) {
            if (\is_string($value) || \is_int($value)) {
                $value = \trim((string) $value);
                if ('' !== $value) {
                    $list[] = $value;
                }
            }
        }

        return $list;
    }
}
