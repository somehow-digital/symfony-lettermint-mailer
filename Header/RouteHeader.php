<?php

namespace SomehowDigital\Symfony\Component\Mailer\Bridge\Lettermint\Header;

use Symfony\Component\Mime\Header\UnstructuredHeader;

final class RouteHeader extends UnstructuredHeader
{
	public function __construct(string $value)
	{
		parent::__construct('x-lettermint-route', $value);
	}
}
