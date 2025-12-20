<?php

use SomehowDigital\Symfony\Component\Mailer\Bridge\Lettermint\RemoteEvent\LettermintPayloadConverter;
use SomehowDigital\Symfony\Component\Mailer\Bridge\Lettermint\Webhook\LettermintWebhookParser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

beforeEach(function () {
	$this->parser = new LettermintWebhookParser(new LettermintPayloadConverter());
});

function createRequest(array $payload, string $secret = 'secret', ?int $timestamp = null): Request
{
	$timestamp ??= time();
	$content = \json_encode($payload);
	$signature = hash_hmac('sha256', $timestamp . '.' . $content, $secret);

	return new Request(
		[],
		[],
		[],
		[],
		[],
		[
			'REQUEST_METHOD' => 'POST',
			'HTTP_X_LETTERMINT_SIGNATURE' => sprintf('t=%d,v1=%s', $timestamp, $signature),
		],
		$content
	);
}

it('parses delivery event', function () {
	$request = createRequest([
		'id' => '54d7e8c9-1195-4ba0-9d3f-b9af92305add',
		'event' => 'message.delivered',
		'timestamp' => '2025-08-08T20:15:12.000Z',
		'data' => [
			'message_id' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
			'subject' => 'Your order has shipped',
			'recipient' => 'user@example.com',
			'metadata' => ['X-Transaction-ID' => 'txn-456'],
			'tag' => 'order-confirmation',
		],
	]);

	$event = $this->parser->parse($request, 'secret');

	expect($event)->toBeInstanceOf(MailerDeliveryEvent::class)
		->and($event->getName())->toBe(MailerDeliveryEvent::DELIVERED)
		->and($event->getId())->toBe('f47ac10b-58cc-4372-a567-0e02b2c3d479')
		->and($event->getRecipientEmail())->toBe('user@example.com')
		->and($event->getDate()->format('Y-m-d H:i:s'))->toBe('2025-08-08 20:15:12')
		->and($event->getMetadata())->toBe(['X-Transaction-ID' => 'txn-456'])
		->and($event->getTags())->toBe(['order-confirmation']);
});

it('parses engagement event', function () {
	$request = createRequest([
		'id' => 'b2c3d4e5-f6a7-8901-bcde-f01234567891',
		'event' => 'message.unsubscribed',
		'timestamp' => '2025-08-08T20:16:30.000Z',
		'data' => [
			'message_id' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
			'recipient' => 'user@example.com',
			'unsubscribed_at' => '2025-08-08T20:16:30.000Z',
		],
	]);

	$event = $this->parser->parse($request, 'secret');

	expect($event)->toBeInstanceOf(MailerEngagementEvent::class)
		->and($event->getName())->toBe(MailerEngagementEvent::UNSUBSCRIBE)
		->and($event->getId())->toBe('f47ac10b-58cc-4372-a567-0e02b2c3d479');
});

it('parses bounce event with reason', function () {
	$request = createRequest([
		'id' => '123e4567-e89b-12d3-a456-426614174000',
		'event' => 'message.hard_bounced',
		'timestamp' => '2025-08-08T20:15:30.000Z',
		'data' => [
			'message_id' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
			'recipient' => 'user@example.com',
			'response' => [
				'status_code' => 250,
				'enhanced_status_code' => '2.0.0',
				'content' => 'Permanent failure'
			],
		],
	]);

	$event = $this->parser->parse($request, 'secret');

	expect($event)->toBeInstanceOf(MailerDeliveryEvent::class)
		->and($event->getName())->toBe(MailerDeliveryEvent::BOUNCE)
		->and($event->getReason())->toBe('Permanent failure');
});

it('throws exception on invalid payload', function () {
	$request = createRequest(['invalid' => 'data']);

	$this->parser->parse($request, 'secret');
})->throws(RejectWebhookException::class);

it('throws exception on missing signature header', function () {
	$request = new Request([], [], [], [], [], ['REQUEST_METHOD' => 'POST'], \json_encode(['event' => 'message.delivered']));

	$this->parser->parse($request, 'secret');
})->throws(RejectWebhookException::class, 'Request does not match.');

it('throws exception on invalid signature', function () {
	$request = createRequest([
		'event' => 'message.delivered',
		'data' => ['message_id' => '123'],
	], 'wrong_secret');

	$this->parser->parse($request, 'secret');
})->throws(RejectWebhookException::class, 'Signature is invalid.');

it('throws exception on expired timestamp', function () {
	$request = createRequest([
		'event' => 'message.delivered',
		'data' => ['message_id' => '123'],
	], 'secret', time() - 600);

	$this->parser->parse($request, 'secret');
})->throws(RejectWebhookException::class, 'Signature timestamp is too old or too new.');
