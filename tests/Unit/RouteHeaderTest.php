<?php

use SomehowDigital\Symfony\Component\Mailer\Bridge\Lettermint\Header\RouteHeader;

it('creates a route header', function () {
	$header = new RouteHeader('test-route');

	expect($header->getName())->toBe('x-lettermint-route')
		->and($header->getValue())->toBe('test-route');
});
