<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Doctrine;

use Doctrine\ORM\Mapping as ORM;

/**
 * Stand-in for a dimension content: an id and a JSON column, which is all the
 * function under test needs to be parsed inside a real query.
 */
#[ORM\Entity]
#[ORM\Table(name: 'iw_json_fixture')]
class JsonFixtureEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    public int $id = 0;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    public array $data = [];
}
