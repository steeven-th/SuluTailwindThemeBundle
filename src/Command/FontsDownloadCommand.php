<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Command;

use ItechWorld\SuluTailwindThemeBundle\Repository\ThemeConfigRepository;
use ItechWorld\SuluTailwindThemeBundle\Service\GoogleFontsResolver;
use ItechWorld\SuluTailwindThemeBundle\Service\SelfHostedFonts;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Fetches the Google fonts of every theme, for the site to serve them.
 *
 * The first page needing a set downloads it anyway. Running this at deploy
 * time spares that first visitor the wait, and says straight away when the
 * server cannot reach Google, rather than in a log line nobody reads.
 */
#[AsCommand(
    name: 'iw-sulu:theme:fonts-download',
    description: 'Download the Google fonts of every theme so the site serves them itself',
)]
final class FontsDownloadCommand extends Command
{
    /**
     * @param ThemeConfigRepository $repository    The themes
     * @param GoogleFontsResolver   $fontsResolver Builds the Google URL of a theme
     * @param SelfHostedFonts       $fonts         Downloads a set
     * @param bool                  $enabled       The `fonts.self_host` setting
     */
    public function __construct(
        private readonly ThemeConfigRepository $repository,
        private readonly GoogleFontsResolver $fontsResolver,
        private readonly SelfHostedFonts $fonts,
        private readonly bool $enabled,
    ) {
        parent::__construct();
    }

    /**
     * Download the set of each theme using Google fonts.
     *
     * @param InputInterface  $input  The console input
     * @param OutputInterface $output The console output
     *
     * @return int Failure when one set could not be fetched
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->enabled) {
            $io->note('fonts.self_host is off: the site loads its fonts from Google.');

            return Command::SUCCESS;
        }

        $failed = 0;
        foreach ($this->repository->findAll() as $theme) {
            $url = $this->fontsResolver->resolve($theme->getTokens()['typography'] ?? []);
            if (null === $url) {
                continue;
            }

            if ($this->fonts->download($url, true)) {
                $io->writeln(sprintf('  <info>✔</info> %s', $theme->getLabel()));
            } else {
                ++$failed;
                $io->writeln(sprintf('  <error>✘</error> %s, the site will load its fonts from Google', $theme->getLabel()));
            }
        }

        if (0 !== $failed) {
            $io->error('Some fonts could not be downloaded. Check that the server reaches fonts.googleapis.com and that symfony/http-client is installed.');

            return Command::FAILURE;
        }

        $io->success('The fonts are served by the site.');

        return Command::SUCCESS;
    }
}
