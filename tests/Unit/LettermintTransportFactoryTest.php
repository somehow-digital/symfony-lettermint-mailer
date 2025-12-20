<?php

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use SomehowDigital\Symfony\Component\Mailer\Bridge\Lettermint\Transport\LettermintApiTransport;
use SomehowDigital\Symfony\Component\Mailer\Bridge\Lettermint\Transport\LettermintSmtpTransport;
use SomehowDigital\Symfony\Component\Mailer\Bridge\Lettermint\Transport\LettermintTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Contracts\HttpClient\HttpClientInterface;

it('creates an API transport', function () {
	$factory = new LettermintTransportFactory(
		\Mockery::mock(EventDispatcherInterface::class),
		\Mockery::mock(HttpClientInterface::class),
		\Mockery::mock(LoggerInterface::class)
	);

	$dsn = new Dsn('lettermint+api', 'default', 'token');
	$transport = $factory->create($dsn);

	expect($transport)->toBeInstanceOf(LettermintApiTransport::class)
		->and((string) $transport)->toBe('lettermint+api://api.lettermint.co');
});

it('creates an API transport with a route', function () {
	$factory = new LettermintTransportFactory(
		\Mockery::mock(EventDispatcherInterface::class),
		\Mockery::mock(HttpClientInterface::class),
		\Mockery::mock(LoggerInterface::class)
	);

	$dsn = new Dsn('lettermint+api', 'default', 'token', null, null, ['route' => 'my-route']);
	$transport = $factory->create($dsn);

	expect($transport)->toBeInstanceOf(LettermintApiTransport::class)
		->and((string) $transport)->toBe('lettermint+api://api.lettermint.co?route=my-route');
});

it('creates an SMTP transport', function () {
	$factory = new LettermintTransportFactory(
		\Mockery::mock(EventDispatcherInterface::class),
		\Mockery::mock(HttpClientInterface::class),
		\Mockery::mock(LoggerInterface::class)
	);

	$dsn = new Dsn('lettermint+smtp', 'default', 'username', 'password');
	$transport = $factory->create($dsn);

	expect($transport)->toBeInstanceOf(LettermintSmtpTransport::class)
		->and((string) $transport)->toBe('smtps://smtp.lettermint.co');
});

it('supports lettermint schemes', function () {
	$factory = new LettermintTransportFactory();

	expect($factory->supports(new Dsn('lettermint+api', 'default')))->toBeTrue()
		->and($factory->supports(new Dsn('lettermint+smtp', 'default')))->toBeTrue()
		->and($factory->supports(new Dsn('other', 'default')))->toBeFalse();
});
