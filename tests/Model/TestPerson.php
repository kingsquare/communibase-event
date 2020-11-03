<?php

declare(strict_types=1);

namespace Communibase\Tests\Model;

use Communibase\CommunibaseId;
use Communibase\Entity\ParticipantInterface;

class TestPerson implements ParticipantInterface
{
    public const VALID_PERSON_ID = '5fa12ded66bd790136bbcd39';
    public const VALID_PERSON_ID_2 = '5644681df29478ca0051340f';
    public const VALID_PERSON_ID_3 = '5f8ece0575788000e3d2a4d2';

    /**
     * @var CommunibaseId
     */
    private $id;

    private function __construct(CommunibaseId $id)
    {
        $this->id = $id;
    }

    public static function createPersonOne(): TestPerson
    {
        return new self(CommunibaseId::fromValidString(self::VALID_PERSON_ID));
    }

    public static function createPersonTwo(): TestPerson
    {
        return new self(CommunibaseId::fromValidString(self::VALID_PERSON_ID_2));
    }

    public static function createPersonThree(): TestPerson
    {
        return new self(CommunibaseId::fromValidString(self::VALID_PERSON_ID_3));
    }

    public function getId(): CommunibaseId
    {
        return $this->id;
    }
}
