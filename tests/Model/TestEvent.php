<?php

declare(strict_types=1);

namespace Communibase\Tests\Model;

use Communibase\Entity\Event;

class TestEvent extends Event
{
    /**
     * Test we can set a custom entityType
     */
    protected $entityType = 'myEvent';

    public static function create(
        $status = Event::STATUS_READY,
        $maxParticipants = null,
        $startDate = null,
        $registrationStartDate = null,
        $registrationEndDate = null
    ): TestEvent {
        return self::factory(
            [
                'status' => $status,
                'maxParticipants' => $maxParticipants,
                'startDate' => $startDate,
                'registrationStartDate' => $registrationStartDate,
                'registrationEndDate' => $registrationEndDate,
                'participants' => [],
            ]
        );
    }

    public function getRawParticipantsData(): array
    {
        return (array)$this->dataBag->get('myEvent.participants', []);
    }
}
