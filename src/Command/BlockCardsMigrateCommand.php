<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use ItechWorld\SuluTailwindThemeBundle\Repository\ThemeConfigRepository;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeCompiler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Gives Blocks > Cards the values the blocks used to read from the Cards tab.
 *
 * Until this change the cards of blocks borrowed part of their shape from the
 * Cards tab of the default settings: the sizes of their text, the image ratio
 * of the article blocks, the hover and the shadow geometry. They have a tab of
 * their own now and no longer read that one, so a theme saved before would
 * see its block cards fall back to the field defaults.
 *
 * This command copies each of those settings into its Blocks > Cards twin,
 * so the cards of blocks look as they did. Two settings are left out on
 * purpose:
 *
 *  - the padding, which only the article blocks read. Copied, it would reach
 *    every card family of a block, each of which ships its own padding;
 *  - the image hover effect, which blocks never read: they always zoomed,
 *    which is the default of the new field.
 *
 * A key already present in Blocks > Cards is never overwritten, so the command
 * can be run twice.
 *
 * Usage:
 *   php bin/adminconsole iw-sulu:theme:migrate-block-cards [--dry-run]
 */
#[AsCommand(
    name: 'iw-sulu:theme:migrate-block-cards',
    description: 'Copy the card settings blocks used to borrow from the Cards tab into Blocks > Cards',
)]
class BlockCardsMigrateCommand extends Command
{
    /**
     * Twins left out of the copy, see the class comment.
     *
     * @var list<string>
     */
    private const NOT_COPIED = ['blockCardPadding', 'blockCardHoverImage'];

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
     * Copy the borrowed card settings of every theme into Blocks > Cards.
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
        foreach ($themes as $theme) {
            $tokens = $theme->getTokens();
            $carried = self::carry($tokens);
            if ([] === $carried) {
                continue;
            }

            ++$touched;
            $io->text(\sprintf('  %s: %s', $theme->getName(), implode(', ', array_keys($carried))));

            if (!$dryRun) {
                $theme->setTokens(array_merge($tokens, $carried));
            }
        }

        if (0 === $touched) {
            $io->success('Nothing to migrate - every theme already has its block card settings.');

            return Command::SUCCESS;
        }

        if ($dryRun) {
            $io->warning(\sprintf('%d theme(s) would be updated. Nothing was written.', $touched));

            return Command::SUCCESS;
        }

        $this->entityManager->flush();
        $io->success(\sprintf('%d theme(s) updated. Recompile with iw-sulu:theme:compile.', $touched));

        return Command::SUCCESS;
    }

    /**
     * The Blocks > Cards values a theme is missing, taken from the Cards tab.
     *
     * @param array<string, mixed> $tokens Flat theme token map
     *
     * @return array<string, mixed> The keys to add, empty when there is nothing to copy
     */
    public static function carry(array $tokens): array
    {
        $carried = [];
        foreach (ThemeCompiler::BLOCK_CARD_KEYS as $blockKey => $cardKey) {
            if (\in_array($blockKey, self::NOT_COPIED, true)
                || \array_key_exists($blockKey, $tokens)
                || !\array_key_exists($cardKey, $tokens)) {
                continue;
            }
            $carried[$blockKey] = $tokens[$cardKey];
        }

        return $carried;
    }
}
