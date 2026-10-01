<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Admin;

use Doctrine\ORM\EntityManagerInterface;
use ItechWorld\SuluTailwindThemeBundle\Controller\Admin\ThemeConfigController;
use ItechWorld\SuluTailwindThemeBundle\Entity\ThemeConfig;
use ItechWorld\SuluTailwindThemeBundle\Repository\ThemeConfigRepository;
use ItechWorld\SuluTailwindThemeBundle\Repository\WebspaceThemeRepository;
use ItechWorld\SuluTailwindThemeBundle\Service\CustomFieldSanitizer;
use ItechWorld\SuluTailwindThemeBundle\Service\GoogleFontsCatalog;
use ItechWorld\SuluTailwindThemeBundle\Service\GoogleFontsResolver;
use ItechWorld\SuluTailwindThemeBundle\Service\OklchPaletteGenerator;
use ItechWorld\SuluTailwindThemeBundle\Service\RequiredButtonStyles;
use ItechWorld\SuluTailwindThemeBundle\Service\SlugValidator;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeCompiler;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeFormMapper;
use ItechWorld\SuluTailwindThemeBundle\Service\TypographyWeightValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactoryInterface;
use Sulu\Component\Rest\ListBuilder\Metadata\FieldDescriptorFactoryInterface;
use Sulu\Component\Rest\RestHelperInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The stylesheet behind the live button preview of the theme form.
 *
 * The preview compiles values the editor has not saved. What matters is what
 * it must never do: change the theme, flush it, or replace its compiled file.
 * A preview that saved would turn every keystroke into a published change.
 */
final class ThemePreviewCssTest extends TestCase
{
    private string $outputDir;

    protected function setUp(): void
    {
        $this->outputDir = sys_get_temp_dir() . '/iw-preview-css-' . bin2hex(random_bytes(4));
        mkdir($this->outputDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->outputDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->outputDir);
    }

    #[Test]
    public function itCompilesTheUnsavedValuesOfTheForm(): void
    {
        $theme = self::theme();
        $response = $this->controller($theme)->previewCssAction(self::request([
            'buttons' => [['type' => 'button', 'label' => 'Main', 'slug' => 'main', 'bg' => '#abcdef']],
        ]), 20);

        self::assertSame(200, $response->getStatusCode());
        $body = self::body($response);
        self::assertStringContainsString('.iw-button--main', $body['css']);
        self::assertStringContainsString('#abcdef', $body['css']);
    }

    #[Test]
    public function itLeavesTheThemeAndItsCompiledFileUntouched(): void
    {
        $theme = self::theme();
        $before = $theme->getTokens();

        // The entity manager mock fails the test on any flush or persist.
        $this->controller($theme)->previewCssAction(self::request([
            'buttons' => [['type' => 'button', 'label' => 'Main', 'slug' => 'main', 'bg' => '#abcdef']],
        ]), 20);

        self::assertSame($before, $theme->getTokens());
        self::assertSame([], glob($this->outputDir . '/*'));
    }

    /**
     * The class the stylesheet uses, not the slug as typed.
     */
    #[Test]
    public function itReturnsTheButtonSlugsAsTheCompilerNormalizesThem(): void
    {
        $response = $this->controller(self::theme())->previewCssAction(self::request([
            'buttons' => [
                ['type' => 'button', 'label' => 'Main', 'slug' => 'main'],
                ['type' => 'button', 'label' => 'Call to action', 'slug' => ''],
            ],
        ]), 20);

        self::assertSame(['main', 'call-to-action'], self::body($response)['buttons']);
    }

    /**
     * The check the compiler logs, run on the unsaved form: the buttons form
     * warns with it before the save.
     */
    #[Test]
    public function itReportsTheRequiredStylesTheFormLacks(): void
    {
        $required = new RequiredButtonStyles(['main', 'profile-employer']);
        $response = $this->controller(self::theme(), $required)->previewCssAction(self::request([
            'buttons' => [['type' => 'button', 'label' => 'Main', 'slug' => 'main']],
        ]), 20);

        self::assertSame(['profile-employer'], self::body($response)['missingButtons']);
    }

    #[Test]
    public function aProjectDeclaringNoStyleMissesNone(): void
    {
        $response = $this->controller(self::theme())->previewCssAction(self::request([
            'buttons' => [],
        ]), 20);

        self::assertSame([], self::body($response)['missingButtons']);
    }

    /**
     * A duplicate slug is a normal state while one is being typed.
     */
    #[Test]
    public function aDuplicateSlugIsAnUnprocessableRequest(): void
    {
        $response = $this->controller(self::theme())->previewCssAction(self::request([
            'buttons' => [
                ['type' => 'button', 'label' => 'A', 'slug' => 'same'],
                ['type' => 'button', 'label' => 'B', 'slug' => 'same'],
            ],
        ]), 20);

        self::assertSame(422, $response->getStatusCode());
    }

    #[Test]
    public function aBodyThatIsNotAnObjectIsABadRequest(): void
    {
        $response = $this->controller(self::theme())->previewCssAction(new Request(content: '"text"'), 20);

        self::assertSame(400, $response->getStatusCode());
    }

    /**
     * The preview field of a button item is never stored.
     */
    #[Test]
    public function thePreviewFieldIsNotStoredWithTheButton(): void
    {
        $theme = self::theme();
        $mapper = new ThemeFormMapper(new SlugValidator(), new CustomFieldSanitizer());
        $mapper->mapDataToEntity([
            'buttons' => [['type' => 'button', 'label' => 'Main', 'slug' => 'main', 'preview' => null]],
        ], $theme);

        self::assertArrayNotHasKey('preview', $theme->getTokens()['buttons'][0]);
    }

    private function controller(ThemeConfig $theme, ?RequiredButtonStyles $required = null): ThemeConfigController
    {
        $repository = $this->createStub(ThemeConfigRepository::class);
        $repository->method('find')->willReturn($theme);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');
        $entityManager->expects(self::never())->method('persist');

        return new ThemeConfigController(
            $repository,
            $entityManager,
            new ThemeCompiler($this->outputDir, new GoogleFontsResolver(), new OklchPaletteGenerator()),
            $this->createStub(FieldDescriptorFactoryInterface::class),
            $this->createStub(DoctrineListBuilderFactoryInterface::class),
            $this->createStub(RestHelperInterface::class),
            $this->createStub(GoogleFontsCatalog::class),
            new OklchPaletteGenerator(),
            $this->createStub(WebspaceThemeRepository::class),
            new ThemeFormMapper(new SlugValidator(), new CustomFieldSanitizer()),
            $this->createStub(TranslatorInterface::class),
            $this->createStub(TypographyWeightValidator::class),
            $required,
        );
    }

    private static function theme(): ThemeConfig
    {
        $theme = new ThemeConfig();
        $theme->setTokens([
            'buttons' => [['label' => 'Main', 'slug' => 'main', 'bg' => '#111111']],
        ]);

        return $theme;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function request(array $data): Request
    {
        return new Request(content: json_encode($data, \JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    private static function body(JsonResponse $response): array
    {
        /** @var array<string, mixed> $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body;
    }
}
