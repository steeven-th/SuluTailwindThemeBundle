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

    /**
     * How an author's name is written out.
     *
     * There is no "nickname" here on purpose: a Sulu contact has no such field
     * — only first name, middle name and last name — and the one pseudonym in
     * the system is the account's login username, which has no business being
     * published. An author who needs a pen name is added as a "custom" author,
     * whose name is free text.
     */
    public const AUTHOR_NAME_FORMAT_FULL = 'full';
    public const AUTHOR_NAME_FORMAT_FIRST = 'first';
    public const AUTHOR_NAME_FORMAT_LAST = 'last';
    public const AUTHOR_NAME_FORMAT_FIRST_INITIAL = 'first_initial';

    /**
     * @var list<string>
     */
    private const AUTHOR_NAME_FORMATS = [
        self::AUTHOR_NAME_FORMAT_FULL,
        self::AUTHOR_NAME_FORMAT_FIRST,
        self::AUTHOR_NAME_FORMAT_LAST,
        self::AUTHOR_NAME_FORMAT_FIRST_INITIAL,
    ];

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
    public function authorName(array $authorBlock, string $format = ''): string
    {
        // articleAuthors() already resolved the name with the effective format.
        if (isset($authorBlock['displayName']) && \is_string($authorBlock['displayName'])) {
            return $authorBlock['displayName'];
        }

        $type = $authorBlock['type'] ?? '';
        $format = $this->resolveAuthorNameFormat($format);

        return match ($type) {
            // A free-text name and an organization name are written as they
            // should read: splitting them into first/last would be guesswork.
            'custom' => (string) ($authorBlock['name'] ?? ''),
            'organization' => $this->resolveOrganizationName($authorBlock),
            'contact' => $this->resolveContactName($authorBlock, $format),
            'sulu_user' => $this->resolveSuluUserName($authorBlock, $format),
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

        $cardImageRatio = (string) ($tokens['cardImageRatio'] ?? '16:9');
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
            'cardSurface' => $tokens['cardSurface'] ?? 'none',
            'cardPadding' => $tokens['cardPadding'] ?? '1rem',
            'cardImagePadded' => (bool) ($tokens['cardImagePadded'] ?? true),
            'cardBorder' => $tokens['cardBorder'] ?? 'none',
            'cardBorderWidth' => $tokens['cardBorderWidth'] ?? '1px',
            'cardBorderStyle' => $tokens['cardBorderStyle'] ?? 'solid',
            'cardHoverTransform' => $tokens['cardHoverTransform'] ?? 'none',
            'cardHoverImage' => $tokens['cardHoverImage'] ?? 'zoom',
            'cardHoverShadow' => $tokens['cardHoverShadow'] ?? 'none',
            'cardHoverBorder' => $tokens['cardHoverBorder'] ?? 'none',
            'cardHoverDuration' => $tokens['cardHoverDuration'] ?? '300ms',
            'cardHoverEasing' => $tokens['cardHoverEasing'] ?? 'ease-out',
            'showDates' => $tokens['articles_showDates'] ?? 'both',
            'showAuthors' => $tokens['articles_showAuthors'] ?? 'both',
            'showCategories' => $tokens['articles_showCategories'] ?? 'both',
            'showTags' => $tokens['articles_showTags'] ?? 'page',
            'showExcerpts' => $tokens['articles_showExcerpts'] ?? 'listing',
            // Reading components: share buttons (opt-in, off by default).
            'shareEnabled' => (bool) ($tokens['articles_shareEnabled'] ?? false),
            'sharePosition' => (string) ($tokens['articles_sharePosition'] ?? 'footer'),
            'shareNative' => (bool) ($tokens['articles_shareNative'] ?? true),
            'shareCopy' => (bool) ($tokens['articles_shareCopy'] ?? true),
            'shareEmail' => (bool) ($tokens['articles_shareEmail'] ?? true),
            'shareButtonStyle' => (string) ($tokens['articles_shareButtonStyle'] ?? ''),
            'readingProgressEnabled' => (bool) ($tokens['articles_readingProgressEnabled'] ?? false),
            'tocEnabled' => (bool) ($tokens['articles_tocEnabled'] ?? false),
            'tocPosition' => (string) ($tokens['articles_tocPosition'] ?? 'inline'),
            'tocDepth' => (string) ($tokens['articles_tocDepth'] ?? 'h3'),
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
    public function articleAuthors(
        ?int $authorId = null,
        array $additionalAuthors = [],
        string $nameFormat = '',
        string $showAvatars = '',
    ): array {
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

        // The name format is resolved once and baked into each entry as
        // `displayName`. Every template that shows an author — the author
        // component, the meta line, the JSON-LD, the OpenGraph tags — goes
        // through authorName(), which prefers that value. Passing the format
        // down instead would mean threading a variable through a dozen
        // `include ... only` calls, and any template that forgot it would
        // quietly show a differently formatted name.
        $format = $this->resolveAuthorNameFormat($nameFormat);
        $avatarsOn = $this->resolveShowAvatars($showAvatars);

        foreach ($authors as $index => $author) {
            $authors[$index]['displayName'] = $this->authorName($author, $format);
            // Structured data keeps the whole name whatever the display format:
            // "Adam" instead of "Adam Ministrator" in schema.org would be a
            // downgrade of the article's metadata, for a purely visual choice.
            $authors[$index]['fullName'] = $this->authorName($author, self::AUTHOR_NAME_FORMAT_FULL);
            // The avatar id is resolved even when avatars are hidden: hiding is
            // a display decision, and a template may still need the picture.
            $authors[$index]['avatarId'] = $this->resolveAvatarId($author);
            $authors[$index]['showAvatar'] = $avatarsOn;
        }

        return $authors;
    }

    /**
     * Resolve whether author avatars are displayed.
     *
     * @param string $override The per-article value ('yes', 'no', or '' for the theme default)
     *
     * @return bool True when avatars must be displayed
     */
    private function resolveShowAvatars(string $override = ''): bool
    {
        if ('yes' === $override) {
            return true;
        }
        if ('no' === $override) {
            return false;
        }

        $tokens = $this->themeExtension->getTokens();

        // Unset means "on": avatars are the richer default, and a theme saved
        // before this setting existed should not lose them.
        return (bool) ($tokens['articles_showAuthorAvatars'] ?? true);
    }

    /**
     * Resolve the media id of an author's avatar.
     *
     * Only the "custom" author type used to expose one, so the primary author
     * of an article — a Sulu user, by far the most common case — always fell
     * back to initials even when their contact had a picture. Both Sulu-backed
     * types carry an avatar on their contact record, so both are read here.
     *
     * @param array<string, mixed> $authorBlock The author block data
     *
     * @return int|null The media id, or null when the author has no avatar
     */
    private function resolveAvatarId(array $authorBlock): ?int
    {
        $type = $authorBlock['type'] ?? '';

        if ('custom' === $type) {
            $avatar = $authorBlock['avatar'] ?? null;

            return is_numeric($avatar) ? (int) $avatar : null;
        }

        if ('contact' === $type) {
            $contact = $authorBlock['contact'] ?? null;

            return \is_array($contact) ? $this->avatarIdFromArray($contact) : null;
        }

        if ('sulu_user' !== $type || null === $this->userRepository) {
            return null;
        }

        $authorId = $authorBlock['authorId'] ?? null;
        if (null === $authorId) {
            return null;
        }

        try {
            $user = $this->userRepository->findUserById((int) $authorId);
            $avatar = $user?->getContact()->getAvatar();

            return null !== $avatar ? $avatar->getId() : null;
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Extract an avatar media id from a resolved contact array.
     *
     * The shape depends on how the contact was resolved: a bare id, or a media
     * array with its own id.
     *
     * @param array<string, mixed> $contact The resolved contact data
     *
     * @return int|null The media id, or null when there is none
     */
    private function avatarIdFromArray(array $contact): ?int
    {
        $avatar = $contact['avatar'] ?? null;

        if (is_numeric($avatar)) {
            return (int) $avatar;
        }

        if (\is_array($avatar) && is_numeric($avatar['id'] ?? null)) {
            return (int) $avatar['id'];
        }

        return null;
    }

    /**
     * Resolve the effective author name format.
     *
     * Article override first, then the theme default, then the full name.
     *
     * @param string $override The per-article value ('' when set to "theme default")
     *
     * @return string One of the self::AUTHOR_NAME_FORMATS keys
     */
    private function resolveAuthorNameFormat(string $override = ''): string
    {
        if (\in_array($override, self::AUTHOR_NAME_FORMATS, true)) {
            return $override;
        }

        $tokens = $this->themeExtension->getTokens();
        $themeFormat = (string) ($tokens['articles_authorNameFormat'] ?? '');

        return \in_array($themeFormat, self::AUTHOR_NAME_FORMATS, true)
            ? $themeFormat
            : self::AUTHOR_NAME_FORMAT_FULL;
    }

    /**
     * Apply a name format to a first/last name pair.
     *
     * Falls back to whichever part exists: a contact with only a last name
     * still has to render something under the "first name" format, otherwise
     * the article would show an author block with no name at all.
     *
     * @param string $firstName The first name
     * @param string $lastName  The last name
     * @param string $format    One of the self::AUTHOR_NAME_FORMATS keys
     *
     * @return string The formatted name
     */
    private function formatPersonName(string $firstName, string $lastName, string $format): string
    {
        $firstName = trim($firstName);
        $lastName = trim($lastName);

        $formatted = match ($format) {
            self::AUTHOR_NAME_FORMAT_FIRST => $firstName,
            self::AUTHOR_NAME_FORMAT_LAST => $lastName,
            self::AUTHOR_NAME_FORMAT_FIRST_INITIAL => '' !== $lastName
                ? trim($firstName . ' ' . mb_strtoupper(mb_substr($lastName, 0, 1)) . '.')
                : $firstName,
            default => trim($firstName . ' ' . $lastName),
        };

        return '' !== $formatted ? $formatted : trim($firstName . ' ' . $lastName);
    }

    /**
     * Resolve a contact name from a Sulu contact selection.
     *
     * @param array<string, mixed> $authorBlock The author block data
     *
     * @return string "firstName lastName"
     */
    private function resolveContactName(array $authorBlock, string $format = self::AUTHOR_NAME_FORMAT_FULL): string
    {
        $contact = $authorBlock['contact'] ?? null;

        if (\is_array($contact)) {
            return $this->formatPersonName(
                (string) ($contact['firstName'] ?? ''),
                (string) ($contact['lastName'] ?? ''),
                $format,
            );
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
    private function resolveSuluUserName(array $authorBlock, string $format = self::AUTHOR_NAME_FORMAT_FULL): string
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

            return $this->formatPersonName(
                $contact->getFirstName() ?? '',
                $contact->getLastName() ?? '',
                $format,
            );
        } catch (\Exception) {
            return '';
        }
    }
}
