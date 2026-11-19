<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sulu\Bundle\MediaBundle\Entity\MediaInterface;

/**
 * The declared origin of a media, kept beside Sulu's own table.
 *
 * Only media made or modified with AI have a row: a missing one means a
 * human-made image. The row goes with its media (cascade on delete).
 *
 * Written and read through DBAL by MediaOriginRegistry: the rows are saved
 * when Sulu dispatches its media events, after its flush, where a second
 * flush of the entity manager is not allowed. The entity declares the table
 * for the schema tools.
 */
#[ORM\Entity]
#[ORM\Table(name: MediaOrigin::TABLE)]
class MediaOrigin
{
    public const TABLE = 'iw_sulu_tailwind_theme_media_origin';

    #[ORM\Id]
    #[ORM\OneToOne(targetEntity: MediaInterface::class)]
    #[ORM\JoinColumn(name: 'media_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private MediaInterface $media;

    #[ORM\Column(type: Types::STRING, length: 32)]
    private string $origin;

    /**
     * @param MediaInterface $media  The media described
     * @param string         $origin An ImageOrigin value
     */
    public function __construct(MediaInterface $media, string $origin)
    {
        $this->media = $media;
        $this->origin = $origin;
    }

    /**
     * @return MediaInterface The media described
     */
    public function getMedia(): MediaInterface
    {
        return $this->media;
    }

    /**
     * @return string An ImageOrigin value
     */
    public function getOrigin(): string
    {
        return $this->origin;
    }
}
