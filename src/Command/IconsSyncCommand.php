<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Refreshes the bundled Heroicons from a copy of the npm package.
 *
 * The icons are committed to the bundle rather than pulled at install time,
 * because the admin reads them from disk through Sulu's `svg://` provider: a
 * project installing the theme with composer must get them, without a node
 * toolchain anywhere near its production server.
 *
 * That makes updating them a deliberate act, which is what this command is for.
 * It takes an extracted package directory - `node_modules/heroicons` is one -
 * and refreshes the two weights the bundle ships, reporting what moved.
 */
#[AsCommand(
    name: 'iw-sulu:theme:sync-icons',
    description: 'Refresh the bundled Heroicons from a copy of the npm package',
)]
class IconsSyncCommand extends Command
{
    /**
     * The weights the bundle ships, as "source directory => bundle directory".
     */
    private const WEIGHTS = [
        '24/outline' => '24/outline',
        '24/solid' => '24/solid',
    ];

    protected function configure(): void
    {
        $this
            ->addOption(
                'source',
                null,
                InputOption::VALUE_REQUIRED,
                'Path to an extracted heroicons package (e.g. node_modules/heroicons)',
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Report what would change without writing anything',
            )
            ->setHelp(<<<'HELP'
                Refresh the icon library from a copy of the heroicons npm package:

                  <info>npm pack heroicons && tar -xzf heroicons-*.tgz</info>
                  <info>php bin/console iw-sulu:theme:sync-icons --source=package</info>

                Only the weights the bundle offers are copied. Run with
                <info>--dry-run</info> first to see what a new version changes.
                HELP);
    }

    /**
     * @param InputInterface  $input  The console input
     * @param OutputInterface $output The console output
     *
     * @return int The command exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string|null $source */
        $source = $input->getOption('source');
        $dryRun = (bool) $input->getOption('dry-run');

        if (null === $source || '' === $source) {
            $io->error('The --source option is required.');
            $io->writeln('  Point it at an extracted heroicons package, for instance <info>node_modules/heroicons</info>.');

            return Command::FAILURE;
        }

        $source = rtrim($source, '/');

        foreach (array_keys(self::WEIGHTS) as $weight) {
            if (!is_dir($source . '/' . $weight)) {
                $io->error(\sprintf('"%s" does not look like a heroicons package: %s is missing.', $source, $weight));

                return Command::FAILURE;
            }
        }

        $target = \dirname(__DIR__, 2) . '/assets/icons/heroicons';
        $filesystem = new Filesystem();
        $version = $this->readVersion($source);

        $added = 0;
        $updated = 0;
        $removed = 0;

        foreach (self::WEIGHTS as $from => $to) {
            $sourceFiles = $this->svgFiles($source . '/' . $from);
            $targetFiles = $this->svgFiles($target . '/' . $to);

            foreach ($sourceFiles as $name => $path) {
                $contents = (string) file_get_contents($path);

                if (!isset($targetFiles[$name])) {
                    ++$added;
                    if (!$dryRun) {
                        $filesystem->dumpFile($target . '/' . $to . '/' . $name . '.svg', $contents);
                    }

                    continue;
                }

                if ($contents !== (string) file_get_contents($targetFiles[$name])) {
                    ++$updated;
                    if (!$dryRun) {
                        $filesystem->dumpFile($targetFiles[$name], $contents);
                    }
                }
            }

            // An icon dropped upstream has to go here too: left behind, it
            // would keep showing in the admin overlay and render fine, until
            // the next sync from a clean checkout made it vanish.
            foreach ($targetFiles as $name => $path) {
                if (!isset($sourceFiles[$name])) {
                    ++$removed;
                    if (!$dryRun) {
                        $filesystem->remove($path);
                    }
                }
            }
        }

        if (!$dryRun) {
            if (is_file($source . '/LICENSE')) {
                $filesystem->copy($source . '/LICENSE', $target . '/LICENSE', true);
            }

            if (null !== $version) {
                $filesystem->dumpFile($target . '/VERSION', $version . "\n");
            }
        }

        $io->definitionList(
            ['Source' => $source],
            ['Version' => $version ?? 'unknown'],
            ['Added' => (string) $added],
            ['Updated' => (string) $updated],
            ['Removed' => (string) $removed],
        );

        if (0 === $added + $updated + $removed) {
            $io->success('The icon library is already up to date.');

            return Command::SUCCESS;
        }

        if ($dryRun) {
            $io->note('Dry run: nothing was written.');

            return Command::SUCCESS;
        }

        $io->success('Icon library refreshed. Review the diff before committing it.');

        return Command::SUCCESS;
    }

    /**
     * The SVG files of a directory, keyed by icon name.
     *
     * @return array<string, string> icon name => absolute path
     */
    private function svgFiles(string $directory): array
    {
        $files = [];

        foreach (glob($directory . '/*.svg') ?: [] as $path) {
            $files[basename($path, '.svg')] = $path;
        }

        return $files;
    }

    /**
     * The version declared by the source package, when it declares one.
     */
    private function readVersion(string $source): ?string
    {
        $manifest = $source . '/package.json';

        if (!is_file($manifest)) {
            return null;
        }

        /** @var array{version?: string}|null $decoded */
        $decoded = json_decode((string) file_get_contents($manifest), true);

        return \is_array($decoded) && isset($decoded['version']) ? $decoded['version'] : null;
    }
}
