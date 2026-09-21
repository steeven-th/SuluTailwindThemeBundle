<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Gives every stored variant the card fill it used to borrow.
 *
 * Cards read `paragraphBg` until 3.0.0, for want of a surface of their own.
 * They have one now, and it no longer falls back: a variant that says nothing
 * about its cards draws none, which is what an editor who filled nothing
 * expects to see.
 *
 * That is the right default for a theme being built, and the wrong one for a
 * theme already built - its cards would empty out at the next compile without
 * anybody touching them. So the old value is copied across, once, and the two
 * settings part ways from there.
 *
 * Only variants that would change are written, so it can be run twice: a
 * variant that already names a card fill is left exactly as it is.
 *
 * Usage:
 *   php bin/adminconsole iw-sulu:theme:migrate-card-surface [--dry-run]
 */
#[AsCommand(
    name: 'iw-sulu:theme:migrate-card-surface',
    description: 'Copy each variant paragraph fill onto its new card surface',
)]
class VariantCardSurfaceMigrateCommand extends Command
{
    /**
     * The settings carried over, old key => new key.
     *
     * The border travels with the fill. A card drew the paragraph border too,
     * so leaving it behind would un-frame every card of a theme that had asked
     * for one - the same regression as the fill, one line further down.
     */
    private const CARRIED = [
        'paragraphBg' => 'cardBg',
        'paragraphBorder' => 'cardBorder',
        'paragraphBorderWidth' => 'cardBorderWidth',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
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
     * Copy the paragraph surface onto the card surface of every variant.
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

        $themes = $this->entityManager->getRepository(ThemeConfig::class)->findAll();
        if ([] === $themes) {
            $io->success('Nothing to migrate - no theme stored.');

            return Command::SUCCESS;
        }

        $touched = 0;
        $variantsTouched = 0;

        foreach ($themes as $theme) {
            $tokens = $theme->getTokens();
            $variants = $tokens['blockVariants'] ?? null;
            if (!\is_array($variants)) {
                continue;
            }

            $changed = false;
            foreach ($variants as $index => $variant) {
                if (!\is_array($variant)) {
                    continue;
                }

                $carried = $this->carry($variant);
                if ([] === $carried) {
                    continue;
                }

                $variants[$index] = array_merge($variant, $carried);
                $changed = true;
                ++$variantsTouched;

                $io->text(\sprintf(
                    '  %s / %s: %s',
                    $theme->getName(),
                    (string) ($variant['label'] ?? $variant['slug'] ?? (string) $index),
                    implode(', ', array_keys($carried)),
                ));
            }

            if (!$changed) {
                continue;
            }

            ++$touched;
            if ($dryRun) {
                continue;
            }

            $tokens['blockVariants'] = $variants;
            $theme->setTokens($tokens);
        }

        if (0 === $variantsTouched) {
            $io->success('Nothing to migrate - every variant already names its card surface.');

            return Command::SUCCESS;
        }

        if ($dryRun) {
            $io->warning(\sprintf(
                '%d variant(s) in %d theme(s) would be updated. Nothing was written.',
                $variantsTouched,
                $touched,
            ));

            return Command::SUCCESS;
        }

        $this->entityManager->flush();

        $io->success(\sprintf(
            '%d variant(s) in %d theme(s) updated. Recompile with iw-sulu:theme:compile.',
            $variantsTouched,
            $touched,
        ));

        return Command::SUCCESS;
    }

    /**
     * The card settings one variant is missing, taken from its paragraph ones.
     *
     * A card key already present wins, empty or not: an editor who cleared it
     * meant to clear it, and running the command again must not undo that.
     *
     * @param array<string, mixed> $variant
     *
     * @return array<string, mixed> The keys to add, empty when there is nothing to do
     */
    private function carry(array $variant): array
    {
        $carried = [];

        foreach (self::CARRIED as $from => $to) {
            if (\array_key_exists($to, $variant)) {
                continue;
            }

            $value = $variant[$from] ?? null;
            if (null === $value || '' === $value) {
                continue;
            }

            $carried[$to] = $value;
        }

        return $carried;
    }
}
