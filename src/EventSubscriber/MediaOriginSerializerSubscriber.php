<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\EventSubscriber;

use ItechWorld\SuluTailwindThemeBundle\Admin\MediaOriginFormVisitor;
use ItechWorld\SuluTailwindThemeBundle\Media\MediaOriginRegistry;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\Metadata\StaticPropertyMetadata;
use JMS\Serializer\Visitor\SerializationVisitorInterface;
use Sulu\Bundle\MediaBundle\Entity\MediaInterface;
use Sulu\Component\Rest\ApiWrapper;

/**
 * Adds the image origin to a serialized media, so the media form shows the
 * stored value again. The same pattern as Sulu's MediaPermissionsSubscriber.
 */
class MediaOriginSerializerSubscriber implements EventSubscriberInterface
{
    /**
     * @param MediaOriginRegistry $registry Where origins are kept
     */
    public function __construct(
        private readonly MediaOriginRegistry $registry,
    ) {
    }

    /**
     * @return mixed[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ['event' => 'serializer.post_serialize', 'method' => 'onPostSerialize'],
        ];
    }

    /**
     * @param ObjectEvent $event A serialized object, kept when it is a media
     */
    public function onPostSerialize(ObjectEvent $event): void
    {
        $object = $event->getObject();
        if ($object instanceof ApiWrapper) {
            $object = $object->getEntity();
        }

        if (!$object instanceof MediaInterface) {
            return;
        }

        $visitor = $event->getVisitor();
        if (!$visitor instanceof SerializationVisitorInterface) {
            return;
        }

        $origin = $this->registry->get($object->getId())->value;
        $visitor->visitProperty(
            new StaticPropertyMetadata('', MediaOriginFormVisitor::FIELD, $origin),
            $origin,
        );
    }
}
