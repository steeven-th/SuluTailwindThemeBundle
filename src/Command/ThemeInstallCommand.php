<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use ItechWorld\SuluTailwindThemeBundle\DataFixtures\ThemeFixtures;
use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;
use ItechWorld\SuluTailwindThemeBundle\Repository\ThemeConfigRepository;
use ItechWorld\SuluTailwindThemeBundle\Repository\WebspaceThemeRepository;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeCompiler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Console command to install a preset theme.
 *
 * Loads preset data from ThemeFixtures, creates the entity,
 * compiles the CSS, and optionally assigns it to a webspace.
 */
#[AsCommand(
    name: 'iw-sulu:theme:install',
    description: 'Install a preset theme (corporate, creative, minimal, nature, halloween, christmas, megamenu)',
)]
class ThemeInstallCommand extends Command
{
    public function __construct(
        private readonly ThemeConfigRepository $repository,
        private readonly ThemeCompiler $compiler,
        private readonly EntityManagerInterface $entityManager,
        private readonly WebspaceThemeRepository $webspaceThemeRepository,
    ) {
        parent::__construct();
    }

    /**
     * Configure the command arguments and options.
     */
    protected function configure(): void
    {
        $this->addArgument(
            'name',
            InputArgument::OPTIONAL,
            'The preset theme name (corporate, creative, minimal, nature, halloween, christmas, megamenu)',
        );
        $this->addOption(
            'all',
            null,
            InputOption::VALUE_NONE,
            'Install all available preset themes',
        );
        $this->addOption(
            'webspace',
            'w',
            InputOption::VALUE_OPTIONAL,
            'Assign the installed theme to this webspace',
        );
    }

    /**
     * Execute the theme installation.
     *
     * @param InputInterface  $input  The console input
     * @param OutputInterface $output The console output
     *
     * @return int The command exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $presets = ThemeFixtures::getPresets();
        $installAll = $input->getOption('all');
        $name = $input->getArgument('name');
        $webspaceKey = $input->getOption('webspace');

        if (!$installAll && null === $name) {
            $io->error(sprintf(
                'Please provide a theme name or use --all. Available presets: %s',
                implode(', ', array_keys($presets)),
            ));

            return Command::FAILURE;
        }

        $themesToInstall = $installAll
            ? array_keys($presets)
            : [$name];

        // Validate all names before installing
        foreach ($themesToInstall as $themeName) {
            if (!isset($presets[$themeName])) {
                $io->error(sprintf(
                    'Unknown theme preset "%s". Available presets: %s',
                    $themeName,
                    implode(', ', array_keys($presets)),
                ));

                return Command::FAILURE;
            }
        }

        $lastTheme = null;

        foreach ($themesToInstall as $themeName) {
            $existing = $this->repository->findByName($themeName);
            if (null !== $existing) {
                $io->warning(sprintf('Theme "%s" already exists (ID: %d). Updating...', $themeName, $existing->getId()));
                $theme = $existing;
            } else {
                $theme = new ThemeConfig();
                $io->info(sprintf('Creating new theme "%s"...', $themeName));
            }

            $preset = $presets[$themeName];

            $theme->setName($preset['name']);
            $theme->setLabel($preset['label']);
            $theme->setTokens($preset['tokens']);
            $theme->setMenuConfig($preset['menuConfig']);
            $theme->setBlockStyles($preset['blockStyles']);

            $this->entityManager->persist($theme);
            $lastTheme = $theme;
        }

        $this->entityManager->flush();

        // Assign to webspace if --webspace option is provided
        if (null !== $webspaceKey && null !== $lastTheme) {
            $this->webspaceThemeRepository->setThemeForWebspace($webspaceKey, $lastTheme);
            $this->entityManager->flush();
        }

        // Compile CSS for all installed themes
        foreach ($themesToInstall as $themeName) {
            $theme = $this->repository->findByName($themeName);
            if (null !== $theme) {
                $this->compiler->compile($theme);
            }
        }

        if ($installAll) {
            $io->success(sprintf(
                '%d themes installed!',
                count($themesToInstall),
            ));
        } else {
            $assignmentMsg = null !== $webspaceKey
                ? sprintf(' and assigned to webspace "%s"', $webspaceKey)
                : '';
            $io->success(sprintf(
                'Theme "%s" (%s) installed successfully%s!',
                $lastTheme->getLabel(),
                $lastTheme->getName(),
                $assignmentMsg,
            ));
        }

        $io->table(
            ['Property', 'Value'],
            [
                ['ID', (string) $lastTheme->getId()],
                ['Name', $lastTheme->getName()],
                ['Label', $lastTheme->getLabel()],
                ['Webspace', $webspaceKey ?? 'None (use webspace settings to assign)'],
                ['Colors', implode(', ', array_column($lastTheme->getTokens()['colors'] ?? [], 'slug'))],
                ['Buttons', implode(', ', array_column($lastTheme->getTokens()['buttons'] ?? [], 'slug'))],
                ['Font Families', (string) count($lastTheme->getTokens()['typography']['families'] ?? [])],
                ['Block Variants', (string) count($lastTheme->getTokens()['blockVariants'] ?? [])],
            ],
        );

        return Command::SUCCESS;
    }
}
