<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;
use ItechWorld\SuluTailwindThemeBundle\Service\RequiredButtonStyles;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequiredButtonStyles::class)]
final class RequiredButtonStylesTest extends TestCase
{
    #[Test]
    public function itListsTheDeclaredSlugsTheThemeLacks(): void
    {
        $required = new RequiredButtonStyles(['profile-employer', 'profile-employee', 'profile-self-employed']);

        self::assertSame(
            ['profile-employee', 'profile-self-employed'],
            $required->missingIn(self::theme([['slug' => 'profile-employer', 'label' => 'Employer']])),
        );
    }

    #[Test]
    public function aRenamedSlugIsReportedMissing(): void
    {
        // Renaming a style in the admin is the case this guards: the label
        // stays, the class changes, the project CSS no longer matches.
        $required = new RequiredButtonStyles(['profile-employer']);

        self::assertSame(
            ['profile-employer'],
            $required->missingIn(self::theme([['slug' => 'employer', 'label' => 'profile-employer']])),
        );
    }

    #[Test]
    public function aThemeWithEveryStyleLacksNothing(): void
    {
        $required = new RequiredButtonStyles(['cta']);

        self::assertSame([], $required->missingIn(self::theme([['slug' => 'cta', 'label' => 'CTA']])));
    }

    #[Test]
    public function aProjectDeclaringNothingIsNeverWarned(): void
    {
        self::assertSame([], (new RequiredButtonStyles())->missingIn(self::theme([])));
    }

    #[Test]
    public function blankAndRepeatedDeclarationsAreDropped(): void
    {
        self::assertSame(['cta'], (new RequiredButtonStyles([' cta ', '', 'cta']))->all());
    }

    /**
     * @param list<array<string, mixed>> $buttons
     */
    private static function theme(array $buttons): ThemeConfig
    {
        $ref = new \ReflectionClass(ThemeConfig::class);
        $theme = $ref->newInstanceWithoutConstructor();
        $ref->getProperty('tokens')->setValue($theme, ['buttons' => $buttons]);

        return $theme;
    }
}
