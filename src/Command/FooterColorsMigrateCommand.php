<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use ItechWorld\SuluTailwindThemeBundle\Color\FooterVariantColors;
use ItechWorld\SuluTailwindThemeBundle\Repository\ThemeConfigRepository;
use ItechWorld\SuluTailwindThemeBundle\Service\VariantResolver;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Turns the variant a footer wore into colors of its own.
 *
 * Until 3.0.0 the footer was colored by a block variant (`footerConfig.variant`).
 * It now reads `footerConfig.colors`. A theme still holding a variant keeps
 * rendering its colors, FooterVariantColors::inherit() reading them through
 * the variant, so nothing has to run before deploying. This command writes
 * them down, which is what lets the variant go: as long as it is stored, a
 * footer color left empty keeps following it.
 *
 * Each theme gets the colors its variant painted, an empty variant meaning the
 * first one, as it always did. A color already set wins: a theme edited after
 * the upgrade keeps what its editor picked. The variant is then removed, so
 * running the command twice changes nothing.
 *
 * Two things a variant drew have no footer setting, and are reported rather
 * than migrated: the separator style (a divider hidden, dashed, wavy...) and
 * the block border around the footer. Both are gone as soon as the bundle is
 * deployed, the footer no longer wearing the variant class. The report says
 * which themes lost them and the line of project CSS that brings them back.
 *
 * Usage:
 *   php bin/adminconsole iw-sulu:theme:migrate-footer-colors [--dry-run]
 */
#[AsCommand(
    name: 'iw-sulu:theme:migrate-footer-colors',
    description: 'Replace the footer color variant with footer colors of its own',
)]
class FooterColorsMigrateCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ThemeConfigRepository $themeConfigRepository,
    ) {
        parent::__construct();
    }

    /**
     * Declare the options.
     */
    protected function configure(): void
    {
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Report what would change without writing anything',
        );
    }

    /**
     * Write the colors of each footer variant into the footer config.
     *
     * @param InputInterface  $input  The console input
     * @param OutputInterface $output The console output
     *
     * @return int The command exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $touched = 0;
        $changes = [];

        foreach ($this->themeConfigRepository->findAll() as $theme) {
            $footer = $theme->getFooterConfig();
            if (!\array_key_exists('variant', $footer)) {
                continue;
            }

            $blockVariants = $theme->getTokens()['blockVariants'] ?? [];
            $variant = VariantResolver::resolveConfig($footer['variant'], $blockVariants);
            $stored = \is_array($footer['colors'] ?? null) ? $footer['colors'] : [];
            $colors = FooterVariantColors::inherit($footer, $blockVariants);
            $written = array_keys(array_diff_assoc($colors, $stored));

            unset($footer['variant']);
            if ([] !== $colors) {
                $footer['colors'] = $colors;
            }

            ++$touched;
            $io->text(\sprintf(
                '  %s: %s',
                $theme->getName(),
                [] === $written ? 'variant removed, no color to carry' : implode(', ', $written),
            ));
            foreach ($this->losses($variant) as $loss) {
                $changes[] = \sprintf('%s: %s', $theme->getName(), $loss);
            }

            if (!$dryRun) {
                $theme->setFooterConfig($footer);
            }
        }

        if (0 === $touched) {
            $io->success('Nothing to migrate - no footer holds a color variant.');

            return Command::SUCCESS;
        }

        if ($dryRun) {
            $io->warning(\sprintf('%d theme(s) would be updated. Nothing was written.', $touched));
        } else {
            $this->entityManager->flush();
            $io->success(\sprintf('%d theme(s) updated. Recompile with iw-sulu:theme:compile.', $touched));
        }

        if ([] !== $changes) {
            $io->warning(
                "These footers no longer show something their variant drew, which has no footer setting.\n"
                . "Add the CSS given to the project stylesheet to bring it back:\n  "
                . implode("\n  ", $changes),
            );
        }

        return Command::SUCCESS;
    }

    /**
     * What a variant drew on the footer that its colors do not carry.
     *
     * The variant rules outranked `.iw-footer__divider`, so the separator
     * settings of the variant decided whether and how the divider was drawn.
     * The footer divider is now a plain rule in the divider color.
     *
     * @param array<string, mixed> $variant The variant the footer wore
     *
     * @return list<string> One sentence per difference, with the CSS restoring it
     */
    private function losses(array $variant): array
    {
        $losses = [];

        $mode = (string) ($variant['separatorMode'] ?? 'style');
        $style = (string) ($variant['separatorStyle'] ?? 'solid');
        if (\in_array($mode, ['none', 'image'], true)) {
            $losses[] = \sprintf('the divider was hidden (separator mode "%s") and now shows. `.iw-footer__divider { display: none; }` hides it.', $mode);
        } elseif ('solid' !== $style && '' !== $style) {
            $losses[] = \sprintf('the divider was drawn "%s" and is now solid. Restyle `.iw-footer__divider` to bring it back.', $style);
        }

        $border = trim((string) ($variant['blockBorder'] ?? ''));
        if ('' !== $border) {
            $width = (int) ($variant['blockBorderWidth'] ?? 1);
            $losses[] = \sprintf(
                'the footer had a border, which is gone. `.iw-footer { border: %dpx solid %s; }` brings it back (resolve any ref: color to its value).',
                max(1, $width),
                $border,
            );
        }

        return $losses;
    }
}
