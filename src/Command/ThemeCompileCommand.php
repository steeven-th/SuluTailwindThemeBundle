<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Command;

use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;
use ItechWorld\SuluTailwindThemeBundle\Repository\ThemeConfigRepository;
use ItechWorld\SuluTailwindThemeBundle\Service\RequiredButtonStyles;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeCompiler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Console command to compile a theme's CSS from its design tokens.
 *
 * Can compile a specific theme by name or all themes in the catalog.
 */
#[AsCommand(
    name: 'iw-sulu:theme:compile',
    description: 'Compile theme CSS from design tokens',
)]
class ThemeCompileCommand extends Command
{
    public function __construct(
        private readonly ThemeConfigRepository $repository,
        private readonly ThemeCompiler $compiler,
        private readonly RequiredButtonStyles $requiredButtonStyles,
    ) {
        parent::__construct();
    }

    /**
     * Configure the command options.
     */
    protected function configure(): void
    {
        $this->addOption(
            'theme',
            't',
            InputOption::VALUE_OPTIONAL,
            'The theme name to compile (compiles all themes if omitted)',
        );
    }

    /**
     * Execute the theme compilation.
     *
     * @param InputInterface  $input  The console input
     * @param OutputInterface $output The console output
     *
     * @return int The command exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $themeName = $input->getOption('theme');

        if (null !== $themeName) {
            $theme = $this->repository->findByName($themeName);

            if (null === $theme) {
                $io->error(sprintf('Theme "%s" not found.', $themeName));

                return Command::FAILURE;
            }

            $io->info(sprintf('Compiling theme "%s" (%s)...', $theme->getLabel(), $theme->getName()));

            $cssPath = $this->compiler->compile($theme);
            $this->warnAboutMissingButtonStyles($io, $theme);

            $io->success('Theme compiled successfully!');
            $io->writeln(sprintf('  CSS file: <info>%s</info>', $cssPath));
            $io->writeln(sprintf('  Web path: <info>%s</info>', $this->compiler->getCssPath($theme)));
        } else {
            $themes = $this->repository->findAll();

            if (empty($themes)) {
                $io->warning('No themes found in the database.');

                return Command::SUCCESS;
            }

            foreach ($themes as $theme) {
                $io->info(sprintf('Compiling theme "%s" (%s)...', $theme->getLabel(), $theme->getName()));
                $this->compiler->compile($theme);
                $this->warnAboutMissingButtonStyles($io, $theme);
            }

            $io->success(sprintf('%d theme(s) compiled successfully!', count($themes)));
        }

        return Command::SUCCESS;
    }

    /**
     * Warn when the theme lacks a button style the project CSS depends on.
     *
     * A warning and not a failure: the theme compiles and works, only the
     * project rules targeting the missing classes match nothing.
     *
     * @param SymfonyStyle $io    The console style
     * @param ThemeConfig  $theme The theme just compiled
     */
    private function warnAboutMissingButtonStyles(SymfonyStyle $io, ThemeConfig $theme): void
    {
        $missing = $this->requiredButtonStyles->missingIn($theme);
        if ([] === $missing) {
            return;
        }

        $io->warning(sprintf(
            'Theme "%s" defines no button style for %s, which the project CSS depends on (required_button_styles). '
            . 'Create the style in the admin or restore its slug.',
            $theme->getLabel(),
            implode(', ', $missing),
        ));
    }
}
