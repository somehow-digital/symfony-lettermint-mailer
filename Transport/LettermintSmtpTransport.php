<?php

namespace SomehowDigital\Symfony\Component\Mailer\Bridge\Lettermint\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

final class LettermintSmtpTransport extends EsmtpTransport
{
	public function __construct(
		#[\SensitiveParameter]
		string $password,
		?EventDispatcherInterface $dispatcher = null,
		?LoggerInterface $logger = null
	) {
		parent::__construct('smtp.lettermint.co', 465, true, $dispatcher, $logger);

		$this->setUsername('lettermint');
		$this->setPassword($password);
	}
}
