<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Consent;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The third parties a site's embeds wait on, and what a visitor must be told
 * about each of them.
 *
 * A service is a key shared by three parties: the content waiting on it
 * (`data-consent-service-value`), the cookie manager granting it, and the
 * visitor, who is owed the name of the company receiving their data and its
 * privacy policy. The bundle sets some keys itself (videos, maps), the
 * project declares its own in `consent.services`, and editors describe the
 * ones the site uses in the "Third-party services" snippet, one version per
 * locale.
 *
 * Descriptions merge in that order: the bundle's own entry, then the
 * project's, then the snippet's, each non-empty field winning over the one
 * before. A site filling nothing still names YouTube's company and policy.
 *
 * Being described is not being declared. A service is declared when the
 * snippet lists it, which is what a cookie manager fed from that snippet
 * knows about. Content waiting on an undeclared service never loads under
 * such a manager, so the placeholder reports it.
 */
final class ThirdPartyServices
{
    /**
     * The snippet area editors assign the inventory to.
     */
    public const SNIPPET_AREA = 'iw_theme_third_party_services';

    /**
     * The services the bundle's own templates wait on.
     *
     * `maps` is a custom tile server: the bundle cannot know whose, so the
     * project names it in `consent.services`.
     */
    private const BUNDLE_SERVICES = [
        'youtube' => [
            'label' => 'YouTube',
            'company' => 'Google Ireland Limited',
            'policy' => 'https://policies.google.com/privacy',
        ],
        'vimeo' => [
            'label' => 'Vimeo',
            'company' => 'Vimeo.com, Inc.',
            'policy' => 'https://vimeo.com/privacy',
        ],
        'openstreetmap' => [
            'label' => 'OpenStreetMap',
            'company' => 'OpenStreetMap Foundation',
            'policy' => 'https://osmfoundation.org/wiki/Privacy_Policy',
        ],
        'carto' => [
            'label' => 'CARTO',
            'company' => 'CartoDB Inc.',
            'policy' => 'https://carto.com/privacy',
        ],
        'maps' => [],
    ];

    /**
     * The fields a description carries, in the order adapters read them.
     */
    private const FIELDS = ['label', 'company', 'policy', 'cookies', 'purpose'];

    /**
     * @param array<string, array<string, string|null>> $projectServices The `consent.services` setting, keyed by service
     * @param TranslatorInterface|null                   $translator      Names the custom map provider when the project does not
     */
    public function __construct(
        private readonly array $projectServices = [],
        private readonly ?TranslatorInterface $translator = null,
    ) {
    }

    /**
     * Every service key an editor may pick, with its name.
     *
     * @return array<string, string> Key => displayed name
     */
    public function keys(): array
    {
        $keys = [];
        foreach (array_keys(self::BUNDLE_SERVICES + $this->projectServices) as $key) {
            $keys[(string) $key] = $this->defaults((string) $key)['label'];
        }

        return $keys;
    }

    /**
     * The key of content waiting on a third party the inventory does not name.
     */
    public const GENERIC = 'embed';

    /**
     * The choices of the service fields of the admin, for a `single_select`.
     *
     * @param bool $withGeneric Offer the generic key too. A block needs it for
     *                          a third party that is none of the listed ones,
     *                          since a `single_select` cannot be emptied once
     *                          set. The snippet needs it so a cookie manager
     *                          fed from it gets a switch for those contents,
     *                          which would otherwise never load
     *
     * @return list<array{name: string, title: string}>
     */
    public function getSelectValues(bool $withGeneric = false): array
    {
        $values = [];
        if ($withGeneric) {
            $values[] = [
                'name' => self::GENERIC,
                'title' => $this->translator?->trans('iw_sulu_tailwind_theme.consent_service_generic', [], 'admin') ?? self::GENERIC,
            ];
        }

        foreach ($this->keys() as $key => $label) {
            // The visitor reads "the map provider" in a sentence, an editor
            // picking from a list needs to know which tiles these are.
            if ('maps' === $key && '' === trim((string) ($this->projectServices['maps']['label'] ?? '')) && null !== $this->translator) {
                $label = $this->translator->trans('iw_sulu_tailwind_theme.consent_service_maps', [], 'admin');
            }

            $values[] = ['name' => $key, 'title' => $label];
        }

        return $values;
    }

    /**
     * The services the snippet declares, described.
     *
     * @param array<string, mixed>|null $snippet The resolved snippet content, null when no snippet is assigned
     *
     * @return list<array{key: string, label: string, company: string, policy: string, cookies: string, purpose: string}>
     */
    public function declared(?array $snippet): array
    {
        $services = [];
        foreach ($this->snippetEntries($snippet) as $key => $entry) {
            $services[] = ['key' => $key] + $this->merge($this->defaults($key), $entry);
        }

        return $services;
    }

    /**
     * What to tell a visitor about one service.
     *
     * @param string                    $key     The service key
     * @param array<string, mixed>|null $snippet The resolved snippet content, null when no snippet is assigned
     *
     * @return array{key: string, label: string, company: string, policy: string, cookies: string, purpose: string, declared: bool|null}
     *         `declared` is null when the site keeps no inventory, so nothing
     *         can be said about it
     */
    public function describe(string $key, ?array $snippet): array
    {
        $entries = $this->snippetEntries($snippet);

        return ['key' => $key]
            + $this->merge($this->defaults($key), $entries[$key] ?? [])
            + ['declared' => null === $snippet ? null : isset($entries[$key])];
    }

    /**
     * The bundle's and the project's description of a service.
     *
     * @param string $key The service key
     *
     * @return array{label: string, company: string, policy: string, cookies: string, purpose: string}
     */
    private function defaults(string $key): array
    {
        $description = $this->merge(
            $this->merge(array_fill_keys(self::FIELDS, ''), self::BUNDLE_SERVICES[$key] ?? []),
            $this->projectServices[$key] ?? [],
        );

        if ('' === $description['label']) {
            $description['label'] = match (true) {
                null === $this->translator => $key,
                'maps' === $key => $this->translator->trans('iw_sulu_tailwind_theme.consent_map_provider'),
                self::GENERIC === $key => $this->translator->trans('iw_sulu_tailwind_theme.consent_generic_service'),
                default => $key,
            };
        }

        return $description;
    }

    /**
     * The snippet's services, keyed by service, the first one winning.
     *
     * @param array<string, mixed>|null $snippet The resolved snippet content
     *
     * @return array<string, array<string, mixed>>
     */
    private function snippetEntries(?array $snippet): array
    {
        $entries = [];
        foreach (\is_array($snippet['services'] ?? null) ? $snippet['services'] : [] as $entry) {
            $key = \is_array($entry) && \is_string($entry['service'] ?? null) ? trim($entry['service']) : '';
            if ('' !== $key && !isset($entries[$key])) {
                $entries[$key] = $entry;
            }
        }

        return $entries;
    }

    /**
     * Lay the non-empty fields of an override over a description.
     *
     * @param array<string, string> $description The description so far
     * @param array<string, mixed>  $override    The fields to lay over it
     *
     * @return array{label: string, company: string, policy: string, cookies: string, purpose: string}
     */
    private function merge(array $description, array $override): array
    {
        foreach (self::FIELDS as $field) {
            $value = $override[$field] ?? null;
            if (\is_string($value) && '' !== trim($value)) {
                $description[$field] = trim($value);
            }
        }

        /** @var array{label: string, company: string, policy: string, cookies: string, purpose: string} $description */
        return $description;
    }
}
