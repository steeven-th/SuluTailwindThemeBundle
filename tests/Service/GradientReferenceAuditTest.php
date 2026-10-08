<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;
use ItechWorld\SuluTailwindThemeBundle\Service\GradientReferenceAudit;
use ItechWorld\SuluTailwindThemeBundle\Service\SnippetWebspaceLocator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GradientReferenceAudit::class)]
final class GradientReferenceAuditTest extends TestCase
{
    private function audit(): GradientReferenceAudit
    {
        return new GradientReferenceAudit($this->createStub(EntityManagerInterface::class), new SnippetWebspaceLocator());
    }

    #[Test]
    public function itFindsTheSettingsNamingAMissingGradient(): void
    {
        $theme = new ThemeConfig();
        $theme->setTokens([
            'gradients' => [['slug' => 'night', 'stops' => [['color' => '#000'], ['color' => '#fff']]]],
            'components_tagBg' => 'gradient:night',
            'blockVariants' => [['slug' => 'dark', 'cardBg' => 'gradient:dawn']],
        ]);
        $theme->setMenuConfig(['type' => 'navbar', 'colors' => ['bg' => 'gradient:gone']]);
        $theme->setFooterConfig(['colors' => ['bg' => '#ffffff']]);

        self::assertSame([
            ['path' => 'tokens.blockVariants.0.cardBg', 'slug' => 'dawn'],
            ['path' => 'menu.colors.bg', 'slug' => 'gone'],
        ], $this->audit()->orphansInTheme($theme));
    }

    #[Test]
    public function itFindsTheGradientMarkersOfStoredContent(): void
    {
        self::assertSame(
            ['night', 'dawn'],
            GradientReferenceAudit::markers([
                'title' => 'Our [[gradient-night:work]] and [[accent:team]]',
                'blocks' => [['title' => '[[gradient-dawn:hello]]', 'text' => '<p>[[gradient]] is a word</p>']],
            ]),
        );
    }

    #[Test]
    public function aThemeWithoutReferencesHasNoOrphan(): void
    {
        $theme = new ThemeConfig();
        $theme->setTokens(['colors' => [['role' => 'primary', 'slug' => 'primary', 'value' => '#000000']]]);

        self::assertSame([], $this->audit()->orphansInTheme($theme));
        self::assertSame([], $this->audit()->orphansInContent([]));
    }
}
