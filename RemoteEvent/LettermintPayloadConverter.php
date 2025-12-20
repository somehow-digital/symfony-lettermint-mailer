<?php

namespace SomehowDigital\Symfony\Component\Mailer\Bridge\Lettermint\RemoteEvent;

use Symfony\Component\RemoteEvent\Event\Mailer\AbstractMailerEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;
use Symfony\Component\RemoteEvent\Exception\ParseException;
use Symfony\Component\RemoteEvent\PayloadConverterInterface;

final class LettermintPayloadConverter implements PayloadConverterInterface
{
	public function convert(array $payload): AbstractMailerEvent
	{
		$data = $payload['data'];

		$name = match ($payload['event']) {
			'message.delivered' => MailerDeliveryEvent::DELIVERED,
			'message.hard_bounced' => MailerDeliveryEvent::BOUNCE,
			'message.soft_bounced' => MailerDeliveryEvent::BOUNCE,
			'message.failed' => MailerDeliveryEvent::DROPPED,
			'message.suppressed' => MailerDeliveryEvent::DROPPED,
			'message.policy_rejected' => MailerDeliveryEvent::DROPPED,
			'message.unsubscribed' => MailerEngagementEvent::UNSUBSCRIBE,
			'message.spam_complaint' => MailerEngagementEvent::SPAM,
			'message.inbound' => MailerDeliveryEvent::RECEIVED,
			default => throw new ParseException(\sprintf('Unsupported event "%s".', $payload['event'])),
		};

		if (\in_array($name, [
			MailerDeliveryEvent::DROPPED,
			MailerDeliveryEvent::DELIVERED,
			MailerDeliveryEvent::DEFERRED,
			MailerDeliveryEvent::BOUNCE,
			MailerDeliveryEvent::RECEIVED,
		], true)) {
			$event = new MailerDeliveryEvent($name, $data['message_id'], $data);

			if (isset($data['reason'])) {
				$event->setReason($data['reason']);
			} elseif (isset($data['response']['content'])) {
				$event->setReason($data['response']['content']);
			}
		} else {
			$event = new MailerEngagementEvent($name, $data['message_id'], $data);
		}

		$event->setRecipientEmail($data['recipient'] ?? '');
		$event->setMetadata($data['metadata'] ?? []);

		if (isset($data['tag'])) {
			$event->setTags([$data['tag']]);
		}

		if (isset($payload['timestamp'])) {
			$event->setDate(new \DateTimeImmutable($payload['timestamp']));
		}

		return $event;
	}
}
