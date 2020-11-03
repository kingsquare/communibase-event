<?php

declare(strict_types=1);

namespace Communibase\Entity;

use Communibase\CommunibaseId;
use Communibase\CommunibaseIdCollection;
use Communibase\DataBag;
use Communibase\Exception\ActionNotAllowedByDateException;
use Communibase\Exception\ActionNotAllowedException;
use Communibase\Exception\AlreadyRegisteredException;
use Communibase\Exception\FullyBookedException;
use Communibase\Exception\InvalidDateException;

/**
 * @author Kingsquare (source@kingsquare.nl)
 * @copyright Copyright (c) Kingsquare BV (http://www.kingsquare.nl)
 */
class Event
{
    public const STATUS_READY = 'Ready';

    public const PARTICIPANT_STATUS_REGISTERED = 'registered';
    public const PARTICIPANT_STATUS_CANCELLED = 'cancelled';

    /**
     * @var DataBag
     */
    protected $dataBag;

    /**
     * @var string
     */
    protected $entityType = 'event';

    /**
     * @var string
     */
    protected $timezone = 'Europe/Amsterdam';

    /**
     * @var string[]
     */
    protected $registeredStatuses = [
        self::PARTICIPANT_STATUS_REGISTERED
    ];

    /**
     * @param array<string,mixed> $eventData
     */
    final private function __construct(array $eventData)
    {
        $this->dataBag = DataBag::fromEntityData($this->entityType, $eventData);
    }

    /**
     * @param array<string,mixed> $eventData
     * @return static
     */
    public static function factory(array $eventData)
    {
        return new static($eventData);
    }

    public function getId(): CommunibaseId
    {
        return CommunibaseId::fromValidString($this->dataBag->get($this->entityType . '._id'));
    }

    public function isReady(): bool
    {
        return $this->dataBag->get($this->entityType . '.status') === self::STATUS_READY;
    }

    public function getMaxParticipants(): ?int
    {
        $maxParticipants = $this->dataBag->get($this->entityType . '.maxParticipants');
        return $maxParticipants === null ? null : (int)$maxParticipants;
    }

    public function isFullyBooked(): bool
    {
        if ($this->getMaxParticipants() === null) {
            return false;
        }
        return $this->getRegisteredParticipantsPersonIds()->count() >= $this->getMaxParticipants();
    }

    public function getRegisteredParticipantsPersonIds(): CommunibaseIdCollection
    {
        return CommunibaseIdCollection::fromValidStrings(
            \array_reduce(
                $this->getParticipantsData(),
                function (array $ids, array $participantData) {
                    if (\in_array($participantData['status'], $this->registeredStatuses, true)) {
                        $ids[] = $participantData['personId'];
                    }
                    return $ids;
                },
                []
            )
        );
    }

    /**
     * @throws InvalidDateException
     */
    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->toDateTimeImmutable($this->dataBag->get($this->entityType . '.startDate'));
    }

    /**
     * @throws InvalidDateException
     */
    public function getRegistrationStartDate(): ?\DateTimeInterface
    {
        return $this->toDateTimeImmutable($this->dataBag->get($this->entityType . '.registrationStartDate'));
    }

    /**
     * @throws InvalidDateException
     */
    public function getRegistrationEndDate(): ?\DateTimeInterface
    {
        return $this->toDateTimeImmutable($this->dataBag->get($this->entityType . '.registrationEndDate'));
    }

    /**
     * @throws AlreadyRegisteredException
     * @throws FullyBookedException
     * @throws ActionNotAllowedException
     */
    public function registerParticipant(ParticipantInterface $participant, CommunibaseId $debtorId = null): void
    {
        $this->guardAgainstAlreadyStarted();
        $this->guardAgainstEventNotReady();
        $this->guardAgainstRegistrationClosedByDate();
        $this->guardAgainstAlreadyRegistered($participant);
        $this->guardAgainstFullyBooked();

        $participantsData = $this->getParticipantsData();
        foreach ($participantsData as &$participantData) {
            if ($participantData['personId'] === $participant->getId()->toString()) {
                $participantData['status'] = self::PARTICIPANT_STATUS_REGISTERED;
                if ($debtorId !== null) {
                    $participantData['debtorId'] = $debtorId->toString();
                }
                $this->setParticipantsData($participantsData);
                return;
            }
        }
        unset($participantData);
        $participantsData[] = [
            'personId' => $participant->getId()->toString(),
            'status' => self::PARTICIPANT_STATUS_REGISTERED,
            'debtorId' => $debtorId === null ? null : $debtorId->toString()
        ];
        $this->setParticipantsData($participantsData);
    }

    /**
     * @throws ActionNotAllowedByDateException
     */
    public function unRegisterParticipant(ParticipantInterface $participant): void
    {
        $this->guardAgainstAlreadyStarted();
        if (!$this->isRegisteredParticipant($participant)) {
            return;
        }
        $participantsData = $this->getParticipantsData();
        foreach ($participantsData as &$participantData) {
            if ($participantData['personId'] === $participant->getId()->toString()) {
                $participantData['status'] = self::PARTICIPANT_STATUS_CANCELLED;
            }
        }
        unset($participantData);
        $this->setParticipantsData($participantsData);
    }

    public function isRegisteredParticipant(ParticipantInterface $participant): bool
    {
        foreach ($this->getParticipantsData() as $participantData) {
            if (\in_array($participantData['status'], $this->registeredStatuses, true)
                && $participantData['personId'] === $participant->getId()->toString()) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return array<array<string,mixed>>
     */
    private function getParticipantsData(): array
    {
        return $this->dataBag->get($this->entityType . '.participants', []);
    }

    /**
     * @param array<string,array<string,mixed>> $participantsData
     */
    private function setParticipantsData(array $participantsData): void
    {
        $this->dataBag->set($this->entityType . '.participants', $participantsData);
    }

    /**
     * Convert CB dateTime to local timezone
     * @throws InvalidDateException
     */
    private function toDateTimeImmutable(?string $string): ?\DateTimeInterface
    {
        if (empty($string)) {
            return null;
        }
        try {
            return (new \DateTimeImmutable($string))->setTimezone(new \DateTimeZone($this->timezone));
        } catch (\Exception $e) {
            throw new InvalidDateException('Invalid date found: ' . $string);
        }
    }

    /**
     * @throws ActionNotAllowedByDateException
     */
    private function guardAgainstAlreadyStarted(): void
    {
        try {
            if ($this->getStartDate() === null) {
                return;
            }
            if (new \DateTimeImmutable() < $this->getStartDate()) {
                return;
            }
        } catch (\Exception $e) {
            // throws below
        }
        throw new ActionNotAllowedByDateException('Event is already started.');
    }

    /**
     * @throws FullyBookedException
     */
    private function guardAgainstFullyBooked(): void
    {
        if ($this->isFullyBooked()) {
            throw new FullyBookedException('Event is fully booked.');
        }
    }

    /**
     * @throws AlreadyRegisteredException
     */
    private function guardAgainstAlreadyRegistered(ParticipantInterface $participant): void
    {
        if ($this->isRegisteredParticipant($participant)) {
            throw new AlreadyRegisteredException('Participant is already registered.');
        }
    }

    /**
     * @throws ActionNotAllowedException
     */
    private function guardAgainstEventNotReady(): void
    {
        if (!$this->isReady()) {
            throw new ActionNotAllowedException('Event status is not ready.');
        }
    }

    /**
     * @throws ActionNotAllowedByDateException
     */
    private function guardAgainstRegistrationClosedByDate(): void
    {
        try {
            $registrationStartDate = $this->getRegistrationStartDate();
            if ($registrationStartDate !== null && (new \DateTimeImmutable() < $registrationStartDate)) {
                throw new ActionNotAllowedByDateException(
                    'Registration starts on ' . $registrationStartDate->format('r')
                );
            }
            $registrationEndDate = $this->getRegistrationEndDate();
            if ($registrationEndDate !== null && (new \DateTimeImmutable() > $registrationEndDate)) {
                throw new ActionNotAllowedByDateException(
                    'Registration ended on ' . $registrationEndDate->format('r')
                );
            }
        } catch (InvalidDateException $e) {
            throw new ActionNotAllowedByDateException('Invalid date found.');
        }
    }
}
