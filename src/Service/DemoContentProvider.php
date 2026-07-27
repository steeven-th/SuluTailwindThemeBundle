<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

/**
 * Supplies lorem-ipsum demo content for the Live Theme Editor preview.
 *
 * The preview renders the theme's REAL block/component Twig for fidelity, so
 * this provider returns block presets shaped exactly like Sulu content blocks
 * ({type, style, ...content fields, ...settings}). Each preset is fed to the
 * shared `_blocks.html.twig` dispatcher, which spreads it into the matching
 * `blocks/<type>/_style_<style>.html.twig` template.
 *
 * Media fields carry small integer seeds instead of real Sulu media ids: in
 * the preview (demo mode) the unified image partial resolves them to stable
 * picsum.photos placeholders instead of calling sulu_resolve_media, so the demo
 * needs no fixtures or uploads.
 *
 * Sections map to the Live Editor sidebar. Only block-based, runtime-free
 * sections are implemented here; media-heavy self-resolving loops and
 * Sulu-runtime components (menu, footer, article_*) come with their own
 * sections in later subtasks.
 */
class DemoContentProvider
{
    /**
     * Default section rendered when none (or an unknown one) is requested.
     */
    public const DEFAULT_SECTION = 'showcase';

    /**
     * Return the demo content blocks for a preview section.
     *
     * @param string $section  The requested section key
     * @param int    $baseSeed Session image seed (0 = use the fixed defaults);
     *                         demo media seeds are derived from it so images
     *                         stay stable within a session but vary between them
     *
     * @return list<array<string, mixed>> Ordered demo blocks (Sulu-block shape)
     */
    public function getBlocks(string $section, int $baseSeed = 0): array
    {
        return match ($section) {
            'showcase' => $this->showcaseBlocks($baseSeed),
            // The typography section renders a type specimen chrome in the
            // preview template itself (not content blocks), so it needs none.
            'typography' => [],
            // The cards and articles sections render a demo article grid (see
            // getArticles()) as preview chrome, not content blocks.
            'cards', 'articles' => [],
            // The hero section renders a demo page banner (see getHero()) above
            // the showcase blocks, which stand in for the page content below it.
            'hero' => $this->showcaseBlocks($baseSeed),
            default => $this->showcaseBlocks($baseSeed),
        };
    }

    /**
     * The "showcase" section: a representative spread of colors, typography,
     * buttons-free text, stats, a testimonial card, an image block and
     * separators — enough to judge the base color/typography/radius tokens.
     *
     * @param int $baseSeed Session image seed (0 = fixed default)
     *
     * @return list<array<string, mixed>> The demo blocks
     */
    private function showcaseBlocks(int $baseSeed = 0): array
    {
        // Derive a distinct image seed from the session base (offset avoids
        // colliding with the hero/cards seeds); fall back to the fixed default.
        $imageSeed = $baseSeed > 0 ? $baseSeed + 50 : 101;

        return [
            $this->withSettings([
                'type' => 'text',
                'style' => 'one_column',
                'title' => 'Lorem ipsum dolor sit amet',
                'subTitle' => 'Consectetur adipiscing elit',
                'titleTag' => 'h2',
                'titleAlignment' => 'left',
                'text' => '<p>Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. '
                    . 'Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut '
                    . 'aliquip ex ea commodo consequat. Duis aute irure dolor in '
                    . '<a href="#">reprehenderit in voluptate</a> velit esse cillum dolore eu fugiat '
                    . 'nulla pariatur.</p>',
            ]),

            $this->withSettings([
                'type' => 'text_images',
                'style' => 'classic',
                'title' => 'Excepteur sint occaecat',
                'subTitle' => 'Cupidatat non proident',
                'titleTag' => 'h2',
                'titleAlignment' => 'left',
                // Integer seed → resolved to a stable picsum image in demo mode.
                'images' => [$imageSeed],
                'imageFilter' => '16_9',
                'imagePosition' => 'right',
                'mobileImagePosition' => 'top',
                'text' => '<p>Sunt in culpa qui officia deserunt mollit anim id est laborum. '
                    . 'Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit.</p>',
            ]),

            $this->withSettings([
                'type' => 'key_figures',
                'style' => 'inline',
                'title' => 'Sed ut perspiciatis',
                'subTitle' => 'Unde omnis iste natus',
                'titleTag' => 'h2',
                'titleAlignment' => 'center',
                'figures' => [
                    ['number' => '250+', 'title' => 'Totam rem', 'subTitle' => 'aperiam eaque'],
                    ['number' => '99%', 'title' => 'Ipsa quae', 'subTitle' => 'ab illo inventore'],
                    ['number' => '12k', 'title' => 'Veritatis', 'subTitle' => 'et quasi architecto'],
                ],
            ]),

            $this->withSettings([
                'type' => 'testimonial',
                'style' => 'cards',
                'title' => 'Neque porro quisquam',
                'subTitle' => 'Qui dolorem ipsum',
                'titleTag' => 'h2',
                'titleAlignment' => 'center',
                'quote' => 'Quis autem vel eum iure reprehenderit qui in ea voluptate velit esse '
                    . 'quam nihil molestiae consequatur, vel illum qui dolorem eum fugiat.',
                'author' => 'Marcus Aurelius',
                'role' => 'Consul, SPQR',
                'rating' => 5,
            ]),

            $this->withSettings([
                'type' => 'separator',
                'style' => 'line',
                'lineStyle' => 'solid',
                'lineWidth' => 'medium',
            ]),

            $this->withSettings([
                'type' => 'text',
                'style' => 'quote',
                'title' => 'At vero eos et accusamus',
                'titleTag' => 'h3',
                'titleAlignment' => 'left',
                'text' => '<p>Itaque earum rerum hic tenetur a sapiente delectus, ut aut reiciendis '
                    . 'voluptatibus maiores alias consequatur aut perferendis doloribus.</p>',
            ]),
        ];
    }

    /**
     * Demo articles for the Cards section preview grid.
     *
     * Each entry is a plain map exposing only the keys the article card partial
     * reads. Image fields carry an integer seed (resolved to a stable picsum
     * placeholder in demo mode); routePath is "#" so no route is resolved.
     *
     * @param int $baseSeed Session image seed (0 = fixed defaults 201–206)
     *
     * @return list<array<string, mixed>> The demo articles
     */
    public function getArticles(int $baseSeed = 0): array
    {
        // Six consecutive seeds derived from the session base (fixed 200 offset
        // by default), so every card gets a distinct but session-stable image.
        $base = $baseSeed > 0 ? $baseSeed : 200;
        $specs = [
            ['Lorem ipsum dolor sit amet', 'Design', '2024-05-12', $base + 1],
            ['Consectetur adipiscing elit sed', 'Development', '2024-04-28', $base + 2],
            ['Eiusmod tempor incididunt labore', 'Product', '2024-04-15', $base + 3],
            ['Ut enim ad minim veniam quis', 'Design', '2024-03-30', $base + 4],
            ['Duis aute irure dolor in reprehenderit', 'Development', '2024-03-11', $base + 5],
            ['Excepteur sint occaecat cupidatat', 'Product', '2024-02-22', $base + 6],
        ];

        $articles = [];
        foreach ($specs as [$title, $category, $date, $seed]) {
            $articles[] = [
                'title' => $title,
                'routePath' => '#',
                'excerptDescription' => 'Sed do eiusmod tempor incididunt ut labore et dolore magna '
                    . 'aliqua ut enim ad minim veniam quis nostrud.',
                'authored' => $date,
                'excerptImages' => [$seed],
                'excerptCategories' => [['name' => $category]],
            ];
        }

        return $articles;
    }

    /**
     * Demo page-hero content for the Page hero section preview.
     *
     * The image is an integer seed resolved to a stable picsum placeholder in
     * demo mode; the caller builds the media object from it.
     *
     * @param int $baseSeed Session image seed (0 = fixed default 701)
     *
     * @return array{title: string, subtitle: string, imageSeed: int} The hero demo data
     */
    public function getHero(int $baseSeed = 0): array
    {
        return [
            'title' => 'Lorem ipsum dolor sit amet',
            'subtitle' => 'Consectetur adipiscing elit — sed do eiusmod tempor incididunt.',
            'imageSeed' => $baseSeed > 0 ? $baseSeed : 701,
        ];
    }

    /**
     * Merge a block preset with sane default layout settings so the shared
     * block wrapper renders comfortably without a full Sulu settings payload.
     *
     * @param array<string, mixed> $block The block content preset
     *
     * @return array<string, mixed> The block with default settings applied
     */
    private function withSettings(array $block): array
    {
        return array_merge([
            'marginTop' => 'mt-5',
            'marginBottom' => 'mb-5',
            'paddingTop' => 'pt-5',
            'paddingBottom' => 'pb-5',
            'paddingLateral' => 'px-5',
            'lateralMargins' => 'exterior',
            'showBackground' => true,
        ], $block);
    }
}
