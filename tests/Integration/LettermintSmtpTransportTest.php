<?php

use SomehowDigital\Symfony\Component\Mailer\Bridge\Lettermint\Transport\LettermintSmtpTransport;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Email;

it('sends an email via SMTP', function () {
	$token = getenv('LETTERMINT_TOKEN');
	$from = getenv('LETTERMINT_FROM');
	$to = getenv('LETTERMINT_TO');

	if (!$token || !$from || !$to) {
		$this->markTestSkipped('LETTERMINT_TOKEN, LETTERMINT_FROM, and LETTERMINT_TO environment variables are required for this test.');
	}

	$transport = new LettermintSmtpTransport($token);

	$email = (new Email())
		->from($from)
		->to($to)
		->subject('Integration Test: Hello World')
		->text('This is the body in plain text.')
		->html('<p>This is the body in HTML.</p>');

	$email->getHeaders()
		->add(new TagHeader('test'))
		->add(new MetadataHeader('test-id', (string) time()));

	$message = $transport->send($email);

	expect($message)->not->toBeNull()
		->and($message->getMessageId())->not->toBeEmpty();
});
