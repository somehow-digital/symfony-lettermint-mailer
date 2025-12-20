<?php

use SomehowDigital\Symfony\Component\Mailer\Bridge\Lettermint\Transport\LettermintSmtpTransport;

it('has a valid DSN', function () {
	expect((string) new LettermintSmtpTransport('password'))
		->toBe('smtps://smtp.lettermint.co');
});
