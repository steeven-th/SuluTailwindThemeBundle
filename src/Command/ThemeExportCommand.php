<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Command;

use ItechWorld\SuluTailwindThemeBundle\Repository\ThemeConfigRepository;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeExporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Writes a theme to a JSON file, or to standard output.
 *
 * The command line half of the admin export button. It is what makes the
 * feature scriptable: a cron pulling production into a staging install, a
 * pre-commit hook keeping `theme.json` in step with the database, a CI job
 * seeding a fresh environment.
 */
#[AsCommand(
    name: 'iw-sulu:theme:export',
    description: 'Export a theme configuration to a portable JSON file',
)]
class ThemeExportCommand extends Command
{
    public function __construct(
        private readonly ThemeConfigRepository $repository,
        private readonly ThemeExporter $exporter,
    ) {
        parent::__construct();
    }

    /**
     * Configure the command arguments and options.
     */
    protected function configure(): void
    {
        $this->addArgument(
            'theme',
            InputArgument::REQUIRED,
            'The theme to export, by machine name or by id',
        );
        $this->addOption(
            'output',
            'o',
            InputOption::VALUE_REQUIRED,
            'File to write to. Defaults to the suggested file name in the current directory, --output=- writes to stdout',
        );
    }

    /**
     * Execute the export.
     *
     * @param InputInterface  $input  The console input
     * @param OutputInterface $output The console output
     *
     * @return int The command exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string $reference */
        $reference = $input->getArgument('theme');

        $theme = $this->repository->findByName($reference)
            ?? (ctype_digit($reference) ? $this->repository->find((int) $reference) : null);

        if (null === $theme) {
            $io->error(\sprintf('No theme found for "%s".', $reference));

            return Command::FAILURE;
        }

        $json = $this->exporter->exportToJson($theme);
        /** @var string|null $target */
        $target = $input->getOption('output');

        // Piping the document somewhere is a first-class use, so stdout stays
        // clean: not a word of chrome goes out with it.
        if ('-' === $target) {
            $output->writeln($json);

            return Command::SUCCESS;
        }

        $target ??= $this->exporter->filename($theme);

        if (false === @file_put_contents($target, $json . "\n")) {
            $io->error(\sprintf('Could not write to "%s".', $target));

            return Command::FAILURE;
        }

        $io->success(\sprintf('Exported theme "%s" to %s.', $theme->getName(), $target));

        $excluded = $this->exporter->countExcludedMedia($theme);

        if ($excluded > 0) {
            $io->warning(\sprintf(
                '%d media reference(s) were left out: images live in the media library of this installation '
                . 'and their ids mean nothing elsewhere. Set the logos again after importing.',
                $excluded,
            ));
        }

        return Command::SUCCESS;
    }
}
