<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle;

use ItechWorld\SuluTailwindThemeBundle\Form\FormSubmissionHandler;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Main bundle class for the SuluTailwindThemeBundle.
 *
 * Provides a complete theming system based on design tokens (JSON)
 * compiled to CSS custom properties for Sulu CMS 3.x.
 */
class ItechWorldSuluTailwindThemeBundle extends AbstractBundle
{
    /**
     * Available article template type keys.
     */
    private const ARTICLE_TYPES = ['news', 'event', 'blog_post'];

    /**
     * Extension alias of pixelopen/cloudflare-turnstile-bundle.
     */
    private const TURNSTILE_EXTENSION = 'pixel_open_cloudflare_turnstile';

    /**
     * Cloudflare's "always passes" test keys.
     *
     * Used only as a boot-time placeholder while the feature is disabled: that
     * bundle marks `key` and `secret` as required and non-empty, so a project
     * that installs it without configuring it cannot start at all. They are
     * never a fallback for an enabled Turnstile — a challenge validating
     * everything is worse than no challenge, because it looks protected.
     */
    /**
     * Cloudflare's "always passes" test site key.
     *
     * Public so the theme can recognise it at runtime: used while the feature is
     * disabled it is a harmless placeholder, but reaching production with the
     * feature enabled means the challenge validates every visitor, robots
     * included - see ThemeExtension::getTurnstileStatus().
     */
    public const TURNSTILE_TEST_SITE_KEY = '1x00000000000000000000AA';
    private const TURNSTILE_TEST_SECRET_KEY = '1x0000000000000000000000000000000AA';

    /**
     * Define the bundle configuration schema.
     */
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('google_fonts_api_key')
                    ->defaultNull()
                    ->info('Google Fonts API key (from env: %env(GOOGLE_FONTS_API_KEY)%)')
                ->end()
                ->arrayNode('article_templates')
                    ->addDefaultsIfNotSet()
                    ->info('Opt-in article templates (requires sulu/sulu article package)')
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultFalse()
                            ->info('Enable article template registration')
                        ->end()
                        ->arrayNode('types')
                            ->defaultValue(self::ARTICLE_TYPES)
                            ->scalarPrototype()->end()
                            ->info('Whitelist of article types to register (news, event, blog_post)')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('turnstile')
                    ->addDefaultsIfNotSet()
                    ->info('Cloudflare Turnstile field for SuluFormBundle (requires pixelopen/cloudflare-turnstile-bundle)')
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultFalse()
                            ->info('Offer the Turnstile field in the form builder and verify submitted tokens')
                        ->end()
                        ->scalarNode('site_key')
                            ->defaultNull()
                            ->info('Cloudflare site key (from env: %env(TURNSTILE_KEY)%)')
                        ->end()
                        ->scalarNode('secret_key')
                            ->defaultNull()
                            ->info('Cloudflare secret key (from env: %env(TURNSTILE_SECRET)%)')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('title_editor')
                    ->addDefaultsIfNotSet()
                    ->info('Which buttons the title editor field offers, per context. Defaults reproduce the shipped behavior, so leaving this out changes nothing.')
                    ->children()
                        ->arrayNode('blocks')
                            ->addDefaultsIfNotSet()
                            ->info('Block headings. Their accent color comes from the block variant, so the palette button is off by default.')
                            ->children()
                                ->booleanNode('highlight')
                                    ->defaultTrue()
                                    ->info('Offer the highlight button, which colors the selection with the variant highlight color')
                                ->end()
                                ->booleanNode('color')
                                    ->defaultFalse()
                                    ->info('Offer the palette button, letting an editor pick an explicit color per word')
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('pages')
                            ->addDefaultsIfNotSet()
                            ->info('Page hero titles and article subtitles. They sit outside any variant, so the palette button is on by default.')
                            ->children()
                                ->booleanNode('highlight')
                                    ->defaultFalse()
                                    ->info('Offer the highlight button')
                                ->end()
                                ->booleanNode('color')
                                    ->defaultTrue()
                                    ->info('Offer the palette button')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('blocks')
                    ->addDefaultsIfNotSet()
                    ->info('Per-block security settings')
                    ->children()
                        ->arrayNode('iframe')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->arrayNode('allowed_hosts')
                                    ->defaultValue([])
                                    ->scalarPrototype()->end()
                                    ->info('Hosts the iframe block may embed (an entry also covers its subdomains). Empty allows any https host.')
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('code')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('allow_unsandboxed')
                                    ->defaultFalse()
                                    ->info('Expose a per-block checkbox letting editors run pasted markup directly in the page. Makes anyone able to edit a page able to execute JavaScript on the site, including in the admin preview. Leave false unless every editor is trusted at administrator level.')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * Prepend configuration into other bundles (sulu_admin, sulu_media, doctrine).
     */
    public function prependExtension(
        ContainerConfigurator $container,
        ContainerBuilder $builder,
    ): void {
        // Register the theme's image formats (iw_theme_*, iw_og_image). Without
        // them the templates fall back to the untouched original file, so images
        // are served full-size, uncropped and without their WebP/AVIF variants.
        if ($builder->hasExtension('sulu_media')) {
            $builder->prependExtensionConfig('sulu_media', [
                'image_format_files' => [
                    __DIR__ . '/../config/image-formats.xml',
                ],
            ]);
        }

        // A form handled by FormSubmissionHandler lives in a page served from a
        // long-lived proxy cache, where a session-bound CSRF token would be the
        // one of whoever warmed that cache - so every later submission would
        // fail, in production only. Declaring the id stateless makes Symfony
        // validate it on the origin of the request instead, and doing it here
        // means a project has nothing to configure. Prepended, so the value
        // adds itself to Symfony's own ids and to the project's.
        if ($builder->hasExtension('framework')) {
            $builder->prependExtensionConfig('framework', [
                'csrf_protection' => [
                    'stateless_token_ids' => [FormSubmissionHandler::CSRF_TOKEN_ID],
                ],
            ]);
        }

        // Feed the Turnstile credentials to pixelopen from this bundle's own
        // config, so a project has a single place to configure the feature.
        $this->prependTurnstileConfig($builder);

        // Register Doctrine ORM mapping for this bundle's entities
        if ($builder->hasExtension('doctrine')) {
            $builder->prependExtensionConfig('doctrine', [
                'orm' => [
                    'mappings' => [
                        'ItechWorldSuluTailwindThemeBundle' => [
                            'type' => 'attribute',
                            'is_bundle' => false,
                            'dir' => __DIR__ . '/Entity',
                            'prefix' => 'ItechWorld\\SuluTailwindThemeBundle\\Entity',
                            'alias' => 'ItechWorldSuluTailwindThemeBundle',
                        ],
                    ],
                ],
            ]);
        }

        // Register Sulu admin resources: lists, forms, and API routes
        if ($builder->hasExtension('sulu_admin')) {
            $builder->prependExtensionConfig('sulu_admin', [
                'lists' => [
                    'directories' => [
                        __DIR__ . '/../config/lists',
                    ],
                ],
                'forms' => [
                    'directories' => [
                        __DIR__ . '/../config/forms',
                    ],
                ],
                'resources' => [
                    'iw_theme_configs' => [
                        'routes' => [
                            'list' => 'iw_sulu_tailwind_theme.get_theme_configs',
                            'detail' => 'iw_sulu_tailwind_theme.get_theme_config',
                        ],
                    ],
                    'iw_webspace_themes' => [
                        'routes' => [
                            'detail' => 'iw_sulu_tailwind_theme.get_webspace_theme',
                        ],
                    ],
                ],
            ]);

            // Register page template directories
            $builder->prependExtensionConfig('sulu_admin', [
                'templates' => [
                    'page' => [
                        'directories' => [
                            'iw_sulu_tailwind_theme' => __DIR__ . '/../config/templates/pages',
                        ],
                    ],
                ],
            ]);

            // Register global block type directories
            $builder->prependExtensionConfig('sulu_admin', [
                'templates' => [
                    'block' => [
                        'directories' => [
                            'iw_sulu_tailwind_theme' => __DIR__ . '/../config/templates/blocks',
                        ],
                    ],
                ],
            ]);

            // Register the form block variant based on SuluFormBundle availability.
            // When the bundle is installed, the form block includes a single_form_selection
            // field; otherwise it only offers a Twig template path input.
            $formBlockDir = class_exists(\Sulu\Bundle\FormBundle\SuluFormBundle::class)
                ? __DIR__ . '/../config/templates/blocks-form-bundle'
                : __DIR__ . '/../config/templates/blocks-form';

            $builder->prependExtensionConfig('sulu_admin', [
                'templates' => [
                    'block' => [
                        'directories' => [
                            'iw_sulu_tailwind_theme_form' => $formBlockDir,
                        ],
                    ],
                ],
            ]);

            // Register the code block variant. The escape-hatch checkbox that
            // disables the sandbox only exists in the admin form when the
            // project explicitly opted in, so an editor is never offered a
            // decision the project has not made. Same trick as the form block:
            // both files declare <key>code</key>, only one directory is loaded.
            $codeBlockDir = $this->isUnsandboxedCodeAllowed($builder)
                ? __DIR__ . '/../config/templates/blocks-code-open'
                : __DIR__ . '/../config/templates/blocks-code';

            $builder->prependExtensionConfig('sulu_admin', [
                'templates' => [
                    'block' => [
                        'directories' => [
                            'iw_sulu_tailwind_theme_code' => $codeBlockDir,
                        ],
                    ],
                ],
            ]);

            // Register article template directories (opt-in, requires SuluArticleBundle)
            $this->registerArticleTemplates($builder);

            // Register snippet template directories
            $builder->prependExtensionConfig('sulu_admin', [
                'templates' => [
                    'snippet' => [
                        'directories' => [
                            'iw_sulu_tailwind_theme' => __DIR__ . '/../config/templates/snippets',
                        ],
                    ],
                ],
            ]);
        }
    }

    /**
     * Load bundle services and set configuration parameters.
     */
    public function loadExtension(
        array $config,
        ContainerConfigurator $container,
        ContainerBuilder $builder,
    ): void {
        $container->parameters()->set(
            'itech_world_sulu_tailwind_theme.css_output_dir',
            '%kernel.project_dir%/var/cache/iw_sulu_tailwind_theme',
        );
        $container->parameters()->set(
            'itech_world_sulu_tailwind_theme.google_fonts_api_key',
            $config['google_fonts_api_key'],
        );
        $container->parameters()->set(
            'itech_world_sulu_tailwind_theme.article_templates_enabled',
            $config['article_templates']['enabled'],
        );
        $container->parameters()->set(
            'itech_world_sulu_tailwind_theme.article_templates_types',
            $config['article_templates']['types'],
        );
        $container->parameters()->set(
            'itech_world_sulu_tailwind_theme.title_editor',
            $config['title_editor'],
        );
        $container->parameters()->set(
            'itech_world_sulu_tailwind_theme.blocks.iframe.allowed_hosts',
            $config['blocks']['iframe']['allowed_hosts'],
        );
        $container->parameters()->set(
            'itech_world_sulu_tailwind_theme.blocks.code.allow_unsandboxed',
            $config['blocks']['code']['allow_unsandboxed'],
        );
        $container->parameters()->set(
            'itech_world_sulu_tailwind_theme.turnstile.enabled',
            $config['turnstile']['enabled'],
        );
        // The site key is public by design (it ships in the HTML), and a form
        // written in Twig template mode has no other way to reach it: the
        // widget of the SuluFormBundle mode is a form field, out of reach
        // there. Exposing it here keeps the credentials declared in one place.
        $container->parameters()->set(
            'itech_world_sulu_tailwind_theme.turnstile.site_key',
            $config['turnstile']['site_key'],
        );

        $container->import('../config/services.yaml');

        // The Turnstile field bridges two optional bundles: it is only usable
        // when both are present and the project opted in. Missing either one
        // must leave the app booting normally, simply without the field.
        //
        // Registration is checked against kernel.bundles rather than
        // class_exists(): a package can sit in vendor/ (so its classes
        // autoload) while its bundle was never added to config/bundles.php.
        // Its form type would then not exist as a service and every form
        // holding the field would break at render time.
        if ($config['turnstile']['enabled'] && $this->hasTurnstileDependencies($builder)) {
            $container->import('../config/services_turnstile.yaml');
        }
    }

    /**
     * Whether both bundles backing the Turnstile field are registered.
     *
     * @param ContainerBuilder $builder The container builder
     *
     * @return bool True when the field can safely be offered
     */
    private function hasTurnstileDependencies(ContainerBuilder $builder): bool
    {
        $bundles = $builder->hasParameter('kernel.bundles') ? $builder->getParameter('kernel.bundles') : [];

        if (!\is_array($bundles)) {
            return false;
        }

        return \array_key_exists('SuluFormBundle', $bundles)
            && \array_key_exists('PixelOpenCloudflareTurnstileBundle', $bundles);
    }

    /**
     * Prepend the Turnstile credentials into pixelopen's extension.
     *
     * That bundle requires `key` and `secret` to be set and non-empty, so
     * installing it without configuring it prevents the container from
     * compiling at all. Prepending keeps a project's own
     * config/packages/pixel_open_cloudflare_turnstile.yaml authoritative (a
     * prepended value always loses against an explicitly configured one) while
     * making this bundle's `turnstile` node enough on its own.
     *
     * @param ContainerBuilder $builder The container builder
     */
    private function prependTurnstileConfig(ContainerBuilder $builder): void
    {
        if (!$builder->hasExtension(self::TURNSTILE_EXTENSION)) {
            return;
        }

        $turnstile = $this->resolveTurnstileConfig($builder);

        // `enable: false` short-circuits both the widget and the token check in
        // pixelopen, which is what "disabled" has to mean end to end.
        $config = ['enable' => $turnstile['enabled']];

        if (null !== $turnstile['site_key']) {
            $config['key'] = $turnstile['site_key'];
        }
        if (null !== $turnstile['secret_key']) {
            $config['secret'] = $turnstile['secret_key'];
        }

        if (!$turnstile['enabled']) {
            // Nothing is verified while disabled, so placeholders are harmless
            // here — and they are what keeps an unconfigured install bootable.
            $config['key'] ??= self::TURNSTILE_TEST_SITE_KEY;
            $config['secret'] ??= self::TURNSTILE_TEST_SECRET_KEY;
        }

        $builder->prependExtensionConfig(self::TURNSTILE_EXTENSION, $config);
    }

    /**
     * Resolve the turnstile config from raw extension config arrays.
     *
     * prependExtension() runs before configuration processing, so the values
     * have to be merged by hand — same approach as the code block above.
     *
     * @param ContainerBuilder $builder The container builder
     *
     * @return array{enabled: bool, site_key: string|null, secret_key: string|null}
     */
    private function resolveTurnstileConfig(ContainerBuilder $builder): array
    {
        $resolved = [
            'enabled' => false,
            'site_key' => null,
            'secret_key' => null,
        ];

        foreach ($builder->getExtensionConfig('itech_world_sulu_tailwind_theme') as $config) {
            if (!isset($config['turnstile']) || !\is_array($config['turnstile'])) {
                continue;
            }

            $turnstile = $config['turnstile'];

            if (isset($turnstile['enabled'])) {
                $resolved['enabled'] = (bool) $turnstile['enabled'];
            }
            foreach (['site_key', 'secret_key'] as $key) {
                if (isset($turnstile[$key]) && \is_string($turnstile[$key]) && '' !== $turnstile[$key]) {
                    $resolved[$key] = $turnstile[$key];
                }
            }
        }

        return $resolved;
    }

    /**
     * Whether the project allows editors to disable the code block sandbox.
     *
     * Read from the raw extension config: prependExtension() runs before config
     * processing, so the processed values are not available yet.
     *
     * @param ContainerBuilder $builder The container builder
     *
     * @return bool True when the unsandboxed checkbox must be exposed
     */
    private function isUnsandboxedCodeAllowed(ContainerBuilder $builder): bool
    {
        $allowed = false;

        foreach ($builder->getExtensionConfig('itech_world_sulu_tailwind_theme') as $config) {
            if (isset($config['blocks']['code']['allow_unsandboxed'])) {
                $allowed = (bool) $config['blocks']['code']['allow_unsandboxed'];
            }
        }

        return $allowed;
    }

    /**
     * Register the article template directory based on configuration.
     *
     * Checks both that the SuluArticleBundle is available and that the
     * developer has opted in via the article_templates config. All article
     * templates live in a single config/templates/articles/ directory
     * following the Sulu convention.
     *
     * @param ContainerBuilder $builder The container builder
     */
    private function registerArticleTemplates(ContainerBuilder $builder): void
    {
        if (!class_exists(\Sulu\Article\Infrastructure\Symfony\HttpKernel\SuluArticleBundle::class)) {
            return;
        }

        // Read raw config to check if article templates are enabled.
        // prependExtension() runs before loadExtension(), so processed
        // config is not available yet — we must inspect the raw arrays.
        $articleConfig = $this->resolveArticleConfig($builder);

        if (!$articleConfig['enabled']) {
            return;
        }

        $builder->prependExtensionConfig('sulu_admin', [
            'templates' => [
                'article' => [
                    'directories' => [
                        'iw_sulu_tailwind_theme' => __DIR__ . '/../config/templates/articles',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Resolve article_templates config from raw extension config arrays.
     *
     * Since prependExtension() runs before config processing, we must
     * manually merge the raw config arrays to find the effective values.
     *
     * @return array{enabled: bool, types: list<string>}
     */
    private function resolveArticleConfig(ContainerBuilder $builder): array
    {
        $defaults = [
            'enabled' => false,
            'types' => self::ARTICLE_TYPES,
        ];

        $configs = $builder->getExtensionConfig('itech_world_sulu_tailwind_theme');

        foreach ($configs as $config) {
            if (isset($config['article_templates']['enabled'])) {
                $defaults['enabled'] = (bool) $config['article_templates']['enabled'];
            }
            if (isset($config['article_templates']['types'])) {
                $defaults['types'] = (array) $config['article_templates']['types'];
            }
        }

        return $defaults;
    }
}
