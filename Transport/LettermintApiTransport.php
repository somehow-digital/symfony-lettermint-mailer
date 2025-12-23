<?php

namespace SomehowDigital\Symfony\Component\Mailer\Bridge\Lettermint\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use SomehowDigital\Symfony\Component\Mailer\Bridge\Lettermint\Header\RouteHeader;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class LettermintApiTransport extends AbstractApiTransport
{
	public function __construct(
		#[\SensitiveParameter]
		private readonly string $token,
		private readonly ?string $route = null,
		?HttpClientInterface $client = null,
		?EventDispatcherInterface $dispatcher = null,
		?LoggerInterface $logger = null,
	) {
		parent::__construct($client, $dispatcher, $logger);
	}

	public function __toString(): string
	{
		return \sprintf('lettermint+api://%s', $this->getEndpoint() . ($this->route ? '?route=' . $this->route : ''));
	}

	protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
	{
		$response = $this->client->request('POST', 'https://' . $this->getEndpoint() . '/v1/send', [
			'json' => $this->getPayload($email, $envelope),
			'headers' => [
				'accept' => 'application/json',
				'x-lettermint-token' => $this->token,
			],
		]);

		try {
			$statusCode = $response->getStatusCode();
			$result = $response->toArray(false);
		} catch (DecodingExceptionInterface) {
			throw new HttpTransportException('Could not decode response.', $response);
		} catch (TransportExceptionInterface $exception) {
			throw new HttpTransportException('Could not reach the server.', $response, 0, $exception);
		}

		if ($statusCode !== 202) {
			throw new HttpTransportException('Unable to send an email: ' . $response->getContent(false) . \sprintf(' (code %d).', $statusCode), $response);
		}

		$sentMessage->setMessageId($result['message_id']);

		return $response;
	}

	private function getPayload(Email $email, Envelope $envelope): array
	{
		$payload = [
			'subject' => $email->getSubject(),
			'from' => $envelope->getSender()->toString(),
			'reply_to' => $this->stringifyAddresses($email->getReplyTo()),
			'to' => $this->stringifyAddresses($this->getRecipients($email, $envelope)),
			'cc' => $this->stringifyAddresses($email->getCc()),
			'bcc' => $this->stringifyAddresses($email->getBcc()),
			'text' => $email->getTextBody(),
			'html' => $email->getHtmlBody(),
			'attachments' => $this->getAttachments($email),
		];

		if ($this->route) {
			$payload['route'] = $this->route;
		}

		foreach ($email->getHeaders()->all() as $header) {
			if ($header instanceof TagHeader) {
				if (isset($payload['tag'])) {
					throw new TransportException('Lettermint only allows a single tag per email.');
				}

				$payload['tag'] = $header->getValue();
				continue;
			}

			if ($header instanceof MetadataHeader) {
				$payload['metadata'][$header->getKey()] = $header->getValue();
				continue;
			}

			if ($header instanceof RouteHeader) {
				$payload['route'] = $header->getValue();
				continue;
			}

			if ($body = $header->getBodyAsString()) {
				$payload['headers'][$header->getName()] = $body;
			}
		}

		return $payload;
	}

	private function getAttachments(Email $email): array
	{
		$attachments = [];

		foreach ($email->getAttachments() as $attachment) {
			$headers = $attachment->getPreparedHeaders();
			$filename = $headers->getHeaderParameter('Content-Disposition', 'filename');
			$disposition = $headers->getHeaderBody('Content-Disposition');

			$file = [
				'filename' => $filename,
				'content' => $attachment->bodyToString(),
			];

			if ($disposition === 'inline') {
				$file['content_id'] = $attachment->getContentId() ?: $filename;
			}

			$file['content_type'] = $attachment->getContentType();

			$attachments[] = $file;
		}

		return $attachments;
	}

	private function getEndpoint(): string
	{
		return ($this->host ?: 'api.lettermint.co') . ($this->port ? ':' . $this->port : '');
	}
}
