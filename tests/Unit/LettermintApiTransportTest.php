<?php

use SomehowDigital\Symfony\Component\Mailer\Bridge\Lettermint\Header\RouteHeader;
use SomehowDigital\Symfony\Component\Mailer\Bridge\Lettermint\Transport\LettermintApiTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

beforeEach(function () {
	$this->client = \Mockery::mock(HttpClientInterface::class);
	$this->response = \Mockery::mock(ResponseInterface::class);
	$this->response->shouldReceive('getInfo')->with('debug')->andReturn(null)->byDefault();
	$this->transport = new LettermintApiTransport('token', null, $this->client);
});

afterEach(function () {
	\Mockery::close();
});

it('sends an email via API and sets the message id', function () {
	$email = (new Email())
		->subject('Hello')
		->from(new Address('from@example.com', 'From'))
		->to(new Address('to@example.com', 'To'))
		->text('Plain text')
		->html('<p>Hello</p>');

	$email->getHeaders()->add(new TagHeader('welcome'));
	$email->getHeaders()->add(new MetadataHeader('customer_id', '123'));

	$envelope = new Envelope(new Address('from@example.com'), [new Address('to@example.com')]);

	$this->client
		->shouldReceive('request')
		->once()
		->withArgs(function (string $method, string $url, array $options) {
			expect($method)->toBe('POST')
				->and($url)->toBe('https://api.lettermint.co/v1/send')
				->and($options)->toHaveKey('headers')
				->and($options['headers'])->toMatchArray([
					'accept' => 'application/json',
					'x-lettermint-token' => 'token',
				])
				->and($options)->toHaveKey('json')
				->and($options['json'])->toMatchArray([
					'subject' => 'Hello',
					'from' => 'from@example.com',
					'to' => ['to@example.com'],
					'tag' => 'welcome',
					'metadata' => ['customer_id' => '123'],
					'text' => 'Plain text',
					'html' => '<p>Hello</p>',
					'attachments' => [],
				]);

			return true;
		})
		->andReturn($this->response);

	$this->response->shouldReceive('getStatusCode')->once()->andReturn(202);
	$this->response->shouldReceive('toArray')->once()->with(false)->andReturn(['message_id' => 'msg_123']);

	$sentMessage = $this->transport->send($email, $envelope);

	expect($sentMessage->getMessageId())->toBe('msg_123');
});

it('includes reply-to, cc, bcc and custom headers in the payload', function () {
	$email = (new Email())
		->subject('Hello')
		->from('from@example.com')
		->to('to@example.com')
		->replyTo('reply@example.com')
		->cc('cc@example.com')
		->bcc('bcc@example.com')
		->text('Plain text');

	$email->getHeaders()->addTextHeader('X-Custom', 'abc');

	$envelope = new Envelope(new Address('from@example.com'), [new Address('to@example.com')]);

	$this->client
		->shouldReceive('request')
		->once()
		->withArgs(function (string $method, string $url, array $options) {
			expect($method)->toBe('POST')
				->and($url)->toBe('https://api.lettermint.co/v1/send')
				->and($options)->toHaveKey('json')
				->and($options['json'])->toHaveKey('reply_to')
				->and($options['json']['reply_to'])->toBe(['reply@example.com'])
				->and($options['json'])->toHaveKey('cc')
				->and($options['json']['cc'])->toBe(['cc@example.com'])
				->and($options['json'])->toHaveKey('bcc')
				->and($options['json']['bcc'])->toBe(['bcc@example.com'])
				->and($options['json'])->toHaveKey('headers')
				->and($options['json']['headers'])->toHaveKey('X-Custom')
				->and($options['json']['headers']['X-Custom'])->toBe('abc');

			return true;
		})
		->andReturn($this->response);

	$this->response->shouldReceive('getStatusCode')->once()->andReturn(202);
	$this->response->shouldReceive('toArray')->once()->with(false)->andReturn(['message_id' => 'msg_abc']);

	$sentMessage = $this->transport->send($email, $envelope);

	expect($sentMessage->getMessageId())->toBe('msg_abc');
});

it('includes attachments in the payload', function () {
	$email = (new Email())
		->subject('Hello')
		->from('from@example.com')
		->to('to@example.com')
		->text('Plain text')
		->attach('attachment content', 'file.txt', 'text/plain');

	$envelope = new Envelope(new Address('from@example.com'), [new Address('to@example.com')]);

	$this->client
		->shouldReceive('request')
		->once()
		->withArgs(function (string $method, string $url, array $options) {
			expect($options['json'])->toHaveKey('attachments')
				->and($options['json']['attachments'])->toHaveCount(1)
				->and($options['json']['attachments'][0])->toMatchArray([
					'filename' => 'file.txt',
					'content' => \base64_encode('attachment content'),
					'content_type' => 'text/plain',
				]);

			return true;
		})
		->andReturn($this->response);

	$this->response->shouldReceive('getStatusCode')->once()->andReturn(202);
	$this->response->shouldReceive('toArray')->once()->with(false)->andReturn(['message_id' => 'msg_att']);

	$this->transport->send($email, $envelope);
});

it('includes route in the payload', function () {
	$email = (new Email())
		->subject('Hello')
		->from('from@example.com')
		->to('to@example.com')
		->text('Plain text');

	$email->getHeaders()->add(new RouteHeader('my-route'));

	$envelope = new Envelope(new Address('from@example.com'), [new Address('to@example.com')]);

	$this->client
		->shouldReceive('request')
		->once()
		->withArgs(function (string $method, string $url, array $options) {
			expect($options['json'])->toHaveKey('route')
				->and($options['json']['route'])->toBe('my-route');

			return true;
		})
		->andReturn($this->response);

	$this->response->shouldReceive('getStatusCode')->once()->andReturn(202);
	$this->response->shouldReceive('toArray')->once()->with(false)->andReturn(['message_id' => 'msg_route']);

	$this->transport->send($email, $envelope);
});

it('throws an exception when the API responds with a non-202 status code', function () {
	$email = (new Email())
		->subject('Hello')
		->from('from@example.com')
		->to('to@example.com')
		->text('Plain text');

	$envelope = new Envelope(new Address('from@example.com'), [new Address('to@example.com')]);

	$this->client->shouldReceive('request')->once()->andReturn($this->response);

	$this->response->shouldReceive('getStatusCode')->once()->andReturn(400);
	$this->response->shouldReceive('toArray')->once()->with(false)->andReturn([]);
	$this->response->shouldReceive('getContent')->once()->with(false)->andReturn('bad request');

	$this->transport->send($email, $envelope);
})->throws(HttpTransportException::class);

it('throws an exception when the API response cannot be decoded', function () {
	$decodingException = new class('decoding error') extends \Exception implements DecodingExceptionInterface {};

	$email = (new Email())
		->subject('Hello')
		->from('from@example.com')
		->to('to@example.com')
		->text('Plain text');

	$envelope = new Envelope(new Address('from@example.com'), [new Address('to@example.com')]);

	$this->client->shouldReceive('request')->once()->andReturn($this->response);

	$this->response->shouldReceive('getStatusCode')->once()->andReturn(202);
	$this->response->shouldReceive('toArray')->once()->with(false)->andThrow($decodingException);

	$this->transport->send($email, $envelope);
})->throws(HttpTransportException::class);

it('throws an exception when the Lettermint server cannot be reached', function () {
	$transportException = new class('transport error') extends \Exception implements TransportExceptionInterface {};

	$email = (new Email())
		->subject('Hello')
		->from('from@example.com')
		->to('to@example.com')
		->text('Plain text');

	$envelope = new Envelope(new Address('from@example.com'), [new Address('to@example.com')]);

	$this->client->shouldReceive('request')->once()->andReturn($this->response);

	$this->response->shouldReceive('getStatusCode')->once()->andThrow($transportException);

	$this->transport->send($email, $envelope);
})->throws(HttpTransportException::class);

it('throws when multiple tags are set', function () {
	$this->client->shouldNotReceive('request');

	$email = (new Email())
		->subject('Hello')
		->from('from@example.com')
		->to('to@example.com')
		->text('Plain text');

	$email->getHeaders()->add(new TagHeader('tag-1'));
	$email->getHeaders()->add(new TagHeader('tag-2'));

	$envelope = new Envelope(new Address('from@example.com'), [new Address('to@example.com')]);

	$this->transport->send($email, $envelope);
})->throws(TransportException::class);
