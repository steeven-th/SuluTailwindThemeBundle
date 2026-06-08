<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Twig;

use Sulu\Component\Security\Authentication\UserRepositoryInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Twig extension providing helper functions for article templates.
 *
 * Loaded conditionally only when article_templates.enabled is true.
 * Provides date formatting, reading time estimation, author name
 * resolution, and style selection helpers.
 */
class ArticleExtension extends AbstractExtension
{
    /**
     * Default styles for each article type when no admin config is set.
     */
    private const DEFAULT_ARTICLE_STYLES = [
        'news' => 'classic',
        'event' => 'card_info',
        'blog_post' => 'classic',
    ];

    /**
     * Mapping from article type to the token key used in the form.
     */
    private const STYLE_TOKEN_KEYS = [
        'news' => 'articles_newsStyle',
        'event' => 'articles_eventStyle',
        'blog_post' => 'articles_blogStyle',
        'listing' => 'articles_listingStyle',
    ];

    /**
     * Default listing style when no admin config is set.
     */
    private const DEFAULT_LISTING_STYLE = 'grid';

    /**
     * Average words per minute for reading time calculation.
     */
    private const WORDS_PER_MINUTE = 250;

    public function __construct(
        private readonly ThemeExtension $themeExtension,
        private readonly ?UserRepositoryInterface $userRepository = null,
    ) {
    }

    /**
     * @return list<TwigFilter>
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('iw_article_visible', [self::class, 'isVisible']),
        ];
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('iw_sulu_tailwind_theme_format_date', $this->formatDate(...)),
            new TwigFunction('iw_sulu_tailwind_theme_reading_time', $this->readingTime(...)),
            new TwigFunction('iw_sulu_tailwind_theme_author_name', $this->authorName(...)),
            new TwigFunction('iw_sulu_tailwind_theme_article_style', $this->articleStyle(...)),
            new TwigFunction('iw_sulu_tailwind_theme_listing_style', $this->listingStyle(...)),
            new TwigFunction('iw_sulu_tailwind_theme_article_config', $this->articleConfig(...)),
            new TwigFunction('iw_sulu_tailwind_theme_article_authors', $this->articleAuthors(...)),
        ];
    }

    /**
     * Format a date using ICU date formatting with the current locale.
     *
     * @param \DateTimeInterface|string|null $date   The date to format
     * @param string                        $format  ICU date format ('long', 'medium', 'short', 'full')
     *                                               or a custom ICU pattern (e.g. "d MMMM yyyy, HH:mm")
     *
     * @return string The formatted date, or empty string if date is null
     */
    public function formatDate(\DateTimeInterface|string|null $date, string $format = 'long'): string
    {
        if (null === $date) {
            return '';
        }

        if (\is_string($date)) {
            try {
                $date = new \DateTimeImmutable($date);
            } catch (\Exception) {
                return $date;
            }
        }

        $dateType = match ($format) {
            'full' => \IntlDateFormatter::FULL,
            'long' => \IntlDateFormatter::LONG,
            'medium' => \IntlDateFormatter::MEDIUM,
            'short' => \IntlDateFormatter::SHORT,
            default => \IntlDateFormatter::NONE,
        };

        // Known format keyword → use IntlDateFormatter with date type
        if (\IntlDateFormatter::NONE !== $dateType) {
            $formatter = new \IntlDateFormatter(
                \Locale::getDefault(),
                $dateType,
                \IntlDateFormatter::NONE,
            );

            return $formatter->format($date) ?: '';
        }

        // Custom ICU pattern (e.g. "d MMMM yyyy, HH:mm")
        $formatter = new \IntlDateFormatter(
            \Locale::getDefault(),
            \IntlDateFormatter::FULL,
            \IntlDateFormatter::FULL,
        );
        $formatter->setPattern($format);

        return $formatter->format($date) ?: '';
    }

    /**
     * Estimate reading time from HTML content.
     *
     * Strips HTML tags, counts words, divides by average reading speed
     * (250 words/min). Returns at least 1 minute.
     *
     * @param string|null $content The HTML content to analyze
     *
     * @return int Estimated reading time in minutes (minimum 1)
     */
    public function readingTime(?string $content): int
    {
        if (null === $content || '' === trim($content)) {
            return 1;
        }

        $text = strip_tags($content);
        $wordCount = str_word_count($text);

        return max(1, (int) ceil($wordCount / self::WORDS_PER_MINUTE));
    }

    /**
     * Resolve the display name from an author block entry.
     *
     * Handles the three author types:
     * - custom: returns the "name" field
     * - contact: returns "firstName lastName" from Sulu contact data
     * - organization: returns the organization name from Sulu account data
     *
     * @param array<string, mixed> $authorBlock A single author block entry
     *
     * @return string The resolved author name, or empty string
     */
    public function authorName(array $authorBlock): string
    {
        $type = $authorBlock['type'] ?? '';

        return match ($type) {
            'custom' => (string) ($authorBlock['name'] ?? ''),
            'contact' => $this->resolveContactName($authorBlock),
            'organization' => $this->resolveOrganizationName($authorBlock),
            'sulu_user' => $this->resolveSuluUserName($authorBlock),
            default => '',
        };
    }

    /**
     * Get the active style for an article type.
     *
     * Reads from the theme's article config (set in Sprint 3 admin tab).
     * Falls back to sensible defaults: news→classic, event→card_info, blog→classic.
     *
     * @param string $type The article type key (news, event, blog_post)
     *
     * @return string The active style key
     */
    public function articleStyle(string $type): string
    {
        $tokens = $this->themeExtension->getTokens();
        $tokenKey = self::STYLE_TOKEN_KEYS[$type] ?? null;

        if (null !== $tokenKey && !empty($tokens[$tokenKey])) {
            return (string) $tokens[$tokenKey];
        }

        return self::DEFAULT_ARTICLE_STYLES[$type] ?? 'classic';
    }

    /**
     * Get the active listing style.
     *
     * Reads from the theme's article config token (articles_listingStyle).
     * Falls back to 'grid'.
     *
     * @return string The active listing style key (grid, list, cards)
     */
    public function listingStyle(): string
    {
        $tokens = $this->themeExtension->getTokens();

        return (string) ($tokens['articles_listingStyle'] ?? self::DEFAULT_LISTING_STYLE);
    }

    /**
     * Get the full article display configuration from the theme.
     *
     * Returns an array with all articles_* settings (visibility, styles, per page, etc.)
     * Visibility values are: 'hidden', 'page', 'listing', 'both'.
     *
     * @return array<string, mixed> The article configuration
     */
    public function articleConfig(): array
    {
        $tokens = $this->themeExtension->getTokens();

        $cardImageRatio = (string) ($tokens['articles_cardImageRatio'] ?? '16:9');
        $ratioParts = explode(':', $cardImageRatio);
        // A portrait ratio has its width < its height (e.g. 3:4, 9:16).
        // Defaults to landscape when the ratio is malformed.
        $isPortrait = 2 === count($ratioParts)
            && (int) $ratioParts[0] > 0
            && (int) $ratioParts[0] < (int) $ratioParts[1];

        return [
            'newsStyle' => $tokens['articles_newsStyle'] ?? 'classic',
            'eventStyle' => $tokens['articles_eventStyle'] ?? 'card_info',
            'blogStyle' => $tokens['articles_blogStyle'] ?? 'classic',
            'listingStyle' => $tokens['articles_listingStyle'] ?? 'grid',
            'cardImageRatio' => $cardImageRatio,
            'cardOrientation' => $isPortrait ? 'portrait' : 'landscape',
            'cardSurface' => $tokens['articles_cardSurface'] ?? 'none',
            'cardPadding' => $tokens['articles_cardPadding'] ?? '1rem',
            'cardImagePadded' => (bool) ($tokens['articles_cardImagePadded'] ?? true),
            'cardBorder' => $tokens['articles_cardBorder'] ?? 'none',
            'cardBorderWidth' => $tokens['articles_cardBorderWidth'] ?? '1px',
            'cardBorderStyle' => $tokens['articles_cardBorderStyle'] ?? 'solid',
            'cardHoverTransform' => $tokens['articles_cardHoverTransform'] ?? 'none',
            'cardHoverImage' => $tokens['articles_cardHoverImage'] ?? 'zoom',
            'cardHoverShadow' => $tokens['articles_cardHoverShadow'] ?? 'none',
            'cardHoverBorder' => $tokens['articles_cardHoverBorder'] ?? 'none',
            'cardHoverDuration' => $tokens['articles_cardHoverDuration'] ?? '300ms',
            'cardHoverEasing' => $tokens['articles_cardHoverEasing'] ?? 'ease-out',
            'showDates' => $tokens['articles_showDates'] ?? 'both',
            'showAuthors' => $tokens['articles_showAuthors'] ?? 'both',
            'showCategories' => $tokens['articles_showCategories'] ?? 'both',
            'showExcerpts' => $tokens['articles_showExcerpts'] ?? 'listing',
            'showRelated' => $tokens['articles_showRelated'] ?? 'page',
            'relatedCount' => (int) ($tokens['articles_relatedCount'] ?? 3),
        ];
    }

    /**
     * Check if an element should be visible in a given context.
     *
     * @param string $visibility The visibility value ('hidden', 'page', 'listing', 'both')
     * @param string $context    The current context ('page' or 'listing')
     *
     * @return bool Whether the element should be displayed
     */
    public static function isVisible(string $visibility, string $context): bool
    {
        if ('both' === $visibility) {
            return true;
        }

        if ('hidden' === $visibility) {
            return false;
        }

        return $visibility === $context;
    }

    /**
     * Build the full authors list: Sulu primary author + additional authors.
     *
     * The primary author comes from Sulu's native article settings (author field,
     * stored as a user ID). Additional authors come from the template's
     * additionalAuthors block (custom/contact/organization).
     *
     * @param int|null              $authorId          The Sulu user ID of the primary author
     * @param array<int, mixed>     $additionalAuthors The additional authors block entries
     *
     * @return list<array{type: string, name: string, role?: string}> Normalized authors list
     */
    public function articleAuthors(?int $authorId = null, array $additionalAuthors = []): array
    {
        $authors = [];

        // Primary author from Sulu settings (user ID → contact name)
        if (null !== $authorId && $authorId > 0) {
            $authors[] = [
                'type' => 'sulu_user',
                'authorId' => $authorId,
                'name' => '', // Resolved in Twig via Sulu contact functions
            ];
        }

        // Additional authors from the template block
        foreach ($additionalAuthors as $entry) {
            if (\is_array($entry)) {
                $authors[] = $entry;
            }
        }

        return $authors;
    }

    /**
     * Resolve a contact name from a Sulu contact selection.
     *
     * @param array<string, mixed> $authorBlock The author block data
     *
     * @return string "firstName lastName"
     */
    private function resolveContactName(array $authorBlock): string
    {
        $contact = $authorBlock['contact'] ?? null;

        if (\is_array($contact)) {
            $firstName = (string) ($contact['firstName'] ?? '');
            $lastName = (string) ($contact['lastName'] ?? '');

            return trim("{$firstName} {$lastName}");
        }

        return '';
    }

    /**
     * Resolve an organization name from a Sulu account selection.
     *
     * @param array<string, mixed> $authorBlock The author block data
     *
     * @return string The organization name
     */
    private function resolveOrganizationName(array $authorBlock): string
    {
        $organization = $authorBlock['organization'] ?? null;

        if (\is_array($organization)) {
            return (string) ($organization['name'] ?? '');
        }

        return '';
    }

    /**
     * Resolve a user name from a Sulu user ID (primary article author).
     *
     * @param array<string, mixed> $authorBlock The author block data with 'authorId'
     *
     * @return string "firstName lastName"
     */
    private function resolveSuluUserName(array $authorBlock): string
    {
        $authorId = $authorBlock['authorId'] ?? null;

        if (null === $authorId || null === $this->userRepository) {
            return '';
        }

        try {
            $user = $this->userRepository->findUserById((int) $authorId);

            if (null === $user) {
                return '';
            }

            $contact = $user->getContact();
            $firstName = $contact->getFirstName() ?? '';
            $lastName = $contact->getLastName() ?? '';

            return trim("{$firstName} {$lastName}");
        } catch (\Exception) {
            return '';
        }
    }
}
