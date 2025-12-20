<?php

namespace SomehowDigital\Symfony\Component\Mailer\Bridge\Lettermint\Webhook;

use SomehowDigital\Symfony\Component\Mailer\Bridge\Lettermint\RemoteEvent\LettermintPayloadConverter;
use Symfony\Component\HttpFoundation\ChainRequestMatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\HeaderRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\IsJsonRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\MethodRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\RemoteEvent\Event\Mailer\AbstractMailerEvent;
use Symfony\Component\RemoteEvent\Exception\ParseException;
use Symfony\Component\Webhook\Client\AbstractRequestParser;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

final class LettermintWebhookParser extends AbstractRequestParser
{
	public function __construct(
		private readonly LettermintPayloadConverter $converter,
	) {
	}

	protected function getRequestMatcher(): RequestMatcherInterface
	{
		return new ChainRequestMatcher([
			new MethodRequestMatcher('POST'),
			new IsJsonRequestMatcher(),
			new HeaderRequestMatcher(['X-Lettermint-Signature']),
		]);
	}

	protected function doParse(
		Request $request,
		#[\SensitiveParameter]
		string $secret
	): AbstractMailerEvent {
		$this->validateSignature($request, $secret);

		$payload = $request->toArray();

		if (
			!isset($payload['id']) ||
			!isset($payload['event']) ||
			!isset($payload['timestamp']) ||
			!isset($payload['data']) ||
			!isset($payload['data']['message_id'])
		) {
			throw new RejectWebhookException(406, 'Payload is malformed.');
		}

		try {
			return $this->converter->convert($payload);
		} catch (ParseException $exception) {
			throw new RejectWebhookException(406, $exception->getMessage(), $exception);
		}
	}

	private function validateSignature(Request $request, string $secret): void
	{
		$header = $request->headers->get('X-Lettermint-Signature');

		parse_str(str_replace(',', '&', $header), $parts);

		if (!isset($parts['t'], $parts['v1'])) {
			throw new RejectWebhookException(401, 'Signature header is malformed.');
		}

		$timestamp = $parts['t'];
		$signature = $parts['v1'];

		if (abs(time() - (int) $timestamp) > 300) {
			throw new RejectWebhookException(401, 'Signature timestamp is too old or too new.');
		}

		$payload = $timestamp . '.' . $request->getContent();
		$hash = hash_hmac('sha256', $payload, $secret);

		if (!hash_equals($hash, $signature)) {
			throw new RejectWebhookException(401, 'Signature is invalid.');
		}
	}
}
