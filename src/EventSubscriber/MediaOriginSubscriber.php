<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\EventSubscriber;

use ItechWorld\SuluTailwindThemeBundle\Admin\MediaOriginFormVisitor;
use ItechWorld\SuluTailwindThemeBundle\Media\ImageOrigin;
use ItechWorld\SuluTailwindThemeBundle\Media\MediaOriginRegistry;
use Sulu\Bundle\MediaBundle\Domain\Event\MediaCreatedEvent;
use Sulu\Bundle\MediaBundle\Domain\Event\MediaModifiedEvent;
use Sulu\Bundle\MediaBundle\Domain\Event\MediaTranslationAddedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Saves the image origin an editor picked in the media form.
 *
 * Sulu stores the fields it knows and hands the whole submitted form to its
 * media events: the origin is read from there. An event without the field
 * (an upload, a move, an API call that does not send it) leaves the stored
 * origin alone.
 */
class MediaOriginSubscriber implements EventSubscriberInterface
{
    /**
     * @param MediaOriginRegistry $registry Where origins are kept
     */
    public function __construct(
        private readonly MediaOriginRegistry $registry,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MediaCreatedEvent::class => 'onMediaSaved',
            MediaModifiedEvent::class => 'onMediaSaved',
            MediaTranslationAddedEvent::class => 'onMediaSaved',
        ];
    }

    /**
     * @param MediaCreatedEvent|MediaModifiedEvent|MediaTranslationAddedEvent $event The media saved
     */
    public function onMediaSaved(MediaCreatedEvent|MediaModifiedEvent|MediaTranslationAddedEvent $event): void
    {
        $payload = $event->getEventPayload() ?? [];
        if (!\array_key_exists(MediaOriginFormVisitor::FIELD, $payload)) {
            return;
        }

        $mediaId = $event->getMedia()->getId();
        if (null === $mediaId) {
            return;
        }

        $this->registry->set($mediaId, ImageOrigin::fromValue($payload[MediaOriginFormVisitor::FIELD]));
    }
}
