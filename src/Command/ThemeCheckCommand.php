<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Command;

use ItechWorld\SuluTailwindThemeBundle\Repository\ThemeConfigRepository;
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
 * bridge CSS is importable, and Stimulus assets are registered.
 */
#[AsCommand(
    name: 'iw:tailwind-theme:check',
    description: 'Run integration diagnostics for the Tailwind Theme Bundle',
)]
class ThemeCheckCommand extends Command
{
    public function __construct(
        private readonly ThemeConfigRepository $themeRepository,
        private readonly WebspaceThemeRepository $webspaceThemeRepository,
        private readonly WebspaceManagerInterface $webspaceManager,
        private readonly ThemeCompiler $compiler,
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
        foreach ($webspaces as $webspace) {
            $wsKey = $webspace->getKey();
            $wsTheme = $this->webspaceThemeRepository->findThemeForWebspace($wsKey);

            if (null === $wsTheme) {
                $checks[] = ['<fg=yellow>!</>', 'Webspace "' . $wsKey . '"', 'No theme assigned. Assign one in Admin > Settings.'];
            } else {
                $checks[] = ['<fg=green>✓</>', 'Webspace "' . $wsKey . '"', 'Theme: ' . $wsTheme->getLabel()];
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
        $bridgePath = $bundleDir . '/assets/styles/bridge.css';
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

        // ── Output table ──
        $io->table(['', 'Check', 'Result'], $checks);

        if ($hasErrors) {
            $io->error('Some critical checks failed. See above for details.');

            return Command::FAILURE;
        }

        $io->success('All critical checks passed!');

        return Command::SUCCESS;
    }
}
