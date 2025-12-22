<?php

use SomehowDigital\Symfony\Component\Mailer\Bridge\Lettermint\RemoteEvent\LettermintPayloadConverter;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;
use Symfony\Component\RemoteEvent\Exception\ParseException;

beforeEach(function () {
	$this->converter = new LettermintPayloadConverter();
});

it('converts events', function (array $payload, string $class, string $name, ?string $reason = null, ?string $recipient = null, ?array $metadata = null, ?array $tags = null) {
	$event = $this->converter->convert($payload);

	expect($event)->toBeInstanceOf($class)
		->and($event->getName())->toBe($name);

	if ($reason !== null) {
		expect($event->getReason())->toBe($reason);
	}

	if ($recipient !== null) {
		expect($event->getRecipientEmail())->toBe($recipient);
	}

	if ($metadata !== null) {
		expect($event->getMetadata())->toBe($metadata);
	}

	if ($tags !== null) {
		expect($event->getTags())->toBe($tags);
	}
})->with([
	'delivered' => [
		[
			'event' => 'message.delivered',
			'timestamp' => '2025-08-08T20:15:12.000Z',
			'data' => [
				'message_id' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
				'recipient' => 'user@example.com',
				'metadata' => ['X-Transaction-ID' => 'txn-456'],
				'tag' => 'order-confirmation',
			],
		],
		MailerDeliveryEvent::class, MailerDeliveryEvent::DELIVERED, null, 'user@example.com', ['X-Transaction-ID' => 'txn-456'], ['order-confirmation'],
	],
	'bounce with reason' => [
		['event' => 'message.hard_bounced', 'data' => ['message_id' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479', 'reason' => 'Permanent failure']],
		MailerDeliveryEvent::class, MailerDeliveryEvent::BOUNCE, 'Permanent failure',
	],
	'bounce with response' => [
		['event' => 'message.soft_bounced', 'data' => ['message_id' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479', 'response' => ['content' => 'Temporary failure']]],
		MailerDeliveryEvent::class, MailerDeliveryEvent::BOUNCE, 'Temporary failure',
	],
	'engagement' => [
		['event' => 'message.unsubscribed', 'data' => ['message_id' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479']],
		MailerEngagementEvent::class, MailerEngagementEvent::UNSUBSCRIBE,
	],
	'failed' => [
		['event' => 'message.failed', 'data' => ['message_id' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479', 'reason' => 'A network error occurred.']],
		MailerDeliveryEvent::class, MailerDeliveryEvent::DROPPED, 'A network error occurred.',
	],
	'suppressed' => [
		['event' => 'message.suppressed', 'data' => ['message_id' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479', 'reason' => 'hard_bounce']],
		MailerDeliveryEvent::class, MailerDeliveryEvent::DROPPED, 'hard_bounce',
	],
	'policy rejected' => [
		['event' => 'message.policy_rejected', 'data' => ['message_id' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479', 'reason' => 'Spam score threshold exceeded']],
		MailerDeliveryEvent::class, MailerDeliveryEvent::DROPPED, 'Spam score threshold exceeded',
	],
	'spam complaint' => [
		['event' => 'message.spam_complaint', 'data' => ['message_id' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479', 'recipient' => 'user@example.com']],
		MailerEngagementEvent::class, MailerEngagementEvent::SPAM, null, 'user@example.com',
	],
	'inbound' => [
		['event' => 'message.inbound', 'data' => ['message_id' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479', 'recipient' => 'support@acme.com']],
		MailerDeliveryEvent::class, MailerDeliveryEvent::RECEIVED, null, 'support@acme.com',
	],
]);

it('throws exception for unsupported event', function () {
	$payload = [
		'event' => 'unsupported.event',
		'data' => ['message_id' => '123'],
	];

	$this->converter->convert($payload);
})->throws(ParseException::class, 'Unsupported event "unsupported.event".');
