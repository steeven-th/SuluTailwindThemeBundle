<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\EventSubscriber;

use ItechWorld\SuluTailwindThemeBundle\EventSubscriber\MediaOriginSubscriber;
use ItechWorld\SuluTailwindThemeBundle\Media\ImageOrigin;
use ItechWorld\SuluTailwindThemeBundle\Media\MediaOriginRegistry;
use ItechWorld\SuluTailwindThemeBundle\Tests\Media\MediaOriginRegistryTest;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\MediaBundle\Domain\Event\MediaCreatedEvent;
use Sulu\Bundle\MediaBundle\Domain\Event\MediaModifiedEvent;
use Sulu\Bundle\MediaBundle\Entity\MediaInterface;

/**
 * The origin picked in the media form reaches the database.
 */
final class MediaOriginSubscriberTest extends TestCase
{
    #[Test]
    public function savingTheFormStoresTheOrigin(): void
    {
        $registry = new MediaOriginRegistry(MediaOriginRegistryTest::connection());

        (new MediaOriginSubscriber($registry))->onMediaSaved(new MediaModifiedEvent($this->media(7), 'en', ['iwImageOrigin' => 'ai_modified']));

        self::assertSame(ImageOrigin::AiModified, $registry->get(7));
    }

    #[Test]
    public function anUploadedMediaGetsItsOriginToo(): void
    {
        $registry = new MediaOriginRegistry(MediaOriginRegistryTest::connection());

        (new MediaOriginSubscriber($registry))->onMediaSaved(new MediaCreatedEvent($this->media(8), 'en', ['iwImageOrigin' => 'ai_generated']));

        self::assertSame(ImageOrigin::AiGenerated, $registry->get(8));
    }

    /**
     * An upload of a new version, a move, an API call without the field: the
     * stored origin stays. Only the form says otherwise.
     */
    #[Test]
    public function anEventWithoutTheFieldLeavesTheOriginAlone(): void
    {
        $registry = new MediaOriginRegistry(MediaOriginRegistryTest::connection());
        $registry->set(7, ImageOrigin::AiGenerated);

        (new MediaOriginSubscriber($registry))->onMediaSaved(new MediaModifiedEvent($this->media(7), 'en', ['title' => 'Renamed']));

        self::assertSame(ImageOrigin::AiGenerated, $registry->get(7));
    }

    private function media(int $id): MediaInterface
    {
        $media = self::createStub(MediaInterface::class);
        $media->method('getId')->willReturn($id);

        return $media;
    }
}
