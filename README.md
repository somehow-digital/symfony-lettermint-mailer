# Lettermint Mailer for Symfony

> Provides `Lettermint` integration for Symfony Mailer.

## Installation

```sh
composer require somehow-digital/symfony-lettermint-mailer
```

## Configuration

```env
# API
MAILER_DSN=lettermint+api://TOKEN@default?route=my-route

# SMTP
MAILER_DSN=lettermint+smtp://TOKEN@default
```

### Route per Email

Set a [route](https://docs.lettermint.co/platform/projects-and-routes/routes) for each email using the `RouteHeader`:

```php
use SomehowDigital\Symfony\Component\Mailer\Bridge\Lettermint\Header\RouteHeader;
$email->getHeaders()->add(new RouteHeader('my-route'));
```

## Webhook

Configure the webhook routing:

```yml
framework:
    webhook:
        routing:
            lettermint_mailer:
                service: mailer.webhook.request_parser.lettermint
                secret: '%env(LETTERMINT_WEBHOOK_SECRET)%'
```

And a consumer:

```php
#[AsRemoteEventConsumer(name: 'lettermint_mailer')]
class LettermintMailEventConsumer implements ConsumerInterface
{
    public function consume(RemoteEvent|AbstractMailerEvent $event): void
    {
        // your code
    }
}
```
