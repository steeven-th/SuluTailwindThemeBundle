<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Admin;

use ItechWorld\SuluTailwindThemeBundle\Service\ThemeCompiler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Keeps the copy buttons of the two card tabs on the same pairs as the compiler.
 *
 * The buttons walk a map written in JavaScript, the compiler and the migration
 * one written in PHP. A field added to one map only would be copied by the
 * buttons and ignored by the stylesheet, or the reverse, with no error.
 */
final class CopyCardSettingsContractTest extends TestCase
{
    /**
     * The JavaScript map pairs the same fields as ThemeCompiler::BLOCK_CARD_KEYS.
     */
    #[Test]
    public function theButtonsCopyTheFieldsTheCompilerReads(): void
    {
        $source = (string) file_get_contents(\dirname(__DIR__, 2) . '/public/js/components/CopyCardSettings/CopyCardSettings.js');

        self::assertSame(1, preg_match('/BLOCK_CARD_KEYS[^=]*=\s*\{(.*?)\};/s', $source, $map));
        preg_match_all("/(\\w+):\\s*'(\\w+)'/", $map[1], $pairs, \PREG_SET_ORDER);

        $js = [];
        foreach ($pairs as [, $blockKey, $cardKey]) {
            $js[$blockKey] = $cardKey;
        }

        self::assertSame(ThemeCompiler::BLOCK_CARD_KEYS, $js);
    }
}
