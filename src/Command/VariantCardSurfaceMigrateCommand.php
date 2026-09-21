<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use ItechWorld\SuluTailwindThemeBundle\Repository\ThemeConfigRepository;
use ItechWorld\SuluTailwindThemeBundle\Service\VariantResolver;
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
 * A theme saved before the surface says nothing about its cards either, and
 * meant the opposite. `VariantResolver` reads the paragraph value for it at
 * runtime, so nothing breaks while it stays unmigrated - the compile, the site
 * and the admin form all agree on the inherited value.
 *
 * This command writes those values down. It is not a rescue, it is what makes
 * the two surfaces settable apart: as long as the card keys are missing, they
 * track the paragraph ones, and clearing the paragraph fill takes the cards
 * with it.
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

        $themes = $this->themeConfigRepository->findAll();
        if ([] === $themes) {
            $io->success('Nothing to migrate - no theme stored.');

            return Command::SUCCESS;
        }

        $touched = 0;
        $variantsTouched = 0;

        // Variants whose cards were filled by something the admin never showed:
        // an empty paragraph fill used to send them to `--iw-variant-subtle-bg`,
        // the tint computed from the block lightness. There is nothing to copy
        // for those - the value never existed - yet their cards do change, so
        // they are the ones worth naming.
        $losingTheTint = [];

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

                $label = (string) ($variant['label'] ?? $variant['slug'] ?? (string) $index);

                if ($this->losesTheComputedTint($variant)) {
                    $losingTheTint[] = \sprintf('%s / %s', $theme->getName(), $label);
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
                    $label,
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
            $this->warnAboutTheTint($io, $losingTheTint);

            return Command::SUCCESS;
        }

        if ($dryRun) {
            $io->warning(\sprintf(
                '%d variant(s) in %d theme(s) would be updated. Nothing was written.',
                $variantsTouched,
                $touched,
            ));
            $this->warnAboutTheTint($io, $losingTheTint);

            return Command::SUCCESS;
        }

        $this->entityManager->flush();

        $io->success(\sprintf(
            '%d variant(s) in %d theme(s) updated. Recompile with iw-sulu:theme:compile.',
            $variantsTouched,
            $touched,
        ));
        $this->warnAboutTheTint($io, $losingTheTint);

        return Command::SUCCESS;
    }

    /**
     * Name the variants whose cards change without anything being copied.
     *
     * Copying is only half the story. A variant with no paragraph fill had
     * nothing to give its cards, yet they were not bare: they landed on
     * `--iw-variant-subtle-bg`, a translucent black or white computed from the
     * lightness of the block. That fallback is gone, so those cards lose a fill
     * the admin never displayed and this command cannot restore - there is no
     * stored value to carry.
     *
     * Silence here would be the worst outcome: the command reports success, the
     * compile runs, and cards turn bare with nobody able to say why.
     *
     * @param SymfonyStyle $io       The console style
     * @param list<string> $variants Theme and variant names, as reported
     */
    private function warnAboutTheTint(SymfonyStyle $io, array $variants): void
    {
        if ([] === $variants) {
            return;
        }

        $io->warning(\sprintf(
            "%d variant(s) name no paragraph fill. Their cards used to take the computed tint\n"
            . "(--iw-variant-subtle-bg), which no longer applies, and there is no stored value to\n"
            . "inherit either. Set Cards > Background on them if that tint mattered:\n  %s",
            \count($variants),
            implode("\n  ", $variants),
        ));
    }

    /**
     * Whether a variant's cards lose the computed tint and gain nothing.
     *
     * Both halves matter. No paragraph fill means the cards were taking the
     * computed tint, and no card fill means nothing replaces it.
     *
     * @param array<string, mixed> $variant
     */
    private function losesTheComputedTint(array $variant): bool
    {
        $paragraph = trim((string) ($variant['paragraphBg'] ?? ''));
        $card = trim((string) ($variant['cardBg'] ?? ''));

        return '' === $paragraph && '' === $card;
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

        foreach (VariantResolver::CARD_INHERITS as $from => $to) {
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
