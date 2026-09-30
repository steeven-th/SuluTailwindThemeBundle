<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Command;

use ItechWorld\SuluTailwindThemeBundle\Color\ColorSet;
use ItechWorld\SuluTailwindThemeBundle\Content\Migration\ButtonFieldAudit;
use ItechWorld\SuluTailwindThemeBundle\Repository\ThemeConfigRepository;
use ItechWorld\SuluTailwindThemeBundle\Service\AppearanceOverrideAudit;
use ItechWorld\SuluTailwindThemeBundle\Service\ButtonResolver;
use ItechWorld\SuluTailwindThemeBundle\Service\ButtonStyleAudit;
use ItechWorld\SuluTailwindThemeBundle\Service\RequiredButtonStyles;
use ItechWorld\SuluTailwindThemeBundle\Repository\WebspaceThemeRepository;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeCompiler;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Diagnostic command that checks the integration health of the Tailwind Theme Bundle.
 *
 * Verifies: active theme exists per webspace, CSS is compiled,
 * bridge CSS is importable, and Stimulus assets are registered. Reports the
 * content the rendering cannot flag on its own: buttons in the old shape or
 * naming a style their theme lacks, and per-site choices pointing nowhere.
 */
#[AsCommand(
    name: 'iw:tailwind-theme:check',
    description: 'Run integration diagnostics for the Tailwind Theme Bundle',
)]
class ThemeCheckCommand extends Command
{
    /**
     * The palette names Tailwind ships with.
     *
     * A colour named after one of them takes its utilities over: the theme
     * stylesheet is plain CSS while Tailwind's utilities sit in a layer, and
     * unlayered rules win whatever the order. `text-rose-500` would then paint
     * the theme colour everywhere the project already used it, silently, which
     * is worth a word even though the name is the user's to choose.
     */
    private const TAILWIND_PALETTES = [
        'slate', 'gray', 'zinc', 'stone', 'red', 'orange', 'amber', 'yellow',
        'lime', 'green', 'emerald', 'teal', 'cyan', 'sky', 'blue', 'indigo',
        'violet', 'purple', 'fuchsia', 'pink', 'rose',
    ];

    public function __construct(
        private readonly ThemeConfigRepository $themeRepository,
        private readonly WebspaceThemeRepository $webspaceThemeRepository,
        private readonly WebspaceManagerInterface $webspaceManager,
        private readonly ThemeCompiler $compiler,
        private readonly AppearanceOverrideAudit $appearanceAudit,
        private readonly ButtonFieldAudit $buttonFieldAudit,
        private readonly ButtonStyleAudit $buttonStyleAudit,
        private readonly RequiredButtonStyles $requiredButtonStyles,
        private readonly KernelInterface $kernel,
        private readonly string $cssOutputDir,
    ) {
        parent::__construct();
    }

    /**
     * Execute the diagnostic checks.
     *
     * @param InputInterface  $input  The console input
     * @param OutputInterface $output The console output
     *
     * @return int The command exit code (SUCCESS if all critical checks pass)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Tailwind Theme Bundle — Diagnostic');

        $hasErrors = false;
        $checks = [];

        // ── Check 1: Themes in database ──
        $themes = $this->themeRepository->findAll();
        if (empty($themes)) {
            $checks[] = ['<fg=red>✗</>', 'Themes in database', 'No themes found. Run iw-sulu:theme:install to create one.'];
            $hasErrors = true;
        } else {
            $themeNames = array_map(fn ($t) => $t->getLabel() . ' (' . $t->getName() . ')', $themes);
            $checks[] = ['<fg=green>✓</>', 'Themes in database', implode(', ', $themeNames)];
        }

        // ── Check 2: Webspace theme assignments ──
        $webspaces = $this->webspaceManager->getWebspaceCollection()->getWebspaces();
        // Webspace key => the button styles of its theme, for the style check.
        $buttonSlugsBySite = [];
        // Theme id => theme, for the themes a site shows.
        $assignedThemes = [];
        foreach ($webspaces as $webspace) {
            $wsKey = $webspace->getKey();
            $wsTheme = $this->webspaceThemeRepository->findThemeForWebspace($wsKey);

            if (null === $wsTheme) {
                $checks[] = ['<fg=yellow>!</>', 'Webspace "' . $wsKey . '"', 'No theme assigned. Assign one in Admin > Settings.'];
            } else {
                $checks[] = ['<fg=green>✓</>', 'Webspace "' . $wsKey . '"', 'Theme: ' . $wsTheme->getLabel()];
                $buttonSlugsBySite[$wsKey] = array_column(ButtonResolver::normalizeButtons($wsTheme->getTokens()['buttons'] ?? []), 'slug');
                $assignedThemes[$wsTheme->getId()] = $wsTheme;
            }
        }

        // ── Check 3: CSS compiled ──
        $cssDir = str_replace('%kernel.project_dir%', $this->kernel->getProjectDir(), $this->cssOutputDir);
        if (is_dir($cssDir)) {
            $cssFiles = glob($cssDir . '/*.css');
            if (empty($cssFiles)) {
                $checks[] = ['<fg=red>✗</>', 'Compiled CSS', 'Output dir exists but no CSS files. Run iw-sulu:theme:compile.'];
                $hasErrors = true;
            } else {
                $checks[] = ['<fg=green>✓</>', 'Compiled CSS', count($cssFiles) . ' file(s) in ' . $cssDir];
            }
        } else {
            $checks[] = ['<fg=red>✗</>', 'Compiled CSS', 'Output dir not found: ' . $cssDir . '. Run iw-sulu:theme:compile.'];
            $hasErrors = true;
        }

        // ── Check 4: Bridge CSS file ──
        $bundleDir = dirname(__DIR__, 2);
        $bridgePath = $bundleDir . '/assets/styles/tailwind-theme-bridge.css';
        if (file_exists($bridgePath)) {
            $checks[] = ['<fg=green>✓</>', 'Bridge CSS', $bridgePath];
        } else {
            $checks[] = ['<fg=yellow>!</>', 'Bridge CSS', 'Not found at ' . $bridgePath . '. Optional but recommended for Tailwind 4 integration.'];
        }

        // ── Check 5: Stimulus controllers ──
        $controllersDir = $bundleDir . '/assets/controllers';
        if (is_dir($controllersDir)) {
            $jsFiles = glob($controllersDir . '/*_controller.js');
            if (empty($jsFiles)) {
                $checks[] = ['<fg=yellow>!</>', 'Stimulus controllers', 'No controllers found in ' . $controllersDir];
            } else {
                $controllerNames = array_map(
                    fn ($f) => basename($f, '_controller.js'),
                    $jsFiles,
                );
                $checks[] = ['<fg=green>✓</>', 'Stimulus controllers', implode(', ', $controllerNames)];
            }
        } else {
            $checks[] = ['<fg=yellow>!</>', 'Stimulus controllers', 'Directory not found: ' . $controllersDir];
        }

        // ── Check 6: controllers.json registration ──
        $projectDir = $this->kernel->getProjectDir();
        $controllersJson = $projectDir . '/assets/controllers.json';
        if (file_exists($controllersJson)) {
            $jsonContent = file_get_contents($controllersJson);
            if (false !== $jsonContent && str_contains($jsonContent, '@itech-world/sulu-tailwind-theme-bundle')) {
                $checks[] = ['<fg=green>✓</>', 'controllers.json', 'Bundle controllers registered'];
            } else {
                $checks[] = ['<fg=yellow>!</>', 'controllers.json', 'Bundle not found in ' . $controllersJson . '. Stimulus controllers may not load.'];
            }
        } else {
            $checks[] = ['<fg=yellow>!</>', 'controllers.json', 'File not found at ' . $controllersJson];
        }

        // ── Check 7: SuluArticleBundle availability ──
        // Articles ship with the Sulu 3 core, under the Sulu\Article namespace.
        // The Sulu 2 class (Sulu\Bundle\ArticleBundle\SuluArticleBundle) no
        // longer exists, so probing it reported "not installed" on every
        // Sulu 3 install. Same class the bundle itself gates on.
        if (class_exists(\Sulu\Article\Infrastructure\Symfony\HttpKernel\SuluArticleBundle::class)) {
            $checks[] = ['<fg=green>✓</>', 'SuluArticleBundle', 'Available — article templates and blocks will work'];
        } else {
            $checks[] = ['<fg=yellow>!</>', 'SuluArticleBundle', 'Not installed — article templates and blocks will be disabled'];
        }

        // ── Check: per-site appearance choices nobody can reach ──
        // Nothing breaks when one is left behind, which is why it takes a
        // check to find them: the page renders the value every site follows
        // and the admin never offers the site again.
        if ($this->appearanceAudit->isSupported()) {
            $orphans = $this->appearanceAudit->findOrphans();

            if ([] === $orphans) {
                $checks[] = ['<fg=green>✓</>', 'Per-site appearance', 'No override points at a site its article left'];
            } else {
                $checks[] = [
                    '<fg=yellow>!</>',
                    'Per-site appearance',
                    \count($orphans) . ' override(s) name a site the article is not published on',
                ];
            }
        }

        // ── Check: buttons and pictograms still in the flat shape ──
        // Sulu no longer resolves them, so the page silently drops them.
        $pendingButtons = array_filter($this->buttonFieldAudit->countPending());
        if ([] === $pendingButtons) {
            $checks[] = ['<fg=green>✓</>', 'Buttons and pictograms', 'All stored on their fields'];
        } else {
            $checks[] = [
                '<fg=red>✗</>',
                'Buttons and pictograms',
                array_sum($pendingButtons) . ' content row(s) in the old shape, not rendered. Run iw-sulu:theme:migrate-buttons.',
            ];
            $hasErrors = true;
        }

        // ── Check: button styles the theme of their site does not define ──
        // The button falls back to the generic look of app.css, so nothing
        // looks broken enough to be noticed: a warning, not a failure.
        $unknownStyles = $this->buttonStyleAudit->findUnknown($buttonSlugsBySite);
        if ([] === $unknownStyles) {
            $checks[] = ['<fg=green>✓</>', 'Button styles', 'Every button names a style of its site\'s theme'];
        } else {
            $checks[] = [
                '<fg=yellow>!</>',
                'Button styles',
                \count(array_unique(array_column($unknownStyles, 'id'))) . ' content(s) hold buttons naming a style their site\'s theme does not define',
            ];
        }

        // ── Check: button styles the project CSS depends on ──
        // Only the themes a site shows: a spare theme lacking them breaks
        // nothing until it is assigned, and would be flagged forever.
        if ([] !== $this->requiredButtonStyles->all()) {
            foreach ($assignedThemes as $theme) {
                $missing = $this->requiredButtonStyles->missingIn($theme);
                $checks[] = [] === $missing
                    ? ['<fg=green>✓</>', 'Required button styles', $theme->getLabel() . ': all defined']
                    : [
                        '<fg=yellow>!</>',
                        'Required button styles',
                        $theme->getLabel() . ': missing ' . implode(', ', $missing) . ' - the project CSS targeting them matches nothing',
                    ];
            }
        }

        // ── Check: colour names shadowing a Tailwind palette ──
        // The compiler emits `bg-<slug>` for every colour named outside the
        // base roles, which is what makes an admin colour usable as a utility
        // class at all. When the name is one Tailwind already uses, the two
        // classes collide and the theme wins.
        $shadowing = [];
        foreach ($themes as $theme) {
            foreach (ColorSet::fromTokens($theme->getTokens())->getColors() as $color) {
                if (\in_array($color['slug'], self::TAILWIND_PALETTES, true)) {
                    $shadowing[] = $theme->getLabel() . ' → ' . $color['slug'];
                }
            }
        }

        if ([] === $shadowing) {
            $checks[] = ['<fg=green>✓</>', 'Color names', 'No colour takes a Tailwind palette name over'];
        } else {
            $checks[] = [
                '<fg=yellow>!</>',
                'Color names',
                implode(', ', $shadowing) . ' - these override Tailwind\'s palette of the same name site-wide',
            ];
        }

        // ── Output table ──
        $io->table(['', 'Check', 'Result'], $checks);

        if (isset($orphans) && [] !== $orphans) {
            $io->section('Per-site appearance overrides pointing nowhere');
            $io->text(
                'Each row is a colour choice recorded for a site the article is no longer published on. '
                . 'It changes nothing on the site, and the admin cannot offer to undo it. '
                . 'Edit the block on the site it belongs to, or publish the article there again.',
            );
            $io->table(
                ['Article', 'Locale', 'Stage', 'Site named', 'Property'],
                array_map(
                    static fn (array $orphan): array => [
                        $orphan['article'],
                        $orphan['locale'],
                        $orphan['stage'],
                        $orphan['webspace'],
                        $orphan['property'],
                    ],
                    $orphans,
                ),
            );
        }

        if ([] !== $unknownStyles) {
            $io->section('Buttons naming a style their theme does not define');
            $io->text(
                'Each row is a button whose class matches no style of the theme: it shows the generic look of app.css '
                . 'instead of the theme\'s colours. Open the content and pick one of the theme\'s styles. '
                . 'Site "' . ButtonStyleAudit::ANY_SITE . '" means a snippet no area assigns, which no theme defines the style for.',
            );
            $io->table(
                ['Content', 'Title', 'Id', 'Site', 'Style', 'Buttons', 'Found in'],
                array_map(
                    static fn (array $group): array => [
                        $group['kind'],
                        $group['title'],
                        $group['id'],
                        $group['site'],
                        $group['style'],
                        $group['buttons'],
                        implode(', ', $group['versions']),
                    ],
                    ButtonStyleAudit::byContent($unknownStyles),
                ),
            );
        }

        if ($hasErrors) {
            $io->error('Some critical checks failed. See above for details.');

            return Command::FAILURE;
        }

        $io->success('All critical checks passed!');

        return Command::SUCCESS;
    }
}
