<?php

declare(strict_types=1);

namespace Communibase\Tests\Entity;

use Communibase\Entity\Event;
use Communibase\Exception\ActionNotAllowedByDateException;
use Communibase\Exception\ActionNotAllowedException;
use Communibase\Exception\AlreadyRegisteredException;
use Communibase\Exception\FullyBookedException;
use Communibase\Exception\InvalidDateException;
use Communibase\Tests\Model\TestEvent;
use Communibase\Tests\Model\TestPerson;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class EventTest extends TestCase
{
    private const VALID_COMMUNIBASE_ID = '50efe68c88949f3b63000017';

    public function test_it_can_be_created(): void
    {
        self::assertInstanceOf(Event::class, TestEvent::create());
    }

    public function test_it_can_return_the_id(): void
    {
        self::assertSame(
            self::VALID_COMMUNIBASE_ID,
            TestEvent::factory(['_id' => self::VALID_COMMUNIBASE_ID])->getId()->toString()
        );
    }

    public function test_it_can_detect_ready_status(): void
    {
        $event = TestEvent::factory(['status' => Event::STATUS_READY]);
        self::assertTrue($event->isReady());
    }

    public function nonReadyStatuses(): array
    {
        return [
            ['Under construction'],
            ['Cancelled'],
        ];
    }

    /**
     * @dataProvider nonReadyStatuses
     */
    public function test_it_can_detect_non_ready_state(string $nonReadyStatus): void
    {
        $event = TestEvent::factory(['status' => $nonReadyStatus]);
        self::assertFalse($event->isReady());
    }

    public function test_it_can_retrieve_max_participants(): void
    {
        $event = TestEvent::factory(['maxParticipants' => 5]);
        self::assertSame(5, $event->getMaxParticipants());
    }

    /**
     * @throws InvalidDateException
     */
    public function test_it_can_retrieve_registrationStartDate(): void
    {
        $date = new DateTimeImmutable('01-01-2020');
        $event = TestEvent::factory(['registrationStartDate' => $date->format('c')]);
        self::assertEquals($date, $event->getRegistrationStartDate());
    }

    /**
     * @throws InvalidDateException
     */
    public function test_it_can_retrieve_registrationEndDate(): void
    {
        $date = new DateTimeImmutable('01-01-2020');
        $event = TestEvent::factory(['registrationEndDate' => $date->format('c')]);
        self::assertEquals($date, $event->getRegistrationEndDate());
    }

    public function test_it_throws_exception_on_invalid_start_date(): void
    {
        $this->expectException(InvalidDateException::class);
        $event = TestEvent::factory(['registrationStartDate' => 'foo']);
        $event->getRegistrationStartDate();
    }

    public function test_it_throws_exception_on_invalid_end_date(): void
    {
        $this->expectException(InvalidDateException::class);
        $event = TestEvent::factory(['registrationEndDate' => 'foo']);
        $event->getRegistrationEndDate();
    }

    public function test_it_can_detect_a_registered_participant(): void
    {
        $event = TestEvent::factory(
            [
                'participants' => [
                    [
                        'personId' => TestPerson::VALID_PERSON_ID,
                        'status' => Event::PARTICIPANT_STATUS_REGISTERED
                    ],
                    [
                        'personId' => TestPerson::VALID_PERSON_ID_2,
                        'status' => Event::PARTICIPANT_STATUS_CANCELLED
                    ],
                ]
            ]
        );
        self::assertTrue($event->isRegisteredParticipant(TestPerson::createPersonOne()));
        self::assertFalse($event->isRegisteredParticipant(TestPerson::createPersonTwo()));
        self::assertFalse($event->isRegisteredParticipant(TestPerson::createPersonThree()));
    }

    /**
     * @throws AlreadyRegisteredException
     * @throws FullyBookedException
     * @throws ActionNotAllowedException
     * @throws ActionNotAllowedException
     */
    public function test_it_can_register_participants(): void
    {
        $event = TestEvent::create();
        $event->registerParticipant(TestPerson::createPersonOne());
        $event->registerParticipant(TestPerson::createPersonTwo());
        self::assertTrue($event->isRegisteredParticipant(TestPerson::createPersonOne()));
        self::assertTrue($event->isRegisteredParticipant(TestPerson::createPersonTwo()));
    }

    /**
     * @throws AlreadyRegisteredException
     * @throws FullyBookedException
     * @throws ActionNotAllowedException
     * @throws ActionNotAllowedException
     */
    public function test_it_throws_already_registered_exception(): void
    {
        $this->expectException(AlreadyRegisteredException::class);
        $event = TestEvent::create();
        $person = TestPerson::createPersonOne();
        $event->registerParticipant($person);
        $event->registerParticipant($person);
    }

    public function test_we_can_retrieve_all_registered_person_ids(): void
    {
        $event = TestEvent::factory(
            [
                'participants' => [
                    [
                        'personId' => TestPerson::VALID_PERSON_ID,
                        'status' => Event::PARTICIPANT_STATUS_REGISTERED,
                    ],
                    [
                        'personId' => TestPerson::VALID_PERSON_ID_2,
                        'status' => Event::PARTICIPANT_STATUS_REGISTERED,
                    ],
                    [
                        'personId' => TestPerson::VALID_PERSON_ID_3,
                        'status' => Event::PARTICIPANT_STATUS_CANCELLED,
                    ],
                ]
            ]
        );
        self::assertEquals(
            [TestPerson::VALID_PERSON_ID, TestPerson::VALID_PERSON_ID_2],
            $event->getRegisteredParticipantsPersonIds()->toStrings()
        );
    }

    /**
     * @throws AlreadyRegisteredException
     * @throws FullyBookedException
     * @throws ActionNotAllowedException
     */
    public function test_it_can_detect_a_fully_booked_event(): void
    {
        $event = TestEvent::factory(
            [
                'status' => Event::STATUS_READY,
                'maxParticipants' => 1,
                'participants' => [
                    [
                        'personId' => TestPerson::VALID_PERSON_ID,
                        'status' => Event::PARTICIPANT_STATUS_CANCELLED,
                    ],
                ]
            ]
        );
        self::assertFalse($event->isFullyBooked());
        $event->registerParticipant(TestPerson::createPersonTwo());
        self::assertTrue($event->isFullyBooked());
    }

    /**
     * @throws AlreadyRegisteredException
     * @throws FullyBookedException
     * @throws ActionNotAllowedException
     */
    public function test_it_throws_fully_booked_exception(): void
    {
        $event = TestEvent::create(Event::STATUS_READY, 1);
        $event->registerParticipant(TestPerson::createPersonOne());
        self::assertCount(1, $event->getRegisteredParticipantsPersonIds());

        $this->expectException(FullyBookedException::class);
        $event->registerParticipant(TestPerson::createPersonTwo());
    }

    /**
     * @throws AlreadyRegisteredException
     * @throws FullyBookedException
     */
    public function test_we_cannot_register_if_not_ready(): void
    {
        $this->expectException(ActionNotAllowedException::class);
        $event = TestEvent::create('notReady');
        $event->registerParticipant(TestPerson::createPersonOne());
    }

    /**
     * @throws ActionNotAllowedException
     * @throws AlreadyRegisteredException
     * @throws FullyBookedException
     */
    public function test_we_cannot_register_if_the_event_has_started(): void
    {
        $startDate = new DateTimeImmutable('yesterday');
        $event = TestEvent::create(Event::STATUS_READY, null, $startDate->format('c'));
        $this->expectException(ActionNotAllowedByDateException::class);
        $event->registerParticipant(TestPerson::createPersonOne());
    }

    /**
     * @throws ActionNotAllowedException
     * @throws AlreadyRegisteredException
     * @throws FullyBookedException
     */
    public function test_we_cannot_register_before_registrationStartDate(): void
    {
        $registrationStartDate = new DateTimeImmutable('tomorrow');
        $event = TestEvent::create(
            Event::STATUS_READY,
            null,
            null,
            $registrationStartDate->format('c')
        );
        $this->expectException(ActionNotAllowedByDateException::class);
        $event->registerParticipant(TestPerson::createPersonOne());
    }

    /**
     * @throws ActionNotAllowedException
     * @throws AlreadyRegisteredException
     * @throws FullyBookedException
     */
    public function test_we_cannot_register_after_endDate(): void
    {
        $registrationEndDate = new DateTimeImmutable('yesterday');
        $event = TestEvent::create(
            Event::STATUS_READY,
            null,
            null,
            null,
            $registrationEndDate->format('c')
        );
        $this->expectException(ActionNotAllowedByDateException::class);
        $event->registerParticipant(TestPerson::createPersonOne());
    }

    /**
     * @throws ActionNotAllowedByDateException
     */
    public function test_it_can_unregister_a_participant(): void
    {
        $event = TestEvent::factory(
            [
                'participants' => [
                    [
                        'personId' => TestPerson::VALID_PERSON_ID,
                        'status' => Event::PARTICIPANT_STATUS_REGISTERED,
                    ],
                    [
                        'personId' => TestPerson::VALID_PERSON_ID_2,
                        'status' => Event::PARTICIPANT_STATUS_REGISTERED,
                    ],
                ]
            ]
        );
        $event->unRegisterParticipant(TestPerson::createPersonOne());
        self::assertSame(
            [
                [
                    'personId' => TestPerson::VALID_PERSON_ID,
                    'status' => Event::PARTICIPANT_STATUS_CANCELLED,
                ],
                [
                    'personId' => TestPerson::VALID_PERSON_ID_2,
                    'status' => Event::PARTICIPANT_STATUS_REGISTERED,
                ],
            ],
            $event->getRawParticipantsData()
        );
    }

    /**
     * @throws ActionNotAllowedByDateException
     */
    public function test_we_cant_unregister_after_startDate(): void
    {
        $event = TestEvent::factory(
            [
                'startDate' => (new DateTimeImmutable('yesterday'))->format('c'),
                'participants' => [
                    [
                        'personId' => TestPerson::VALID_PERSON_ID,
                        'status' => Event::PARTICIPANT_STATUS_REGISTERED,
                    ]
                ]
            ]
        );
        $this->expectException(ActionNotAllowedByDateException::class);
        $event->unRegisterParticipant(TestPerson::createPersonOne());
    }

    /**
     * @throws ActionNotAllowedException
     * @throws AlreadyRegisteredException
     * @throws FullyBookedException
     */
    public function test_it_changes_the_status_of_an_existing_participant(): void
    {
        $event = TestEvent::factory(
            [
                'status' => Event::STATUS_READY,
                'participants' => [
                    [
                        'personId' => TestPerson::VALID_PERSON_ID,
                        'status' => Event::PARTICIPANT_STATUS_CANCELLED,
                    ]
                ]
            ]
        );
        $event->registerParticipant(TestPerson::createPersonOne());
        self::assertCount(1, $event->getRegisteredParticipantsPersonIds());
        self::assertTrue($event->isRegisteredParticipant(TestPerson::createPersonOne()));
    }
}
