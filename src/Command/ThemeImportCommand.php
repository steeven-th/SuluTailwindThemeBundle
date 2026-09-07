<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use ItechWorld\SuluTailwindThemeBundle\Exception\SlugValidationException;
use ItechWorld\SuluTailwindThemeBundle\Exception\ThemeImportException;
use ItechWorld\SuluTailwindThemeBundle\Exception\TypographyWeightException;
use ItechWorld\SuluTailwindThemeBundle\Repository\ThemeConfigRepository;
use ItechWorld\SuluTailwindThemeBundle\Repository\WebspaceThemeRepository;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeCompiler;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeImporter;
use ItechWorld\SuluTailwindThemeBundle\Service\TypographyWeightValidator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Reads a theme file into the database, as a new theme or over an existing one.
 *
 * The command line half of the admin import button. Reads from a file, or from
 * standard input so an export can be piped straight in from another
 * installation.
 */
#[AsCommand(
    name: 'iw-sulu:theme:import',
    description: 'Import a theme configuration from a JSON file produced by iw-sulu:theme:export',
)]
class ThemeImportCommand extends Command
{
    public function __construct(
        private readonly ThemeImporter $importer,
        private readonly ThemeConfigRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ThemeCompiler $compiler,
        private readonly WebspaceThemeRepository $webspaceThemeRepository,
        private readonly TypographyWeightValidator $weightValidator,
    ) {
        parent::__construct();
    }

    /**
     * Configure the command arguments and options.
     */
    protected function configure(): void
    {
        $this->addArgument(
            'file',
            InputArgument::REQUIRED,
            'The exported JSON file to read, or "-" to read standard input',
        );
        $this->addOption(
            'replace',
            'r',
            InputOption::VALUE_REQUIRED,
            'Import onto this existing theme (machine name or id) instead of creating a new one',
        );
        $this->addOption(
            'name',
            null,
            InputOption::VALUE_REQUIRED,
            'Machine name for the created theme, when not replacing. Defaults to the name in the file',
        );
    }

    /**
     * Execute the import.
     *
     * @param InputInterface  $input  The console input
     * @param OutputInterface $output The console output
     *
     * @return int The command exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string $file */
        $file = $input->getArgument('file');

        $contents = '-' === $file ? stream_get_contents(\STDIN) : @file_get_contents($file);

        if (false === $contents) {
            $io->error(\sprintf('Could not read "%s".', $file));

            return Command::FAILURE;
        }

        /** @var string|null $replace */
        $replace = $input->getOption('replace');
        /** @var string|null $name */
        $name = $input->getOption('name');

        try {
            $payload = $this->importer->decode($contents);

            if (null !== $replace) {
                $target = $this->repository->findByName($replace)
                    ?? (ctype_digit($replace) ? $this->repository->find((int) $replace) : null);

                if (null === $target) {
                    $io->error(\sprintf('No theme found for "%s".', $replace));

                    return Command::FAILURE;
                }

                $this->importer->importInto($payload, $target);
                $theme = $target;
            } else {
                $theme = $this->importer->importAsNew($payload, $name);
            }

            $this->weightValidator->validate($theme->getTokens()['typography'] ?? []);
        } catch (ThemeImportException|SlugValidationException|TypographyWeightException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $this->entityManager->persist($theme);
        $this->entityManager->flush();

        if (\count($this->webspaceThemeRepository->findByTheme($theme)) > 0) {
            $this->compiler->compile($theme);
            $io->success(\sprintf('Imported theme "%s" and recompiled its CSS.', $theme->getName()));
        } else {
            $io->success(\sprintf(
                'Imported theme "%s". No webspace uses it yet, so no CSS was compiled.',
                $theme->getName(),
            ));
        }

        $io->note('Media references do not travel: set the logos of this theme again if it has any.');

        return Command::SUCCESS;
    }
}
