<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Media;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\TableNotFoundException;
use ItechWorld\SuluTailwindThemeBundle\Entity\MediaOrigin;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Reads and writes the declared origin of media.
 *
 * Every origin is loaded in a single query, the first time one is asked for,
 * then served from memory: a page with thirty images, or a media list in the
 * admin, costs one query and no join. The table only holds AI images, so it
 * stays small. Reset between requests (long-running workers).
 */
class MediaOriginRegistry implements ResetInterface
{
    /**
     * @var array<int, ImageOrigin>|null Origin by media id, null until loaded
     */
    private ?array $origins = null;

    /**
     * @param Connection $connection The database holding Sulu's media
     */
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @param int|null $mediaId A media id
     *
     * @return ImageOrigin Its declared origin, human when none was declared
     */
    public function get(?int $mediaId): ImageOrigin
    {
        if (null === $mediaId) {
            return ImageOrigin::Human;
        }

        return $this->load()[$mediaId] ?? ImageOrigin::Human;
    }

    /**
     * Store the origin of a media. A human origin removes its row.
     *
     * @param int         $mediaId The media id
     * @param ImageOrigin $origin  Its declared origin
     */
    public function set(int $mediaId, ImageOrigin $origin): void
    {
        if ($this->get($mediaId) === $origin) {
            return;
        }

        $this->connection->transactional(function (Connection $connection) use ($mediaId, $origin): void {
            $connection->delete(MediaOrigin::TABLE, ['media_id' => $mediaId]);

            if ($origin->isAi()) {
                $connection->insert(MediaOrigin::TABLE, ['media_id' => $mediaId, 'origin' => $origin->value]);
            }
        });

        $this->origins = null;
    }

    public function reset(): void
    {
        $this->origins = null;
    }

    /**
     * @return array<int, ImageOrigin> Origin by media id
     */
    private function load(): array
    {
        if (null !== $this->origins) {
            return $this->origins;
        }

        $this->origins = [];

        try {
            $rows = $this->connection->fetchAllAssociative(\sprintf('SELECT media_id, origin FROM %s', MediaOrigin::TABLE));
        } catch (TableNotFoundException) {
            // A project that has not updated its schema yet renders as
            // before, with no label, rather than an error on every page.
            return $this->origins;
        }

        foreach ($rows as $row) {
            $this->origins[(int) $row['media_id']] = ImageOrigin::fromValue($row['origin']);
        }

        return $this->origins;
    }
}
