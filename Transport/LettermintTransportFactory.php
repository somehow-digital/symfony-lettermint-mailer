<?php

namespace SomehowDigital\Symfony\Component\Mailer\Bridge\Lettermint\Transport;

use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

final class LettermintTransportFactory extends AbstractTransportFactory
{
	public function create(Dsn $dsn): TransportInterface
	{
		$scheme = $dsn->getScheme();

		if ('lettermint+api' === $scheme) {
			return new LettermintApiTransport(
				$this->getUser($dsn),
				$dsn->getOption('route'),
				$this->client,
				$this->dispatcher,
				$this->logger,
			);
		}

		if ('lettermint+smtp' === $scheme) {
			return new LettermintSmtpTransport(
				$this->getPassword($dsn),
				$this->dispatcher,
				$this->logger,
			);
		}

		throw new UnsupportedSchemeException($dsn, 'lettermint', $this->getSupportedSchemes());
	}

	protected function getSupportedSchemes(): array
	{
		return ['lettermint+api', 'lettermint+smtp'];
	}
}
