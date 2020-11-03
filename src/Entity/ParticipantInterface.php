<?php

declare(strict_types=1);

namespace Communibase\Entity;

use Communibase\CommunibaseId;

interface ParticipantInterface
{
    public function getId(): CommunibaseId;
}
