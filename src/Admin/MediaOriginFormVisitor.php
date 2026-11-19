<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Admin;

use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadataVisitorInterface;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\ItemMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\SectionMetadata;

/**
 * Places the image origin field next to the copyright and credits of a media.
 *
 * The field is declared in the bundle's config/forms/media_details.xml, which
 * Sulu merges into its own form of that key. A merge can only add sections at
 * the top level, and the bundle's form is merged first: the field would sit
 * above the upload area and break the two-column layout. This visitor moves
 * it into Sulu's "License" section, where the rights of the image already
 * are. Should a later Sulu rename that section, the field stays where the
 * merge put it: misplaced, never lost.
 */
class MediaOriginFormVisitor implements FormMetadataVisitorInterface
{
    public const FORM_KEY = 'media_details';

    /**
     * The section the bundle's media_details.xml declares.
     */
    public const SECTION = 'iw_media_origin';

    public const FIELD = 'iwImageOrigin';

    /**
     * @param mixed[] $metadataOptions
     */
    public function visitFormMetadata(FormMetadata $formMetadata, string $locale, array $metadataOptions = []): void
    {
        if (self::FORM_KEY !== $formMetadata->getKey()) {
            return;
        }

        $items = $formMetadata->getItems();
        $own = $items[self::SECTION] ?? null;
        $details = $items['media_details'] ?? null;

        if (!$own instanceof SectionMetadata || !$details instanceof SectionMetadata) {
            return;
        }

        $license = $details->getItems()['license'] ?? null;
        if (!$license instanceof SectionMetadata) {
            return;
        }

        foreach ($own->getItems() as $item) {
            if ($item instanceof ItemMetadata) {
                $license->addItem($item);
            }
        }

        unset($items[self::SECTION]);
        $formMetadata->setItems($items);
    }
}
