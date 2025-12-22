<?php

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use SomehowDigital\Symfony\Component\Mailer\Bridge\Lettermint\Transport\LettermintApiTransport;
use SomehowDigital\Symfony\Component\Mailer\Bridge\Lettermint\Transport\LettermintSmtpTransport;
use SomehowDigital\Symfony\Component\Mailer\Bridge\Lettermint\Transport\LettermintTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Contracts\HttpClient\HttpClientInterface;

beforeEach(function () {
	$this->factory = new LettermintTransportFactory(
		\Mockery::mock(EventDispatcherInterface::class),
		\Mockery::mock(HttpClientInterface::class),
		\Mockery::mock(LoggerInterface::class)
	);
});

it('creates an API transport', function () {
	$dsn = new Dsn('lettermint+api', 'default', 'token');
	$transport = $this->factory->create($dsn);

	expect($transport)->toBeInstanceOf(LettermintApiTransport::class)
		->and((string) $transport)->toBe('lettermint+api://api.lettermint.co');
});

it('creates an API transport with a route', function () {
	$dsn = new Dsn('lettermint+api', 'default', 'token', null, null, ['route' => 'my-route']);
	$transport = $this->factory->create($dsn);

	expect($transport)->toBeInstanceOf(LettermintApiTransport::class)
		->and((string) $transport)->toBe('lettermint+api://api.lettermint.co?route=my-route');
});

it('creates an SMTP transport', function () {
	$dsn = new Dsn('lettermint+smtp', 'default', 'username', 'password');
	$transport = $this->factory->create($dsn);

	expect($transport)->toBeInstanceOf(LettermintSmtpTransport::class)
		->and((string) $transport)->toBe('smtps://smtp.lettermint.co');
});

it('supports lettermint schemes', function () {
	expect($this->factory->supports(new Dsn('lettermint+api', 'default')))->toBeTrue()
		->and($this->factory->supports(new Dsn('lettermint+smtp', 'default')))->toBeTrue()
		->and($this->factory->supports(new Dsn('other', 'default')))->toBeFalse();
});
