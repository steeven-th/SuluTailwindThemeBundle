<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Article;

use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;

/**
 * The "what is still ahead" half of an agenda, as a query fragment.
 *
 * An event carries a start date and, when it lasts, an end date, both typed by
 * an editor into the `iw_event` template and therefore stored inside the JSON
 * of the dimension content rather than in a column
 * ({@see \ItechWorld\SuluTailwindThemeBundle\Doctrine\JsonTextFunction}).
 *
 * Three places ask the same question of them - the smart content provider that
 * fills a block, the Twig function a template calls, and the listing page in
 * agenda mode - so the clause is written once, here, and handed a query builder
 * to sit on.
 *
 * An event counts as upcoming until it is over, not until it has begun: a
 * festival running from the 10th to the 15th belongs in the agenda on the 12th.
 * The end date is therefore what is compared when there is one, and the start
 * date otherwise. An article with no start date at all - a news item - never
 * matches, since comparing against a missing value is never true.
 */
final class EventDateScope
{
    /**
     * Still ahead, or still running.
     */
    public const DIRECTION_UPCOMING = 'upcoming';

    /**
     * Over, for an archive.
     */
    public const DIRECTION_PAST = 'past';

    /**
     * The template fields an event is dated by.
     */
    public const FIELD_START = 'startDate';
    public const FIELD_END = 'endDate';

    /**
     * How Sulu writes a datetime into the template data.
     *
     * Mirrors {@see \Sulu\Content\Application\PropertyResolver\Resolver\DateTimePropertyResolver::FORMAT}.
     * Its lexicographic order is its chronological order, which is what lets
     * the comparison below happen on text and stay true on every database.
     */
    public const STORED_FORMAT = 'Y-m-d\TH:i:s';

    /**
     * Name of the bound parameter, distinctive enough not to meet another.
     */
    private const NOW_PARAMETER = 'iwEventNow';

    private function __construct(
        private readonly string $direction,
        private readonly \DateTimeImmutable $now,
    ) {
    }

    /**
     * Events that have not ended yet, soonest first.
     *
     * @param \DateTimeImmutable|null $now The moment to compare against, now by default
     */
    public static function upcoming(?\DateTimeImmutable $now = null): self
    {
        return new self(self::DIRECTION_UPCOMING, $now ?? new \DateTimeImmutable());
    }

    /**
     * Events that are over, most recent first.
     *
     * @param \DateTimeImmutable|null $now The moment to compare against, now by default
     */
    public static function past(?\DateTimeImmutable $now = null): self
    {
        return new self(self::DIRECTION_PAST, $now ?? new \DateTimeImmutable());
    }

    /**
     * Build the scope a stored direction names, or none when it names neither.
     *
     * Anything that is not one of the two directions means "do not date-filter
     * at all", which is what an editor picking plain articles asks for.
     *
     * @param mixed                   $direction The stored value
     * @param \DateTimeImmutable|null $now       The moment to compare against
     *
     * @return self|null The scope, or null when no date filtering applies
     */
    public static function fromDirection(mixed $direction, ?\DateTimeImmutable $now = null): ?self
    {
        return match ($direction) {
            self::DIRECTION_UPCOMING => self::upcoming($now),
            self::DIRECTION_PAST => self::past($now),
            default => null,
        };
    }

    /**
     * Whether this scope looks ahead.
     */
    public function isUpcoming(): bool
    {
        return self::DIRECTION_UPCOMING === $this->direction;
    }

    /**
     * Add the date clause and the agenda order to a query.
     *
     * @param QueryBuilder $queryBuilder    The query being built
     * @param string       $dimensionAlias  Alias of the joined dimension content
     */
    public function applyTo(QueryBuilder $queryBuilder, string $dimensionAlias): void
    {
        $queryBuilder
            ->andWhere(\sprintf(
                '%s %s :%s',
                $this->endExpression($dimensionAlias),
                $this->isUpcoming() ? '>=' : '<',
                self::NOW_PARAMETER,
            ))
            ->setParameter(self::NOW_PARAMETER, $this->now->format(self::STORED_FORMAT));

        // The agenda order has to come first, whatever the caller had asked
        // for: a listing page arrives here with the sort an editor chose, and a
        // date order appended behind it would never decide anything. The
        // caller's sort is kept as the tie-breaker it becomes.
        /** @var Expr\OrderBy[] $existing */
        $existing = $queryBuilder->getDQLPart('orderBy');
        $queryBuilder->resetDQLPart('orderBy');

        $queryBuilder->addOrderBy(
            $this->startExpression($dimensionAlias),
            $this->isUpcoming() ? 'ASC' : 'DESC',
        );

        foreach ($existing as $orderBy) {
            $queryBuilder->addOrderBy($orderBy);
        }
    }

    /**
     * Read the start date, which is what an agenda is ordered by.
     *
     * Written without a single space on purpose. Sulu puts the expressions of
     * the ORDER BY back into the SELECT after it has narrowed the query to
     * DISTINCT uuids - which PostgreSQL requires - and it splits each one on
     * the first space to do so
     * ({@see \Sulu\Bundle\AdminBundle\SmartContent\SmartContentQueryEnhancer::addOrderBySelects()}).
     * A spaced expression would reach the SELECT cut in half.
     *
     * @param string $dimensionAlias Alias of the joined dimension content
     *
     * @return string A DQL expression, free of spaces
     */
    public function startExpression(string $dimensionAlias): string
    {
        return \sprintf("IW_JSON_TEXT(%s.templateData,'%s')", $dimensionAlias, self::FIELD_START);
    }

    /**
     * Read the date an event is over: its end when it has one, its start
     * otherwise.
     *
     * NULLIF guards the empty string, which is what an end date left blank can
     * leave behind, and which would otherwise read as a date before every other.
     *
     * @param string $dimensionAlias Alias of the joined dimension content
     *
     * @return string A DQL expression, free of spaces
     */
    public function endExpression(string $dimensionAlias): string
    {
        return \sprintf(
            "COALESCE(NULLIF(IW_JSON_TEXT(%s.templateData,'%s'),''),IW_JSON_TEXT(%s.templateData,'%s'))",
            $dimensionAlias,
            self::FIELD_END,
            $dimensionAlias,
            self::FIELD_START,
        );
    }
}
