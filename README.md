# Communibase Event

Basic register/unregister functionality for event participants.

Contains:
- Communibase\Entity\Event
- Communibase\Entity\ParticipantInterface

Your event can extend Communibase\Entity\Event, your person/member/participant must implement ParticipantInterface.

```php
$person = Person::factory($personData);
$event = Communibase\Entity\Event::factory($eventData);

// add participant and set status to 'registered'
$event->registerParticipant($person);

// if present set participant status to 'cancelled'
$event->unRegisterParticipant($person);

// checks if this person is a registered participant
$event->isRegisteredParticipant($person);
```
