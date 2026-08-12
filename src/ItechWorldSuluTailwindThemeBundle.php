<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle;

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
                    ->end()
                ->end()
            ->end();
    }

    /**
     * Prepend configuration into other bundles (sulu_admin, doctrine).
     */
    public function prependExtension(
        ContainerConfigurator $container,
        ContainerBuilder $builder,
    ): void {
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
            'itech_world_sulu_tailwind_theme.blocks.iframe.allowed_hosts',
            $config['blocks']['iframe']['allowed_hosts'],
        );

        $container->import('../config/services.yaml');
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
