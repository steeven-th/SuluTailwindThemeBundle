<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Admin;

use ItechWorld\SuluTailwindThemeBundle\Admin\MediaOriginFormVisitor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\SectionMetadata;

/**
 * The image origin field lands beside the copyright and the credits, not
 * above the upload area where the form merge puts it.
 */
final class MediaOriginFormVisitorTest extends TestCase
{
    #[Test]
    public function theFieldMovesIntoTheLicenseSection(): void
    {
        $form = self::mergedForm(withLicense: true);

        (new MediaOriginFormVisitor())->visitFormMetadata($form, 'en');

        self::assertSame(['media_upload', 'media_details'], array_keys($form->getItems()));

        $license = $form->getItems()['media_details']->getItems()['license'];
        self::assertInstanceOf(SectionMetadata::class, $license);
        self::assertSame(['copyright', 'credits', MediaOriginFormVisitor::FIELD], array_keys($license->getItems()));
    }

    /**
     * Should Sulu rename its section, the field stays misplaced, never lost.
     */
    #[Test]
    public function withoutTheLicenseSectionTheFieldStaysWhereTheMergePutIt(): void
    {
        $form = self::mergedForm(withLicense: false);

        (new MediaOriginFormVisitor())->visitFormMetadata($form, 'en');

        self::assertArrayHasKey(MediaOriginFormVisitor::SECTION, $form->getItems());
    }

    #[Test]
    public function anotherFormIsLeftAlone(): void
    {
        $form = self::mergedForm(withLicense: true);
        $form->setKey('page');

        (new MediaOriginFormVisitor())->visitFormMetadata($form, 'en');

        self::assertArrayHasKey(MediaOriginFormVisitor::SECTION, $form->getItems());
    }

    /**
     * The bundle's form declares the section and the field it is read from.
     */
    #[Test]
    public function theBundleFormDeclaresTheField(): void
    {
        $xml = new \SimpleXMLElement((string) file_get_contents(\dirname(__DIR__, 2) . '/config/forms/media_details.xml'));
        $xml->registerXPathNamespace('t', 'http://schemas.sulu.io/template/template');

        self::assertSame(MediaOriginFormVisitor::FORM_KEY, (string) $xml->key);
        self::assertCount(1, $xml->xpath(\sprintf('//t:section[@name="%s"]//t:property[@name="%s"]', MediaOriginFormVisitor::SECTION, MediaOriginFormVisitor::FIELD)));
    }

    /**
     * The form as Sulu hands it over after the merge: the bundle's section
     * first, then Sulu's own.
     */
    private static function mergedForm(bool $withLicense): FormMetadata
    {
        $own = new SectionMetadata(MediaOriginFormVisitor::SECTION);
        $own->addItem(new FieldMetadata(MediaOriginFormVisitor::FIELD));

        $details = new SectionMetadata('media_details');
        $details->addItem(new FieldMetadata('title'));
        if ($withLicense) {
            $license = new SectionMetadata('license');
            $license->addItem(new FieldMetadata('copyright'));
            $license->addItem(new FieldMetadata('credits'));
            $details->addItem($license);
        }

        $form = new FormMetadata();
        $form->setKey(MediaOriginFormVisitor::FORM_KEY);
        $form->setItems([
            MediaOriginFormVisitor::SECTION => $own,
            'media_upload' => new SectionMetadata('media_upload'),
            'media_details' => $details,
        ]);

        return $form;
    }
}
