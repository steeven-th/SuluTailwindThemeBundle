<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;

/**
 * The button styles a project's CSS depends on, and the themes lacking them.
 *
 * A project decorating a style (a dot after the label, a slanted corner)
 * targets its class, `.iw-button--<slug>`, from CSS contributed through
 * ThemeCompileEvent. The slug is typed in the admin, so renaming it there
 * silently detaches the project CSS: nothing fails, the ornament is just
 * gone. Declaring the slugs lets the compile command, the compiler log and
 * the diagnostic say so.
 *
 * ```yaml
 * itech_world_sulu_tailwind_theme:
 *     required_button_styles: [profile-employer, profile-employee]
 * ```
 */
class RequiredButtonStyles
{
    /**
     * @var list<string>
     */
    private readonly array $slugs;

    /**
     * @param list<string> $slugs The slugs declared by the project
     */
    public function __construct(array $slugs = [])
    {
        $this->slugs = array_values(array_unique(array_filter(
            array_map(static fn (mixed $slug): string => trim((string) $slug), $slugs),
            static fn (string $slug): bool => '' !== $slug,
        )));
    }

    /**
     * The slugs declared by the project.
     *
     * @return list<string>
     */
    public function all(): array
    {
        return $this->slugs;
    }

    /**
     * The declared slugs the theme defines no button style for.
     *
     * @param ThemeConfig $theme The theme to check
     *
     * @return list<string> The missing slugs, in declaration order
     */
    public function missingIn(ThemeConfig $theme): array
    {
        if ([] === $this->slugs) {
            return [];
        }

        $defined = array_column(ButtonResolver::normalizeButtons($theme->getTokens()['buttons'] ?? []), 'slug');

        return array_values(array_diff($this->slugs, $defined));
    }
}
